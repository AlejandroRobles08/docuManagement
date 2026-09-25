/**
 * Al elegir un archivo en el formulario de subida de documentos, muestra una
 * vista previa en un modal e intenta leer el nombre, apellidos y fecha de
 * nacimiento con OCR (Tesseract.js, corriendo en el navegador) para agilizar
 * el llenado del formulario. Tesseract.js solo puede leer imágenes, así que
 * para PDF se renderiza primero la primera página a una imagen con pdf.js y
 * esa imagen es la que se escanea.
 *
 * Los campos del modal siempre quedan editables: el OCR sobre fotos de
 * documentos físicos (brillo, ángulo, calidad) puede fallar o confundir
 * texto cercano, así que es una ayuda y no una fuente de verdad.
 */
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('document');
    const modalEl = document.getElementById('document-ocr-modal');
    if (!input || !modalEl) {
        return;
    }

    const fullNameInput = document.getElementById('full_name');
    const birthDateInput = document.getElementById('birth_date');
    const previewImage = modalEl.querySelector('[data-ocr-preview-image]');
    const previewPdf = modalEl.querySelector('[data-ocr-preview-pdf]');
    const statusBox = modalEl.querySelector('[data-ocr-status]');
    const statusText = modalEl.querySelector('[data-ocr-status-text]');
    const nombreInput = modalEl.querySelector('[data-ocr-field="nombre"]');
    const apellidosInput = modalEl.querySelector('[data-ocr-field="apellidos"]');
    const ocrBirthDateInput = modalEl.querySelector('[data-ocr-field="birth_date"]');
    const confirmButton = modalEl.querySelector('[data-ocr-confirm]');
    const cancelButtons = modalEl.querySelectorAll('[data-ocr-cancel]');

    const modal = UIkit.modal('#document-ocr-modal');

    let worker = null;
    let pdfjsLib = null;
    let previewUrl = null;
    let scanToken = 0;
    let confirmed = false;

    const getWorker = async () => {
        if (!worker) {
            const { createWorker } = await import('tesseract.js');
            worker = await createWorker('spa');
        }
        return worker;
    };

    // pdf.js (y su worker) solo se descargan cuando de verdad se sube un PDF,
    // para no engordar el bundle que carga cada página con una librería que
    // la mayoría de las subidas (imágenes) no necesita.
    //
    // El worker se vuelve a envolver en un blob URL con el content-type
    // forzado a "text/javascript" porque Apache (tanto en Laragon como en la
    // imagen Docker) no trae registrada la extensión ".mjs" y lo sirve sin
    // Content-Type; los navegadores rechazan un <script type="module"> (o un
    // Worker de tipo "module") si el Content-Type no es explícitamente JS.
    const getPdfjs = async () => {
        if (!pdfjsLib) {
            pdfjsLib = await import('pdfjs-dist');
            const { default: workerUrl } = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
            const workerSource = await fetch(workerUrl).then((response) => response.text());
            const workerBlobUrl = URL.createObjectURL(new Blob([workerSource], {type: 'text/javascript'}));
            pdfjsLib.GlobalWorkerOptions.workerSrc = workerBlobUrl;
        }
        return pdfjsLib;
    };

    /**
     * Renderiza la primera página de un PDF a un blob PNG, para poder
     * pasársela a Tesseract (que solo lee imágenes, no PDF).
     */
    const renderPdfFirstPageToBlob = async (file) => {
        const pdfjs = await getPdfjs();
        const data = await file.arrayBuffer();
        const pdf = await pdfjs.getDocument({data}).promise;
        const page = await pdf.getPage(1);

        // Escala alta: el OCR necesita texto nítido, no solo que se vea bien.
        const viewport = page.getViewport({scale: 2.5});
        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;

        await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;

        return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    };

    // Rótulos que marcan el final del bloque de nombre en una credencial INE
    // (ver extractNameFields), para no arrastrar texto de otros campos si el
    // documento no trae exactamente 3 líneas después de "NOMBRE".
    const NAME_BLOCK_STOP_WORDS = /^(SEXO|DOMICILIO|CLAVE DE ELECTOR|CURP|FECHA DE NACIMIENTO|A[ÑN]O DE REGISTRO|VIGENCIA|SECCI[OÓ]N|ESTADO|MUNICIPIO|LOCALIDAD|FIRMA|INSTITUTO)/i;

    // Texto que no es parte del nombre pero puede colarse en el barrido de
    // respaldo (ver extractNameFields, caso 4): cualquier línea con dígitos
    // (CURP, clave de elector, fechas, folios: un nombre real nunca lleva
    // números) o el encabezado/microtexto de seguridad del propio INE, que
    // el OCR suele leer como palabras sueltas sin sentido.
    const NON_NAME_LINE = /\d|^(INSTITUTO|NACIONAL|ELECTORAL|CREDENCIAL|PARA VOTAR|M[EÉ]XICO|ESTADOS UNIDOS|GENERO|SEXO|INDIGENA)/i;

    /**
     * Heurística de extracción de nombre/apellidos a partir del texto crudo
     * del OCR. De más a menos confiable:
     *
     * 1) Etiquetas explícitas: "APELLIDO PATERNO/MATERNO" (INE) o "PRIMER/
     *    SEGUNDO APELLIDO" (Formato Único del acta de nacimiento), más
     *    "NOMBRE(S)", cada una seguida de su valor (misma línea o la
     *    siguiente).
     * 2) Formato INE/IFE con encabezado: una línea "NOMBRE" sola, seguida
     *    -sin etiqueta propia- de apellido paterno, apellido materno y
     *    nombre(s), en ese orden.
     * 3) Sin encabezado limpio (frecuente: "NOMBRE" es letra chica pegada a
     *    la foto y al microtexto de seguridad del INE, así que el OCR rara
     *    vez la lee bien): se ancla en la línea "DOMICILIO" -que sí suele
     *    leerse, aunque sea con algún error de OCR- y se toman las hasta 3
     *    líneas que la preceden, que es donde vive el bloque de nombre.
     *
     * No es infalible (el OCR sobre fotos de documentos físicos es
     * ruidoso), por eso los campos del modal siempre quedan editables.
     */
    const extractNameFields = (rawText) => {
        const lines = rawText
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter(Boolean);

        const valueAfter = (labelPattern) => {
            const index = lines.findIndex((line) => labelPattern.test(line));
            if (index === -1) {
                return null;
            }

            const sameLine = lines[index].replace(labelPattern, '').trim();

            return sameLine || lines[index + 1] || '';
        };

        // "APELLIDO PATERNO/MATERNO" es la redacción del INE; "PRIMER/SEGUNDO
        // APELLIDO" es la que usa el Formato Único del acta de nacimiento.
        const paterno = valueAfter(/^(APELLIDO\s+PATERNO|PRIMER\s+APELLIDO):?/i);
        const materno = valueAfter(/^(APELLIDO\s+MATERNO|SEGUNDO\s+APELLIDO):?/i);

        if (paterno !== null || materno !== null) {
            return {
                nombre: valueAfter(/^NOMBRE\(?S?\)?:?/i) || '',
                apellidos: [paterno, materno].filter(Boolean).join(' '),
            };
        }

        const headerIndex = lines.findIndex((line) => /^NOMBRE\(?S?\)?:?\s*$/i.test(line));

        if (headerIndex !== -1) {
            const block = [];
            for (let i = headerIndex + 1; i < lines.length && block.length < 3; i++) {
                if (NAME_BLOCK_STOP_WORDS.test(lines[i])) {
                    break;
                }
                block.push(lines[i]);
            }

            const [apellidoPaterno, apellidoMaterno, nombre] = block;

            return {
                nombre: nombre || '',
                apellidos: [apellidoPaterno, apellidoMaterno].filter(Boolean).join(' '),
            };
        }

        const explicitLabels = {
            nombre: valueAfter(/^NOMBRE\(?S?\)?:?/i),
            apellidos: valueAfter(/^APELLIDOS?:?/i),
        };

        if (explicitLabels.nombre || explicitLabels.apellidos) {
            return {
                nombre: explicitLabels.nombre || '',
                apellidos: explicitLabels.apellidos || '',
            };
        }

        // Caso 4: no se detectó ninguna etiqueta de nombre. Se busca
        // "DOMICILIO" con tolerancia a errores de OCR (con que se lea el
        // fragmento "CILIO" alcanza) y se toman las líneas justo arriba,
        // ignorando ruido. Es más confiable que barrer desde el inicio del
        // texto, porque evita el encabezado/microtexto de seguridad del INE.
        const domicilioIndex = lines.findIndex((line) => /CILIO/i.test(line));

        if (domicilioIndex !== -1) {
            const block = [];
            for (let i = domicilioIndex - 1; i >= 0 && block.length < 3; i--) {
                if (NAME_BLOCK_STOP_WORDS.test(lines[i]) || NON_NAME_LINE.test(lines[i])) {
                    break;
                }
                block.unshift(lines[i]);
            }

            const [apellidoPaterno, apellidoMaterno, nombre] = block;

            if (nombre || apellidoPaterno) {
                return {
                    nombre: nombre || '',
                    apellidos: [apellidoPaterno, apellidoMaterno].filter(Boolean).join(' '),
                };
            }
        }

        return {nombre: '', apellidos: ''};
    };

    // DD/MM/AAAA (o con "-" o "." como separador): el formato en el que el
    // INE imprime la fecha de nacimiento.
    const DATE_PATTERN = /(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/;

    // "01 de enero de 1990": formato largo en español, común en actas de
    // nacimiento (tanto el Formato Único como formatos estatales antiguos).
    const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const LONG_DATE_PATTERN = new RegExp(`(\\d{1,2})\\s+de\\s+(${MONTHS.join('|')})\\s+de\\s+(\\d{4})`, 'i');

    const toIsoDateIfValid = (day, month, year) => {
        const d = Number(day);
        const m = Number(month);
        const y = Number(year);
        const date = new Date(y, m - 1, d);
        const isRealDate = date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d;

        if (!isRealDate || date > new Date()) {
            return '';
        }

        return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    };

    /**
     * Busca "FECHA DE NACIMIENTO" (tolerante a que el OCR pierda alguna
     * palabra entre medio) y una fecha -en formato DD/MM/AAAA o en formato
     * largo en español- en esa línea o en las dos siguientes. Si no
     * encuentra la etiqueta, no adivina: una fecha suelta en cualquier parte
     * del documento (CURP, vigencia, año de registro, fecha de registro del
     * acta...) es demasiado fácil de confundir con la de nacimiento.
     */
    const extractBirthDate = (lines) => {
        const labelIndex = lines.findIndex((line) => /FECHA.{0,20}NACIMIENTO/i.test(line));
        if (labelIndex === -1) {
            return '';
        }

        for (let i = labelIndex; i < Math.min(labelIndex + 3, lines.length); i++) {
            const shortMatch = lines[i].match(DATE_PATTERN);
            if (shortMatch) {
                const [, day, month, year] = shortMatch;
                const iso = toIsoDateIfValid(day, month, year);
                if (iso) {
                    return iso;
                }
            }

            const longMatch = lines[i].match(LONG_DATE_PATTERN);
            if (longMatch) {
                const [, day, monthName, year] = longMatch;
                const monthNumber = MONTHS.indexOf(monthName.toLowerCase()) + 1;
                const iso = toIsoDateIfValid(day, monthNumber, year);
                if (iso) {
                    return iso;
                }
            }
        }

        return '';
    };

    const resetPreview = () => {
        previewImage.hidden = true;
        previewImage.removeAttribute('src');
        previewPdf.hidden = true;
        previewPdf.removeAttribute('src');

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
    };

    const setStatus = (visible, text) => {
        statusBox.hidden = !visible;
        statusText.textContent = text ?? '';
    };

    const scanImage = async (imageSource, token, statusMessage) => {
        setStatus(true, statusMessage);
        nombreInput.value = '';
        apellidosInput.value = '';
        ocrBirthDateInput.value = '';

        try {
            const activeWorker = await getWorker();
            const {data} = await activeWorker.recognize(imageSource);

            if (token !== scanToken) {
                return; // Se canceló o se eligió otro archivo mientras se escaneaba.
            }

            // Ayuda a diagnosticar por qué el OCR no encontró el nombre en un
            // documento real: revisa este texto en la consola del navegador
            // (console.log, no console.debug, porque Chrome oculta "Debug"
            // del filtro de la consola por defecto).
            console.log('[OCR] texto reconocido:', data.text);

            const lines = data.text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            const fields = extractNameFields(data.text);
            nombreInput.value = fields.nombre;
            apellidosInput.value = fields.apellidos;
            ocrBirthDateInput.value = extractBirthDate(lines);

            if (fields.nombre || fields.apellidos) {
                setStatus(false);
            } else {
                setStatus(true, 'No se detectaron nombre y apellidos automáticamente. Completa los datos a mano (revisa la consola del navegador para ver el texto que sí se leyó).');
            }
        } catch (error) {
            if (token !== scanToken) {
                return;
            }

            console.error('[OCR] error al escanear el documento:', error);
            setStatus(true, 'No se pudo escanear el documento automáticamente. Completa los datos a mano.');
        }
    };

    const scanPdf = async (file, token) => {
        setStatus(true, 'Preparando el PDF para escanearlo...');

        try {
            const pageImage = await renderPdfFirstPageToBlob(file);

            if (token !== scanToken) {
                return;
            }

            await scanImage(pageImage, token, 'Escaneando documento...');
        } catch (error) {
            if (token !== scanToken) {
                return;
            }

            console.error('[OCR] error al renderizar el PDF:', error);
            setStatus(true, 'No se pudo leer el PDF automáticamente. Completa los datos a mano.');
        }
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }

        scanToken += 1;
        const token = scanToken;
        confirmed = false;

        resetPreview();
        previewUrl = URL.createObjectURL(file);

        if (file.type === 'application/pdf') {
            previewPdf.src = previewUrl;
            previewPdf.hidden = false;
            scanPdf(file, token);
        } else {
            previewImage.src = previewUrl;
            previewImage.hidden = false;
            scanImage(file, token, 'Escaneando documento...');
        }

        modal.show();
    });

    confirmButton.addEventListener('click', () => {
        const fullName = [nombreInput.value.trim(), apellidosInput.value.trim()]
            .filter(Boolean)
            .join(' ');

        if (fullName && fullNameInput) {
            fullNameInput.value = fullName;
        }

        if (ocrBirthDateInput.value && birthDateInput) {
            birthDateInput.value = ocrBirthDateInput.value;
        }

        confirmed = true;
        modal.hide();
    });

    cancelButtons.forEach((button) => {
        button.addEventListener('click', () => modal.hide());
    });

    UIkit.util.on(modalEl, 'hidden', () => {
        scanToken += 1; // Invalida cualquier escaneo en curso.

        if (!confirmed) {
            input.value = '';
            input.dispatchEvent(new Event('change'));
        }
    });
});
