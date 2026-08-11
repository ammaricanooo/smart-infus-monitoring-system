<?php
// =====================================================
// AUTH HELPER — DATABASE-BASED USER AUTH
// =====================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';

// Role permission map: role => array of allowed page keys
const ROLE_PERMISSIONS = [
    'superadmin' => ['dashboard', 'devices', 'settings', 'docs', 'users'],
    'admin'      => ['dashboard', 'devices', 'docs'],
    'nurse'      => ['dashboard', 'docs'],
];

// Pages that are public when login_required = 0
const PUBLIC_PAGES = ['dashboard', 'docs'];

// Page key per file
const PAGE_MAP = [
    'index.php'   => 'dashboard',
    'detail.php'  => 'dashboard',
    'devices.php' => 'devices',
    'settings.php'=> 'settings',
    'docs.php'    => 'docs',
    'users.php'   => 'users',
];

function authStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    authStart();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'] ?? '',
        'nama'     => $_SESSION['user_nama'] ?? '',
        'role'     => $_SESSION['user_role'],
    ];
}

function getCurrentRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function canAccess(string $pageKey): bool {
    if (!isLoggedIn()) return false;
    $role  = getCurrentRole();
    $perms = ROLE_PERMISSIONS[$role] ?? [];
    return in_array($pageKey, $perms, true);
}

/**
 * Cek apakah page ini butuh login.
 * Jika login_required=1, semua page butuh login.
 * Jika login_required=0, PUBLIC_PAGES bebas.
 */
function pageNeedsLogin(string $pageKey): bool {
    if (getSetting('login_required', '0') === '1') return true;
    return !in_array($pageKey, PUBLIC_PAGES, true);
}

/**
 * Guard halaman. Panggil di top setiap halaman.
 * $pageKey: salah satu dari PAGE_MAP values.
 */
function requireAccess(string $pageKey): void {
    authStart();
    $needsLogin = pageNeedsLogin($pageKey);

    if ($needsLogin && !isLoggedIn()) {
        $current = basename($_SERVER['PHP_SELF']);
        header('Location: login.php?redirect=' . urlencode($current));
        exit;
    }

    if (isLoggedIn() && !canAccess($pageKey)) {
        // Logged in tapi tidak punya akses ke halaman ini
        header('Location: index.php?error=forbidden');
        exit;
    }
}

function doLogin(string $username, string $password): bool {
    authStart();
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u AND aktif = 1 LIMIT 1");
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_nama']     = $user['nama'];
            $_SESSION['user_role']     = $user['role'];
            return true;
        }
    } catch (\Throwable $e) {
        error_log('Login error: ' . $e->getMessage());
    }
    return false;
}

function doLogout(): void {
    authStart();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Auto-migrate: buat tabel users jika belum ada dan insert superadmin default.
 * Dipanggil dari login.php saat pertama kali diakses.
 */
function ensureUsersTable(): void {
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS `users` (
              `id`         INT(11)      NOT NULL AUTO_INCREMENT,
              `username`   VARCHAR(50)  NOT NULL UNIQUE,
              `password`   VARCHAR(255) NOT NULL,
              `nama`       VARCHAR(100) NOT NULL DEFAULT '',
              `role`       ENUM('superadmin','admin','nurse') NOT NULL DEFAULT 'nurse',
              `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Insert superadmin default jika tabel masih kosong
        $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ((int)$count === 0) {
            $hash = password_hash('admin123', PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)")
               ->execute(['superadmin', $hash, 'Super Administrator', 'superadmin']);
        }
        // Migrate settings baru jika belum ada
        $newSettings = [
            'login_required'             => '0',
            'wa_nurse_call_msg_suster'   => "NURSE CALL \xF0\x9F\x9A\xA8\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.",
            'wa_nurse_call_msg_keluarga' => "PEMBERITAHUAN \xF0\x9F\x94\x94\nPasien {pasien} di {lokasi} membutuhkan bantuan perawat.\nWaktu: {waktu}\n\nTim medis sedang menuju lokasi.",
            'wa_low_volume_msg_suster'   => "PERINGATAN INFUS \xE2\x9A\xA0\xEF\xB8\x8F\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.",
            'wa_low_volume_msg_keluarga' => "INFO INFUS \xE2\x84\xB9\xEF\xB8\x8F\nCairan infus {pasien} di {lokasi} hampir habis ({persen}%).\nWaktu: {waktu}\n\nTim medis sedang menangani.",
            'wa_tpm_zero_msg_suster'     => "INFUS MACET \xF0\x9F\x94\xB4\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml\nWaktu: {waktu}\n\nTidak ada tetesan terdeteksi. Periksa selang segera.",
            'wa_tpm_zero_msg_keluarga'   => "INFO TEKNIS \xE2\x84\xB9\xEF\xB8\x8F\nPerangkat infus {pasien} di {lokasi} mendeteksi anomali.\nWaktu: {waktu}\n\nTim medis sedang menangani.",
            'wa_tpm_high_msg_suster'     => "TPM TERLALU CEPAT \xE2\x9A\xA1\nPasien: {pasien}\nLokasi: {lokasi}\nTPM: {tpm} tetes/menit\nWaktu: {waktu}\n\nHarap periksa dan sesuaikan pengaturan.",
            'wa_tpm_high_msg_keluarga'   => "INFO TEKNIS \xE2\x84\xB9\xEF\xB8\x8F\nPerangkat infus {pasien} di {lokasi} membutuhkan penyesuaian.\nWaktu: {waktu}\n\nTim medis sedang menangani.",
            'wa_resolved_msg_keluarga'   => "KONDISI NORMAL \xE2\x9C\x85\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nKabar baik! {resolved_label}. Tidak perlu khawatir.",
        ];
        $ins = $db->prepare("INSERT IGNORE INTO settings (key_name, key_value) VALUES (?, ?)");
        foreach ($newSettings as $k => $v) {
            $ins->execute([$k, $v]);
        }
    } catch (\Throwable $e) {
        error_log('ensureUsersTable error: ' . $e->getMessage());
    }
}
