<?php
/**
 * Helper de validaciones - Minas Burguer
 */

if (!function_exists('v_limpiar')) {
    function v_limpiar($valor) {
        if (is_null($valor)) return '';
        return trim((string)$valor);
    }
}

if (!function_exists('v_str')) {
    function v_str($valor, $min = 1, $max = 255, $permitir_numeros = true, $permitir_acentos = true) {
        $valor = v_limpiar($valor);
        if (mb_strlen($valor) < $min) return null;
        if (mb_strlen($valor) > $max) return null;

        if ($permitir_acentos) {
            $patron = $permitir_numeros
                ? '/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s\.\,\-\_\'\"\#\/\(\)]+$/u'
                : '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]+$/u';
        } else {
            $patron = $permitir_numeros
                ? '/^[a-zA-Z0-9\s\.\,\-\_\'\"\#\/\(\)]+$/'
                : '/^[a-zA-Z\s]+$/';
        }

        return preg_match($patron, $valor) ? $valor : null;
    }
}

if (!function_exists('v_texto')) {
    function v_texto($valor, $min = 0, $max = 1000) {
        $valor = v_limpiar($valor);
        if ($min > 0 && mb_strlen($valor) < $min) return null;
        if (mb_strlen($valor) > $max) return null;
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $valor)) return null;
        return $valor;
    }
}

if (!function_exists('v_email')) {
    function v_email($valor) {
        $valor = v_limpiar($valor);
        if (mb_strlen($valor) > 100) return null;
        $valor = filter_var($valor, FILTER_VALIDATE_EMAIL);
        return $valor === false ? null : $valor;
    }
}

if (!function_exists('v_password')) {
    function v_password($valor, $min = 6, $max = 72) { // Límite ajustado a 72 (seguro para bcrypt)
        $valor = (string)$valor;
        if (strlen($valor) < $min || strlen($valor) > $max) return null;
        if (!preg_match('/[a-zA-Z]/', $valor)) return null;
        return $valor;
    }
}

if (!function_exists('v_int')) {
    function v_int($valor, $min = null, $max = null) {
        $valor = filter_var($valor, FILTER_VALIDATE_INT);
        if ($valor === false) return null;
        if ($min !== null && $valor < $min) return null;
        if ($max !== null && $valor > $max) return null;
        return $valor;
    }
}

if (!function_exists('v_float')) {
    function v_float($valor, $min = 0, $max = 999999.99, $decimales = 2) {
        if (!is_numeric($valor)) return null;
        $valor = floatval($valor);
        if ($valor < $min || $valor > $max) return null;
        $redondeado = round($valor, $decimales);
        if (abs($valor - $redondeado) > 0.00001) return null;
        return round($valor, $decimales);
    }
}

if (!function_exists('v_fecha')) {
    function v_fecha($valor, $permitir_vacio = true) {
        $valor = v_limpiar($valor);
        if ($valor === '' && $permitir_vacio) return '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return null;
        $partes = explode('-', $valor);
        if (!checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0])) return null;
        return $valor;
    }
}

if (!function_exists('v_telefono_ve')) {
    function v_telefono_ve($prefijo, $numero) {
        $prefijos_validos = ['0412','0414','0416','0422','0424','0426'];
        $prefijo = v_limpiar($prefijo);
        $numero = v_limpiar($numero);
        if (!in_array($prefijo, $prefijos_validos, true)) return null;
        if (!preg_match('/^\d{7}$/', $numero)) return null;
        return $prefijo . $numero;
    }
}

if (!function_exists('v_archivo_imagen')) {
    function v_archivo_imagen($archivo, $max_mb = 2) {
        if (!isset($archivo) || !is_array($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => null];
        }
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $ext_permitidas = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $ext_permitidas, true)) {
            return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG, GIF o WEBP.'];
        }
        if ($archivo['size'] > $max_mb * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El archivo no debe superar ' . $max_mb . 'MB.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        $mimes_ok = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($mime, $mimes_ok, true)) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen valida.'];
        }
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen valida.'];
        }
        return ['ok' => true, 'extension' => $ext, 'error' => null];
    }
}

if (!function_exists('v_archivo_comprobante')) {
    function v_archivo_comprobante($archivo, $max_mb = 2) {
        if (!isset($archivo) || !is_array($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => null];
        }
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $ext_permitidas = ['jpg','jpeg','png','gif','pdf'];
        if (!in_array($ext, $ext_permitidas, true)) {
            return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG, GIF o PDF.'];
        }
        if ($archivo['size'] > $max_mb * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El archivo no debe superar ' . $max_mb . 'MB.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
        $mimes_ok = ['image/jpeg','image/png','image/gif','application/pdf'];
        if (!in_array($mime, $mimes_ok, true)) {
            return ['ok' => false, 'error' => 'El archivo no es valido.'];
        }
        return ['ok' => true, 'extension' => $ext, 'error' => null];
    }
}

if (!function_exists('v_generar_nombre_archivo')) {
    function v_generar_nombre_archivo($prefijo, $extension) {
        return $prefijo . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
    }
}

if (!function_exists('v_json_error')) {
    function v_json_error($mensaje, $codigo = 400) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
}