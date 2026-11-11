/* ============================================================
   LÓGICA DEL COMPROBANTE DE PAGO - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    
    const input = document.getElementById('inputComprobante');
    const previewContainer = document.getElementById('previewContainer');
    const previewWrapper = document.getElementById('previewWrapper');
    const previewNombre = document.getElementById('previewNombre');
    const uploadArea = document.getElementById('uploadArea');
    const btnCambiar = document.getElementById('btnCambiar');
    const btnEnviar = document.getElementById('btnEnviar');
    const modalPreview = document.getElementById('modalPreview');
    const modalPreviewImg = document.getElementById('modalPreviewImg');
    const btnCerrarModal = document.getElementById('btnCerrarModal');

    if (!input) return; // Si no existe el input, detenemos la ejecución

    /* ============================================================
       SELECCIONAR ARCHIVO
       ============================================================ */
    input.addEventListener('change', function() {
        const archivo = this.files[0];
        if (!archivo) return;

        const nombre = archivo.name;
        const tipo = archivo.type;
        const tamañoMB = (archivo.size / (1024 * 1024)).toFixed(2);

        previewWrapper.innerHTML = '';

        if (tipo.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-imagen';
                img.alt = 'Preview del comprobante';
                img.onclick = function() { abrirPreview(e.target.result); };
                previewWrapper.appendChild(img);
            };
            reader.readAsDataURL(archivo);
        } else if (tipo === 'application/pdf') {
            const div = document.createElement('div');
            div.className = 'preview-pdf';
            div.innerHTML = '<i class="fas fa-file-pdf"></i><span>' + nombre + '</span>';
            previewWrapper.appendChild(div);
        } else {
            const div = document.createElement('div');
            div.className = 'preview-pdf';
            div.innerHTML = '<i class="fas fa-file"></i><span>' + nombre + '</span>';
            previewWrapper.appendChild(div);
        }

        previewNombre.textContent = nombre + ' (' + tamañoMB + ' MB)';
        previewContainer.classList.add('activo');
        
        // Ocultamos la zona de subida de forma limpia
        uploadArea.classList.add('d-none'); 
        btnEnviar.disabled = false;
    });

    /* ============================================================
       CAMBIAR ARCHIVO
       ============================================================ */
    btnCambiar.addEventListener('click', function() {
        input.value = '';
        previewContainer.classList.remove('activo');
        previewWrapper.innerHTML = '';
        uploadArea.classList.remove('d-none'); // Volvemos a mostrar
        btnEnviar.disabled = true;
    });

    /* ============================================================
       MODAL DE PREVISUALIZACIÓN
       ============================================================ */
    function abrirPreview(src) {
        modalPreviewImg.src = src;
        modalPreview.classList.add('activo');
        document.body.style.overflow = 'hidden';
    }

    window.cerrarPreview = function() {
        modalPreview.classList.remove('activo');
        modalPreviewImg.src = '';
        document.body.style.overflow = '';
    };

    if (btnCerrarModal) {
        btnCerrarModal.addEventListener('click', cerrarPreview);
    }

    if (modalPreview) {
        modalPreview.addEventListener('click', function(e) { 
            if (e.target === this) cerrarPreview(); 
        });
    }

    document.addEventListener('keydown', function(e) { 
        if (e.key === 'Escape') cerrarPreview(); 
    });
});