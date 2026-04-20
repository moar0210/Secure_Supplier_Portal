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

(function () {
    const STORAGE_KEY = 'portal:sidebarCollapsed';

    function applyState() {
        const shell = document.getElementById('appShell');
        if (!shell) return;
        try {
            if (localStorage.getItem(STORAGE_KEY) === '1') {
                shell.classList.add('is-sidebar-collapsed');
            }
        } catch (_) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyState);
    } else {
        applyState();
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('#sidebarToggle, [data-sidebar-toggle]');
        if (!trigger) return;
        event.preventDefault();
        const shell = document.getElementById('appShell');
        if (!shell) return;
        const collapsed = shell.classList.toggle('is-sidebar-collapsed');
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
        } catch (_) {}
        trigger.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
})();

(function () {
    function syncHostRow(panel) {
        const hostRow = panel.closest('tr');
        if (hostRow) {
            hostRow.classList.toggle('collapsible-host-open', panel.classList.contains('is-open'));
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-collapsible-target]');
        if (trigger) {
            const targetId = trigger.getAttribute('data-collapsible-target');
            const panel = document.getElementById(targetId);
            if (panel) {
                panel.classList.toggle('is-open');
                const expanded = panel.classList.contains('is-open');
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                syncHostRow(panel);
                if (expanded) {
                    const firstField = panel.querySelector('input, select, textarea');
                    if (firstField) {
                        firstField.focus();
                    }
                }
            }
            return;
        }

        const closer = event.target.closest('[data-collapsible-close]');
        if (closer) {
            const targetId = closer.getAttribute('data-collapsible-close');
            const panel = document.getElementById(targetId);
            if (panel) {
                panel.classList.remove('is-open');
                syncHostRow(panel);
            }
        }
    });
})();

(function () {
    document.addEventListener('click', function (event) {
        const header = event.target.closest('[data-row-toggle]');
        if (!header) {
            return;
        }
        if (event.target.closest('a, button, input, select, textarea, label')) {
            return;
        }
        const card = header.closest('.row-card');
        if (card) {
            card.classList.toggle('is-open');
        }
    });
})();
