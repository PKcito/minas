<?php
/**
 * Helper de paginacion reutilizable
 * Minas Burguer
 * 
 * Uso:
 *   $pag = paginar($pdo, "SELECT ... FROM ... WHERE ...", [$params], 20);
 *   $items = $pag['items'];
 *   echo renderPaginacion($pag);
 */

if (!function_exists('paginar')) {
    function paginar($pdo, $sql_base, $params = [], $por_pagina = 20) {
        $pagina = max(1, intval($_GET['pagina'] ?? 1));

        // Contar total de registros
        $sql_count = "SELECT COUNT(*) FROM (" . $sql_base . ") AS conteo";
        $stmt = $pdo->prepare($sql_count);
        $stmt->execute($params);
        $total = intval($stmt->fetchColumn());

        $total_paginas = max(1, (int) ceil($total / $por_pagina));
        if ($pagina > $total_paginas) $pagina = $total_paginas;

        $offset = ($pagina - 1) * $por_pagina;

        // Obtener los registros de la pagina actual
        $sql = $sql_base . " LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        return [
            'items'         => $items,
            'pagina'        => $pagina,
            'total_paginas' => $total_paginas,
            'total'         => $total,
            'por_pagina'    => $por_pagina,
            'offset'        => $offset,
        ];
    }
}

if (!function_exists('renderPaginacion')) {
    function renderPaginacion($pag) {
        if ($pag['total_paginas'] <= 1) return '';

        $pagina = $pag['pagina'];
        $total  = $pag['total_paginas'];
        $base_url = strtok($_SERVER['REQUEST_URI'], '?');

        $qs = $_GET;
        unset($qs['pagina']);

        $construir_url = function ($num) use ($base_url, $qs) {
            $qs['pagina'] = $num;
            return $base_url . '?' . http_build_query($qs);
        };

        $html = '<nav class="paginacion" aria-label="Paginacion">';

        // Boton anterior
        if ($pagina > 1) {
            $html .= '<a href="' . htmlspecialchars($construir_url($pagina - 1)) . '" class="pag-btn" title="Anterior"><i class="fas fa-chevron-left"></i></a>';
        } else {
            $html .= '<span class="pag-btn disabled"><i class="fas fa-chevron-left"></i></span>';
        }

        // Numeros de pagina (rango de 2 alrededor de la actual)
        $rango  = 2;
        $inicio = max(1, $pagina - $rango);
        $fin    = min($total, $pagina + $rango);

        if ($inicio > 1) {
            $html .= '<a href="' . htmlspecialchars($construir_url(1)) . '" class="pag-btn">1</a>';
            if ($inicio > 2) $html .= '<span class="pag-ellipsis">...</span>';
        }

        for ($i = $inicio; $i <= $fin; $i++) {
            $clase = ($i == $pagina) ? 'pag-btn active' : 'pag-btn';
            $html .= '<a href="' . htmlspecialchars($construir_url($i)) . '" class="' . $clase . '">' . $i . '</a>';
        }

        if ($fin < $total) {
            if ($fin < $total - 1) $html .= '<span class="pag-ellipsis">...</span>';
            $html .= '<a href="' . htmlspecialchars($construir_url($total)) . '" class="pag-btn">' . $total . '</a>';
        }

        // Boton siguiente
        if ($pagina < $total) {
            $html .= '<a href="' . htmlspecialchars($construir_url($pagina + 1)) . '" class="pag-btn" title="Siguiente"><i class="fas fa-chevron-right"></i></a>';
        } else {
            $html .= '<span class="pag-btn disabled"><i class="fas fa-chevron-right"></i></span>';
        }

        $html .= '</nav>';

        // Info de rango
        $desde = $pag['offset'] + 1;
        $hasta = min($pag['offset'] + $pag['por_pagina'], $pag['total']);
        $html .= '<div class="paginacion-info">Mostrando <strong>' . $desde . '</strong> - <strong>' . $hasta . '</strong> de <strong>' . $pag['total'] . '</strong> registros</div>';

        return $html;
    }
}