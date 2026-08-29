<?php
// =====================================================
// HALAMAN MANAJEMEN USER — HANYA SUPERADMIN
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/settings.php';

requireAccess('users'); // hanya superadmin

$db      = getDB();
$message = '';
$msgType = 'success';

// --- DATA ESCAPING HELPER ---
if (!function_exists('esc')) {
    function esc($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$currentUser = getCurrentUser();
$currentId   = (int)($currentUser['id'] ?? 0);

// ── CRUD LOGIC ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama     = trim($_POST['nama']     ?? '');
    $password = $_POST['password']      ?? '';
    $role     = $_POST['role']          ?? 'nurse';

    // Validasi role
    $allowedRoles = ['superadmin', 'admin', 'nurse'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'nurse';
    }

    if ($action === 'add') {
        if (empty($username) || empty($nama) || empty($password)) {
            $message = 'Username, nama, dan password wajib diisi!';
            $msgType = 'danger';
        } else {
            // Cek uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE username = :u");
            $chk->execute([':u' => $username]);
            if ($chk->fetch()) {
                $message = "Username '" . esc($username) . "' sudah digunakan!";
                $msgType = 'danger';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (username, password, nama, role) VALUES (:u, :p, :n, :r)");
                $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nama, ':r' => $role]);
                $message = "User '" . esc($nama) . "' berhasil ditambahkan!";
            }
        }
    }

    elseif ($action === 'edit' && $targetId > 0) {
        if (empty($username) || empty($nama)) {
            $message = 'Username dan nama wajib diisi!';
            $msgType = 'danger';
        } else {
            // Cek jika username dipakai user lain
            $chk = $db->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
            $chk->execute([':u' => $username, ':id' => $targetId]);
            if ($chk->fetch()) {
                $message = "Username '" . esc($username) . "' sudah digunakan user lain!";
                $msgType = 'danger';
            } else {
                // Proteksi: tidak bisa ubah role diri sendiri jika satu-satunya superadmin
                if ($targetId === $currentId && $role !== 'superadmin') {
                    $countSuperadmin = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND aktif = 1")->fetchColumn();
                    if ($countSuperadmin <= 1) {
                        $message = 'Tidak bisa mengubah role — Anda adalah satu-satunya superadmin aktif!';
                        $msgType = 'danger';
                        goto done_post;
                    }
                }

                if (!empty($password)) {
                    // Update dengan password baru
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET username=:u, password=:p, nama=:n, role=:r WHERE id=:id");
                    $stmt->execute([':u' => $username, ':p' => $hash, ':n' => $nama, ':r' => $role, ':id' => $targetId]);
                } else {
                    // Update tanpa ubah password
                    $stmt = $db->prepare("UPDATE users SET username=:u, nama=:n, role=:r WHERE id=:id");
                    $stmt->execute([':u' => $username, ':n' => $nama, ':r' => $role, ':id' => $targetId]);
                }
                $message = "User '" . esc($nama) . "' berhasil diperbarui!";
            }
        }
    }

    elseif ($action === 'deactivate' && $targetId > 0) {
        if ($targetId === $currentId) {
            $message = 'Tidak bisa menonaktifkan diri sendiri!';
            $msgType = 'danger';
        } else {
            // Proteksi: tidak bisa nonaktifkan superadmin terakhir
            $targetRole = $db->prepare("SELECT role FROM users WHERE id = :id");
            $targetRole->execute([':id' => $targetId]);
            $tRow = $targetRole->fetch();
            if ($tRow && $tRow['role'] === 'superadmin') {
                $countSuperadmin = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND aktif = 1")->fetchColumn();
                if ($countSuperadmin <= 1) {
                    $message = 'Tidak bisa menonaktifkan superadmin terakhir!';
                    $msgType = 'danger';
                    goto done_post;
                }
            }
            $stmt = $db->prepare("UPDATE users SET aktif = 0 WHERE id = :id");
            $stmt->execute([':id' => $targetId]);
            $message = 'User berhasil dinonaktifkan.';
        }
    }

    done_post:;
}

// ── Data untuk edit form ──────────────────────────────
$editUser = null;
if (isset($_GET['edit'])) {
    $editStmt = $db->prepare("SELECT * FROM users WHERE id = :id AND aktif = 1");
    $editStmt->execute([':id' => (int)$_GET['edit']]);
    $editUser = $editStmt->fetch();
}

// ── Ambil semua user aktif ────────────────────────────
$users = $db->query("SELECT * FROM users WHERE aktif = 1 ORDER BY id ASC")->fetchAll();

