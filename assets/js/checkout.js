/* ============================================================
   LÓGICA DEL CHECKOUT - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {

    /* ============================================================
       PASO 1: MOSTRAR/OCULTAR SEGÚN ENTREGA
       ============================================================ */
    window.toggleEntrega = function(v) {
        const contenedor = document.getElementById('direccionContainer');
        if (v === 'delivery') contenedor.classList.remove('hidden');
        else contenedor.classList.add('hidden');
        
        document.getElementById('radioDelivery').classList.toggle('seleccionada', v === 'delivery');
        document.getElementById('radioRecoger').classList.toggle('seleccionada', v === 'recoger');
    };

    /* ============================================================
       PASO 2: MOSTRAR/OCULTAR SEGÚN PAGO
       ============================================================ */
    window.togglePago = function(v) {
        const infoEfectivo = document.getElementById('efectivoInfo');
        const infoPagoMovil = document.getElementById('pagoMovilInfo');
        
        if (v === 'efectivo') {
            infoEfectivo.classList.remove('hidden');
            infoPagoMovil.classList.add('hidden');
        } else if (v === 'pago_movil') {
            infoEfectivo.classList.add('hidden');
            infoPagoMovil.classList.remove('hidden');
        }
        
        document.getElementById('radioEfectivo').classList.toggle('seleccionada', v === 'efectivo');
        document.getElementById('radioPagoMovil').classList.toggle('seleccionada', v === 'pago_movil');
    };

    /* ============================================================
       BOTONES + Y - PARA EL MONTO DEL ADELANTO
       ============================================================ */
    function actualizarEstadoBotones(input) {
        if (!input) return;
        const id = input.id;
        const min = parseFloat(input.min) || 0;
        const max = parseFloat(input.max) || Infinity;
        const valor = parseFloat(input.value) || 0;

        document.querySelectorAll('.input-number-btn[data-target="' + id + '"]').forEach(function(btn) {
            if (btn.dataset.action === 'menos') btn.disabled = valor <= min;
            else if (btn.dataset.action === 'mas') btn.disabled = valor >= max;
        });
    }

    document.querySelectorAll('.input-number-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const step = parseFloat(this.dataset.step) || 0.5;
            const input = document.getElementById(targetId);
            if (!input || input.disabled) return;

            const min = parseFloat(input.min) || 0;
            const max = parseFloat(input.max) || Infinity;
            let valor = parseFloat(input.value) || 0;

            if (this.dataset.action === 'mas') valor = Math.min(max, valor + step);
            else valor = Math.max(min, valor - step);

            valor = Math.round(valor * 100) / 100;
            input.value = valor.toFixed(2);
            input.dispatchEvent(new Event('input', { bubbles: true }));

            const spanMonto = document.getElementById('monto_efectivo_span');
            if (spanMonto) spanMonto.textContent = '$' + valor.toFixed(2);

            actualizarEstadoBotones(input);
        });
    });

    /* ============================================================
       GUARDAR / RESTAURAR ESTADO (Al ir y volver del perfil)
       ============================================================ */
    function guardarEstadoCheckout() {
        const tipo = document.querySelector('input[name="tipo_entrega"]:checked');
        const metodo = document.querySelector('input[name="metodo_pago"]:checked');
        const monto = document.querySelector('input[name="monto_pagado"]');
        const direccion = document.querySelector('input[name="direccion_id"]:checked');

        sessionStorage.setItem('checkout_temp_state', JSON.stringify({
            tipo_entrega: tipo ? tipo.value : '',
            metodo_pago: metodo ? metodo.value : '',
            monto_pagado: monto ? monto.value : '',
            direccion_id: direccion ? direccion.value : ''
        }));
    }

    function restaurarEstadoCheckout() {
        const params = new URLSearchParams(location.search);
        if (!params.has('direccion_agregada')) return;

        const saved = sessionStorage.getItem('checkout_temp_state');
        if (!saved) return;

        try {
            const state = JSON.parse(saved);

            if (state.tipo_entrega) {
                const radio = document.querySelector('input[name="tipo_entrega"][value="' + state.tipo_entrega + '"]');
                if (radio) {
                    radio.checked = true;
                    toggleEntrega(state.tipo_entrega);
                }
            }

            if (state.metodo_pago) {
                const radio = document.querySelector('input[name="metodo_pago"][value="' + state.metodo_pago + '"]');
                if (radio) {
                    radio.checked = true;
                    togglePago(state.metodo_pago);
                }
            }

            if (state.monto_pagado) {
                const input = document.querySelector('input[name="monto_pagado"]');
                if (input) {
                    input.value = state.monto_pagado;
                    const spanMonto = document.getElementById('monto_efectivo_span');
                    if (spanMonto) spanMonto.textContent = '$' + parseFloat(state.monto_pagado).toFixed(2);
                    actualizarEstadoBotones(input);
                }
            }

            if (state.direccion_id) {
                const radio = document.querySelector('input[name="direccion_id"][value="' + state.direccion_id + '"]');
                if (radio) {
                    radio.checked = true;
                    document.querySelectorAll('.direccion-checkout').forEach(function(c) { c.classList.remove('seleccionada'); });
                    radio.closest('.direccion-checkout').classList.add('seleccionada');
                }
            }

            sessionStorage.removeItem('checkout_temp_state');
        } catch (e) { 
            console.error('Error al restaurar estado:', e);
        }
    }

    /* ============================================================
       INICIALIZACIÓN
       ============================================================ */
    const btnAgregar = document.getElementById('btnAgregarDireccion');
    if (btnAgregar) btnAgregar.addEventListener('click', guardarEstadoCheckout);

    restaurarEstadoCheckout();

    const inputMonto = document.querySelector('input[name="monto_pagado"]');
    const spanMonto = document.getElementById('monto_efectivo_span');
    if (inputMonto && spanMonto) {
        inputMonto.addEventListener('input', function() {
            const valor = parseFloat(this.value) || 0;
            spanMonto.textContent = '$' + valor.toFixed(2);
            actualizarEstadoBotones(this);
        });
        actualizarEstadoBotones(inputMonto);
    }

    document.querySelectorAll('input[name="direccion_id"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.direccion-checkout').forEach(function(c) { c.classList.remove('seleccionada'); });
            if (this.checked) this.closest('.direccion-checkout').classList.add('seleccionada');
        });
    });

});