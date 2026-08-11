<?php
// =====================================================
// PARTIAL: SIDEBAR (desktop) + TOPBAR + MOBILE BOTTOM NAV
// Require: $activePage sudah didefinisikan sebelum include
// =====================================================

require_once __DIR__ . '/auth.php';
authStart();
$_isLoggedIn  = isLoggedIn();
$_user        = getCurrentUser();
$_userRole    = $_user['role'] ?? '';
$_userName    = $_user['nama'] ?? '';
$_canDevices  = canAccess('devices');
$_canSettings = canAccess('settings');
$_canUsers    = canAccess('users');

$_roleLabel = match($_userRole) {
    'superadmin' => 'Superadmin',
    'admin'      => 'Admin',
    'nurse'      => 'Nurse',
    default      => '',
};
$_roleColor = match($_userRole) {
    'superadmin' => '#6b2072',
    'admin'      => '#2563eb',
    'nurse'      => '#059669',
    default      => '#94a3b8',
};
$_roleBg = match($_userRole) {
    'superadmin' => 'rgba(107,32,114,0.10)',
    'admin'      => 'rgba(37,99,235,0.10)',
    'nurse'      => 'rgba(5,150,105,0.10)',
    default      => 'rgba(148,163,184,0.10)',
};
$_displayName = mb_strlen($_userName) > 16 ? mb_substr($_userName, 0, 15) . '…' : $_userName;
$_activePage  = $activePage ?? '';

// ── Helper: satu item nav ──────────────────────────────
function _navItem(string $href, string $icon, string $label, bool $active): string {
    $base  = 'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all group';
    $style = $active
        ? 'background:#6b2072;color:#fff;box-shadow:0 4px 12px rgba(107,32,114,.25);'
        : 'color:#64748b;';
    $hover = $active ? '' : 'onmouseover="this.style.background=\'#f1f5f9\';this.style.color=\'#0f172a\';" onmouseout="this.style.background=\'\';this.style.color=\'#64748b\';"';
    return "<a href=\"{$href}\" class=\"{$base}\" style=\"{$style}\" {$hover}>"
         . "<i class=\"bi bi-{$icon} text-base flex-shrink-0\"></i>"
         . "<span>{$label}</span>"
         . "</a>";
}
?>

<!-- ═══════════════════════════════════════════════════
     DESKTOP SIDEBAR — hidden on mobile
     ═══════════════════════════════════════════════════ -->
<aside id="app-sidebar"
  class="hidden md:flex flex-col fixed top-0 left-0 h-screen w-56 z-40"
  style="background:#fff;border-right:1px solid #e2e8f0;">

  <!-- Brand -->
  <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-100 flex-shrink-0">
    <div class="w-9 h-9 bg-[#6b2072] text-white rounded-xl flex items-center justify-center shadow-lg shadow-[#6b2072]/20 flex-shrink-0">
      <i class="bi bi-droplet-fill text-base"></i>
    </div>
    <div class="min-w-0">
      <div class="text-xs font-black tracking-wider text-slate-900 uppercase leading-tight">Smart Infus</div>
      <div class="text-[9px] font-bold text-[#6b2072] tracking-widest uppercase">Central Station</div>
    </div>
  </div>

  <!-- Nav Items -->
  <nav class="flex-1 overflow-y-auto px-3 py-4 flex flex-col gap-1">

    <!-- Monitoring group -->
    <div class="mb-1">
      <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-3 mb-1.5">Monitoring</div>
      <?= _navItem('index.php',  'grid-1x2-fill', 'Dashboard',    $_activePage === 'dashboard') ?>
      <a href="docs.php"
         class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all"
         style="color:<?= $_activePage === 'docs' ? '#fff' : '#64748b' ?>;<?= $_activePage === 'docs' ? 'background:#6b2072;box-shadow:0 4px 12px rgba(107,32,114,.25);' : '' ?>"
         <?= $_activePage !== 'docs' ? 'onmouseover="this.style.background=\'#f1f5f9\';this.style.color=\'#0f172a\';" onmouseout="this.style.background=\'\';this.style.color=\'#64748b\';"' : '' ?>>
        <i class="bi bi-book-half text-base flex-shrink-0"></i><span>Dokumentasi</span>
      </a>
    </div>

    <!-- Admin group — hanya jika punya akses salah satu -->
    <?php if ($_canDevices || $_canSettings || $_canUsers): ?>
    <div class="mt-3">
      <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-3 mb-1.5">Manajemen</div>
      <?php if ($_canDevices): ?>
      <?= _navItem('devices.php',  'cpu-fill',     'Devices',  $_activePage === 'devices') ?>
      <?php endif; ?>
      <?php if ($_canSettings): ?>
      <?= _navItem('settings.php', 'sliders',      'Settings', $_activePage === 'settings') ?>
      <?php endif; ?>
      <?php if ($_canUsers): ?>
      <?= _navItem('users.php',    'people-fill',  'Users',    $_activePage === 'users') ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </nav>

  <!-- User Info + Logout -->
  <div class="flex-shrink-0 px-3 py-4 border-t border-slate-100">
    <?php if ($_isLoggedIn): ?>
    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl mb-2"
         style="background:<?= $_roleBg ?>;">
      <!-- Avatar -->
      <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-black text-white flex-shrink-0"
           style="background:<?= $_roleColor ?>;">
        <?= mb_strtoupper(mb_substr($_userName ?: $_roleLabel, 0, 1)) ?>
      </div>
      <div class="min-w-0 flex-1">
        <div class="text-xs font-bold text-slate-800 truncate leading-tight"><?= htmlspecialchars($_displayName ?: 'User') ?></div>
        <div class="text-[9px] font-black uppercase tracking-wider" style="color:<?= $_roleColor ?>;"><?= htmlspecialchars($_roleLabel) ?></div>
      </div>
    </div>
    <a href="logout.php"
       onclick="return confirm('Yakin ingin logout?')"
       class="flex items-center gap-2.5 w-full px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-500 transition-all"
       onmouseover="this.style.background='#fef2f2';this.style.color='#dc2626';"
       onmouseout="this.style.background='';this.style.color='#64748b';">
      <i class="bi bi-box-arrow-right text-base"></i><span>Logout</span>
    </a>
    <?php else: ?>
    <a href="login.php"
       class="flex items-center justify-center gap-2 w-full px-3 py-2.5 rounded-xl text-sm font-bold text-white transition-all"
       style="background:#6b2072;"
       onmouseover="this.style.background='#541859';"
       onmouseout="this.style.background='#6b2072';">
      <i class="bi bi-lock-fill"></i><span>Login Admin</span>
    </a>
    <?php endif; ?>
  </div>

