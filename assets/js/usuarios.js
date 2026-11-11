/* ============================================================
   LÓGICA DE GESTIÓN DE ADMINISTRADORES - MINAS BURGUER
   ============================================================ */

window.editarPermisos = function(userId, permisosActuales) {
    const inputUser = document.getElementById('perm_user_id');
    const modal = document.getElementById('modalPermisos');
    if (!inputUser || !modal) return;

    inputUser.value = userId;
    document.querySelectorAll('.chk-permiso').forEach(function(chk) {
        const marcado = Array.isArray(permisosActuales) && permisosActuales.includes(chk.value);
        chk.checked = marcado;
        const item = chk.closest('.extra-check-item');
        if (item) {
            item.classList.toggle('seleccionado', marcado);
        }
    });
    modal.classList.add('activo');
};

window.cerrarModalPermisos = function() {
    const modal = document.getElementById('modalPermisos');
    if (modal) {
        modal.classList.remove('activo');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const modalPerm = document.getElementById('modalPermisos');
    if (modalPerm) {
        modalPerm.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalPermisos();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarModalPermisos();
    });

    // Resaltar visualmente los checkboxes al marcarlos o desmarcarlos
    document.querySelectorAll('.extra-check-item input[type="checkbox"]').forEach(function(chk) {
        chk.addEventListener('change', function() {
            const item = this.closest('.extra-check-item');
            if (item) {
                item.classList.toggle('seleccionado', this.checked);
            }
        });
    });

    // Confirmación al bloquear o activar un administrador
    document.querySelectorAll('.form-cambiar-estado').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = form.getAttribute('data-email') || 'este administrador';
            const accion = form.getAttribute('data-accion') || 'cambiar estado';

            if (typeof confirmarAccion === 'function') {
                confirmarAccion({
                    titulo: (accion === 'bloquear' ? '¿Bloquear' : '¿Activar') + ' administrador?',
                    texto: 'Se va a ' + accion + ' el acceso para ' + email + '.',
                    colorBoton: accion === 'bloquear' ? '#dc3545' : '#28a745'
                }, function() {
                    form.submit();
                });
            } else {
                form.submit();
            }
        });
    });
});