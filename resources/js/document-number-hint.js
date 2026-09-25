/**
 * Muestra, bajo el campo "Identificador", qué número se espera según el
 * tipo de documento elegido. Un campo genérico sin esta pista invita a
 * escribir texto descriptivo (p. ej. "Acta de nacimiento") en vez de un
 * identificador real, lo que provoca falsos positivos de "persona
 * duplicada" entre personas distintas que cometen el mismo error (ver
 * PersonMatcher::findDuplicate() y DocumentType::identifierHint()).
 */
document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('[data-document-number-hints]');
    const hint = document.querySelector('[data-document-number-hint]');
    if (!select || !hint) {
        return;
    }

    const updateHint = () => {
        hint.textContent = select.selectedOptions[0]?.dataset.hint ?? '';
    };

    select.addEventListener('change', updateHint);
    updateHint();
});
