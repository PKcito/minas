<?php
/**
 * Helper de seguridad - Minas Burguer
 * Headers de seguridad, sanitizacion CSV y utilidades.
 */

if (!function_exists('aplicar_headers_seguridad')) {
    function aplicar_headers_seguridad($es_json = false) {
        // Anti-cache
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Prevencion de sniffing de MIME
        header('X-Content-Type-Options: nosniff');

        // Prevencion de clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Politica de referer
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (limitado a lo que no uses)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Content-Security-Policy
        // Permite CDNs conocidos (FontAwesome, SweetAlert, Chart.js, Google Fonts)
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
             . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; "
             . "img-src 'self' data: blob:; "
             . "connect-src 'self'; "
             . "frame-ancestors 'self'; "
             . "base-uri 'self'; "
             . "form-action 'self';";
        header("Content-Security-Policy: $csp");

        if ($es_json) {
            header('Content-Type: application/json; charset=utf-8');
        }
    }
}

if (!function_exists('sanitizar_celda_csv')) {
    /**
     * Previene CSV Injection (formula injection)
     * Si la celda empieza con =, +, -, @, se prefija con apostrofe
     * para que Excel la trate como texto.
     */
    function sanitizar_celda_csv($valor) {
        $valor = (string)$valor;
        if ($valor === '') return '';
        $primer_caracter = substr($valor, 0, 1);
        if (in_array($primer_caracter, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $valor;
        }
        return $valor;
    }
}

if (!function_exists('sanitizar_fila_csv')) {
    function sanitizar_fila_csv($fila) {
        $salida = [];
        foreach ($fila as $celda) {
            if (is_numeric($celda)) {
                $salida[] = $celda;
            } else {
                $salida[] = sanitizar_celda_csv($celda);
            }
        }
        return $salida;
    }
}

if (!function_exists('log_error_seguro')) {
    function log_error_seguro($contexto, $e) {
        error_log("[MINAS-$contexto] " . $e->getMessage());
    }
}