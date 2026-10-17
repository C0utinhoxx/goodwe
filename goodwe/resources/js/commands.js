function setupCommands() {
    const dialog = document.querySelector('[data-commands-dialog]');
    if (!dialog) return;
    const input = dialog.querySelector('[data-commands-search]');
    const results = dialog.querySelector('[data-commands-results]');
    const status = dialog.querySelector('[data-commands-status]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
    let trigger = null;
    let restoreFocus = true;
    const entries = [...document.querySelectorAll('.pt-nav a[data-command-label]')].map(link => ({
        label: link.dataset.commandLabel, category: link.dataset.commandCategory, keywords: link.dataset.commandKeywords || '', href: link.href,
    }));
    if (document.querySelector('[data-chat-open]')) entries.push({label: 'Abrir assistente IA', category: 'Ajuda', action: '[data-chat-open]'});
    entries.push({label: 'Alternar tema claro ou escuro', category: 'Preferências', action: '#theme-toggle'});
    const render = () => {
        const words = normalize(input.value.trim()).split(/\s+/).filter(Boolean);
        const matches = entries.filter(entry => words.every(word => normalize(`${entry.label} ${entry.category} ${entry.keywords || ''}`).includes(word)));
        const score = entry => {
            const label = normalize(entry.label);
            const query = normalize(input.value.trim());
            if (!query || label === query) return 0;
            if (label.startsWith(query)) return 1;
            return words.every(word => label.includes(word)) ? 2 : 3;
        };
        matches.sort((left, right) => score(left) - score(right));
        results.replaceChildren();
        for (const entry of matches) {
            const item = document.createElement(entry.href ? 'a' : 'button');
            if (entry.href) item.href = entry.href;
            else item.type = 'button';
            item.className = 'gw-command-result';
            const text = document.createElement('span');
            const title = document.createElement('strong');
            title.textContent = entry.label;
            const category = document.createElement('small');
            category.textContent = entry.category;
            text.append(title, category);
            const arrow = document.createElement('span');
            arrow.textContent = '↗';
            arrow.setAttribute('aria-hidden', 'true');
            item.append(text, arrow);
            item.addEventListener('click', () => {
                if (entry.action) {
                    restoreFocus = false;
                    dialog.close();
                    setTimeout(() => document.querySelector(entry.action)?.click(), 0);
                } else dialog.close();
            });
            results.append(item);
        }
        status.textContent = matches.length ? `${matches.length} ${matches.length === 1 ? 'resultado' : 'resultados'}` : 'Nenhuma página ou ação encontrada. Tente outra palavra.';
    };
    const show = opener => {
        if (document.querySelector('dialog[open]')) return;
        trigger = opener;
        restoreFocus = true;
        document.dispatchEvent(new CustomEvent('portal:close-navigation'));
        document.dispatchEvent(new CustomEvent('portal:close-popovers'));
        input.value = '';
        render();
        dialog.showModal();
        input.focus();
    };
    document.querySelectorAll('[data-commands-open]').forEach(button => button.addEventListener('click', () => show(button)));
    dialog.querySelector('[data-commands-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (!restoreFocus) return;
        const target = trigger?.isConnected && !trigger.closest('[inert]') && trigger.getClientRects().length ? trigger : document.querySelector('[data-commands-open]');
        target?.focus({preventScroll: true});
    });
    dialog.addEventListener('click', event => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    input.addEventListener('input', render);
    dialog.addEventListener('keydown', event => {
        const targets = [...results.querySelectorAll('a, button')];
        if (event.target === input && event.key === 'Enter') { event.preventDefault(); targets[0]?.click(); }
        if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
        event.preventDefault();
        const index = targets.indexOf(document.activeElement);
        if (event.key === 'ArrowDown') targets[Math.min(index + 1, targets.length - 1)]?.focus();
        else if (index <= 0) input.focus();
        else targets[index - 1]?.focus();
    });
    document.addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if (dialog.open) dialog.close();
            else show(document.activeElement === document.body ? document.querySelector('[data-commands-open]') : document.activeElement);
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupCommands);
else setupCommands();
