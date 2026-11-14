/* ============================================================
   LÓGICA DE GESTIÓN DE CLIENTES - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    const config = window.CLIENTES_CONFIG || {};
    const COLORES_ESTADOS = config.coloresEstados || {};
    const URL_PEDIDOS_ADMIN = config.urlPedidosAdmin || '';
    const URL_VER_DETALLE = config.urlVerDetalle || '';

    window.verPedidos = function(cliente, pedidos) {
        document.getElementById('modal_ped_titulo').innerHTML =
            '<i class="fas fa-receipt"></i> Últimos pedidos de ' + cliente.nombre;

        document.getElementById('modal_ped_resumen').innerHTML =
            'Total histórico válido: <strong>' + cliente.total_pedidos + ' pedido(s)</strong>  |  Total gastado: <strong>$' +
            parseFloat(cliente.total_gastado || 0).toFixed(2) + '</strong>';

        document.getElementById('modal_ped_ver_todos').href = URL_PEDIDOS_ADMIN + (cliente.usuario_id || cliente.id);

        const contenedor = document.getElementById('modal_ped_lista');
        contenedor.innerHTML = '';

        if (!Array.isArray(pedidos) || pedidos.length === 0) {
            contenedor.innerHTML =
                '<div style="text-align:center; color: var(--texto-secundario); padding: 1.5rem; background: var(--bg-principal); border: 1px dashed var(--borde); border-radius: 12px;">' +
                '<i class="fas fa-shopping-bag" style="font-size: 1.5rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>' +
                'Este cliente aún no tiene pedidos registrados.' +
                '</div>';
        } else {
            pedidos.forEach(function(p) {
                const estadoId = parseInt(p.estado_id, 10) || 0;
                const color = COLORES_ESTADOS[estadoId] || '#6c757d';
                const colorTexto = (estadoId === 1) ? '#111111' : '#ffffff';
                const iconoEntrega = (p.tipo_entrega === 'delivery')
                    ? '<i class="fas fa-motorcycle"></i> Delivery'
                    : '<i class="fas fa-store"></i> Recoger';

                const fila = document.createElement('div');
                fila.className = 'pedido-item-modal';
                fila.innerHTML =
                    '<div class="pedido-item-info">' +
                        '<div class="pedido-item-top">' +
                            '<a href="' + URL_VER_DETALLE + p.id + '" class="pedido-item-link">Pedido #' + p.id + '</a>' +
                            '<span class="pedido-item-fecha"><i class="far fa-clock"></i> ' + p.fecha_pedido + '</span>' +
                        '</div>' +
                        '<div class="pedido-item-meta">' +
                            '<span>' + iconoEntrega + '</span>' +
                            '<span>•</span>' +
                            '<span><i class="fas fa-wallet"></i> ' + p.metodo_pago_nombre + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="pedido-item-derecha">' +
                        '<span class="pedido-item-monto">$' + parseFloat(p.total_pedido).toFixed(2) + '</span>' +
                        '<span class="badge-estado" style="background:' + color + '; color:' + colorTexto + ' !important;">' +
                            p.estado_nombre +
                        '</span>' +
                    '</div>';
                contenedor.appendChild(fila);
            });
        }

        document.getElementById('modalPedidosCliente').classList.add('activo');
    };

    window.cerrarModalPedidos = function() {
        document.getElementById('modalPedidosCliente').classList.remove('activo');
    };

    window.editarCliente = function(c) {
        document.getElementById('edit_cli_id').value = c.id;
        document.getElementById('edit_cli_nombres').value = c.nombres || '';
        document.getElementById('edit_cli_apellidos').value = c.apellidos || '';
        document.getElementById('edit_cli_email').value = c.email || '';
        document.getElementById('edit_cli_nacimiento').value = c.fecha_nacimiento || '';
        document.getElementById('edit_cli_notas').value = c.notas || '';

        const tel = String(c.telefono || '');
        if (tel.length >= 11) {
            document.getElementById('edit_cli_prefijo').value = tel.substring(0, 4);
            document.getElementById('edit_cli_numero').value = tel.substring(4, 11);
        } else {
            document.getElementById('edit_cli_numero').value = tel;
        }

        document.getElementById('modalEditarCliente').classList.add('activo');
    };

    window.cerrarModalEditar = function() {
        document.getElementById('modalEditarCliente').classList.remove('activo');
    };

    ['modalPedidosCliente', 'modalEditarCliente'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('activo');
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModalPedidos();
            cerrarModalEditar();
        }
    });

    const numInput = document.getElementById('edit_cli_numero');
    if (numInput) {
        numInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }
});