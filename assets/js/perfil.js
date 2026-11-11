/* ============================================================
   LÓGICA DE MI PERFIL - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    const telInput = document.getElementById('perfil_numero_7');
    if (telInput) {
        telInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    document.querySelectorAll('.form-eliminar-direccion').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const calle = form.getAttribute('data-calle') || 'esta dirección';
            if (typeof confirmarAccion === 'function') {
                confirmarAccion({
                    titulo: '¿Eliminar dirección?',
                    texto: 'Se eliminará "' + calle + '" de tus direcciones guardadas.',
                    colorBoton: '#dc3545'
                }, function() {
                    form.submit();
                });
            } else if (confirm('¿Seguro que deseas eliminar esta dirección?')) {
                form.submit();
            }
        });
    });
});