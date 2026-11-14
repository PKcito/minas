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

    // Solo permitir números en teléfono, WhatsApp y cédula
    document.querySelectorAll('input[name="numero_tel"], input[name="numero_wa"], input[name="numero_cedula"]').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });

    // Vista previa en vivo para Logo y Banner
    function configurarPreview(inputId, imgId, placeholderId) {
        const input = document.getElementById(inputId);
        const img = document.getElementById(imgId);
        const placeholder = document.getElementById(placeholderId);

        if (!input || !img) return;

        input.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.classList.remove('d-none');
                if (placeholder) {
                    placeholder.classList.add('d-none');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    configurarPreview('inputLogo', 'imgPreviewLogo', 'placeholderLogo');
    configurarPreview('inputBanner', 'imgPreviewBanner', 'placeholderBanner');
});