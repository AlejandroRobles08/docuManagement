/**
 * Buscador de personas ya registradas para el formulario de subida de
 * documentos. Permite elegir a alguien existente (evitando volver a
 * escribir sus datos y crear un duplicado) o, si no aparece en los
 * resultados, crear una persona nueva completando los campos manuales.
 *
 * Al elegir a alguien existente, los campos de nombre/CURP/fecha de
 * nacimiento se llenan con sus datos ya registrados y se deshabilitan (para
 * confirmarlos de un vistazo, no para editarlos desde aquí), y se muestra
 * cuántos documentos tiene ya y con qué nivel de riesgo.
 *
 * El formulario sigue funcionando sin JavaScript: los campos manuales están
 * visibles y habilitados por defecto; este script solo los deshabilita (ya
 * no los oculta) cuando se elige a alguien del buscador.
 */
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-person-picker]');
    if (!picker) {
        return;
    }

    const searchUrl = picker.dataset.searchUrl;
    const searchInput = picker.querySelector('[data-person-search-input]');
    const personIdInput = picker.querySelector('[data-person-id-input]');
    const resultsBox = picker.querySelector('[data-person-search-results]');
    const resultsList = picker.querySelector('[data-person-search-list]');
    const selectedSummary = picker.querySelector('[data-person-selected-summary]');
    const selectedName = picker.querySelector('[data-person-selected-name]');
    const documentsSummary = picker.querySelector('[data-person-documents-summary]');
    const clearButton = picker.querySelector('[data-person-clear]');
    const emptyHint = picker.querySelector('[data-person-empty-hint]');
    const newFields = document.querySelector('[data-person-new-fields]');
    const newFieldsInputs = newFields ? newFields.querySelectorAll('input') : [];
    const curpInput = newFields?.querySelector('#curp');
    const birthDateInput = newFields?.querySelector('#birth_date');

    const RISK_LABELS = {bajo: 'Riesgo bajo', medio: 'Riesgo medio', alto: 'Riesgo alto'};

    let debounceTimer = null;
    let currentController = null;

    const hideResults = () => {
        resultsBox.hidden = true;
        resultsList.innerHTML = '';
    };

    // Los campos "Nombre completo" / CURP / Fecha de nacimiento ya NO se
    // ocultan al elegir una persona existente: quedan visibles pero
    // deshabilitados y con sus datos ya registrados, para que el staff
    // confirme de un vistazo que es la persona correcta antes de subir el
    // documento. Al crear una persona nueva, vuelven a quedar editables.
    const toggleNewFields = (editable) => {
        newFieldsInputs.forEach((input) => {
            input.disabled = !editable;
        });
    };

    // "Sin documentos registrados todavía." si la persona no tiene ninguno,
    // o el total más una insignia por cada nivel de riesgo con al menos un
    // documento (mismas clases .risk-badge que el resto de la app).
    const renderDocumentsSummary = (person) => {
        if (!documentsSummary) {
            return;
        }

        if (!person.documents_count) {
            documentsSummary.innerHTML = '<span class="uk-text-meta">Sin documentos registrados todavía.</span>';
            return;
        }

        const badges = Object.entries(RISK_LABELS)
            .filter(([level]) => (person.risk_counts?.[level] ?? 0) > 0)
            .map(([level, label]) => `<span class="risk-badge risk-badge--${level}">${person.risk_counts[level]} ${label}</span>`)
            .join(' ');

        documentsSummary.innerHTML = `<strong>${person.documents_count}</strong> documento(s) registrado(s): ${badges}`;
    };

    const selectPerson = (person) => {
        personIdInput.value = person.id;
        selectedName.textContent = person.curp
            ? `${person.full_name} (CURP: ${person.curp})`
            : person.full_name;
        selectedSummary.hidden = false;
        searchInput.value = '';
        emptyHint.hidden = true;
        hideResults();
        toggleNewFields(false);
        renderDocumentsSummary(person);

        // Refleja en los mismos campos los datos ya registrados de la
        // persona (deshabilitados: no se editan desde aquí, solo se
        // confirman visualmente).
        const fullNameInput = newFields?.querySelector('#full_name');
        if (fullNameInput) fullNameInput.value = person.full_name ?? '';
        if (curpInput) curpInput.value = person.curp ?? '';
        if (birthDateInput) birthDateInput.value = person.birth_date ?? '';
    };

    const clearSelection = () => {
        personIdInput.value = '';
        selectedSummary.hidden = true;
        toggleNewFields(true);

        const fullNameInput = newFields?.querySelector('#full_name');
        if (fullNameInput) fullNameInput.value = '';
        if (curpInput) curpInput.value = '';
        if (birthDateInput) birthDateInput.value = '';
        if (documentsSummary) documentsSummary.innerHTML = '';

        searchInput.focus();
    };

    clearButton?.addEventListener('click', clearSelection);

    const renderResults = (persons, query) => {
        resultsList.innerHTML = '';

        persons.forEach((person) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = person.curp
                ? `${person.full_name} — CURP: ${person.curp}`
                : person.full_name;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                selectPerson(person);
            });
            item.appendChild(link);
            resultsList.appendChild(item);
        });

        const divider = document.createElement('li');
        divider.className = 'uk-nav-divider';
        resultsList.appendChild(divider);

        const createItem = document.createElement('li');
        const createLink = document.createElement('a');
        createLink.href = '#';
        createLink.innerHTML = `<span uk-icon="icon: plus-circle" class="uk-margin-small-right"></span> Crear persona nueva${query ? ` "${query}"` : ''}`;
        createLink.addEventListener('click', (event) => {
            event.preventDefault();
            clearSelection();
            toggleNewFields(true);
            hideResults();

            const fullNameInput = newFields?.querySelector('#full_name');
            if (fullNameInput) {
                fullNameInput.value = query;
                newFields.scrollIntoView({behavior: 'smooth', block: 'center'});
                fullNameInput.focus();
            }
        });
        createItem.appendChild(createLink);
        resultsList.appendChild(createItem);

        resultsBox.hidden = false;
        emptyHint.hidden = persons.length > 0;
    };

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();
        personIdInput.value = '';
        selectedSummary.hidden = true;

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            hideResults();
            emptyHint.hidden = true;
            return;
        }

        debounceTimer = setTimeout(() => {
            currentController?.abort();
            currentController = new AbortController();

            fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                headers: {Accept: 'application/json'},
                signal: currentController.signal,
            })
                .then((response) => response.json())
                .then((persons) => renderResults(persons, query))
                .catch((error) => {
                    if (error.name !== 'AbortError') {
                        hideResults();
                    }
                });
        }, 300);
    });

    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) {
            hideResults();
        }
    });
});
