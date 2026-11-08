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
        var topUser = document.getElementById('mm-top-user');
        if (topUser) topUser.style.display = (type === 'mailbox') ? '' : 'none';
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

/**
 * Utilisateur <-> adresse mail <-> informations Exchange/MS365.
 *  - adresse saisie, utilisateur vide      => utilisateur retrouvé par son adresse
 *  - utilisateur choisi, adresse vide      => adresse par défaut de l'utilisateur
 *  - adresse absente de la fiche utilisateur => on demande : l'ajouter, la remplacer ou ne rien changer
 *  - champs Exchange/MS365 vides            => remplis depuis la fiche utilisateur
 *  - champs non vides différents            => on propose de les mettre à jour
 *  - création d'un utilisateur à la volée (bouton +)
 */
(function () {
    'use strict';

    var $    = window.jQuery;
    var cfg  = window.mailmanagerObject || {};
    var userSel = document.querySelector('select[name="users_id"]');
    var emailIn = document.querySelector('input[name="email"]');
    if (!userSel || !emailIn || !cfg.ajaxUrl) return;

    // Champs de la fiche alimentés par la fiche utilisateur (nom du champ => libellé affiché)
    var FIELDS = {
        phone:        'Téléphone',
        phone_mobile: 'Mobile',
        job_title:    'Fonction / Titre',
        department:   'Service / Département',
        postal_code:  'Code postal',
        city:         'Ville',
        country:      'Pays',
    };

    var programmatic = false;

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------
    function csrf() {
        var m = document.querySelector('meta[property="glpi:csrf_token"]');
        return m ? m.content : '';
    }

    function getJson(query) {
        return fetch(cfg.ajaxUrl + '?action=' + query, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function postJson(action, params) {
        params.action = action;
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':      'application/x-www-form-urlencoded',
                'X-Glpi-Csrf-Token': csrf(),
                'X-Requested-With':  'XMLHttpRequest',
            },
            body: new URLSearchParams(params).toString(),
        }).then(function (r) {
            return r.json().then(function (d) { d._status = r.status; return d; });
        });
    }

    function field(name) { return document.querySelector('[name="' + name + '"]'); }
    function val(el) { return el ? el.value.trim() : ''; }

    function setUser(id, label) {
        programmatic = true;
        if (!Array.prototype.some.call(userSel.options, function (o) { return o.value === String(id); })) {
            userSel.add(new Option(label, id, true, true));
        }
        if ($) { $(userSel).val(String(id)).trigger('change'); } else { userSel.value = String(id); }
        programmatic = false;
    }

    /**
     * Petite boîte de dialogue à choix multiples ; renvoie la valeur du bouton choisi.
     * Le dernier bouton sert aussi de choix par défaut si on ferme la fenêtre.
     */
    function choose(title, intro, lines, buttons) {
        return new Promise(function (resolve) {
            if (!window.bootstrap || !bootstrap.Modal) {
                var txt = title + '\n' + intro + '\n' + lines.join('\n') + '\n\nOK = ' + buttons[0].label;
                resolve(window.confirm(txt) ? buttons[0].value : buttons[buttons.length - 1].value);
                return;
            }
            var wrap = document.createElement('div');
            wrap.className = 'modal fade';
            wrap.tabIndex = -1;
            wrap.innerHTML = '<div class="modal-dialog"><div class="modal-content">'
                + '<div class="modal-header"><h5 class="modal-title"></h5></div>'
                + '<div class="modal-body"><p class="mm-dlg-intro"></p><ul class="mm-dlg-lines mb-0"></ul></div>'
                + '<div class="modal-footer"></div></div></div>';
            wrap.querySelector('.modal-title').textContent = title;
            wrap.querySelector('.mm-dlg-intro').textContent = intro;
            var ul = wrap.querySelector('.mm-dlg-lines');
            lines.forEach(function (l) {
                var li = document.createElement('li');
                li.textContent = l;
                ul.appendChild(li);
            });
            if (!lines.length) ul.remove();

            var chosen = null;
            var modal;
            var footer = wrap.querySelector('.modal-footer');
            buttons.forEach(function (b, i) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn ' + (i === 0 ? 'btn-primary' : 'btn-outline-secondary');
                btn.textContent = b.label;
                btn.addEventListener('click', function () { chosen = b.value; modal.hide(); });
                footer.appendChild(btn);
            });
            wrap.addEventListener('hidden.bs.modal', function () {
                wrap.remove();
                resolve(chosen !== null ? chosen : buttons[buttons.length - 1].value);
            });
            document.body.appendChild(wrap);
            modal = new bootstrap.Modal(wrap, { backdrop: 'static' });
            modal.show();
        });
    }

    // ------------------------------------------------------------------
    // Cohérence adresse mail / utilisateur
    // ------------------------------------------------------------------
    function reconcileEmail(uid, info, prompt) {
        var mail = val(emailIn);
        if (mail === '') {
            if (info.email) emailIn.value = info.email;
            return Promise.resolve();
        }
        var known = (info.emails || []).map(function (e) { return e.toLowerCase(); });
        if (known.indexOf(mail.toLowerCase()) !== -1 || !prompt) return Promise.resolve();

        var buttons = [{ label: 'Ajouter l\'adresse à la fiche utilisateur', value: 'add' }];
        if (info.email) buttons.push({ label: 'Utiliser l\'adresse de l\'utilisateur', value: 'replace' });
        buttons.push({ label: 'Ne rien changer', value: 'none' });

        var lines = ['Adresse saisie dans la boîte : ' + mail];
        lines.push(info.email ? 'Adresse de l\'utilisateur : ' + info.email : 'L\'utilisateur n\'a aucune adresse enregistrée.');

        return choose('Adresse différente de celle de l\'utilisateur',
            'L\'adresse saisie ne figure pas dans la fiche de ' + (info.label || 'l\'utilisateur') + '. Que faire ?',
            lines, buttons).then(function (choice) {
                if (choice === 'add') {
                    return postJson('user_addemail', { users_id: uid, email: mail }).then(function (d) {
                        if (d && d.error) { window.alert(d.error); }
                    });
                }
                if (choice === 'replace') { emailIn.value = info.email; }
            });
    }

    // ------------------------------------------------------------------
    // Champs Exchange/MS365 alimentés par la fiche utilisateur
    // ------------------------------------------------------------------
    function reconcileFields(info, prompt) {
        var dn = field('display_name');
        if (dn && val(dn) === '' && info.display_name) dn.value = info.display_name;

        var diffs = [];
        Object.keys(FIELDS).forEach(function (name) {
            var el = field(name);
            var v  = (info.fields || {})[name] || '';
            if (!el || v === '') return;
            if (val(el) === '') {
                el.value = v;                                   // vide : on remplit
            } else if (val(el) !== v) {
                diffs.push({ name: name, current: val(el), next: v });
            }
        });
        if (!diffs.length || !prompt) return Promise.resolve();

        return choose('Mettre à jour avec la fiche utilisateur ?',
            'Certaines informations diffèrent de celles de ' + (info.label || 'l\'utilisateur') + ' :',
            diffs.map(function (d) { return FIELDS[d.name] + ' : « ' + d.current + ' » → « ' + d.next + ' »'; }),
            [{ label: 'Mettre à jour', value: 'update' }, { label: 'Conserver mes valeurs', value: 'keep' }]
        ).then(function (choice) {
            if (choice !== 'update') return;
            diffs.forEach(function (d) { field(d.name).value = d.next; });
        });
    }

    function handleUser(prompt) {
        var uid = parseInt(userSel.value, 10) || 0;
        if (!uid) return Promise.resolve();
        return getJson('user_info&users_id=' + uid).then(function (info) {
            if (!info || !info.found) return;
            return reconcileEmail(uid, info, prompt).then(function () { return reconcileFields(info, prompt); });
        }).catch(function () { /* silencieux : saisie manuelle toujours possible */ });
    }

    // ------------------------------------------------------------------
    // Événements
    // ------------------------------------------------------------------
    // Changement d'utilisateur par l'opérateur
    if ($) {
        $(userSel).on('change', function () { if (!programmatic) handleUser(true); });
    } else {
        userSel.addEventListener('change', function () { if (!programmatic) handleUser(true); });
    }

    // Adresse saisie sans utilisateur : on le retrouve par son adresse
    emailIn.addEventListener('change', function () {
        var mail = val(emailIn);
        if (mail === '' || (parseInt(userSel.value, 10) || 0) > 0) return;
        getJson('user_by_email&email=' + encodeURIComponent(mail)).then(function (d) {
            if (d && d.users_id) {
                setUser(d.users_id, d.name || ('#' + d.users_id));
                handleUser(true);
            }
        }).catch(function () {});
    });

    // Au chargement : on complète seulement les zones vides, sans poser de question
    if ((parseInt(userSel.value, 10) || 0) > 0) handleUser(false);

    // ------------------------------------------------------------------
    // Création d'un utilisateur à la volée
    // ------------------------------------------------------------------
    var openBtn = document.getElementById('mm-user-create');
    var modalEl = document.getElementById('mm-user-modal');
    if (openBtn && modalEl && window.bootstrap && bootstrap.Modal) {
        var modal   = new bootstrap.Modal(modalEl);
        var errBox  = document.getElementById('mm-uc-error');
        var saveBtn = document.getElementById('mm-uc-save');
        var ids = ['realname', 'firstname', 'email', 'login', 'phone', 'mobile'];

        function ucField(n) { return document.getElementById('mm-uc-' + n); }
        function ucError(msg) { errBox.textContent = msg || ''; errBox.style.display = msg ? '' : 'none'; }

        openBtn.addEventListener('click', function () {
            ids.forEach(function (n) { ucField(n).value = ''; });
            ucError('');
            ucField('email').value = val(emailIn);
            // prénom / nom proposés depuis « Nom à afficher » s'il est rempli
            var dn = val(field('display_name')).split(/\s+/);
            if (dn[0]) {
                ucField('firstname').value = dn.length > 1 ? dn[0] : '';
                ucField('realname').value  = dn.length > 1 ? dn.slice(1).join(' ') : dn[0];
            }
            ucField('phone').value  = val(field('phone'));
            ucField('mobile').value = val(field('phone_mobile'));
            modal.show();
        });

        // Entrée dans la fenêtre = Créer (et jamais l'envoi de la fiche en arrière-plan)
        modalEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); saveBtn.click(); }
        });

        saveBtn.addEventListener('click', function () {
            ucError('');
            saveBtn.disabled = true;
            postJson('user_create', {
                realname:  val(ucField('realname')),
                firstname: val(ucField('firstname')),
                email:     val(ucField('email')),
                login:     val(ucField('login')),
                phone:     val(ucField('phone')),
                mobile:    val(ucField('mobile')),
            }).then(function (d) {
                saveBtn.disabled = false;
                if (!d || d.error || !d.id) { ucError((d && d.error) || 'Création impossible.'); return; }
                modal.hide();
                setUser(d.id, d.label);
                handleUser(false);
            }).catch(function () {
                saveBtn.disabled = false;
                ucError('Erreur de communication avec le serveur.');
            });
        });
    }
})();