$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manajemen User — Smart Infus</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-[#6b2072]/10 selection:text-[#6b2072]">

  <?php require __DIR__ . '/config/navbar.php'; ?>

  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">

    <!-- ALERT -->
    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 border transition-all <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200/80 text-emerald-800' : 'bg-rose-50 border-rose-200/80 text-rose-800' ?>">
      <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?> text-lg flex-shrink-0"></i>
      <span class="text-xs font-bold tracking-wide"><?= esc($message) ?></span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

      <!-- FORM PANEL (LEFT) -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden lg:sticky lg:top-24">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
          <div class="w-8 h-8 bg-[#6b2072]/10 rounded-lg flex items-center justify-center">
            <i class="bi bi-<?= $editUser ? 'pencil-square text-amber-600' : 'person-plus-fill text-[#6b2072]' ?> text-sm"></i>
          </div>
          <div>
            <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase"><?= $editUser ? 'Edit User' : 'Tambah User Baru' ?></h2>
            <p class="text-[10px] font-bold text-[#6b2072] tracking-wide uppercase">Manajemen Akun</p>
          </div>
        </div>

        <form method="POST" action="users.php" class="p-5 flex flex-col gap-4">
          <input type="hidden" name="action" value="<?= $editUser ? 'edit' : 'add' ?>" />
          <?php if ($editUser): ?>
          <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>" />
          <?php endif; ?>

          <!-- Nama -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
              <i class="bi bi-person text-slate-500 mr-1"></i> Nama Lengkap
            </label>
            <input type="text" name="nama" placeholder="Nama Lengkap"
                   value="<?= esc($editUser['nama'] ?? '') ?>"
                   class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                   required />
          </div>

          <!-- Username -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
              <i class="bi bi-at text-slate-500 mr-1"></i> Username
            </label>
            <input type="text" name="username" placeholder="username"
                   value="<?= esc($editUser['username'] ?? '') ?>"
                   class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                   required autocomplete="off" />
          </div>

          <!-- Password -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
              <i class="bi bi-lock text-slate-500 mr-1"></i> Password <?= $editUser ? '<span class="text-slate-400 lowercase normal-case font-normal">(kosong = tidak ubah)</span>' : '' ?>
            </label>
            <div class="relative">
              <input type="password" name="password" id="user-pw-input"
                     placeholder="<?= $editUser ? 'Biarkan kosong jika tidak diubah' : 'Password baru' ?>"
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl pl-3.5 pr-10 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                     <?= $editUser ? '' : 'required' ?> autocomplete="new-password" />
              <button type="button" onclick="toggleUserPw()"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1">
                <i class="bi bi-eye text-xs" id="user-pw-eye"></i>
              </button>
            </div>
          </div>

          <!-- Role -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
              <i class="bi bi-shield text-slate-500 mr-1"></i> Role
            </label>
            <select name="role"
                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-800 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5">
              <option value="nurse"      <?= ($editUser['role'] ?? 'nurse') === 'nurse'      ? 'selected' : '' ?>>Nurse (Perawat)</option>
              <option value="admin"      <?= ($editUser['role'] ?? '') === 'admin'      ? 'selected' : '' ?>>Admin</option>
              <option value="superadmin" <?= ($editUser['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
            <div class="mt-2 text-[9px] text-slate-400 flex flex-col gap-0.5">
              <div><span class="font-bold text-emerald-600">Nurse:</span> Dashboard, Dokumentasi</div>
              <div><span class="font-bold text-blue-600">Admin:</span> + Devices</div>
              <div><span class="font-bold text-[#6b2072]">Superadmin:</span> Semua halaman</div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold shadow-md shadow-[#6b2072]/10 active:scale-95 transition-all cursor-pointer">
              <i class="bi bi-<?= $editUser ? 'save2' : 'person-plus' ?>"></i> <?= $editUser ? 'UPDATE USER' : 'TAMBAH USER' ?>
            </button>
            <?php if ($editUser): ?>
            <a href="users.php" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 rounded-xl text-xs transition-all" title="Batal">
              <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- TABLE PANEL (RIGHT) -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden lg:col-span-2">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
              <span class="w-1.5 h-4 bg-[#6b2072] rounded-full inline-block"></span>
              Daftar User Aktif
            </h2>
          </div>
          <span class="text-xs bg-[#6b2072]/10 border border-[#6b2072]/20 text-[#6b2072] px-2.5 py-0.5 rounded-full font-bold">
            <?= count($users) ?> User
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-200 text-[10px] font-bold text-slate-400 tracking-wider uppercase">
                <th class="py-3.5 px-5">Nama &amp; Username</th>
                <th class="py-3.5 px-5">Role</th>
                <th class="py-3.5 px-5">Terdaftar</th>
                <th class="py-3.5 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
              <?php foreach ($users as $u):
                $isMe = (int)$u['id'] === $currentId;
                $roleBg = match($u['role']) {
                    'superadmin' => 'bg-purple-100 text-purple-700 border-purple-200',
                    'admin'      => 'bg-blue-100 text-blue-700 border-blue-200',
                    'nurse'      => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                    default      => 'bg-slate-100 text-slate-600 border-slate-200',
                };
                $roleLabel = match($u['role']) {
                    'superadmin' => 'Superadmin',
                    'admin'      => 'Admin',
                    'nurse'      => 'Nurse',
                    default      => ucfirst($u['role']),
                };
              ?>
              <tr class="hover:bg-slate-50/60 transition-colors <?= $isMe ? 'bg-[#6b2072]/3' : '' ?>">
                <td class="py-4 px-5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-black text-white flex-shrink-0"
                         style="background:<?= $u['role'] === 'superadmin' ? '#6b2072' : ($u['role'] === 'admin' ? '#2563eb' : '#059669') ?>;">
                      <?= mb_strtoupper(mb_substr($u['nama'], 0, 1)) ?>
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 leading-tight">
                        <?= esc($u['nama']) ?>
                        <?php if ($isMe): ?><span class="ml-1 text-[9px] font-black text-[#6b2072] bg-[#6b2072]/10 px-1.5 py-0.5 rounded">ANDA</span><?php endif; ?>
                      </div>
                      <div class="text-[10px] font-mono text-slate-400 mt-0.5">@<?= esc($u['username']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-5">
                  <span class="text-[10px] font-black px-2 py-1 rounded-lg border <?= $roleBg ?> uppercase tracking-wider">
                    <?= $roleLabel ?>
                  </span>
                </td>
                <td class="py-4 px-5">
                  <div class="text-xs font-semibold text-slate-500 font-mono">
                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                  </div>
                  <div class="text-[10px] text-slate-400"><?= date('H:i', strtotime($u['created_at'])) ?></div>
                </td>
                <td class="py-4 px-5 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- Edit -->
                    <a href="users.php?edit=<?= (int)$u['id'] ?>" title="Edit User"
                       class="w-8 h-8 bg-amber-50 border border-amber-200 text-amber-600 hover:bg-amber-500 hover:text-white rounded-xl flex items-center justify-center transition-all active:scale-90">
                      <i class="bi bi-pencil-fill text-[11px]"></i>
                    </a>
                    <!-- Nonaktifkan (soft delete) -->
                    <?php if (!$isMe): ?>
                    <form method="POST" action="users.php" class="inline" id="form-deact-<?= (int)$u['id'] ?>">
                      <input type="hidden" name="action" value="deactivate" />
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>" />
                      <button type="button" title="Nonaktifkan"
                              onclick="confirmAction({icon:'person-x-fill',iconBg:'#fee2e2',iconColor:'#dc2626',title:'Nonaktifkan User',sub:'<?= esc(addslashes($u['nama'])) ?>',body:'<p>User <strong><?= esc(addslashes($u['nama'])) ?></strong> akan dinonaktifkan dan tidak bisa login.<br><br>Akun tidak dihapus — bisa diaktifkan kembali.</p>',confirmLabel:'<i class=\'bi bi-person-x-fill\'></i> Ya, Nonaktifkan',confirmStyle:'background:#dc2626;color:#fff;',formId:'form-deact-<?= (int)$u['id'] ?>'})"
                              class="w-8 h-8 bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-500 hover:text-white rounded-xl flex items-center justify-center cursor-pointer transition-all active:scale-90">
                        <i class="bi bi-person-x-fill text-[11px]"></i>
                      </button>
                    </form>
                    <?php else: ?>
                    <div class="w-8 h-8 bg-slate-50 border border-slate-200 text-slate-300 rounded-xl flex items-center justify-center cursor-not-allowed" title="Tidak bisa nonaktifkan diri sendiri">
                      <i class="bi bi-person-x-fill text-[11px]"></i>
                    </div>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($users)): ?>
              <tr>
                <td colspan="4" class="py-16 text-center text-xs font-bold text-slate-400 tracking-wider uppercase">
                  <i class="bi bi-people text-3xl block text-slate-300 mb-2"></i>
                  Belum Ada User Terdaftar
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

  <script>
    function toggleUserPw() {
      const input = document.getElementById('user-pw-input');
      const icon  = document.getElementById('user-pw-eye');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-xs';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye text-xs';
      }
    }
  </script>
</body>
</html>
