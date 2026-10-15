function setupManager() {
    const managerConfirmation = document.querySelector('[data-manager-confirmation]');
    if (!managerConfirmation) return;
    let pendingForm = null;
    let confirmedForm = null;
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-manager-confirm]')) return;
        if (confirmedForm === form) { confirmedForm = null; return; }
        event.preventDefault();
        pendingForm = form;
        managerConfirmation.querySelector('[data-manager-confirmation-message]').textContent = form.dataset.managerConfirm;
        managerConfirmation.showModal();
    });
    managerConfirmation.querySelector('[data-manager-confirmation-cancel]').addEventListener('click', () => managerConfirmation.close());
    managerConfirmation.querySelector('[data-manager-confirmation-accept]').addEventListener('click', () => {
        const form = pendingForm;
        managerConfirmation.close();
        if (form) { confirmedForm = form; form.requestSubmit(); }
    });
    managerConfirmation.addEventListener('close', () => { pendingForm = null; });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupManager);
else setupManager();
