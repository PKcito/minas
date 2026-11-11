/* ============================================================
   LÓGICA DE PEDIDOS (ADMIN) - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    
    const config = window.PEDIDOS_CONFIG || {};

    /* ============================================================
       CONTADOR REGRESIVO (Sin recargas automáticas)
       ============================================================ */
    setInterval(function() {
        document.querySelectorAll('.timer-container').forEach(function(el) {
            let segundos = parseInt(el.getAttribute('data-tiempo'));
            if (isNaN(segundos)) return;
            
            segundos--;
            if (segundos <= 0) {
                el.innerHTML = '<i class="fas fa-ban"></i> Expirado';
                el.style.color = '#6c757d';
                el.removeAttribute('data-tiempo');
                
                // Deshabilitar botones de acción visualmente
                const fila = el.closest('tr');
                if (fila) {
                    const pedidoId = fila.getAttribute('data-pedido-row');
                    const contenedorAcciones = document.getElementById('acciones-pedido-' + pedidoId);
                    if (contenedorAcciones) {
                        contenedorAcciones.innerHTML = '<span class="accion-expirada"><i class="fas fa-ban"></i> Expirado</span>';
                    }
                }
            } else {
                el.setAttribute('data-tiempo', segundos);
                let m = Math.floor(segundos / 60);
                let s = segundos % 60;
                el.querySelector('.tiempo-texto').textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                if (segundos < 120) el.style.color = '#dc3545'; // Rojo si quedan menos de 2 min
            }
        });
    }, 1000);

    /* ============================================================
       MODAL DE COMPROBANTE
       ============================================================ */
    window.verComprobante = function(archivo, pedidoId) {
        const modal = document.getElementById('modalComprobante');
        const contenido = document.getElementById('contenidoComprobante');
        const titulo = document.getElementById('tituloComprobante');
        const ruta = config.urlComprobantes + archivo;
        titulo.textContent = 'Comprobante del pedido #' + pedidoId;
        
        const ext = archivo.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) { 
            contenido.innerHTML =
                '<div class="comprobante-wrapper">' +
                    '<img src="' + ruta + '" alt="Comprobante">' +
                    '<a href="' + ruta + '" target="_blank" rel="noopener" class="comprobante-full-link">' +
                        '<i class="fas fa-external-link-alt"></i> Abrir original' +
                    '</a>' +
                '</div>';
        } else if (ext === 'pdf') { 
            contenido.innerHTML = '<iframe src="' + ruta + '"></iframe>'; 
        } else { 
            contenido.innerHTML = '<a href="' + ruta + '" target="_blank">Descargar archivo</a>'; 
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.cerrarComprobante = function() {
        document.getElementById('modalComprobante').style.display = 'none';
        document.getElementById('contenidoComprobante').innerHTML = '';
        document.body.style.overflow = '';
    };

    const modalComp = document.getElementById('modalComprobante');
    if (modalComp) {
        modalComp.addEventListener('click', function(e) { if (e.target === this) cerrarComprobante(); });
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') cerrarComprobante(); });

    /* ============================================================
       MODAL DE HISTORIAL
       ============================================================ */
    window.verHistorial = function(pedidoId) {
        document.getElementById('histPedidoId').textContent = pedidoId;
        document.getElementById('histContenido').innerHTML = '<p style="color: var(--texto-secundario);">Cargando...</p>';
        document.getElementById('modalHistorial').classList.add('activo');
        
        fetch(config.urlObtenerHistorial + '?pedido_id=' + pedidoId)
            .then(res => res.json())
            .then(data => {
                const cont = document.getElementById('histContenido');
                if (!data.historial || data.historial.length === 0) { 
                    cont.innerHTML = '<p style="color: var(--texto-secundario); text-align: center; padding: 1rem;">Sin cambios.</p>'; 
                    return; 
                }
                
                let html = '<div style="display: flex; flex-direction: column;">';
                data.historial.forEach(function(h) {
                    const colorAnt = h.color_anterior || '#6c757d'; 
                    const colorNuevo = h.color_nuevo || '#6c757d';
                    
                    html += '<div class="hist-item" style="border-left-color: ' + colorNuevo + ';">' +
                        '<div class="hist-fecha"><i class="far fa-clock"></i> ' + h.fecha_formateada + '</div>' +
                        '<div class="hist-usuario"><i class="fas fa-user"></i> <strong>' + h.usuario_nombre + '</strong></div>' +
                        '<div class="hist-estados">' + 
                            (h.estado_anterior_nombre ? '<span class="hist-badge" style="background: ' + colorAnt + ';">' + h.estado_anterior_nombre + '</span>' : '<span style="font-style: italic;">Sin estado previo</span>') + 
                            ' <i class="fas fa-arrow-right" style="color: var(--color-acento);"></i> ' + 
                            '<span class="hist-badge" style="background: ' + colorNuevo + ';">' + h.estado_nuevo_nombre + '</span>' +
                        '</div>' +
                    '</div>';
                });
                html += '</div>'; 
                cont.innerHTML = html;
            })
            .catch(() => { 
                document.getElementById('histContenido').innerHTML = '<p style="color: #dc3545;">Error al cargar.</p>'; 
            });
    };

    window.cerrarHistorial = function() { 
        document.getElementById('modalHistorial').classList.remove('activo'); 
    };

    const modalHist = document.getElementById('modalHistorial');
    if (modalHist) {
        modalHist.addEventListener('click', function(e) { if (e.target === this) cerrarHistorial(); });
    }

    /* ============================================================
       CONFIRMACIONES DE FORMULARIOS
       ============================================================ */
    document.querySelectorAll('.form-cambiar-estado').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const boton = form.querySelector('button[type="submit"]');
            const pedido = form.getAttribute('data-pedido');
            const textoBoton = boton.textContent.trim();
            
            confirmarAccion({ 
                titulo: 'Avanzar estado?', 
                texto: 'El pedido #' + pedido + ' pasara a "' + textoBoton + '".', 
                icono: 'question', 
                colorBoton: '#d32f2f' 
            }, function() {
                boton.disabled = true; 
                boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 
                form.submit();
            });
        });
    });

    document.querySelectorAll('.form-cancelar').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const pedido = form.getAttribute('data-pedido');
            Swal.fire({
                title: 'Cancelar pedido #' + pedido,
                input: 'textarea',
                inputLabel: 'Razon de la cancelacion',
                inputPlaceholder: 'Escribe el motivo por el cual se cancela...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Si, cancelar',
                cancelButtonText: 'Volver',
                inputValidator: (value) => { if (!value) return 'Debes escribir una razon'; }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.querySelector('.input-razon').value = result.value;
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('.form-cobrar').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const pedido = form.getAttribute('data-pedido');
            confirmarAccion({ 
                titulo: 'Confirmar pago recibido?', 
                texto: 'El pago del pedido #' + pedido + ' se marcara como COMPLETADO. Esto habilitara el avance del pedido.', 
                icono: 'warning', 
                colorBoton: '#28a745', 
                textoConfirmar: 'Si, confirmar' 
            }, function() { form.submit(); });
        });
    });

});