(function () {
    'use strict';

    document.querySelectorAll('[data-autosubmit]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.value !== '' && input.checkValidity()) { input.form.submit(); }
        });
    });

    var radios = document.querySelectorAll('[data-address-toggle]');
    var block = document.getElementById('address-block');
    if (!radios.length || !block) { return; }
    function update() {
        var delivery = document.querySelector('[data-address-toggle][value="delivery"]').checked;
        block.hidden = !delivery;
        block.querySelectorAll('textarea, input').forEach(function (el) { el.disabled = !delivery; });
        // Delivery fee line and total in the summary (only a preview
        var feeLine = document.querySelector('[data-fee-line]');
        var grand = document.querySelector('[data-grand]');
        if (feeLine) { feeLine.hidden = !delivery; }
        if (grand) { grand.textContent = delivery ? grand.dataset.delivery : grand.dataset.pickup; }
    }
    radios.forEach(function (r) { r.addEventListener('change', update); });
    update();
})();
