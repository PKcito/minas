<?php
// ============================================================
// ENVÍO DE CORREOS VÍA EMAILJS API (XAMPP Y RAILWAY)
// ============================================================

define('EMAILJS_SERVICE_ID',  getenv('EMAILJS_SERVICE_ID')  ?: 'service_x8ef0nx');
define('EMAILJS_TEMPLATE_ID', getenv('EMAILJS_TEMPLATE_ID') ?: 'template_4bo0p1w');
define('EMAILJS_PUBLIC_KEY',  getenv('EMAILJS_PUBLIC_KEY')  ?: 'VRmKANGt9kjeIncPf');
define('EMAILJS_PRIVATE_KEY', getenv('EMAILJS_PRIVATE_KEY') ?: '4ZtcA2H_aSrIKQ-iOhRRp');

function enviar_correo_recuperacion($destinatario, $enlace_recuperacion, &$detalle_error = '') {
    $service_id  = trim(EMAILJS_SERVICE_ID);
    $template_id = trim(EMAILJS_TEMPLATE_ID);
    $public_key  = trim(EMAILJS_PUBLIC_KEY);
    $private_key = trim(EMAILJS_PRIVATE_KEY);

    if (empty($service_id) || empty($template_id) || empty($public_key)) {
        $detalle_error = 'Faltan credenciales en includes/correo.php';
        return false;
    }

    if (!function_exists('curl_init')) {
        $detalle_error = 'La extension cURL de PHP no esta activa en XAMPP.';
        return false;
    }

    $payload = [
        'service_id'  => $service_id,
        'template_id' => $template_id,
        'user_id'     => $public_key,
        'accessToken' => $private_key,
        'template_params' => [
            'to_email'   => $destinatario,
            'email'      => $destinatario,
            'user_email' => $destinatario,
            'reset_link' => $enlace_recuperacion
        ]
    ];

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Origin: http://localhost',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code === 200 || trim((string)$response) === 'OK') {
        return true;
    }

    $detalle_error = "HTTP $http_code: " . ($response ?: $curl_error);
    error_log("Error EmailJS ($http_code): " . $response . " | cURL: " . $curl_error);
    return false;
}