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
