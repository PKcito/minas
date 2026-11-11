/* ============================================================
   LÓGICA DE GESTIÓN DE CATEGORÍAS - MINAS BURGUER
   ============================================================ */

window.editarCategoria = function(c) {
    const idInput = document.getElementById('edit_id');
    const nombreInput = document.getElementById('edit_nombre');
    const descInput = document.getElementById('edit_descripcion');
    const modal = document.getElementById('editModal');

    if (!idInput || !nombreInput || !descInput || !modal) return;

    idInput.value = c.id;
    nombreInput.value = c.nombre;
    descInput.value = c.descripcion || '';
    modal.classList.add('activo');
};

window.cerrarModal = function() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.classList.remove('activo');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === this) cerrarModal();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarModal();
    });

    document.querySelectorAll('.form-cambiar-estado-categoria').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const nombre = form.getAttribute('data-categoria');
            const accion = form.getAttribute('data-accion');

            if (typeof confirmarAccion === 'function') {
                confirmarAccion({
                    titulo: (accion === 'ocultar' ? '¿Ocultar' : '¿Mostrar') + ' categoría?',
                    texto: '"' + nombre + '" se va a ' + accion + '.',
                    colorBoton: accion === 'ocultar' ? '#dc3545' : '#28a745'
                }, function() {
                    form.submit();
                });
            } else {
                form.submit();
            }
        });
    });
});