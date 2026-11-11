/* ============================================================
   LÓGICA DE GESTIÓN DE PRODUCTOS - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {

    const config = window.PRODUCTOS_CONFIG || {};
    const EXTRAS_POR_CATEGORIA = config.extrasPorCategoria || {};
    const TODOS_EXTRAS = config.todosExtras || [];
    const URL_UPLOADS = config.urlUploads || '';

    /* ============================================================
       BOTONES + Y - PARA EL PRECIO
       ============================================================ */
    document.querySelectorAll('.input-number-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const step = parseFloat(this.dataset.step) || 0.5;
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

    /* ============================================================
       APLICAR EXTRAS HEREDADOS DE LA CATEGORÍA
       ============================================================ */
    function aplicarHeredados(selector, categoriaId, infoId, listaId) {
        const heredadosIds = EXTRAS_POR_CATEGORIA[categoriaId] || [];
        const nombres = [];

        document.querySelectorAll(selector).forEach(function(chk) {
            const id = parseInt(chk.value);
            const item = chk.closest('.extra-check-item');
            const esHeredado = heredadosIds.includes(id);

            if (esHeredado) {
                chk.checked = true;
                chk.disabled = true;
                item.classList.add('heredado');
                item.classList.add('seleccionado');
                const extra = TODOS_EXTRAS.find(e => parseInt(e.id) === id);
                if (extra) nombres.push(extra.nombre);
            } else {
                chk.disabled = false;
                item.classList.remove('heredado');
            }
        });

        const infoEl = document.getElementById(infoId);
        const listaEl = document.getElementById(listaId);
        if (infoEl && listaEl) {
            if (nombres.length > 0) {
                infoEl.classList.remove('d-none');
                listaEl.textContent = nombres.join(', ');
            } else {
                infoEl.classList.add('d-none');
                listaEl.textContent = '';
            }
        }
    }

    /* ============================================================
       EDITAR PRODUCTO (Abrir modal con datos)
       ============================================================ */
    window.editarProducto = function(p, extras_ids) {
        document.getElementById('edit_id').value = p.id;
        document.getElementById('edit_nombre').value = p.nombre;
        document.getElementById('edit_precio').value = p.precio_unitario;
        document.getElementById('edit_categoria_id').value = p.categoria_id;
        document.getElementById('edit_descripcion').value = p.descripcion || '';

        aplicarHeredados('.edit-extra-check', parseInt(p.categoria_id), 'info_heredados_edit', 'lista_heredados_edit');

        document.querySelectorAll('.edit-extra-check').forEach(function(chk) {
            const id = parseInt(chk.value);
            const item = chk.closest('.extra-check-item');
            if (!chk.disabled) {
                const marcado = extras_ids.includes(id);
                chk.checked = marcado;
                item.classList.toggle('seleccionado', marcado);
            }
        });

        const imgContainer = document.getElementById('edit_imagen_actual');
        if (p.imagen && p.imagen !== '') {
            imgContainer.innerHTML = '<img src="' + URL_UPLOADS + p.imagen + '" class="producto-imagen-preview"><span class="imagen-actual-text">Imagen actual</span>';
        } else {
            imgContainer.innerHTML = '<span class="imagen-actual-text">Sin imagen</span>';
        }
        document.getElementById('editModal').classList.add('activo');
    };

    window.cerrarModal = function() { 
        document.getElementById('editModal').classList.remove('activo'); 
    };

    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) { 
            if (e.target === this) cerrarModal(); 
        });
    }

    /* ============================================================
       INICIALIZACIÓN
       ============================================================ */
    const createCatSelect = document.getElementById('create_categoria_id');
    if (createCatSelect) {
        aplicarHeredados('.create-extra-check', parseInt(createCatSelect.value), 'info_heredados_create', 'lista_heredados_create');
        createCatSelect.addEventListener('change', function() {
            aplicarHeredados('.create-extra-check', parseInt(this.value), 'info_heredados_create', 'lista_heredados_create');
        });
    }

    const editCatSelect = document.getElementById('edit_categoria_id');
    if (editCatSelect) {
        editCatSelect.addEventListener('change', function() {
            aplicarHeredados('.edit-extra-check', parseInt(this.value), 'info_heredados_edit', 'lista_heredados_edit');
        });
    }

    document.querySelectorAll('.extra-check-item input[type="checkbox"]:not([disabled])').forEach(function(chk) {
        chk.addEventListener('change', function() {
            this.closest('.extra-check-item').classList.toggle('seleccionado', this.checked);
        });
    });

    /* ============================================================
       CONFIRMACIÓN PARA CAMBIAR ESTADO
       ============================================================ */
    document.querySelectorAll('.form-cambiar-estado-producto').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const nombre = form.getAttribute('data-nombre');
            const accion = form.getAttribute('data-accion');
            confirmarAccion({
                titulo: (accion === 'ocultar' ? 'Ocultar' : 'Mostrar') + ' producto?',
                texto: '"' + nombre + '" se va a ' + accion + ' en la tienda.',
                colorBoton: accion === 'ocultar' ? '#dc3545' : '#28a745'
            }, function() { form.submit(); });
        });
    });

});