'use strict';

document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('click', function (event) {
    const target = event.target;
    if (!(target instanceof Element)) {
        return;
    }

    const button = target.closest('button[data-confirm], input[type="submit"][data-confirm]');
    if (!(button instanceof HTMLElement)) {
        return;
    }

    const message = button.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
