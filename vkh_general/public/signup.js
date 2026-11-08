document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vkh-signup]').forEach(function (form) {
        var consents = Array.from(form.querySelectorAll('[data-vkh-consent]'));
        function validateConsent() {
            consents[0].setCustomValidity(consents.some(function (box) { return box.checked; }) ? '' : 'Bitte die Einwilligung für weitere Informationen bestätigen.');
        }
        consents.forEach(function (box) { box.addEventListener('change', validateConsent); });
        validateConsent();
        // Fresh per-IP tokens also work when a page cache served the markup.
        fetch(form.dataset.tokenUrl, {credentials: 'same-origin', cache: 'no-store'})
            .then(function (response) { if (!response.ok) throw new Error('token'); return response.json(); })
            .then(function (result) {
                if (result.success && result.data) {
                    form.elements.vkh_token.value = result.data.token;
                    form.elements._wpnonce.value = result.data.nonce;
                }
            }).catch(function () { /* Server-rendered token remains the no-JS fallback. */ });
    });
});