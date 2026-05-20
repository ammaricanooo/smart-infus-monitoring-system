<?php
// =====================================================
// HELPER: KIRIM WHATSAPP VIA FONNTE API
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
    $token = getSetting('fonnte_token', '');

    if (empty($token)) {
        return ['success' => false, 'error' => 'Fonnte token belum dikonfigurasi'];
    }

    // Fonnte menerima multiple target dipisah koma
    if (is_array($targets)) {
        $targets = array_filter($targets);          // buang yang kosong
        if (empty($targets)) {
            return ['success' => false, 'error' => 'Tidak ada nomor tujuan'];
        }
        $targetStr = implode(',', $targets);
    } else {
        $targetStr = trim($targets);
        if (empty($targetStr)) {
            return ['success' => false, 'error' => 'Tidak ada nomor tujuan'];
        }
    }

    $ch = curl_init('https://api.fonnte.com/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'target'  => $targetStr,
            'message' => $message,
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['success' => false, 'error' => 'cURL error: ' . $curlErr];
    }

    $decoded = json_decode($response, true);
    $ok      = ($httpCode === 200) && isset($decoded['status']) && $decoded['status'] === true;

    return [
        'success'  => $ok,
        'http'     => $httpCode,
        'response' => $decoded,
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
