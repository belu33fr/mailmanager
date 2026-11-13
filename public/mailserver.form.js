/**
 * mailserver.form.js — fiche Serveur mail (onglets natifs GLPI, contenus insérés en AJAX).
 *  - onglet principal : contact technique déduit du compte administrateur
 *  - onglet Paramétrage : port par défaut selon le chiffrement
 *  - onglet Organisation : sélecteur de domaines (Ajouter / Retirer / Efface liste)
 * Chaque init est idempotent ; elles sont relancées à chaque insertion de contenu.
 */
(function () {
    'use strict';

    var $ = window.jQuery;
    function getData() { return window.mailmanagerServer || {}; }

    function initDomains() {
        var avail    = document.getElementById('mm-dom-available');
        var selected = document.getElementById('mm-dom-selected');
        var addBtn   = document.getElementById('mm-dom-add');
        var delBtn   = document.getElementById('mm-dom-remove');
        var counter  = document.getElementById('mm-dom-count');
        if (!avail || !selected || !addBtn || !delBtn) return;
        if (avail.dataset.mmInit === '1') return;
        avail.dataset.mmInit = '1';

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

        function clearAll() {
            if (!selected.options.length) return;
            if (!window.confirm('Vider toute la liste des domaines liés à ce serveur ?')) return;
            Array.prototype.slice.call(selected.options).forEach(function (opt) {
                opt.selected = false;
                avail.appendChild(opt);
            });
            sortSelect(avail);
            refresh();
        }

        // Écouteurs délégués sur document : robustes même si le DOM est remanié (select2, onglets)
        if (!window.__mmDomBound) {
            window.__mmDomBound = true;
            document.addEventListener('click', function (e) {
                var t = e.target.closest ? e.target.closest('#mm-dom-add, #mm-dom-remove, #mm-dom-clear') : null;
                if (!t) return;
                e.preventDefault();
                if (t.id === 'mm-dom-add') window.mmDomActions.add();
                else if (t.id === 'mm-dom-remove') window.mmDomActions.remove();
                else window.mmDomActions.clear();
            });
            document.addEventListener('dblclick', function (e) {
                if (e.target.closest && e.target.closest('#mm-dom-selected')) window.mmDomActions.remove();
            });
            document.addEventListener('submit', function (e) {
                var sel = document.getElementById('mm-dom-selected');
                if (sel && e.target.contains(sel)) {
                    Array.prototype.forEach.call(sel.options, function (o) { o.selected = true; });
                }
            }, true);
        }
        window.mmDomActions = { add: add, remove: removeSelected, clear: clearAll };
        refresh();
    }

    // ------------------------------------------------------------------
    // Contact technique : déduit du compte d'accès, modifiable à la main
    // ------------------------------------------------------------------
    function initTechContact() {
        var data = getData();
        var account = document.querySelector('select[name="plugin_accounts_accounts_id"]');
        var tech    = document.querySelector('select[name="users_id_tech"]');
        var hint    = document.querySelector('#mm-tech-hint small');
        var hintRow = document.getElementById('mm-tech-hint');
        if (!account || !tech || !data.ajaxUrl) return;
        if (tech.dataset.mmInit === '1') return;
        tech.dataset.mmInit = '1';

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
            $(account).on('change select2:select', function () { fromAccount(false); });
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

    
    function initParams() {
        var wrap = document.getElementById('mm-params');
        if (!wrap || wrap.dataset.mmInit === '1') return;
        wrap.dataset.mmInit = '1';
        var ports = {}, pwTypes = [];
        try { ports = JSON.parse(wrap.getAttribute('data-ports') || '{}'); } catch (e) { /* ignore */ }
        try { pwTypes = JSON.parse(wrap.getAttribute('data-pwtypes') || '[]'); } catch (e) { /* ignore */ }
        var typeSel = document.getElementById('mm-server-type');
        var enc  = document.getElementById('mm-encryption');
        var port = document.getElementById('mm-port');
        var net  = document.getElementById('mm-network-fields');
        var none = document.getElementById('mm-network-none');
        var pw   = document.getElementById('mm-password-in-out-section');
        var type = wrap.getAttribute('data-type');

        function applyType() {
            var fixed = (type === 'exchange' || type === 'ms365');
            if (net)  net.style.display  = fixed ? 'none' : '';
            if (none) none.style.display = fixed ? '' : 'none';
            if (pw)   pw.style.display   = pwTypes.indexOf(type) >= 0 ? '' : 'none';
            // Exchange / MS365 : une seule identité entrée/sortie => case masquée et cochée
            if (fixed) {
                var cb = document.querySelector('input[type="checkbox"][name="password_in_out"]');
                if (cb) cb.checked = true;
            }
        }
        function applyPort() {
            if (enc && port && ports[type] && ports[type][enc.value]) { port.value = ports[type][enc.value]; }
        }
        if (typeSel) {
            type = typeSel.value;
            typeSel.addEventListener('change', function () { type = typeSel.value; applyType(); applyPort(); });
        }
        if (enc) enc.addEventListener('change', applyPort);
        applyType();
    }

    function tryInit() { initDomains(); initTechContact(); initParams(); }
    tryInit();
    document.addEventListener('DOMContentLoaded', tryInit);
    if (window.MutationObserver) {
        new MutationObserver(tryInit).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
