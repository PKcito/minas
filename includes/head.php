<?php
$titulo_pagina = $titulo_pagina ?? 'Minas Burguer';
$cargar_sweetalert = $cargar_sweetalert ?? false;
$cargar_chartjs = $cargar_chartjs ?? false;
$css_pagina = $css_pagina ?? [];
$css_extra = $css_extra ?? [];
$js_extra = $js_extra ?? [];

if (is_string($css_pagina) && !empty($css_pagina)) {
    $css_pagina = [$css_pagina];
}
if (!is_array($css_pagina)) {
    $css_pagina = [];
}

// Incrementamos la versión del caché para forzar la recarga de CSS y JS
$v = '4';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina) ?> - Minas Burguer</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link rel="stylesheet" href="<?= asset('css/estilos.css') ?>?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>?v=<?= $v ?>">

    <?php foreach ($css_pagina as $css): ?>
        <link rel="stylesheet" href="<?= asset('css/' . $css . '.css') ?>?v=<?= $v ?>">
    <?php endforeach; ?>

    <?php foreach ($css_extra as $css): ?>
        <link rel="stylesheet" href="<?= asset($css) ?>?v=<?= $v ?>">
    <?php endforeach; ?>

    <?php if ($cargar_sweetalert): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>

    <?php if ($cargar_chartjs): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>

    <?php foreach ($js_extra as $js): ?>
        <script src="<?= asset($js) ?>?v=<?= $v ?>"></script>
    <?php endforeach; ?>

    <!-- Script unificado para precarga de menú fijo y respaldo de visibilidad -->
    <script>
    (function() {
        try {
            var modo = localStorage.getItem('minas_menu_mode');
            if (modo === 'fijo' && window.innerWidth > 768) {
                document.documentElement.classList.add('modo-fijo-preload');
            }
        } catch(e) {}
    })();

    // Respaldo de seguridad: remueve la clase de precarga si el DOM carga completo
    window.addEventListener('DOMContentLoaded', function() {
        if (document.documentElement.classList.contains('modo-fijo-preload')) {
            document.documentElement.classList.remove('modo-fijo-preload');
        }
    });
    </script>
</head>
<body>
<script>
(function() {
    try {
        var modo = localStorage.getItem('minas_menu_mode');
        if (modo === 'fijo' && window.innerWidth > 768) {
            document.body.classList.add('menu-fijo');
            document.body.classList.add('no-transition');
        }
    } catch(e) {}
})();
</script>