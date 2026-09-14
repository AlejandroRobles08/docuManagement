/**
 * Mejora progresiva del campo de archivo del formulario de subida de documentos:
 * convierte un <input type="file"> normal en una zona de arrastrar y soltar.
 * El formulario sigue funcionando perfectamente sin JavaScript: el <input>
 * vive dentro de un <label for="..."> (por eso puede llevar el atributo
 * "hidden" y aun así abrirse al hacer clic en cualquier parte de la zona).
 */
document.addEventListener('DOMContentLoaded', () => {
    const dropzone = document.querySelector('[data-dropzone]');
    if (!dropzone) {
        return;
    }

    const input = dropzone.querySelector('input[type="file"]');
    const fileNameEl = dropzone.querySelector('[data-dropzone-filename]');
    const defaultText = dropzone.querySelector('[data-dropzone-default]');

    const showFile = (file) => {
        if (!file) {
            dropzone.classList.remove('has-file');
            if (fileNameEl) fileNameEl.textContent = '';
            if (fileNameEl) fileNameEl.hidden = true;
            if (defaultText) defaultText.hidden = false;
            return;
        }

        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        dropzone.classList.add('has-file');
        if (fileNameEl) {
            fileNameEl.textContent = `${file.name} (${sizeMb} MB)`;
            fileNameEl.hidden = false;
        }
        if (defaultText) defaultText.hidden = true;
    };

    input.addEventListener('change', () => showFile(input.files[0]));

    ['dragenter', 'dragover'].forEach((evt) => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((evt) => {
        dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (files && files.length) {
            input.files = files;
            showFile(files[0]);
        }
    });

    // Si el navegador restauró el input tras un error de validación, refleja el archivo.
    if (input.files && input.files[0]) {
        showFile(input.files[0]);
    }
});
