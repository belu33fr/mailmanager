/**
 * mailobject.form.js
 * Affiche/masque les sections selon le type d'objet mail sélectionné.
 * Gère aussi les toggles de redirection et de réponse automatique.
 */
(function () {
    'use strict';

    // Map type → ID de section
    const SECTION_MAP = {
        mailbox : 'mm-section-mailbox',
        alias   : 'mm-section-alias',
        list    : 'mm-section-list',
        shared  : 'mm-section-shared',
        group   : 'mm-section-group',
    };

    const typeSelect = document.getElementById('mm-mail-type');

    function showSection(type) {
        Object.keys(SECTION_MAP).forEach(function (t) {
            const el = document.getElementById(SECTION_MAP[t]);
            if (!el) return;
            el.style.display = (t === type) ? '' : 'none';
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            showSection(typeSelect.value);
        });
        // Init
        const data = window.mailmanagerObject || {};
        showSection(data.currentType || typeSelect.value);
    }

    // ----------------------------------------------------------------
    // Toggle forward (boîte mail)
    // ----------------------------------------------------------------
    function wireToggle(checkboxId, sectionId) {
        const cb      = document.getElementById(checkboxId);
        const section = document.getElementById(sectionId);
        if (!cb || !section) return;
        cb.addEventListener('change', function () {
            section.style.display = cb.checked ? '' : 'none';
        });
    }

    wireToggle('mm-has-forward',         'mm-forward-section');
    wireToggle('mm-has-autoreply',        'mm-autoreply-section');
    wireToggle('mm-shared-has-forward',   'mm-shared-forward-section');
    wireToggle('mm-shared-has-autoreply', 'mm-shared-autoreply-section');

})();
