/* ============================================================
   index.js - Logica de la tienda (carrito, extras, notificaciones)
   Minas Burguer
   ============================================================ */

(function () {
    'use strict';

    const config = window.MINAS_CONFIG || {};
    const isLoggedIn = !!config.isLoggedIn;
    const tasaDolar = parseFloat(config.tasaDolar) || 1;
    const URLS = config.URLs || {};

    document.addEventListener('DOMContentLoaded', function () {

        /* ============================================================
           WELCOME MODAL
           ============================================================ */
        const welcomeModal = document.getElementById('welcomeModal');
        const welcomeAcceptBtn = document.getElementById('welcomeAcceptBtn');
        const welcomeDontShowAgain = document.getElementById('welcomeDontShowAgain');

        if (welcomeModal && welcomeAcceptBtn && welcomeDontShowAgain) {
            if (!localStorage.getItem('minas_burguer_welcome_shown')) welcomeModal.style.display = 'flex';

            welcomeAcceptBtn.addEventListener('click', function () {
                if (welcomeDontShowAgain.checked) localStorage.setItem('minas_burguer_welcome_shown', 'true');
                welcomeModal.style.display = 'none';
            });

            welcomeModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    if (welcomeDontShowAgain.checked) localStorage.setItem('minas_burguer_welcome_shown', 'true');
                    welcomeModal.style.display = 'none';
                }
            });
        }

        /* ============================================================
           REFERENCIAS DEL DOM
           ============================================================ */
        const cartToggle = document.getElementById('cartToggle');
        const cartOverlay = document.getElementById('cartOverlay');
        const cartOffcanvas = document.getElementById('cartOffcanvas');
        const closeCart = document.getElementById('closeCart');
        const cartItemsContainer = document.getElementById('cartItems');
        const cartTotalPrice = document.getElementById('cartTotalPrice');
        const cartTotalBs = document.getElementById('cartTotalBs');
        const cartCount = document.getElementById('cartCount');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const productCards = document.querySelectorAll('.product-card');
        const addBtns = document.querySelectorAll('.add-btn');
        const userBtn = document.getElementById('userBtn');
        const scrollToMenuBtn = document.getElementById('scrollToMenu');
        const notifBtn = document.getElementById('notifBtn');
        const notifBadge = document.getElementById('notifBadge');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifList = document.getElementById('notifList');

        let carrito = [];
        let productoSeleccionado = null;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        }

        /* ============================================================
           CARRITO
           ============================================================ */
        function actualizarContador() {
            if (cartCount) cartCount.textContent = carrito.reduce((sum, item) => sum + item.cantidad, 0);
        }

        function renderizarCarrito() {
            if (!cartItemsContainer) return;

            if (carrito.length === 0) {
                cartItemsContainer.innerHTML = '<div class="cart-vacio"><i class="fas fa-shopping-bag"></i><p>Tu carrito esta vacio</p></div>';
                if (cartTotalPrice) cartTotalPrice.textContent = '$0.00';
                if (cartTotalBs) cartTotalBs.textContent = 'Bs. 0,00';
                return;
            }

            let html = '', total = 0;

            carrito.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;

                let extrasHtml = '';
                if (item.extras && item.extras.length > 0) {
                    extrasHtml = '<div class="cart-extras">' + item.extras.map(e => '<i class="fas fa-plus-circle"></i> ' + escapeHtml(e.nombre)).join(' · ') + '</div>';
                }

                html += '<div class="cart-item-card">' +
                    '<div class="cart-item-header">' +
                        '<h4>' + escapeHtml(item.nombre) + '</h4>' +
                        '<button class="remove-btn" data-index="' + index + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>' +
                    '</div>' +
                    extrasHtml +
                    '<div class="cart-item-precio-unit">Precio: $' + item.precio.toFixed(2) + ' / Bs. ' + (item.precio * tasaDolar).toFixed(2) + '</div>' +
                    '<div class="cart-item-footer">' +
                        '<div class="qty-control">' +
                            '<button type="button" class="qty-btn qty-menos" data-index="' + index + '"><i class="fas fa-minus"></i></button>' +
                            '<span class="qty-valor">' + item.cantidad + '</span>' +
                            '<button type="button" class="qty-btn qty-mas" data-index="' + index + '"><i class="fas fa-plus"></i></button>' +
                        '</div>' +
                        '<div class="cart-item-subtotal">$' + subtotal.toFixed(2) + '</div>' +
                    '</div>' +
                '</div>';
            });

            cartItemsContainer.innerHTML = html;
            if (cartTotalPrice) cartTotalPrice.textContent = '$' + total.toFixed(2);
            if (cartTotalBs) cartTotalBs.textContent = 'Bs. ' + (total * tasaDolar).toFixed(2);

            document.querySelectorAll('.qty-mas').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = parseInt(this.dataset.index);
                    carrito[idx].cantidad += 1;
                    guardarCarrito();
                    actualizarContador();
                    renderizarCarrito();
                });
            });

            document.querySelectorAll('.qty-menos').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = parseInt(this.dataset.index);
                    if (carrito[idx].cantidad > 1) {
                        carrito[idx].cantidad -= 1;
                        guardarCarrito();
                        actualizarContador();
                        renderizarCarrito();
                    } else {
                        Swal.fire({
                            icon: 'question',
                            title: 'Quitar del carrito?',
                            text: 'Este es el ultimo ' + carrito[idx].nombre + ' del carrito.',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Si, quitar',
                            cancelButtonText: 'Cancelar'
                        }).then(r => {
                            if (r.isConfirmed) {
                                carrito.splice(idx, 1);
                                guardarCarrito();
                                actualizarContador();
                                renderizarCarrito();
                            }
                        });
                    }
                });
            });

            document.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = parseInt(this.dataset.index);
                    Swal.fire({
                        icon: 'warning',
                        title: 'Eliminar producto?',
                        text: 'Se quitara ' + carrito[idx].nombre + ' del carrito.',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Si, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then(r => {
                        if (r.isConfirmed) {
                            carrito.splice(idx, 1);
                            guardarCarrito();
                            actualizarContador();
                            renderizarCarrito();
                        }
                    });
                });
            });
        }

        function guardarCarrito() {
            fetch(URLS.carritoActualizar, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ carrito: carrito })
            }).catch(() => {});
        }

        function cargarCarrito() {
            fetch(URLS.carritoObtener)
                .then(res => res.json())
                .then(data => {
                    if (data.carrito) {
                        carrito = data.carrito;
                        actualizarContador();
                        renderizarCarrito();
                    }
                })
                .catch(() => {});
        }

        /* ============================================================
           MODAL DE EXTRAS
           ============================================================ */
        function abrirModalExtras(id, nombre, precio) {
            productoSeleccionado = { id, nombre, precio };
            document.getElementById('modalPrecioBase').textContent = '$' + precio.toFixed(2);
            document.getElementById('modalTotalExtras').textContent = '+$0.00';
            document.getElementById('modalTotalFinal').textContent = '$' + precio.toFixed(2);
            document.getElementById('btnPrecioCarrito').textContent = '$' + precio.toFixed(2);
            document.getElementById('modalProductoNombre').textContent = 'Personaliza tu ' + nombre;
            document.getElementById('modalExtras').style.display = 'flex';
            document.getElementById('modalExtrasLista').innerHTML = '<div class="modal-extras-cargando"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:0.5rem;display:block;"></i>Cargando opciones...</div>';

            fetch(URLS.extras + '?producto_id=' + id)
                .then(res => res.json())
                .then(data => {
                    const lista = document.getElementById('modalExtrasLista');

                    if (!data.extras || data.extras.length === 0) {
                        lista.innerHTML = '<div class="modal-extras-vacio"><i class="fas fa-info-circle"></i>No hay opciones para este producto.</div>';
                        return;
                    }

                    const removibles = [];
                    const adicionales = [];

                    data.extras.forEach(extra => {
                        const nombreLower = (extra.nombre || '').toLowerCase().trim();
                        if (nombreLower.startsWith('sin ')) {
                            removibles.push(extra);
                        } else {
                            adicionales.push(extra);
                        }
                    });

                    let html = '';

                    if (removibles.length > 0) {
                        html += '<div class="extras-seccion">';
                        html += '<div class="extras-seccion-header">';
                        html += '<span class="extras-seccion-icono" style="background: rgba(220,53,69,0.12); color: #dc3545;"><i class="fas fa-minus-circle"></i></span>';
                        html += '<div><strong>Quitar ingredientes</strong><small>Toca para quitarlos de tu pedido</small></div>';
                        html += '</div>';
                        html += '<div class="extras-removibles-grid">';

                        removibles.forEach(extra => {
                            const nombreLimpio = extra.nombre.replace(/^Sin\s+/i, '');
                            html += '<label class="removible-chip">';
                            html += '<input type="checkbox" class="extra-checkbox removible-checkbox"';
                            html += ' data-extra-id="' + extra.id + '"';
                            html += ' data-extra-nombre="' + escapeHtml(extra.nombre) + '"';
                            html += ' data-extra-precio="0">';
                            html += '<i class="fas fa-times"></i> Sin ' + escapeHtml(nombreLimpio);
                            html += '</label>';
                        });

                        html += '</div>';
                        html += '</div>';
                    }

                    if (adicionales.length > 0) {
                        html += '<div class="extras-seccion">';
                        html += '<div class="extras-seccion-header">';
                        html += '<span class="extras-seccion-icono" style="background: rgba(40,167,69,0.12); color: #28a745;"><i class="fas fa-plus-circle"></i></span>';
                        html += '<div><strong>Agregar extras</strong><small>Ingredientes adicionales con costo</small></div>';
                        html += '</div>';
                        html += '<div class="extras-adicionales-lista">';

                        adicionales.forEach(extra => {
                            const p = Number(extra.precio_extra) || 0;
                            html += '<label class="adicional-item">';
                            html += '<div class="adicional-info">';
                            html += '<input type="checkbox" class="extra-checkbox adicional-checkbox"';
                            html += ' data-extra-id="' + extra.id + '"';
                            html += ' data-extra-nombre="' + escapeHtml(extra.nombre) + '"';
                            html += ' data-extra-precio="' + p + '">';
                            html += '<span class="adicional-nombre">' + escapeHtml(extra.nombre) + '</span>';
                            html += '</div>';
                            html += '<span class="adicional-precio">+$' + p.toFixed(2) + '</span>';
                            html += '</label>';
                        });

                        html += '</div>';
                        html += '</div>';
                    }

                    lista.innerHTML = html;

                    document.querySelectorAll('.extra-checkbox').forEach(cb => {
                        cb.addEventListener('change', function() {
                            if (this.classList.contains('removible-checkbox')) {
                                this.closest('.removible-chip').classList.toggle('seleccionado', this.checked);
                            } else {
                                this.closest('.adicional-item').classList.toggle('seleccionado', this.checked);
                            }
                            actualizarTotalExtras();
                        });
                    });
                })
                .catch(() => {
                    document.getElementById('modalExtrasLista').innerHTML = '<div class="modal-extras-vacio"><i class="fas fa-exclamation-triangle"></i>No se pudieron cargar los extras.</div>';
                });
        }

        function actualizarTotalExtras() {
            let total = 0;
            document.querySelectorAll('.adicional-checkbox:checked').forEach(cb => {
                total += parseFloat(cb.dataset.extraPrecio) || 0;
            });
            const base = productoSeleccionado ? productoSeleccionado.precio : 0;
            const totalFinal = base + total;

            document.getElementById('modalTotalExtras').textContent = '+$' + total.toFixed(2);
            document.getElementById('modalTotalFinal').textContent = '$' + totalFinal.toFixed(2);
            const btnPrecio = document.getElementById('btnPrecioCarrito');
            if (btnPrecio) btnPrecio.textContent = '$' + totalFinal.toFixed(2);
        }

        function cerrarModalExtras() {
            const modal = document.getElementById('modalExtras');
            if (modal) modal.style.display = 'none';
        }

        function confirmarExtras() {
            const extras = [];
            document.querySelectorAll('.extra-checkbox:checked').forEach(cb => {
                extras.push({
                    id: parseInt(cb.dataset.extraId),
                    nombre: cb.dataset.extraNombre,
                    precio: parseFloat(cb.dataset.extraPrecio) || 0
                });
            });

            const totalExtras = extras.reduce((s, e) => s + e.precio, 0);
            const precioTotal = productoSeleccionado.precio + totalExtras;
            const idx = carrito.findIndex(i =>
                i.id === productoSeleccionado.id &&
                JSON.stringify(i.extras || []) === JSON.stringify(extras)
            );

            if (idx !== -1) {
                carrito[idx].cantidad += 1;
            } else {
                carrito.push({
                    id: productoSeleccionado.id,
                    nombre: productoSeleccionado.nombre,
                    precio: precioTotal,
                    precio_base: productoSeleccionado.precio,
                    extras,
                    cantidad: 1
                });
            }

            guardarCarrito();
            actualizarContador();
            renderizarCarrito();
            cerrarModalExtras();

            Swal.fire({
                icon: 'success',
                title: productoSeleccionado.nombre + ' agregado',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
            });
        }

        /* ============================================================
           FILTROS Y EVENTOS
           ============================================================ */
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const cat = this.dataset.category;
                productCards.forEach(card => {
                    card.style.display = (cat === 'all' || card.dataset.category == cat) ? 'block' : 'none';
                });
            });
        });

        if (cartToggle) cartToggle.addEventListener('click', () => { cartOffcanvas.classList.add('abierto'); cartOverlay.style.display = 'block'; });
        if (closeCart) closeCart.addEventListener('click', () => { cartOffcanvas.classList.remove('abierto'); cartOverlay.style.display = 'none'; });
        if (cartOverlay) cartOverlay.addEventListener('click', () => { cartOffcanvas.classList.remove('abierto'); cartOverlay.style.display = 'none'; });

        const btnAgregarExtras = document.getElementById('btnAgregarExtras');
        const cerrarModalExtrasBtn = document.getElementById('cerrarModalExtrasBtn');
        const modalExtrasEl = document.getElementById('modalExtras');

        if (btnAgregarExtras) btnAgregarExtras.addEventListener('click', confirmarExtras);
        if (cerrarModalExtrasBtn) cerrarModalExtrasBtn.addEventListener('click', cerrarModalExtras);
        if (modalExtrasEl) modalExtrasEl.addEventListener('click', function (e) { if (e.target === this) cerrarModalExtras(); });

        addBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                if (!isLoggedIn) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Inicia sesion primero',
                        text: 'Necesitas estar registrado para agregar productos.',
                        confirmButtonColor: '#d32f2f',
                        confirmButtonText: 'Ir a login',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) window.location.href = URLS.login; });
                    return;
                }
                abrirModalExtras(parseInt(this.dataset.id), this.dataset.name, parseFloat(this.dataset.price));
            });
        });

        if (scrollToMenuBtn) {
            scrollToMenuBtn.addEventListener('click', function () {
                document.getElementById('menuSection').scrollIntoView({ behavior: 'smooth' });
            });
        }

        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function () {
                if (carrito.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'Carrito vacio', text: 'Agrega productos antes de finalizar.', confirmButtonColor: '#d32f2f' });
                    return;
                }
                if (!isLoggedIn) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Inicia sesion',
                        text: 'Necesitas estar registrado para finalizar.',
                        confirmButtonColor: '#d32f2f',
                        confirmButtonText: 'Ir a login',
                        showCancelButton: true,
                        cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) window.location.href = URLS.login; });
                    return;
                }
                window.location.href = URLS.checkout;
            });
        }

        if (userBtn) {
            userBtn.addEventListener('click', function () {
                window.location.href = isLoggedIn ? URLS.dashboard : URLS.login;
            });
        }

        /* ============================================================
           NOTIFICACIONES
           ============================================================ */
        function actualizarContadorNotif() {
            if (!isLoggedIn || !notifBadge) return;
            fetch(URLS.notificaciones + '?action=contar')
                .then(res => res.json())
                .then(data => {
                    if (data.count > 0) {
                        notifBadge.style.display = 'inline';
                        notifBadge.textContent = data.count;
                    } else {
                        notifBadge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }

        function cargarNotifDropdown() {
            if (!isLoggedIn || !notifList) return;
            fetch(URLS.notificaciones + '?action=listar_dropdown')
                .then(res => res.json())
                .then(data => {
                    if (data.notificaciones && data.notificaciones.length > 0) {
                        let html = '';
                        data.notificaciones.forEach(n => {
                            html += '<div class="notif-item"><div class="notif-titulo">' + escapeHtml(n.titulo) + '</div><div class="notif-mensaje">' + escapeHtml(n.mensaje) + '</div><span class="notif-fecha">' + new Date(n.fecha_creacion).toLocaleString() + '</span></div>';
                        });
                        notifList.innerHTML = html;
                    } else {
                        notifList.innerHTML = '<div class="notif-vacio">No hay notificaciones nuevas.</div>';
                    }
                })
                .catch(() => {});
        }

        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (notifDropdown.classList.contains('active')) {
                    notifDropdown.classList.remove('active');
                } else {
                    cargarNotifDropdown();
                    notifDropdown.classList.add('active');
                    fetch(URLS.notificaciones + '?action=marcar_leidas')
                        .then(() => actualizarContadorNotif())
                        .catch(() => {});
                }
            });

            document.addEventListener('click', function (e) {
                const c = document.querySelector('.notif-container');
                if (c && !c.contains(e.target)) notifDropdown.classList.remove('active');
            });
        }

        /* ============================================================
           INICIALIZACION
           ============================================================ */
        cargarCarrito();

        if (isLoggedIn) {
            actualizarContadorNotif();
            setTimeout(function () {
                fetch(URLS.notificaciones + '?action=contar')
                    .then(res => res.json())
                    .then(data => {
                        if (data.count > 0 && notifBtn && notifDropdown) {
                            cargarNotifDropdown();
                            notifDropdown.classList.add('active');
                            setTimeout(function () { notifDropdown.classList.remove('active'); }, 8000);
                            fetch(URLS.notificaciones + '?action=marcar_leidas').then(() => actualizarContadorNotif()).catch(() => {});
                        }
                    })
                    .catch(() => {});
            }, 1000);
            setInterval(actualizarContadorNotif, 30000);
        }

        // Abrir carrito automaticamente (?abrir_carrito=1)
        if (new URLSearchParams(location.search).get('abrir_carrito') === '1') {
            setTimeout(function () {
                fetch(URLS.carritoObtener)
                    .then(res => res.json())
                    .then(data => {
                        if (data.carrito && data.carrito.length > 0) {
                            carrito = data.carrito;
                            actualizarContador();
                            renderizarCarrito();
                            cartOffcanvas.classList.add('abierto');
                            cartOverlay.style.display = 'block';
                        }
                    })
                    .catch(() => {});
            }, 400);
        }
    });
})();