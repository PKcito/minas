/* ============================================================
   LÓGICA DE AUTENTICACIÓN (MOSTRAR/OCULTAR CONTRASEÑA)
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-toggle-pass').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('title', 'Ocultar contraseña');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('title', 'Mostrar contraseña');
            }
        });
    });
});