</aside>

<!-- ═══════════════════════════════════════════════════
     DESKTOP TOPBAR — hanya jam + SSE + (spacer untuk sidebar)
     ═══════════════════════════════════════════════════ -->
<div class="hidden md:flex fixed top-0 left-56 right-0 h-16 z-30 items-center justify-between px-6"
     style="background:rgba(248,250,252,0.85);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-bottom:1px solid #e2e8f0;">

  <!-- Kiri: judul halaman dinamis -->
  <div class="flex items-center gap-2">
    <?php
    $pageTitle = match($_activePage) {
        'dashboard' => ['icon' => 'grid-1x2-fill',  'label' => 'Dashboard Monitoring'],
        'devices'   => ['icon' => 'cpu-fill',        'label' => 'Kelola Perangkat'],
        'settings'  => ['icon' => 'sliders',         'label' => 'Pengaturan Sistem'],
        'users'     => ['icon' => 'people-fill',     'label' => 'Manajemen User'],
        'docs'      => ['icon' => 'book-half',       'label' => 'Dokumentasi'],
        default     => ['icon' => 'circle',          'label' => 'Smart Infus'],
    };
    ?>
    <i class="bi bi-<?= $pageTitle['icon'] ?> text-slate-400"></i>
    <span class="text-sm font-bold text-slate-600"><?= $pageTitle['label'] ?></span>
  </div>

  <!-- Kanan: SSE indicator + jam -->
  <div class="flex items-center gap-3">
    <?php if (isset($showSseIndicator) && $showSseIndicator): ?>
    <div id="sse-indicator"
      style="display:none;align-items:center;gap:6px;padding:5px 10px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">
      <span id="sse-dot" style="width:7px;height:7px;border-radius:50%;background:#cbd5e1;flex-shrink:0;display:inline-block;"></span>
      <span id="sse-label" style="color:#94a3b8;">Menghubungkan…</span>
    </div>
    <?php endif; ?>
    <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:5px 12px;">
      <span id="clockText" class="text-sm font-bold text-slate-700 tabular-nums">--:--:--</span>
    </div>
  </div>

</div>

<!-- ═══════════════════════════════════════════════════
     MOBILE TOPBAR — brand + jam (tidak ada menu)
     ═══════════════════════════════════════════════════ -->
