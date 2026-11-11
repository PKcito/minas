/* ============================================================
   LÓGICA DE REGISTRO DE USUARIO - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    const numeroInput = document.getElementById('numero_7');
    if (numeroInput) {
        numeroInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 7);
        });
    }
});