<?php
$cargar_admin_ux = $cargar_admin_ux ?? false;
$js_extra_footer = $js_extra_footer ?? [];
$v = '7';
?>

<?php if (!empty($_SESSION['bienvenida_pendiente'])): ?>
    <?php if (empty($cargar_sweetalert)): ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Bienvenido!',
                    text: 'Hola <?= htmlspecialchars(addslashes($_SESSION['nombres'] ?? 'cliente')) ?>! Que bueno verte de nuevo.',
                    confirmButtonColor: '#d32f2f',
                    confirmButtonText: 'Ver el menu'
                });
            }
        });
    </script>
    <?php unset($_SESSION['bienvenida_pendiente']); ?>
<?php endif; ?>

<!-- LOADER DE HAMBURGUESA -->
<div id="pageLoader" aria-hidden="true">
    <div class="hamburger-loader">
        <div class="capa bun-top"></div>
        <div class="capa lettuce"></div>
        <div class="capa cheese"></div>
        <div class="capa patty"></div>
        <div class="capa bun-bottom"></div>
    </div>
    <div class="loader-texto">Cargando...</div>
</div>

<script>
(function() {
    'use strict';

    // ============================================================
    // LOADER DE HAMBURGUESA
    // ============================================================
    const loader = document.getElementById('pageLoader');
    if (loader) {
        let loaderTimeout = null;

        function mostrarLoader() {
            loader.classList.add('activo');
            clearTimeout(loaderTimeout);
            loaderTimeout = setTimeout(function() {
                loader.classList.remove('activo');
            }, 15000);
        }

        function ocultarLoader() {
            clearTimeout(loaderTimeout);
            loader.classList.remove('activo');
        }

        window.mostrarLoaderGlobal = mostrarLoader;
        window.ocultarLoaderGlobal = ocultarLoader;

        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href) return;
            if (href.startsWith('#') || href.startsWith('javascript:')) return;
            if (link.target === '_blank') return;
            if (link.hasAttribute('data-no-loader')) return;
            if (link.hasAttribute('onclick')) return;
            if (link.href && link.href.indexOf(location.origin) !== 0) return;
            mostrarLoader();
        });

        document.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            const form = e.target;
            if (!form || form.nodeName !== 'FORM') return;
            if (form.hasAttribute('data-no-loader')) return;
            if (form.target === '_blank') return;
            if (form.classList.contains('confirm-form')) return;
            mostrarLoader();
        });

        window.addEventListener('pageshow', function() {
            ocultarLoader();
        });
        window.addEventListener('load', function() {
            ocultarLoader();
        });
        document.addEventListener('DOMContentLoaded', function() {
            ocultarLoader();
        });

        const observer = new MutationObserver(function() {
            if (loader.classList.contains('activo') && document.querySelector('.swal2-container')) {
                ocultarLoader();
            }
        });
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                observer.observe(document.body, { childList: true, subtree: true });
            });
        }
    }

    // ============================================================
    // ANTI "CONFIRM FORM RESUBMISSION"
    // Solo recarga si el navegador restaura la pagina del bfcache
    // ============================================================
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // La pagina fue restaurada del bfcache - recargar para tener datos frescos
            window.location.reload();
        }
    });

})();
</script>

<script src="<?= asset('js/script.js') ?>?v=<?= $v ?>"></script>

<?php if ($cargar_admin_ux): ?>
    <script src="<?= asset('js/admin_ux.js') ?>?v=<?= $v ?>"></script>
<?php endif; ?>

<?php foreach ($js_extra_footer as $js): ?>
    <script src="<?= asset($js) ?>?v=<?= $v ?>"></script>
<?php endforeach; ?>

</body>
</html>