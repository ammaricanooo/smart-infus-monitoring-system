<?php
// =====================================================
// HELPER: KIRIM WHATSAPP VIA CUSTOM GATEWAY API
// =====================================================

require_once __DIR__ . '/settings.php';

/**
 * Kirim pesan WhatsApp ke satu atau beberapa nomor.
 *
 * @param  string|array $targets  Nomor tujuan (format: 628xxx) atau array nomor
 * @param  string       $message  Isi pesan (plain text, bisa pakai \n)
 * @return array                  ['success' => bool, 'results' => [...]]
 */
function sendWhatsApp(string|array $targets, string $message): array {
    $apiUrl      = trim(getSetting('wa_api_url', ''));
    $apiKey      = trim(getSetting('wa_api_key', ''));
    $legacyToken = trim(getSetting('fonnte_token', ''));

    $useLegacyFonnte = false;
    if (empty($apiUrl) || empty($apiKey)) {
        if (empty($legacyToken)) {
            return ['success' => false, 'error' => 'WhatsApp API Key belum dikonfigurasi'];
        }
        $apiUrl = 'https://api.fonnte.com/send';
        $apiKey = $legacyToken;
        $useLegacyFonnte = true;
    }

    if (is_array($targets)) {
        $targets = array_filter($targets);
        if (empty($targets)) {
            return ['success' => false, 'error' => 'Tidak ada nomor tujuan'];
        }
    } else {
        $targets = [trim($targets)];
    }

    if ($useLegacyFonnte) {
        $targetStr = implode(',', $targets);
        $payload = [
            'target'  => $targetStr,
            'message' => $message,
        ];
        $headers = ['Authorization: ' . $apiKey];
        $contentType = 'form';
    } else {
        $baseUrl = rtrim($apiUrl, '/');
        $endpoint = count($targets) > 1 ? '/send-bulk' : '/send-text';
        if (preg_match('#/(send-text|send-bulk|send)$#i', $baseUrl)) {
            $apiUrl = $baseUrl;
        } else {
            $apiUrl = $baseUrl . $endpoint;
        }

        if (count($targets) > 1) {
            $payload = [
                'phones'  => array_values($targets),
                'message' => $message,
            ];
        } else {
            $payload = [
                'phone'   => $targets[0],
                'message' => $message,
            ];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: infus2/1.0',
            'x-api-key: ' . $apiKey,
        ];
        $contentType = 'json';
    }

    $ch = curl_init($apiUrl);
    // capture headers + body so we can diagnose non-JSON HTML errors
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    // Follow HTTP redirects (301/302) so APIs that upgrade to HTTPS or redirect paths work
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    // Ensure POST is preserved when following redirects (prevent POST -> GET conversion)
    if (defined('CURLOPT_POSTREDIR')) {
        curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
    } else {
        // Fallback numeric value if constant missing
        @curl_setopt($ch, CURLOPT_POSTREDIR, 3);
    }

    if ($contentType === 'json') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $responseHeaders = null;
    $responseBody = $response;
    if (is_int($headerSize) && $headerSize > 0) {
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
    }

    if ($curlErr) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlErr, 'http' => $httpCode, 'url' => $effectiveUrl, 'raw' => $responseBody, 'headers' => $responseHeaders];
    }

    $decoded = json_decode($responseBody, true);
    if ($useLegacyFonnte) {
        $ok = ($httpCode === 200) && isset($decoded['status']) && $decoded['status'] === true;
    } else {
        $ok = ($httpCode === 200) && (
            (isset($decoded['success']) && $decoded['success'] === true) ||
            (isset($decoded['status']) && $decoded['status'] === true)
        );
    }

    return [
        'success'  => $ok,
        'http'     => $httpCode,
        'url'      => $effectiveUrl,
        'headers'  => $responseHeaders,
        'response' => $decoded,
        'raw'      => $responseBody,
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
