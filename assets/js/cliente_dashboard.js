/* ============================================================
   LÓGICA DEL DASHBOARD CLIENTE - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    
    // CONTADOR REGRESIVO PARA EL CLIENTE (Sin recargas)
    let intervalCliente = setInterval(function() {
        document.querySelectorAll('.timer-container').forEach(function(el) {
            let segundos = parseInt(el.getAttribute('data-tiempo'));
            if (isNaN(segundos)) return;
            
            segundos--;
            if (segundos <= 0) {
                el.innerHTML = '<i class="fas fa-times-circle"></i> <div><h4>¡Pedido Expirado!</h4><p>El tiempo límite ha terminado. El pedido se ha cancelado automáticamente.</p></div>';
                el.style.background = 'rgba(220, 53, 69, 0.1)';
                el.style.color = '#dc3545';
                el.style.borderColor = '#dc3545';
                el.removeAttribute('data-tiempo');
                clearInterval(intervalCliente); // Detener el intervalo
            } else {
                el.setAttribute('data-tiempo', segundos);
                let m = Math.floor(segundos / 60);
                let s = segundos % 60;
                el.querySelector('.tiempo-texto').textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }
        });
    }, 1000);

});