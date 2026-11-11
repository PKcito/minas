/* ============================================================
   LÓGICA DE CONFIGURACIÓN DEL SISTEMA - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    // Lógica para los botones + y - de la tasa del dólar
    document.querySelectorAll('.input-number-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const step = parseFloat(this.dataset.step) || 1;
            const input = document.getElementById(targetId);
            if (!input || input.disabled) return;

            const min = parseFloat(input.min) || 0;
            const max = parseFloat(input.max) || Infinity;
            let valor = parseFloat(input.value) || 0;

            if (this.dataset.action === 'mas') {
                valor = Math.min(max, valor + step);
            } else {
                valor = Math.max(min, valor - step);
            }

            valor = Math.round(valor * 100) / 100;
            input.value = valor.toFixed(2);
        });
    });

    // Solo permitir números en teléfono y cédula
    document.querySelectorAll('input[name="numero_tel"], input[name="numero_cedula"]').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });
});