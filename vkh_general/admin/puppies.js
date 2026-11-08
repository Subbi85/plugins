document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.vkh-puppies');
    if (!root) return;
    root.querySelectorAll('[data-vkh-status-form]').forEach(function (form) {
        var select = form.querySelector('select');
        var button = form.querySelector('[data-vkh-save-status]');
        if (!window.fetch || !window.FormData) return;
        button.hidden = true;
        select.addEventListener('change', function () {
            var previous = select.dataset.previous;
            var value = select.value;
            var body = new FormData(form);
            var message = root.querySelector('[data-vkh-status-message]');
            select.disabled = true;
            fetch(window.ajaxurl || form.action.replace('admin-post.php', 'admin-ajax.php'), {method: 'POST', body: body, credentials: 'same-origin'})
                .then(function (response) { return response.json(); })
                .then(function (result) {
                    if (!result.success) throw new Error(result.data && result.data.message || 'Der Status konnte nicht gespeichert werden.');
                    select.dataset.previous = value;
                    form.querySelector('.vkh-dot').className = 'vkh-dot vkh-dot-' + value;
                    var litter = form.closest('.vkh-litter');
                    var available = Array.from(litter.querySelectorAll('[name="verkaufsstatus"]')).filter(function (input) { return input.value === 'nein'; }).length;
                    litter.querySelector('[data-vkh-available]').textContent = available;
                    message.textContent = 'Verkaufsstatus gespeichert.';
                    message.className = 'notice notice-success inline';
                }).catch(function (error) {
                    select.value = previous;
                    message.textContent = error.message || 'Der Status konnte nicht gespeichert werden. Bitte erneut versuchen.';
                    message.className = 'notice notice-error inline';
                }).finally(function () { select.disabled = false; });
        });
    });
    var form = root.querySelector('[data-vkh-litter-form]');
    if (!form) return;
    var editor = form.closest('.vkh-litter-editor');
    var rows = form.querySelector('[data-vkh-puppy-rows]');
    var panels = Array.from(form.querySelectorAll('[data-vkh-step]'));
    var back = form.querySelector('[data-vkh-back]');
    var next = form.querySelector('[data-vkh-next]');
    var submit = form.querySelector('[data-vkh-submit]');
    var error = form.querySelector('[data-vkh-litter-error]');
    var add = form.querySelector('[data-vkh-add]');
    var step = 0;
    form.noValidate = true;
    add.hidden = false;
    function setError(message) { error.textContent = message; error.hidden = !message; }
    function renumber() {
        Array.from(rows.children).forEach(function (row, index) {
            row.querySelectorAll('input,select').forEach(function (input) {
                input.name = input.name.replace(/welpen\[\d+\]/, 'welpen[' + index + ']');
            });
            var remove = row.querySelector('[data-vkh-remove]');
            var savedId = row.querySelector('[data-vkh-puppy-id]');
            var existing = savedId && Number(savedId.value) > 0;
            remove.hidden = existing;
            remove.disabled = existing || rows.children.length <= 1;
            remove.setAttribute('aria-label', 'Welpe ' + (index + 1) + ' entfernen');
        });
        add.disabled = rows.children.length >= 20;
    }
    function renderReview() {
        form.querySelector('[data-vkh-review-title]').textContent = form.elements.bezeichnung.value.trim() + (form.elements.wurfdatum.value ? ' · ' + form.elements.wurfdatum.value.split('-').reverse().join('.') : '');
        var table = document.createElement('table'); table.className = 'widefat striped';
        var head = document.createElement('thead'), tr = document.createElement('tr');
        ['Name','Geschlecht','Verkauft?'].forEach(function (text) { var th = document.createElement('th'); th.scope = 'col'; th.textContent = text; tr.appendChild(th); });
        head.appendChild(tr); table.appendChild(head);
        var body = document.createElement('tbody');
        Array.from(rows.children).forEach(function (row) {
            var tr = document.createElement('tr');
            var name = row.querySelector('[data-vkh-puppy-name]').value.trim();
            var sex = row.querySelector('[data-vkh-puppy-sex]');
            var status = row.querySelector('[data-vkh-puppy-status]');
            [name, sex.options[sex.selectedIndex].textContent].forEach(function (text) { var td = document.createElement('td'); td.textContent = text; tr.appendChild(td); });
            var td = document.createElement('td'), dot = document.createElement('span');
            dot.className = 'vkh-dot vkh-dot-' + status.value; dot.setAttribute('aria-hidden','true');
            td.appendChild(dot); td.appendChild(document.createTextNode(' ' + status.options[status.selectedIndex].textContent));
            tr.appendChild(td); body.appendChild(tr);
        });
        table.appendChild(body); form.querySelector('[data-vkh-review]').replaceChildren(table);
    }
    function show() {
        panels.forEach(function (panel, i) { panel.hidden = i !== step; });
        editor.querySelectorAll('[data-vkh-step-label]').forEach(function (label, i) {
            label.classList.toggle('is-current', i === step);
            if (i === step) label.setAttribute('aria-current','step'); else label.removeAttribute('aria-current');
        });
        back.hidden = step === 0; next.hidden = step === 2; submit.hidden = step !== 2;
        next.textContent = step === 0 ? 'Weiter zu den Welpen' : 'Angaben prüfen';
        if (step === 2) renderReview();
    }
    function valid(index) {
        var controls = Array.from(panels[index].querySelectorAll('input,select'));
        var invalid = controls.find(function (input) { return !input.checkValidity() || (input.required && !input.value.trim()); });
        if (invalid) { step = index; show(); invalid.focus(); invalid.reportValidity(); setError('Bitte die Pflichtangaben vollständig und gültig ausfüllen.'); return false; }
        return true;
    }
    next.addEventListener('click', function () { setError(''); if (valid(step)) { step++; show(); } });
    back.addEventListener('click', function () { setError(''); step--; show(); });
    add.addEventListener('click', function () {
        if (rows.children.length >= 20) return;
        var template = editor.querySelector('[data-vkh-row-template]');
        rows.appendChild(template.content.querySelector('tr').cloneNode(true)); renumber();
    });
    rows.addEventListener('click', function (event) {
        var remove = event.target.closest('[data-vkh-remove]');
        if (remove && !remove.disabled && rows.children.length > 1) { remove.closest('tr').remove(); renumber(); }
    });
    form.addEventListener('submit', function (event) {
        setError('');
        if (step < 2) { event.preventDefault(); if (valid(step)) { step++; show(); } return; }
        if (!valid(0) || !valid(1)) { event.preventDefault(); return; }
        submit.disabled = true; submit.textContent = 'Wird gespeichert …';
    });
    renumber(); show();
});