<?php
require_once __DIR__ . '/../config.php';

if (!esAdmin()) { redirect('index.php'); }
requierePermiso('reportes'); 

$estados = $pdo->query("SELECT * FROM estados_pedido ORDER BY id")->fetchAll();
$metodos_pago = $pdo->query("SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id")->fetchAll();
$estados_pago = $pdo->query("SELECT * FROM estados_pago ORDER BY id")->fetchAll();
$clientes = $pdo->query("SELECT c.id, c.nombres, c.apellidos FROM clientes c JOIN usuarios u ON c.usuario_id = u.id ORDER BY c.nombres, c.apellidos")->fetchAll();

$tipos_reporte = [
    'general'         => 'Reporte general de pedidos',
    'por_producto'    => 'Ventas por producto',
    'por_categoria'   => 'Ventas por categoria',
    'por_metodo_pago' => 'Ventas por metodo de pago',
    'por_entrega'     => 'Delivery vs Recoger',
    'por_cliente'     => 'Pedidos por cliente',
    'por_estado'      => 'Pedidos por estado',
];

partial('head', [
    'titulo_pagina' => 'Reportes',
    'css_pagina' => 'admin',
    'css_extra' => ['css/reportes.css']
]);
?>

<?php partial('menu', ['menu_activo' => 'reportes']); ?>
<?php partial('header', ['titulo_pagina' => 'Reportes']); ?>

<main class="container reportes-main">
    <h2 class="titulo-admin"><i class="fas fa-file-pdf"></i> Generar reportes</h2>

    <div class="reportes-info-box">
        <i class="fas fa-info-circle"></i> Selecciona el tipo de reporte y los filtros opcionales. El documento PDF se abrirá en una pestaña nueva listo para descargar o imprimir.
    </div>

    <form method="post" action="<?= url('admin/reporte_pdf.php') ?>" target="_blank">
        <div class="tabla-card reportes-card">
            <h3 class="reportes-subtitulo"><i class="fas fa-file-alt"></i> Tipo de reporte</h3>
            <div class="campo-form">
                <select name="tipo_reporte" required>
                    <?php foreach ($tipos_reporte as $key => $label): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="tabla-card reportes-card">
            <h3 class="reportes-subtitulo"><i class="fas fa-filter"></i> Filtros (opcionales)</h3>
            <div class="filtros-grid">
                <div class="filtro-item">
                    <label>Fecha desde</label>
                    <input type="date" name="fecha_desde">
                </div>
                <div class="filtro-item">
                    <label>Fecha hasta</label>
                    <input type="date" name="fecha_hasta">
                </div>
                <div class="filtro-item">
                    <label>Estado del pedido</label>
                    <select name="estado">
                        <option value="0">Todos</option>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= intval($e['id']) ?>"><?= htmlspecialchars(ucfirst($e['nombre'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-item">
                    <label>Tipo de entrega</label>
                    <select name="entrega">
                        <option value="">Todas</option>
                        <option value="delivery">Delivery</option>
                        <option value="recoger">Recoger</option>
                    </select>
                </div>
                <div class="filtro-item">
                    <label>Metodo de pago</label>
                    <select name="metodo_pago">
                        <option value="0">Todos</option>
                        <?php foreach ($metodos_pago as $mp): ?>
                            <option value="<?= intval($mp['id']) ?>"><?= htmlspecialchars($mp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-item">
                    <label>Estado del pago</label>
                    <select name="estado_pago">
                        <option value="0">Todos</option>
                        <?php foreach ($estados_pago as $ep): ?>
                            <option value="<?= intval($ep['id']) ?>"><?= htmlspecialchars(ucfirst($ep['nombre'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtro-item">
                    <label>Cliente</label>
                    <select name="cliente_id">
                        <option value="0">Todos</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= intval($c['id']) ?>"><?= htmlspecialchars($c['nombres'] . ' ' . $c['apellidos']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="reportes-acciones">
            <button type="submit" class="btn-admin btn-generar-pdf">
                <i class="fas fa-file-pdf"></i> Generar PDF
            </button>
        </div>
    </form>
</main>

<?php partial('footer', ['cargar_admin_ux' => true]); ?>