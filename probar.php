<?php
$archivo = __DIR__ . '/admin/dashboard.php';

echo "<h2>Diagnóstico del dashboard</h2>";
echo "<p><strong>Ruta completa:</strong> " . $archivo . "</p>";
echo "<p><strong>¿Existe?</strong> " . (file_exists($archivo) ? '✅ SÍ' : '❌ NO') . "</p>";
echo "<p><strong>¿Es legible?</strong> " . (is_readable($archivo) ? '✅ SÍ' : '❌ NO') . "</p>";
echo "<p><strong>Tamaño:</strong> " . filesize($archivo) . " bytes</p>";
echo "<p><strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($archivo)), -4) . "</p>";

echo "<hr>";
echo "<h3>Primeras 15 líneas del archivo:</h3>";
echo "<pre>";
$lineas = file($archivo);
for ($i = 0; $i < min(15, count($lineas)); $i++) {
    echo htmlspecialchars($lineas[$i]);
}
echo "</pre>";

echo "<hr>";
echo "<p><a href='admin/dashboard.php'>Ir al dashboard</a></p>";