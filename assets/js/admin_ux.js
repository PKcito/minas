// ============================================================
// admin_ux.js - Utilidades UX (Minas Burguer)
// ============================================================

function toastExito(mensaje, titulo) {
    if (typeof mostrarAlerta === 'function') {
        mostrarAlerta('success', titulo || 'Listo!', mensaje);
    } else {
        console.log('EXITO:', mensaje);
    }
}

function toastError(mensaje, titulo) {
    if (typeof mostrarAlerta === 'function') {
        mostrarAlerta('error', titulo || 'Error', mensaje);
    } else {
        console.log('ERROR:', mensaje);
    }
}

function toastInfo(mensaje, titulo) {
    if (typeof mostrarAlerta === 'function') {
        mostrarAlerta('info', titulo || 'Info', mensaje);
    } else {
        console.log('INFO:', mensaje);
    }
}

function confirmarAccion(opciones, callback) {
    const opts = Object.assign({
        titulo: 'Estas seguro?',
        texto: '',
        icono: 'question',
        textoConfirmar: 'Si, continuar',
        textoCancelar: 'Cancelar',
        colorBoton: '#d32f2f'
    }, opciones || {});

    if (typeof Swal === 'undefined') {
        if (confirm(opts.titulo + '\n\n' + opts.texto)) {
            if (typeof callback === 'function') callback();
        }
        return;
    }

    Swal.fire({
        title: opts.titulo,
        text: opts.texto,
        icon: opts.icono,
        showCancelButton: true,
        confirmButtonColor: opts.colorBoton,
        cancelButtonColor: '#6c757d',
        confirmButtonText: opts.textoConfirmar,
        cancelButtonText: opts.textoCancelar,
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed && typeof callback === 'function') {
            callback();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {

    // Bloqueo de doble submit
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            if (form.classList.contains('confirm-form')) return;

            const boton = form.querySelector('button[type="submit"]');
            if (boton && !boton.hasAttribute('data-no-block')) {
                boton.disabled = true;
                boton.setAttribute('data-html-original', boton.innerHTML);
                boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                setTimeout(function() {
                    boton.disabled = false;
                    if (boton.getAttribute('data-html-original')) {
                        boton.innerHTML = boton.getAttribute('data-html-original');
                    }
                }, 10000);
            }
        });
    });

    // Foco automatico
    const primerInput = document.querySelector(
        'input[type="text"]:not([readonly]):not([disabled]):not([type="hidden"]), ' +
        'input[type="email"]:not([readonly]), ' +
        'input[type="password"]:not([readonly])'
    );
    if (primerInput) {
        setTimeout(function() { primerInput.focus(); }, 300);
    }

    // Convertir banners PHP en toasts
    const successEl = document.querySelector('[data-flash-success]');
    const errorEl   = document.querySelector('[data-flash-error]');

    if (successEl) {
        toastExito(successEl.getAttribute('data-flash-success'));
        successEl.style.display = 'none';
    }
    if (errorEl) {
        toastError(errorEl.getAttribute('data-flash-error'));
        errorEl.style.display = 'none';
    }

    // ============================================================
    // CONTADOR DE CARACTERES AUTOMÁTICO
    // Busca cualquier input o textarea con maxlength y le pone un contador
    // ============================================================
    document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function(input) {
        // Evitar duplicados si ya tiene un contador
        if (input.nextElementSibling && input.nextElementSibling.classList.contains('char-counter-auto')) return;

        const max = input.getAttribute('maxlength');
        if (!max || max > 500) return; // No poner contador a campos gigantes
        
        const counter = document.createElement('small');
        counter.className = 'char-counter-auto';
        counter.style.cssText = 'display:block; text-align:right; font-size:0.72rem; color:var(--texto-secundario); margin-top:0.2rem; font-weight:600; transition: 0.2s;';
        
        function updateCount() {
            const len = input.value.length;
            counter.textContent = len + '/' + max;
            if (len >= max) {
                counter.style.color = '#dc3545'; // Rojo
            } else if (len >= max * 0.8) {
                counter.style.color = '#ff9800'; // Naranja
            } else {
                counter.style.color = 'var(--texto-secundario)'; // Normal
            }
        }

        input.addEventListener('input', updateCount);
        input.parentNode.insertBefore(counter, input.nextSibling);
        updateCount();
    });
});