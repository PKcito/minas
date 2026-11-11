<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/validaciones.php';

if (!usuarioLogueado()) { redirect('auth/login.php'); }

// ============================================================
// VALIDACION DE ESTADO
// ============================================================
if (isset($_SESSION['checkout_data']['comprobante'])) {
    redirect_prg('cliente/finalizar_pedido.php');
}

if (!isset($_SESSION['checkout_data']) || empty($_SESSION['carrito'])) {
    redirect_prg('cliente/checkout.php');
}

$metodo_pago = $_SESSION['checkout_data']['metodo_pago'] ?? '';
$total = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total += floatval($item['precio']) * intval($item['cantidad']);
}
$monto_a_pagar = $metodo_pago === 'efectivo'
    ? floatval($_SESSION['checkout_data']['monto_pagado'] ?? ($total * 0.5))
    : $total;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_comprobante'])) {
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $validacion = v_archivo_comprobante($_FILES['comprobante'], 2);

        if (!$validacion['ok']) {
            $error = $validacion['error'] ?: 'Error al validar el archivo.';
        } else {
            $nombre = v_generar_nombre_archivo('comprobante', $validacion['extension']);
            $ruta = __DIR__ . '/../comprobantes/' . $nombre;

            if (!is_dir(__DIR__ . '/../comprobantes')) mkdir(__DIR__ . '/../comprobantes', 0777, true);

            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $ruta)) {
                $_SESSION['checkout_data']['comprobante'] = $nombre;
                // PRG: limpia el POST del historial
                redirect_prg('cliente/finalizar_pedido.php');
            } else {
                $error = 'Error al guardar el comprobante.';
            }
        }
    } else {
        $error = 'Selecciona un archivo.';
    }
}

partial('head', [
    'titulo_pagina' => 'Subir comprobante',
    'css_pagina' => 'cliente',
    'css_extra' => ['css/comprobante_pago.css'] // Enlazamos el CSS externo
]);
?>

<?php partial('header', ['titulo_pagina' => 'Subir comprobante', 'mostrar_salir' => false]); ?>

<div class="comprobante-container">
    <div class="comprobante-card">
        <h2><i class="fas fa-upload"></i> Subir comprobante</h2>
        <p class="subtitulo">
            <?php if ($metodo_pago === 'efectivo'): ?>
                Cancelaste un adelanto del <strong>50%</strong>. Sube el comprobante de tu pago para confirmarlo.
            <?php else: ?>
                Realiza el pago a la cuenta de PagoMovil y sube el comprobante.
            <?php endif; ?>
        </p>

        <div class="monto-box">
            <div class="label">Monto a pagar</div>
            <div class="valor">$<?= number_format($monto_a_pagar, 2) ?></div>
        </div>

        <?php if ($error): ?>
            <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="formComprobante">
            <input type="file" name="comprobante" id="inputComprobante" accept="image/jpeg,image/png,image/gif,application/pdf" required>

            <label for="inputComprobante" class="upload-area" id="uploadArea">
                <div class="icono-subida"><i class="fas fa-cloud-upload-alt"></i></div>
                <div class="texto-subida">Pulsa aqui para subir tu comprobante</div>
                <div class="texto-info">JPG, PNG, GIF, PDF · Max 2MB</div>
            </label>

            <div class="preview-container" id="previewContainer">
                <div class="preview-wrapper" id="previewWrapper"></div>
                <div class="preview-info">
                    <i class="fas fa-check-circle"></i>
                    <span id="previewNombre">Archivo listo</span>
                </div>
                <br>
                <button type="button" class="btn-cambiar" id="btnCambiar">
                    <i class="fas fa-sync-alt"></i> Cambiar archivo
                </button>
            </div>

            <button type="submit" name="subir_comprobante" class="btn-enviar" id="btnEnviar" disabled>
                <i class="fas fa-check-circle"></i> Confirmar y continuar
            </button>
        </form>

        <!-- Clases CSS en lugar de estilos en línea -->
        <div class="volver-pedido">
            <a href="<?= url('cliente/checkout.php') ?>" class="btn-volver-pedido">
                <i class="fas fa-arrow-left"></i> Volver a modificar pedido
            </a>
        </div>
    </div>
</div>

<div class="modal-preview" id="modalPreview">
    <!-- Eliminamos el onclick="..." y lo manejamos con JS -->
    <button class="cerrar-preview" id="btnCerrarModal">X</button>
    <img src="" alt="Preview" id="modalPreviewImg">
</div>

<script>
// Pasamos la config necesaria al JS
window.COMPROBANTE_CONFIG = {};
</script>

<?php partial('footer', ['js_extra_footer' => ['js/comprobante_pago.js']]); ?>