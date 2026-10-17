function setupNavigation() {
    const shell = document.querySelector('[data-portal-shell]');
    if (!shell) return;
    const sidebar = document.getElementById('portal-sidebar');
    const opener = shell.querySelector('[data-mobile-open]');
    const overlay = shell.querySelector('[data-mobile-close]');
    const main = shell.querySelector('.pt-main-shell');
    const chat = document.querySelector('[data-chat-widget]');
    const mobile = matchMedia('(max-width: 990px)');
    const initialOverflow = document.body.style.overflow;
    const collapseButton = shell.querySelector('[data-sidebar-toggle]');
    const storageKey = `powertag:sidebar:${shell.dataset.portalRole}`;
    const setCollapsed = (collapsed, remember = false) => {
        shell.classList.toggle('is-sidebar-collapsed', collapsed);
        const label = collapsed ? 'Expandir menu lateral' : 'Recolher menu lateral';
        collapseButton?.setAttribute('aria-expanded', String(!collapsed));
        collapseButton?.setAttribute('aria-label', label);
        collapseButton?.setAttribute('title', label);
        if (remember) { try { localStorage.setItem(storageKey, String(collapsed)); } catch {} }
    };
    try { setCollapsed(localStorage.getItem(storageKey) === 'true'); } catch { setCollapsed(false); }
    collapseButton?.addEventListener('click', () => setCollapsed(!shell.classList.contains('is-sidebar-collapsed'), true));
    shell.querySelectorAll('[data-submenu-toggle]').forEach(button => button.addEventListener('click', () => {
        const target = document.getElementById(button.getAttribute('aria-controls'));
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded));
        target.hidden = !expanded;
    }));
    const updateSection = () => {
        shell.querySelectorAll('[data-section-link]').forEach(link => {
            const url = new URL(link.href);
            const active = url.pathname === location.pathname && url.hash === location.hash && !!url.hash;
            if (active) {
                link.setAttribute('aria-current', 'location');
                const group = link.closest('[data-nav-group]');
                group.querySelector('[data-nav-submenu]').hidden = false;
                group.querySelector('[data-submenu-toggle]').setAttribute('aria-expanded', 'true');
            } else link.removeAttribute('aria-current');
        });
    };
    window.addEventListener('hashchange', updateSection);
    updateSection();
    let open = false;
    const close = (restoreFocus = true) => {
        open = false;
        shell.classList.remove('is-menu-open');
        opener?.setAttribute('aria-expanded', 'false');
        overlay.hidden = true;
        sidebar.inert = mobile.matches;
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        main.inert = false;
        if (chat) chat.inert = false;
        document.body.style.overflow = initialOverflow;
        if (restoreFocus && mobile.matches) opener?.focus();
    };
    const show = () => {
        if (!mobile.matches) return;
        document.dispatchEvent(new CustomEvent('portal:close-popovers'));
        open = true;
        shell.classList.add('is-menu-open');
        opener?.setAttribute('aria-expanded', 'true');
        overlay.hidden = false;
        sidebar.inert = false;
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        main.inert = true;
        if (chat) chat.inert = true;
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => {
            if (open) sidebar.querySelector('[data-mobile-close-button]')?.focus();
        });
    };
    document.body.classList.add('navigation-ready');
    opener?.addEventListener('click', show);
    overlay.addEventListener('click', () => close());
    sidebar.querySelector('[data-mobile-close-button]')?.addEventListener('click', () => close());
    mobile.addEventListener('change', () => close(sidebar.contains(document.activeElement)));
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key !== 'Tab') return;
        const targets = [...sidebar.querySelectorAll('a[href], button:not(:disabled), [tabindex="0"]')].filter(element => element.getClientRects().length && !element.closest('[inert]'));
        const first = targets[0];
        const last = targets[targets.length - 1];
        if (!sidebar.contains(document.activeElement)) { event.preventDefault(); (event.shiftKey ? last : first)?.focus(); }
        else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    document.addEventListener('portal:close-navigation', () => close(false));
    sidebar.querySelectorAll('[data-section-link]').forEach(link => link.addEventListener('click', event => {
        const url = new URL(link.href);
        if (url.pathname !== location.pathname || url.search !== location.search) return;
        const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
        if (!target) return;
        event.preventDefault();
        close(false);
        history.pushState(null, '', url.hash);
        updateSection();
        target.setAttribute('tabindex', '-1');
        target.focus({preventScroll: true});
        target.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start'});
    }));
    setupAccountMenu();
    close(false);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupNavigation);
else setupNavigation();

function setupAccountMenu() {
    const toggle = document.querySelector('[data-account-toggle]');
    const menu = document.getElementById('account-menu');
    if (!toggle || !menu) return;
    const close = (restore = false) => {
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (restore) toggle.focus();
    };
    const show = () => {
        document.dispatchEvent(new CustomEvent('portal:close-popovers', {detail: {source: 'account'}}));
        menu.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
    };
    toggle.addEventListener('click', event => { event.stopPropagation(); if (menu.hidden) show(); else close(); });
    document.addEventListener('click', event => { if (!menu.parentElement.contains(event.target)) close(); });
    document.addEventListener('portal:close-popovers', event => { if (event.detail?.source !== 'account') close(); });
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => close()));
    menu.parentElement.addEventListener('focusout', event => { if (!menu.parentElement.contains(event.relatedTarget)) close(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !menu.hidden) close(true); });
    toggle.addEventListener('keydown', event => {
        if (event.key !== 'ArrowDown') return;
        event.preventDefault(); show(); menu.querySelector('a, button')?.focus();
    });
    menu.addEventListener('keydown', event => {
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const targets = [...menu.querySelectorAll('a, button')];
        const index = targets.indexOf(document.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? targets.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + targets.length) % targets.length;
        targets[next]?.focus();
    });
}
