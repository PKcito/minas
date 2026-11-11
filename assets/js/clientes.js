/* ============================================================
   LÓGICA DE GESTIÓN DE CLIENTES - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    const config = window.CLIENTES_CONFIG || {};
    const COLORES_ESTADOS = config.coloresEstados || {};
    const URL_PEDIDOS_ADMIN = config.urlPedidosAdmin || '';
    const URL_VER_DETALLE = config.urlVerDetalle || '';

    window.verPedidos = function(cliente, pedidos) {
        document.getElementById('modal_ped_titulo').innerHTML = '<i class="fas fa-receipt"></i> Últimos pedidos de ' + cliente.nombre;
        document.getElementById('modal_ped_resumen').textContent = 'Total histórico: ' + cliente.total_pedidos + ' pedido(s) | Total gastado: $' + parseFloat(cliente.total_gastado || 0).toFixed(2);
        document.getElementById('modal_ped_ver_todos').href = URL_PEDIDOS_ADMIN + cliente.id;

        const contenedor = document.getElementById('modal_ped_lista');
        contenedor.innerHTML = '';

        if (!Array.isArray(pedidos) || pedidos.length === 0) {
            contenedor.innerHTML = '<p style="text-align:center; color: var(--texto-secundario); padding: 1rem;">Este cliente aún no tiene pedidos registrados.</p>';
        } else {
            pedidos.forEach(function(p) {
                const color = COLORES_ESTADOS[p.estado_id] || '#6c757d';
                const fila = document.createElement('div');
                fila.className = 'pedido-item-modal';
                fila.innerHTML =
                    '<div>' +
                        '<a href="' + URL_VER_DETALLE + p.id + '">Pedido #' + p.id + '</a> ' +
                        '<small style="color:var(--texto-secundario);">(' + p.fecha_pedido + ')</small><br>' +
                        '<small>' + (p.tipo_entrega === 'delivery' ? 'Delivery' : 'Recoger') + ' • ' + p.metodo_pago_nombre + '</small>' +
                    '</div>' +
                    '<div style="text-align:right;">' +
                        '<strong>$' + parseFloat(p.total_pedido).toFixed(2) + '</strong><br>' +
                        '<span class="badge-estado" style="background:' + color + '; color:#000; font-size:0.7rem; padding:0.15rem 0.5rem; border-radius:50px;">' + p.estado_nombre + '</span>' +
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