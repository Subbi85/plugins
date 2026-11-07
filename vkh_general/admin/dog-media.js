/* One media selection per field; files remain managed by WordPress. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vkh-media-field').forEach(function (field) {
        var input = field.querySelector('input[type="hidden"]');
        var preview = field.querySelector('.vkh-media-preview');
        var remove = field.querySelector('.vkh-media-remove');
        var isPhoto = field.dataset.kind === 'foto_id';
        var frame;
        field.querySelector('.vkh-media-select').addEventListener('click', function () {
            if (!frame) {
                frame = wp.media({
                    title: isPhoto ? 'Hundefoto auswählen' : 'Ahnentafel auswählen',
                    button: { text: 'Übernehmen' },
                    library: { type: isPhoto ? 'image' : ['image', 'application/pdf'] },
                    multiple: false
                });
                frame.on('select', function () {
                    var item = frame.state().get('selection').first().toJSON();
                    input.value = item.id;
                    preview.replaceChildren();
                    if (item.type === 'image') {
                        var img = document.createElement('img');
                        img.src = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
                        img.alt = item.alt || '';
                        img.style.cssText = 'max-width:150px;height:auto;display:block;margin-bottom:8px';
                        preview.appendChild(img);
                    }
                    var title = document.createElement('span');
                    title.textContent = item.title || item.filename || 'Ausgewählte Datei';
                    preview.appendChild(title);
                    remove.hidden = false;
                });
                frame.on('open', function () {
                    var selection = frame.state().get('selection');
                    selection.reset();
                    if (input.value) {
                        var attachment = wp.media.attachment(Number(input.value));
                        attachment.fetch();
                        selection.add(attachment);
                    }
                });
            }
            frame.open();
        });
        remove.addEventListener('click', function () {
            input.value = '';
            preview.replaceChildren();
            remove.hidden = true;
        });
    });
});