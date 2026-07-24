// Progressive enhancement for the survey form: highlight selected choices.
document.addEventListener('change', function (e) {
    var input = e.target;
    if (!(input instanceof HTMLInputElement)) return;

    // radio / checkbox option cards (.opt)
    var opt = input.closest('.opt');
    if (opt) {
        if (input.type === 'radio') {
            var name = input.name;
            document.querySelectorAll('input[name="' + CSS.escape(name) + '"]').forEach(function (i) {
                var o = i.closest('.opt');
                if (o) o.classList.toggle('checked', i.checked);
            });
        } else {
            opt.classList.toggle('checked', input.checked);
        }
    }

    // scale / boolean segmented control
    var scale = input.closest('.scale');
    if (scale && input.type === 'radio') {
        scale.querySelectorAll('label').forEach(function (l) {
            var i = l.querySelector('input');
            l.classList.toggle('checked', !!(i && i.checked));
        });
    }
});


document.addEventListener('change', (e) => {
    const el = e.target.closest('[data-other-toggle]');
    if (!el) return;

    if (el.type === 'radio') {
        // hide every "other" input tied to this radio group, then show only the active one
        document.querySelectorAll(`input[type="radio"][name="${el.name}"][data-other-toggle]`)
            .forEach(radio => {
                const target = document.getElementById(radio.dataset.otherToggle);
                if (target) target.style.display = radio.checked ? '' : 'none';
            });
    } else {
        const target = document.getElementById(el.dataset.otherToggle);
        if (target) target.style.display = el.checked ? '' : 'none';
    }
});