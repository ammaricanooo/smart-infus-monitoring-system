<?php
// =====================================================
// HELPER: KIRIM WHATSAPP
// Mendukung 2 provider:
//   - 'custom'  : Gateway pribadi berbasis Baileys/whatsapp-web.js
//   - 'fonnte'  : Fonnte (https://fonnte.com)
// Provider aktif dikontrol lewat setting 'wa_provider'.
// =====================================================

require_once __DIR__ . '/settings.php';

/**
 * Kirim pesan WhatsApp ke satu atau beberapa nomor.
 *
 * @param  string|array $targets  Nomor tujuan (format: 628xxx) atau array nomor
 * @param  string       $message  Isi pesan (plain text, bisa pakai \n)
 * @return array                  ['success' => bool, 'provider' => string, ...]
 */
function sendWhatsApp(string|array $targets, string $message): array {
    // Normalise targets
    if (is_array($targets)) {
        $targets = array_values(array_filter(array_map('trim', $targets)));
        if (empty($targets)) {
            return ['success' => false, 'error' => 'Tidak ada nomor tujuan'];
        }
    } else {
        $targets = [trim($targets)];
    }

    $provider = trim(getSetting('wa_provider', 'custom'));

    if ($provider === 'fonnte') {
        return _sendViaFonnte($targets, $message);
    }

    // default: custom gateway
    return _sendViaCustom($targets, $message);
}

// ─────────────────────────────────────────────────
// PROVIDER: Custom Gateway (Baileys / whatsapp-web.js)
// Endpoint  : wa_api_url  (setting)
// Auth      : x-api-key header dengan wa_api_key
// ─────────────────────────────────────────────────
function _sendViaCustom(array $targets, string $message): array {
    $apiUrl = trim(getSetting('wa_api_url', ''));
    $apiKey = trim(getSetting('wa_api_key', ''));

    if (empty($apiUrl) || empty($apiKey)) {
        return ['success' => false, 'provider' => 'custom', 'error' => 'Custom Gateway: URL atau API Key belum dikonfigurasi'];
    }

    $baseUrl = rtrim($apiUrl, '/');
    $endpoint = count($targets) > 1 ? '/send-bulk' : '/send-text';
    // Jika URL sudah berakhiran endpoint kirim, pakai apa adanya
    if (!preg_match('#/(send-text|send-bulk|send)$#i', $baseUrl)) {
        $apiUrl = $baseUrl . $endpoint;
    } else {
        $apiUrl = $baseUrl;
    }

    $payload = count($targets) > 1
        ? ['phones' => $targets, 'message' => $message]
        : ['phone'  => $targets[0], 'message' => $message];

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: infus2/1.0',
        'x-api-key: ' . $apiKey,
    ];

    $result = _curlPost($apiUrl, json_encode($payload), $headers);
    $result['provider'] = 'custom';

    if (!$result['curl_ok']) return $result;

    $decoded = json_decode($result['raw'], true);
    $result['response'] = $decoded;
    $result['success'] = ($result['http'] === 200) && (
        (isset($decoded['success']) && $decoded['success'] === true) ||
        (isset($decoded['status'])  && $decoded['status']  === true)
    );
    return $result;
}

// ─────────────────────────────────────────────────
// PROVIDER: Fonnte (https://fonnte.com)
// Endpoint  : https://api.fonnte.com/send  (tetap)
// Auth      : Authorization header dengan fonnte_token
// Mendukung banyak nomor sekaligus (dipisah koma)
// ─────────────────────────────────────────────────
function _sendViaFonnte(array $targets, string $message): array {
    $token = trim(getSetting('fonnte_token', ''));

    if (empty($token)) {
        return ['success' => false, 'provider' => 'fonnte', 'error' => 'Fonnte: Token belum dikonfigurasi'];
    }

    $payload = [
        'target'  => implode(',', $targets),
        'message' => $message,
    ];

    $headers = ['Authorization: ' . $token];

    // Fonnte pakai form-encoded (bukan JSON)
    $result = _curlPost('https://api.fonnte.com/send', $payload, $headers, 'form');
    $result['provider'] = 'fonnte';

    if (!$result['curl_ok']) return $result;

    $decoded = json_decode($result['raw'], true);
    $result['response'] = $decoded;
    $result['success'] = ($result['http'] === 200) &&
                         isset($decoded['status']) && $decoded['status'] === true;
    return $result;
}

// ─────────────────────────────────────────────────
// HELPER cURL — dipakai oleh kedua provider
// ─────────────────────────────────────────────────
function _curlPost(string $url, $payload, array $headers, string $type = 'json'): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    if (defined('CURLOPT_POSTREDIR')) {
        curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
    } else {
        @curl_setopt($ch, CURLOPT_POSTREDIR, 3);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response     = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErr      = curl_error($ch);
    curl_close($ch);

    $rawHeaders = '';
    $rawBody    = $response;
    if (is_int($headerSize) && $headerSize > 0) {
        $rawHeaders = substr($response, 0, $headerSize);
        $rawBody    = substr($response, $headerSize);
    }

    if ($curlErr) {
        return [
            'success'  => false,
            'curl_ok'  => false,
            'error'    => 'cURL error: ' . $curlErr,
            'http'     => $httpCode,
            'url'      => $effectiveUrl,
            'headers'  => $rawHeaders,
            'raw'      => $rawBody,
        ];
    }

    return [
        'curl_ok' => true,
        'success' => false,   // diisi caller
        'http'    => $httpCode,
        'url'     => $effectiveUrl,
        'headers' => $rawHeaders,
        'raw'     => $rawBody,
    ];
}

/**
 * Render template pesan dengan mengganti placeholder {key} → value.
 *
 * @param  string $template  Template dari settings
 * @param  array  $vars      Associative array placeholder → value
 * @return string
 */
function renderWaMessage(string $template, array $vars): string {
    foreach ($vars as $key => $val) {
        $template = str_replace('{' . $key . '}', $val, $template);
    }
    // Konversi literal \n ke newline nyata
    return str_replace('\n', "\n", $template);
}
