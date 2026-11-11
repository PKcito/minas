/* ============================================================
   LÓGICA DE GESTIÓN DE EXTRAS - MINAS BURGUER
   ============================================================ */

window.editarExtra = function(ex, categoriasIds) {
    document.getElementById('edit_extra_id').value = ex.id;
    document.getElementById('edit_extra_nombre').value = ex.nombre;
    document.getElementById('edit_extra_precio').value = ex.precio_extra;

    document.querySelectorAll('.edit-cat-check').forEach(function(chk) {
        const id = parseInt(chk.value);
        const marcado = Array.isArray(categoriasIds) && categoriasIds.includes(id);
        chk.checked = marcado;
        const item = chk.closest('.cat-check-item');
        if (item) {
            item.classList.toggle('seleccionado', marcado);
        }
    });

    document.getElementById('editExtraModal').classList.add('activo');
};

window.cerrarModalExtra = function() {
    const modal = document.getElementById('editExtraModal');
    if (modal) {
        modal.classList.remove('activo');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editExtraModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) cerrarModalExtra();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarModalExtra();
    });

    document.querySelectorAll('.cat-check-item input[type="checkbox"]').forEach(function(chk) {
        chk.addEventListener('change', function() {
            const item = this.closest('.cat-check-item');
            if (item) {
                item.classList.toggle('seleccionado', this.checked);
            }
        });
    });

    document.querySelectorAll('.form-cambiar-estado-extra').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const nombre = form.getAttribute('data-nombre');
            const accion = form.getAttribute('data-accion');
            if (typeof confirmarAccion === 'function') {
                confirmarAccion({
                    titulo: (accion === 'ocultar' ? '¿Ocultar' : '¿Activar') + ' extra?',
                    texto: '"' + nombre + '" se va a ' + accion + '.',
                    colorBoton: accion === 'ocultar' ? '#dc3545' : '#28a745'
                }, function() { form.submit(); });
            } else {
                form.submit();
            }
        });
    });

    document.querySelectorAll('.form-desasignar-extra').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const nombre = form.getAttribute('data-nombre');
            if (typeof confirmarAccion === 'function') {
                confirmarAccion({
                    titulo: '¿Desasignar y desactivar extra?',
                    texto: 'Se quitará "' + nombre + '" de todas las categorías y productos, pero se conservará en el historial de pedidos.',
                    colorBoton: '#dc3545'
                }, function() { form.submit(); });
            } else {
                form.submit();
            }
        });
    });
});