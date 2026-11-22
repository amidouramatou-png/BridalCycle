(function () {
    'use strict';
    document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
        var main = gallery.querySelector('[data-gallery-main]');
        var buttons = gallery.querySelectorAll('[data-gallery-src]');
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                main.src = button.dataset.gallerySrc;
                buttons.forEach(function (b) { b.classList.remove('active'); });
                button.classList.add('active');
            });
        });
    });
})();
