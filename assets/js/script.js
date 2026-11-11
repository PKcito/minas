document.addEventListener('DOMContentLoaded', function() {

    const body = document.body;

    // Quitar el bloqueo de transiciones despues de que se renderiza
    if (document.documentElement.classList.contains('modo-fijo-preload')) {
        document.documentElement.classList.remove('modo-fijo-preload');
        body.style.visibility = 'visible';
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                body.classList.remove('no-transition');
            });
        });
    }

    // ============================================================
    // MODO OSCURO / CLARO
    // ============================================================
    const themeToggle = document.getElementById('themeToggle');

    if (themeToggle) {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }

        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            this.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });
    }

    // ============================================================
    // FUNCIONES COMPARTIDAS
    // ============================================================
    window.mostrarAlerta = function(icono, titulo, mensaje = '') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: icono,
                title: titulo,
                text: mensaje,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    };

    // ============================================================
    // MENU LATERAL
    // ============================================================
    const menuToggleBtn = document.getElementById('menuToggle');
    const offcanvasMenu = document.getElementById('offcanvasMenu');
    const closeMenuBtn  = document.getElementById('closeMenu');
    const overlayBtn    = document.getElementById('overlay');

    function esModoFijo() {
        return body.classList.contains('menu-fijo') && window.innerWidth > 768;
    }

    function abrirMenu() {
        if (offcanvasMenu) offcanvasMenu.classList.add('open');
        if (overlayBtn)    overlayBtn.classList.add('active');
        body.classList.add('menu-abierto');
    }

    function cerrarMenu() {
        if (offcanvasMenu) offcanvasMenu.classList.remove('open');
        if (overlayBtn)    overlayBtn.classList.remove('active');
        body.classList.remove('menu-abierto');
    }

    if (menuToggleBtn) {
        menuToggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (esModoFijo()) return;
            if (offcanvasMenu && offcanvasMenu.classList.contains('open')) {
                cerrarMenu();
            } else {
                abrirMenu();
            }
        });
    }

    if (closeMenuBtn) closeMenuBtn.addEventListener('click', cerrarMenu);
    if (overlayBtn) overlayBtn.addEventListener('click', cerrarMenu);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarMenu();
    });

    // ============================================================
    // MODO FIJO / FLOTANTE
    // ============================================================
    const MODE_KEY = 'minas_menu_mode';
    const toggleModeBtn = document.getElementById('toggleMenuMode');
    const menuModeText = document.getElementById('menuModeText');

    function aplicarModoMenu(modo) {
        if (window.innerWidth <= 768) {
            body.classList.remove('menu-fijo');
            body.classList.remove('menu-abierto');
            if (menuModeText) menuModeText.textContent = 'Modo fijo';
            return;
        }

        if (modo === 'fijo') {
            body.classList.add('menu-fijo');
            body.classList.remove('menu-abierto');
            if (offcanvasMenu) offcanvasMenu.classList.remove('open');
            if (overlayBtn)    overlayBtn.classList.remove('active');
            if (menuModeText) menuModeText.textContent = 'Modo flotante';
        } else {
            body.classList.remove('menu-fijo');
            if (menuModeText) menuModeText.textContent = 'Modo fijo';
        }
    }

    const modoGuardado = localStorage.getItem(MODE_KEY);
    if (modoGuardado) {
        aplicarModoMenu(modoGuardado);
    } else {
        if (menuModeText) menuModeText.textContent = 'Modo fijo';
    }

    if (toggleModeBtn) {
        toggleModeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const actual = localStorage.getItem(MODE_KEY) === 'fijo' ? 'fijo' : 'flotante';
            const nuevo = actual === 'fijo' ? 'flotante' : 'fijo';
            localStorage.setItem(MODE_KEY, nuevo);
            aplicarModoMenu(nuevo);
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            body.classList.remove('menu-fijo');
            body.classList.remove('menu-abierto');
        } else {
            const modo = localStorage.getItem(MODE_KEY) || 'flotante';
            aplicarModoMenu(modo);
        }
    });

});