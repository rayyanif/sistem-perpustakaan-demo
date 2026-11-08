const menu = document.querySelector('.menu-toggle');
const nav = document.querySelector('.main-navigation');
if (menu && nav) {
    const setMenu = open => {
        nav.hidden = !open;
        menu.setAttribute('aria-expanded', String(open));
    };
    setMenu(false);
    menu.hidden = false;
    menu.addEventListener('click', () => setMenu(nav.hidden));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !nav.hidden) {
            setMenu(false);
            menu.focus();
        }
    });
}
const tableHint = document.querySelector('.table-hint');
const tableRegion = tableHint?.nextElementSibling;
const scrollTable = tableRegion?.querySelector('table');
if (scrollTable && 'ResizeObserver' in window) {
    const syncTableHint = () => {
        tableHint.hidden = tableRegion.scrollWidth <= tableRegion.clientWidth;
    };
    syncTableHint();
    const tableObserver = new ResizeObserver(syncTableHint);
    tableObserver.observe(tableRegion);
    tableObserver.observe(scrollTable);
}
const dialog = document.querySelector('.confirm-dialog');
let pendingForm;
let trigger;
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirm && form.dataset.confirmed !== 'yes') {
            if (dialog && typeof dialog.showModal === 'function') {
                event.preventDefault();
                pendingForm = form;
                trigger = event.submitter;
                dialog.querySelector('#confirm-title').textContent = form.dataset.confirm;
                dialog.showModal();
                dialog.querySelector('[data-cancel]').focus();
                return;
            }
            if (!window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        }
        form.setAttribute('aria-busy', 'true');
        const button = event.submitter;
        if (button) {
            button.dataset.original = button.textContent;
            button.textContent = form.method === 'get' ? 'Mencari…' : 'Memproses…';
            button.disabled = true;
        }
        const status = document.querySelector('.submit-status');
        status.hidden = false;
        status.textContent = 'Permintaan sedang diproses.';
    });
});
if (dialog) {
    dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-confirm]').addEventListener('click', () => {
        const form = pendingForm;
        dialog.close();
        form.dataset.confirmed = 'yes';
        form.requestSubmit(trigger);
    });
    dialog.addEventListener('close', () => trigger?.focus());
}
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-original]').forEach(button => {
        button.textContent = button.dataset.original;
        button.disabled = false;
    });
    document.querySelectorAll('form').forEach(form => {
        form.removeAttribute('aria-busy');
        delete form.dataset.confirmed;
    });
    document.querySelector('.submit-status').hidden = true;
});
