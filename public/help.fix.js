/**
 * help.fix.js — corrige les pastilles d'aide « ? » qui restent figées.
 *
 * GLPI crée ces infobulles sans préciser de déclencheur : Bootstrap utilise alors
 * « survol + focus », et l'infobulle peut rester affichée si la souris reste dessus.
 * On les recrée avec le seul déclencheur « survol » et on les ferme au clic ou à la
 * sortie de la souris. Idempotent : le marquage data-bs-original-title empêche GLPI de
 * les réinitialiser (son initialisation ignore les éléments qui portent déjà l'attribut).
 *
 * Même principe que la correction retenue dans le plugin DNS.
 */
window.mailmanagerFixHelp = function () {
    'use strict';
    if (!window.bootstrap || !bootstrap.Tooltip) return;

    document.querySelectorAll('.form-help[data-bs-toggle="tooltip"]').forEach(function (el) {
        if (el.dataset.mailmanagerHelp === '1') return;
        el.dataset.mailmanagerHelp = '1';

        var old = bootstrap.Tooltip.getInstance(el);
        if (old) old.dispose();

        var tip = new bootstrap.Tooltip(el, {
            trigger:   'hover',
            placement: el.getAttribute('data-bs-placement') || 'top',
            html:      el.getAttribute('data-bs-html') === 'true',
            title:     el.getAttribute('data-bs-title') || '',
        });
        el.setAttribute('data-bs-original-title', el.getAttribute('data-bs-title') || '');

        el.addEventListener('click',      function () { tip.hide(); });
        el.addEventListener('mouseleave', function () { tip.hide(); });
    });
};

(function () {
    'use strict';
    function run() { window.mailmanagerFixHelp(); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    // GLPI peut initialiser ses infobulles après nous : repasser quelques fois (idempotent).
    [300, 1200, 3000].forEach(function (ms) { setTimeout(run, ms); });
})();
