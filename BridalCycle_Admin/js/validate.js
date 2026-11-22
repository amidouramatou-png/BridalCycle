(function () {
    'use strict';

    // The regular expressions (the same rules as the PHP side)
    var patterns = {
        // Name: starts with a letter, then letters, spaces, apostrophes, dots, hyphens
        name: /^[\p{L}][\p{L}\s'’.-]{1,119}$/u,
        // Email: something@something.something (lab regex)
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        // Niger phone: optional +227 or 00227, then 8 digits
        phone: /^(\+227|00227)?[\s.-]?\d{2}([\s.-]?\d{2}){3}$/,
        // Whole number greater than 0 (prices, quantities)
        positive: /^[1-9]\d*$/,
        // Category name: 2 to 80 characters, letters, numbers, spaces and ' & / ( ) . , -
        catname: /^[\p{L}\p{N}][\p{L}\p{N}\s'’&\/().,-]{1,79}$/u,
        // Link name: small letters, numbers and single hyphens (grand-boubou)
        slug: /^[a-z0-9]+(-[a-z0-9]+)*$/,
        // Display order: whole number 0 to 999
        order: /^\d{1,3}$/,
        // Country: letters, spaces, apostrophes, hyphens (2 to 60)
        country: /^[\p{L}][\p{L}\s'’-]{1,59}$/u,
        // Outfit title: 3 to 150 characters
        title: /^[\p{L}\p{N}][\p{L}\p{N}\s'’&\/().,:!+-]{2,149}$/u,
        // Size: 1 to 20 characters, e.g. M, 38, 40/42, Sur mesure
        size: /^[\p{L}\p{N}][\p{L}\p{N}\s\/.-]{0,19}$/u,
        // Colour: letters, e.g. Ivoire, gold, bleu nuit
        colour: /^[\p{L}][\p{L}\s,'’-]{1,39}$/u
    };

    function check(input) {
        var value = input.value.trim();
        var rule = input.dataset.rule;

        if (input.disabled) { return true; }          // hidden part of the form: not sent, not checked

        if (input.required && value === '') { return false; }
        if (value === '' && !input.required) { return true; }

        if (rule === 'password') {
            var min = parseInt(input.dataset.min || '10', 10);
            // at least min characters, at least one letter and one digit
            var strong = new RegExp('^(?=.*\\p{L})(?=.*\\d).{' + min + ',}$', 'u');
            return strong.test(input.value) && input.value.length <= 72;
        }
        if (rule === 'match') {
            var other = document.getElementById(input.dataset.match);
            return other !== null && input.value === other.value;
        }
        if (rule === 'money') {
            // A price in XOF
            var digits = value.replace(/[\s.]/g, '');
            var min = parseInt(input.dataset.min || '0', 10);
            return /^\d{1,9}$/.test(digits) && parseInt(digits, 10) >= min && parseInt(digits, 10) <= 100000000;
        }
        if (rule === 'length') {
            var n = value.length;
            return n >= parseInt(input.dataset.min || '0', 10) && n <= parseInt(input.dataset.max || '100000', 10);
        }
        if (patterns[rule]) {
            return patterns[rule].test(value);
        }
        return true;
    }

    function showError(input, ok) {
        var field = input.closest('.field') || input.parentNode;
        var box = field.querySelector('.field-error');
        if (ok) {
            field.classList.remove('invalid');
            if (box) { box.remove(); }
            return;
        }
        field.classList.add('invalid');
        if (!box) {
            box = document.createElement('div');
            box.className = 'field-error';
            field.appendChild(box);
        }
        box.textContent = input.dataset.error || 'Please check this field.';   // textContent = safe, no HTML
    }

    // Eye button: show / hide a password
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.togglePassword);
            if (!input) { return; }
            var show = input.type === 'password';          // hidden now → show it
            input.type = show ? 'text' : 'password';
            button.classList.toggle('is-visible', show);
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? button.dataset.labelHide : button.dataset.labelShow);
        });
    });

    // Live message: do the two passwords match? (while typing)
    document.querySelectorAll('[data-match-status]').forEach(function (box) {
        var confirm = document.getElementById(box.dataset.matchStatus);
        var original = document.getElementById(box.dataset.matchWith);
        if (!confirm || !original) { return; }

        function update() {
            if (confirm.value === '') {             // nothing typed yet: no message
                box.textContent = '';
                box.className = 'match-status';
                return;
            }
            var same = confirm.value === original.value;
            box.textContent = same ? box.dataset.ok : box.dataset.bad;
            box.className = 'match-status ' + (same ? 'is-ok' : 'is-bad');
        }
        confirm.addEventListener('input', update);
        original.addEventListener('input', update);
    });

    // Live checklist: each password rule turns green when it is respected
    document.querySelectorAll('[data-password-rules]').forEach(function (list) {
        var input = document.getElementById(list.dataset.passwordRules);
        if (!input) { return; }
        var tests = {
            length: function (v, li) { return v.length >= parseInt(li.dataset.min || '10', 10); },
            letter: function (v) { return /\p{L}/u.test(v); },
            number: function (v) { return /\d/.test(v); }
        };
        input.addEventListener('input', function () {
            list.querySelectorAll('[data-check]').forEach(function (li) {
                var test = tests[li.dataset.check];
                li.classList.toggle('is-ok', test ? test(input.value, li) : false);
            });
        });
    });

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        var inputs = form.querySelectorAll('[data-rule], [required]');

        // Check a field again when the user leaves it
        inputs.forEach(function (input) {
            input.addEventListener('blur', function () {
                if (input.value !== '') { showError(input, check(input)); }
            });
        });

        // Check everything when the form is sent
        form.addEventListener('submit', function (event) {
            var firstBad = null;
            inputs.forEach(function (input) {
                var ok = check(input);
                showError(input, ok);
                if (!ok && !firstBad) { firstBad = input; }
            });

            if (firstBad) {
                event.preventDefault();   // stop: the form is NOT sent
                firstBad.focus();
                return;
            }

            // Loading state on the button, so the user does not click twice
            var button = form.querySelector('button[type="submit"]');
            if (button && button.dataset.loading) {
                button.disabled = true;
                button.textContent = button.dataset.loading;
            }
        });
    });

    // "Are you sure?" before dangerous buttons (Delete)
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) { return; }          // a field is wrong: no question, the form is not sent anyway
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
})();
