/**
 * mailserver.form.js
 * Gère l'auto-complétion du port selon le type de serveur et le chiffrement,
 * et affiche/masque la section "password in=out" selon le type.
 */
(function () {
    'use strict';

    const data        = window.mailmanagerServer || {};
    const defaultPorts= data.defaultPorts || {};
    const pwInOutTypes= data.passwordInOutTypes || [];

    const typeSelect  = document.getElementById('mm-server-type');
    const encSelect   = document.getElementById('mm-encryption');
    const portInput   = document.getElementById('mm-port');
    const pwSection   = document.getElementById('mm-password-in-out-section');

    function updatePort() {
        if (!typeSelect || !encSelect || !portInput) return;
        const type = typeSelect.value;
        const enc  = encSelect.value;
        if (defaultPorts[type] && defaultPorts[type][enc]) {
            portInput.value = defaultPorts[type][enc];
        }
    }

    function updatePasswordSection() {
        if (!typeSelect || !pwSection) return;
        const type = typeSelect.value;
        pwSection.style.display = pwInOutTypes.includes(type) ? '' : 'none';
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            updatePort();
            updatePasswordSection();
        });
    }

    if (encSelect) {
        encSelect.addEventListener('change', updatePort);
    }

    // Init au chargement
    updatePasswordSection();

})();

/**
 * Sélecteur de domaines (liste déroulante avec recherche + Ajouter / Retirer)
 * et contact technique déduit du compte d'accès.
 */
(function () {
    'use strict';

    var $ = window.jQuery;
    var data = window.mailmanagerServer || {};

    // ------------------------------------------------------------------
    // Domaines gérés
    // ------------------------------------------------------------------
    function initDomains() {
        var avail    = document.getElementById('mm-dom-available');
        var selected = document.getElementById('mm-dom-selected');
        var addBtn   = document.getElementById('mm-dom-add');
        var delBtn   = document.getElementById('mm-dom-remove');
        var counter  = document.getElementById('mm-dom-count');
        if (!avail || !selected || !addBtn || !delBtn) return;

        // Liste déroulante avec zone de recherche (select2 est fourni par GLPI)
        if ($ && $.fn && $.fn.select2) {
            $(avail).select2({
                width: '100%',
                placeholder: avail.getAttribute('data-placeholder') || '',
                allowClear: true,
            });
        }

        function sortSelect(select) {
            var opts = Array.prototype.slice.call(select.options).filter(function (o) { return o.value !== ''; });
            opts.sort(function (a, b) { return a.text.localeCompare(b.text, 'fr', { sensitivity: 'base' }); });
            opts.forEach(function (o) { select.appendChild(o); });
        }

        function refresh() {
            if ($ && $.fn && $.fn.select2) {
                $(avail).val('').trigger('change.select2');
            } else {
                avail.value = '';
            }
            if (counter) {
                var n = selected.options.length;
                counter.textContent = n + (n > 1 ? ' domaines liés' : ' domaine lié');
            }
        }

        function add() {
            var opt = avail.options[avail.selectedIndex];
            if (!opt || opt.value === '') return;
            opt.selected = true;
            selected.appendChild(opt);
            sortSelect(selected);
            refresh();
        }

        function removeSelected() {
            Array.prototype.slice.call(selected.selectedOptions).forEach(function (opt) {
                opt.selected = false;
                avail.appendChild(opt);
            });
            sortSelect(avail);
            refresh();
        }

        addBtn.addEventListener('click', add);
        delBtn.addEventListener('click', removeSelected);
        selected.addEventListener('dblclick', removeSelected);

        // Tout ce qui est dans la liste liée doit partir avec le formulaire
        var form = selected.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                Array.prototype.forEach.call(selected.options, function (o) { o.selected = true; });
            });
        }
        refresh();
    }

    // ------------------------------------------------------------------
    // Contact technique : déduit du compte d'accès, modifiable à la main
    // ------------------------------------------------------------------
    function initTechContact() {
        var account = document.querySelector('select[name="plugin_accounts_accounts_id"]');
        var tech    = document.querySelector('select[name="users_id_tech"]');
        var hint    = document.querySelector('#mm-tech-hint small');
        var hintRow = document.getElementById('mm-tech-hint');
        if (!account || !tech || !data.ajaxUrl) return;

        var programmatic = false;

        function showHint(text) {
            if (!hint || !hintRow) return;
            hint.textContent = text;
            hintRow.style.display = text ? '' : 'none';
        }

        function setUser(id, label) {
            programmatic = true;
            if (!Array.prototype.some.call(tech.options, function (o) { return o.value === String(id); })) {
                tech.add(new Option(label, id, true, true));
            }
            if ($) { $(tech).val(String(id)).trigger('change'); } else { tech.value = String(id); }
            programmatic = false;
            tech.dataset.mmAuto = '1';
        }

        function fromAccount(initial) {
            var accountId = parseInt(account.value, 10) || 0;
            if (accountId <= 0) return;
            var hasValue = !!tech.value && tech.value !== '0';
            // Ne jamais écraser un choix fait à la main
            if (hasValue && tech.dataset.mmAuto !== '1') return;

            fetch(data.ajaxUrl + '?action=account_user&accounts_id=' + accountId, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.users_id) {
                        setUser(d.users_id, d.name || ('#' + d.users_id));
                        showHint('Proposé d\'après le compte d\'accès — modifiable.');
                    } else if (!initial) {
                        showHint('Aucun utilisateur GLPI n\'est rattaché à ce compte : choisissez le contact technique.');
                    }
                })
                .catch(function () { /* silencieux : le choix manuel reste possible */ });
        }

        if ($) {
            $(account).on('change', function () { fromAccount(false); });
            $(tech).on('change', function () {
                if (!programmatic) { tech.dataset.mmAuto = '0'; showHint(''); }
            });
        } else {
            account.addEventListener('change', function () { fromAccount(false); });
        }

        // Contact vide au chargement alors qu'un compte est choisi : proposer
        if ((!tech.value || tech.value === '0') && account.value && account.value !== '0') {
            fromAccount(true);
        }
    }

    function run() { initDomains(); initTechContact(); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); } else { run(); }
})();