<div class="flex md:hidden sticky top-0 z-40 h-14 items-center justify-between px-4"
     style="background:rgba(255,255,255,0.92);backdrop-filter:blur(10px);border-bottom:1px solid #e2e8f0;">
  <a href="index.php" class="flex items-center gap-2.5">
    <div class="w-8 h-8 bg-[#6b2072] text-white rounded-lg flex items-center justify-center">
      <i class="bi bi-droplet-fill text-sm"></i>
    </div>
    <div>
      <div class="text-[11px] font-black tracking-wider text-slate-900 uppercase leading-tight">Smart Infus</div>
      <div class="text-[9px] font-bold text-[#6b2072] tracking-widest uppercase">Central Station</div>
    </div>
  </a>
  <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:4px 10px;">
    <span id="clockText" class="text-xs font-bold text-slate-700 tabular-nums">--:--:--</span>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MOBILE BOTTOM NAV
     ═══════════════════════════════════════════════════ -->
<div class="fixed bottom-0 left-0 right-0 z-50 md:hidden"
     style="background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);border-top:1px solid #e2e8f0;padding:6px 8px 6px;">
  <div class="flex justify-around items-center">

    <?php
    function _mobileNavItem(string $href, string $icon, string $label, bool $active): void {
        $color = $active ? '#6b2072' : '#64748b';
        echo "<a href=\"{$href}\" class=\"flex flex-col items-center gap-0.5 px-2 py-1 rounded-xl transition-all\" style=\"color:{$color};\">"
           . "<i class=\"bi bi-{$icon}\" style=\"font-size:1.1rem;\"></i>"
           . "<span style=\"font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;\">{$label}</span>"
           . "</a>";
    }
    ?>

    <?= _mobileNavItem('index.php', 'grid-1x2-fill', 'Dashboard', $_activePage === 'dashboard') . '' ?>
    <?php if ($_canDevices): _mobileNavItem('devices.php',  'cpu-fill',    'Devices',  $_activePage === 'devices'); endif; ?>
    <?php if ($_canSettings): _mobileNavItem('settings.php', 'sliders',    'Settings', $_activePage === 'settings'); endif; ?>
    <?php if ($_canUsers): _mobileNavItem('users.php',    'people-fill', 'Users',    $_activePage === 'users'); endif; ?>
    <?= _mobileNavItem('docs.php', 'book-half', 'Docs', $_activePage === 'docs') . '' ?>

    <?php if ($_isLoggedIn): ?>
    <a href="logout.php" onclick="return confirm('Logout?')"
       class="flex flex-col items-center gap-0.5 px-2 py-1 rounded-xl transition-all"
       style="color:#64748b;"
       onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='#64748b'">
      <i class="bi bi-box-arrow-right" style="font-size:1.1rem;"></i>
      <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;">Logout</span>
    </a>
    <?php else: ?>
    <a href="login.php" class="flex flex-col items-center gap-0.5 px-2 py-1 rounded-xl" style="color:#6b2072;">
      <i class="bi bi-lock-fill" style="font-size:1.1rem;"></i>
      <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;">Login</span>
    </a>
    <?php endif; ?>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     DESKTOP CONTENT SPACER
     Semua halaman otomatis di-push ke kanan (pl-56)
     dan ke bawah (pt-16) oleh wrapper ini.
     Tutup div ini TIDAK perlu — ini hanya injeksi style
     via script agar tidak perlu ubah setiap halaman.
     ═══════════════════════════════════════════════════ -->
<script>
(function() {
  // Tambahkan kelas layout ke <body> setelah DOM siap
  // supaya semua konten di halaman ini punya offset sidebar
  function applyLayout() {
    const body = document.body;
    if (window.innerWidth >= 768) {
      // Desktop: konten di-offset kanan (sidebar 224px) + bawah (topbar 64px)
      body.style.paddingLeft   = '224px';
      body.style.paddingTop    = '64px';
    } else {
      // Mobile: tidak ada sidebar, ada topbar tipis 56px + bottom nav 60px
      body.style.paddingLeft   = '0';
      body.style.paddingTop    = '0';
      body.style.paddingBottom = '64px';
    }
  }
  applyLayout();
  window.addEventListener('resize', applyLayout);
})();

// Clock — sama di semua halaman
(function tickClock() {
  function pad(n) { return String(n).padStart(2,'0'); }
  function update() {
    const now = new Date();
    // Ada dua #clockText (desktop topbar & mobile topbar)
    document.querySelectorAll('#clockText').forEach(function(el) {
      el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    });
  }
  update();
  setInterval(update, 1000);
})();
</script>

<!-- Global Notifications variables & Script -->
<script>
  window.activePage = '<?= $_activePage ?>';
  window.isDetailPage = <?= strpos($_SERVER['SCRIPT_NAME'], 'detail.php') !== false ? 'true' : 'false' ?>;
</script>
<script src="assets/js/notifications.js?v=<?= time() ?>"></script>

