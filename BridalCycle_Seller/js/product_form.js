(function () {
    'use strict';

    // Show / hide the price fields
    document.querySelectorAll('[data-toggles]').forEach(function (box) {
        var block = document.getElementById(box.dataset.toggles);
        if (!block) { return; }
        function update() {
            block.hidden = !box.checked;
            block.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !box.checked;
            });
        }
        box.addEventListener('change', update);
        update();
    });

    // Photo previews + checks
    var input = document.getElementById('photos');
    var preview = document.getElementById('photo-preview');
    if (!input || !preview) { return; }

    var allowed = ['image/jpeg', 'image/png', 'image/webp'];

    function showError(message) {
        var field = input.closest('.field');
        var box = field.querySelector('.field-error');
        field.classList.toggle('invalid', message !== '');
        if (message === '') { if (box) { box.remove(); } return; }
        if (!box) {
            box = document.createElement('div');
            box.className = 'field-error';
            field.insertBefore(box, preview);
        }
        box.textContent = message;
    }

    input.addEventListener('change', function () {
        preview.textContent = '';
        var files = Array.prototype.slice.call(input.files);
        var max = parseInt(input.dataset.maxFiles, 10);
        var maxBytes = parseInt(input.dataset.maxBytes, 10);
        var message = '';

        if (files.length > max) { message = input.dataset.errorCount; }
        files.forEach(function (file) {
            if (allowed.indexOf(file.type) === -1) { message = message || input.dataset.errorType; }
            else if (file.size > maxBytes) { message = message || input.dataset.errorSize; }
        });
        showError(message);
        if (message !== '') { input.value = ''; return; }   // clear the wrong choice

        files.forEach(function (file) {
            var reader = new FileReader();
            reader.onload = function (event) {
                var img = document.createElement('img');
                img.src = event.target.result;      // data: picture, allowed by our security policy
                img.alt = '';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });

    // Not enough photos (minimum 2 for a new outfit): stop the form before it is sent
    var form = input.form;
    form.addEventListener('submit', function (event) {
        var min = parseInt(input.dataset.minFiles || '0', 10);
        if (input.files.length < min) {
            event.preventDefault();
            event.stopImmediatePropagation();      // validate.js does not run (no "loading" button)
            showError(input.dataset.errorMin);
            input.focus();
        }
    });
})();
