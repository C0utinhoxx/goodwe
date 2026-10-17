function setupManager() {
    const managerConfirmation = document.querySelector('[data-manager-confirmation]');
    if (!managerConfirmation) return;
    let pendingForm = null;
    let confirmedForm = null;
    let trigger = null;
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-manager-confirm]')) return;
        if (confirmedForm === form) { confirmedForm = null; return; }
        event.preventDefault();
        pendingForm = form;
        trigger = document.activeElement;
        managerConfirmation.querySelector('[data-manager-confirmation-message]').textContent = form.dataset.managerConfirm;
        managerConfirmation.showModal();
    });
    managerConfirmation.querySelectorAll('[data-manager-confirmation-cancel]').forEach(button => button.addEventListener('click', () => managerConfirmation.close()));
    managerConfirmation.querySelector('[data-manager-confirmation-accept]').addEventListener('click', () => {
        const form = pendingForm;
        managerConfirmation.close();
        if (form) { confirmedForm = form; form.requestSubmit(); }
    });
    managerConfirmation.addEventListener('close', () => { pendingForm = null; trigger?.focus(); });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupManager);
else setupManager();
