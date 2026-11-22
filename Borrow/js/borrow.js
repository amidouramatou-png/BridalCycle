(function () {
    'use strict';
    var form = document.querySelector('[data-borrow]');
    if (!form) { return; }

    var start = form.querySelector('#start_date');
    var end = form.querySelector('#end_date');
    var event_ = form.querySelector('#event_date');
    var box = form.querySelector('[data-borrow-total]');
    var price = parseInt(form.dataset.price, 10);
    var deposit = parseInt(form.dataset.deposit, 10);
    var min = parseInt(form.dataset.min, 10);
    var max = parseInt(form.dataset.max, 10);

    function money(n) {   // 150000 → "150 000 XOF"
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' XOF';
    }
    function out(name) { return box.querySelector('[data-out="' + name + '"]'); }

    // Returns true when the dates are correct
    function update() {
        if (start.value) {
            end.min = start.value;            // the calendar greys the days before the pick-up day
            event_.min = start.value;
        }
        if (end.value) { event_.max = end.value; }
        ['error', 'before', 'event'].forEach(function (n) { out(n).hidden = true; });
        [end, event_].forEach(function (el) { (el.closest('.field') || el.parentNode).classList.remove('invalid'); });

        if (!start.value || !end.value) {
            ['days', 'rent', 'total'].forEach(function (n) { out(n).textContent = '—'; });
            return false;
        }
        // Count the days, pick-up day and return day included
        var days = Math.round((Date.parse(end.value) - Date.parse(start.value)) / 86400000) + 1;
        var ok = days >= min && days <= max;
        if (days < 1) {
            out('before').hidden = false;     // return day BEFORE pick-up day (e.g. typed by hand)
            (end.closest('.field') || end.parentNode).classList.add('invalid');
        } else if (!ok) {
            out('error').hidden = false;
            (end.closest('.field') || end.parentNode).classList.add('invalid');
        }
        // The wedding day (optional) must be between pick-up and return
        if (event_.value && (event_.value < start.value || event_.value > end.value)) {
            out('event').hidden = false;
            (event_.closest('.field') || event_.parentNode).classList.add('invalid');
            ok = false;
        }
        out('days').textContent = days >= min && days <= max ? days : '—';
        out('rent').textContent = days >= min && days <= max ? money(days * price) : '—';
        out('total').textContent = days >= min && days <= max ? money(days * price + deposit) : '—';
        return ok;
    }

    [start, end, event_].forEach(function (input) {
        input.addEventListener('change', update);
        input.addEventListener('input', update);
    });
    // Wrong dates: the form is NOT sent (the server would refuse them too)
    form.addEventListener('submit', function (event) {
        if (start.value && end.value && !update()) {
            event.preventDefault();
            end.focus();
        }
    });
    update();
})();
