<?php
/**
 * Helpers del carrito - Minas Burguer
 * Centraliza la logica de manipulacion del carrito en sesion.
 */

if (!function_exists('agregarAlCarrito')) {
    function agregarAlCarrito($producto_id, $producto_nombre, $producto_precio, $cantidad = 1, $extras = []) {
        if (!isset($_SESSION['carrito'])) $_SESSION['carrito'] = [];
        $precio_total = $producto_precio;
        foreach ($extras as $extra) { $precio_total += $extra['precio']; }
        $encontrado = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id'] == $producto_id && json_encode($item['extras'] ?? []) == json_encode($extras)) {
                $item['cantidad'] += $cantidad; $encontrado = true; break;
            }
        }
        unset($item);
        if (!$encontrado) {
            $_SESSION['carrito'][] = [
                'id'         => $producto_id,
                'nombre'     => $producto_nombre,
                'precio'     => $precio_total,
                'precio_base'=> $producto_precio,
                'extras'     => $extras,
                'cantidad'   => $cantidad
            ];
        }
    }
}

if (!function_exists('eliminarDelCarrito')) {
    function eliminarDelCarrito($producto_id) {
        if (isset($_SESSION['carrito'])) {
            foreach ($_SESSION['carrito'] as $key => $item) {
                if ($item['id'] == $producto_id) { unset($_SESSION['carrito'][$key]); $_SESSION['carrito'] = array_values($_SESSION['carrito']); break; }
            }
        }
    }
}

if (!function_exists('actualizarCantidad')) {
    function actualizarCantidad($producto_id, $nueva_cantidad) {
        if (isset($_SESSION['carrito'])) {
            foreach ($_SESSION['carrito'] as &$item) {
                if ($item['id'] == $producto_id) {
                    if ($nueva_cantidad <= 0) { eliminarDelCarrito($producto_id); } else { $item['cantidad'] = $nueva_cantidad; }
                    break;
                }
            }
            unset($item);
        }
    }
}