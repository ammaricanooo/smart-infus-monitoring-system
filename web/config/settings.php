<?php
// =====================================================
// HELPER: BACA / TULIS SETTINGS
// =====================================================

function getSetting(string $key, string $default = ''): string {
    static $cache = null;

    // Invalidate cache if setSetting was called in this request
    if (!empty($GLOBALS['_settings_cache_dirty'])) {
        $cache = null;
        $GLOBALS['_settings_cache_dirty'] = false;
    }

    if ($cache === null) {
        try {
            $db   = getDB();
            $rows = $db->query("SELECT key_name, key_value FROM settings")->fetchAll();
            $cache = [];
            foreach ($rows as $r) {
                $cache[$r['key_name']] = $r['key_value'];
            }
        } catch (\Throwable $e) {
            return $default;
        }
    }

    return $cache[$key] ?? $default;
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $db->prepare("
        INSERT INTO settings (key_name, key_value)
        VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE key_value = :v2, updated_at = NOW()
    ")->execute([':k' => $key, ':v' => $value, ':v2' => $value]);

    // Invalidate static cache so subsequent getSetting() in the same request
    // sees the updated value immediately — prevents double-trigger on same POST
    static $cacheRef = null;
    // Force cache reset by calling getSetting with a side-effect flag
    $GLOBALS['_settings_cache_dirty'] = true;
}

function getAllSettings(): array {
    try {
        $db   = getDB();
        $rows = $db->query("SELECT key_name, key_value FROM settings ORDER BY key_name")->fetchAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['key_name']] = $r['key_value'];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}
