<?php
// =====================================================
// HALAMAN LOGIN — DATABASE-BASED AUTH
// =====================================================

require_once __DIR__ . '/config/auth.php';

// Auto-migrate: buat tabel users & seed superadmin jika belum ada
ensureUsersTable();

authStart();

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error    = '';
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : 'index.php';

// Whitelist redirect target agar tidak bisa di-abuse
$allowed = ['index.php', 'devices.php', 'settings.php', 'docs.php', 'users.php'];
if (!in_array($redirect, $allowed, true)) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (doLogin($username, $password)) {
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Username atau password salah. Coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — Smart Infus</title>

  <!-- Local Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/style.css" />

  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col items-center justify-center selection:bg-[#6b2072]/10 selection:text-[#6b2072] p-4">

  <div class="w-full max-w-sm">

    <!-- Brand -->
    <div class="flex flex-col items-center mb-8">
      <div class="w-14 h-14 bg-[#6b2072] text-white rounded-2xl flex items-center justify-center shadow-xl shadow-[#6b2072]/25 mb-4">
        <i class="bi bi-droplet-fill text-2xl"></i>
      </div>
      <div class="text-center">
        <div class="text-sm font-black tracking-wider text-slate-900 uppercase">Smart Infus</div>
        <div class="text-[10px] font-bold text-[#6b2072] tracking-widest uppercase mt-0.5">Central Station</div>
      </div>
    </div>

    <!-- Card Login -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">

      <!-- Header Card -->
      <div class="px-6 pt-6 pb-4 border-b border-slate-100">
        <h1 class="text-base font-black text-slate-900 tracking-wide">Login</h1>
        <p class="text-xs font-medium text-slate-400 mt-0.5">Masuk untuk mengakses sistem monitoring</p>
      </div>

      <!-- Form -->
      <form method="POST" action="login.php<?= $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>" class="p-6 flex flex-col gap-4">

        <?php if ($error): ?>
        <div class="flex items-center gap-2.5 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs font-bold">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Username -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
            <i class="bi bi-person-fill text-slate-400 mr-1"></i> Username
          </label>
          <input
            type="text"
            name="username"
            autocomplete="username"
            placeholder="superadmin"
            class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required autofocus />
        </div>

        <!-- Password -->
        <div>
          <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
            <i class="bi bi-lock-fill text-slate-400 mr-1"></i> Password
          </label>
          <div class="relative">
            <input
              type="password"
              name="password"
              id="password-input"
              autocomplete="current-password"
              placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;"
              class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl pl-4 pr-11 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
              required />
            <button
              type="button"
              onclick="togglePw()"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors p-1">
              <i class="bi bi-eye text-sm" id="pw-eye"></i>
            </button>
          </div>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-sm font-bold shadow-md shadow-[#6b2072]/15 active:scale-[0.98] transition-all mt-1">
          <i class="bi bi-box-arrow-in-right"></i> MASUK
        </button>

      </form>
    </div>

    <!-- Back to Dashboard -->
    <div class="text-center mt-5">
      <a href="index.php" class="text-xs font-bold text-slate-400 hover:text-[#6b2072] transition-colors flex items-center justify-center gap-1.5">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
      </a>
    </div>

  </div>

  <script>
    function togglePw() {
      const input = document.getElementById('password-input');
      const icon  = document.getElementById('pw-eye');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash text-sm';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye text-sm';
      }
    }
  </script>

</body>
</html>
