(function () {
    var toggle = document.getElementById('theme-toggle');
    var preference = window.matchMedia('(prefers-color-scheme: dark)');
    function applyTheme(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        if (toggle) {
            var label = theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro';
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);
        }
        document.dispatchEvent(new CustomEvent('portal:themechange', {detail: {theme: theme}}));
    }
    function chosenTheme() {
        return localStorage.getItem('theme') || (preference.matches ? 'dark' : 'light');
    }
    if (toggle) {
        applyTheme(chosenTheme());
        toggle.addEventListener('click', function () {
            var theme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            applyTheme(theme);
        });
        preference.addEventListener('change', function () {
            if (!localStorage.getItem('theme')) applyTheme(chosenTheme());
        });
    }
    var langBtn = document.getElementById('lang-toggle');
    var langMenu = document.getElementById('lang-menu');
    if (langBtn && langMenu) {
        function closeLanguages(restoreFocus) {
            langMenu.classList.add('hidden');
            langBtn.setAttribute('aria-expanded', 'false');
            if (restoreFocus) langBtn.focus();
        }
        langBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            document.dispatchEvent(new CustomEvent('portal:close-popovers', {detail: {source: 'language'}}));
            var expanded = langBtn.getAttribute('aria-expanded') !== 'true';
            langMenu.classList.toggle('hidden', !expanded);
            langBtn.setAttribute('aria-expanded', String(expanded));
        });
        document.addEventListener('click', function () { closeLanguages(false); });
        document.addEventListener('portal:close-popovers', function (event) { if (!event.detail || event.detail.source !== 'language') closeLanguages(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && langBtn.getAttribute('aria-expanded') === 'true') closeLanguages(true);
        });
        langMenu.addEventListener('click', function (event) { event.stopPropagation(); });
        langBtn.addEventListener('keydown', function (event) {
            if (event.key !== 'ArrowDown') return;
            event.preventDefault();
            document.dispatchEvent(new CustomEvent('portal:close-popovers', {detail: {source: 'language'}}));
            langMenu.classList.remove('hidden');
            langBtn.setAttribute('aria-expanded', 'true');
            langMenu.querySelector('a').focus();
        });
        langMenu.addEventListener('keydown', function (event) {
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            var links = Array.from(langMenu.querySelectorAll('a'));
            var index = links.indexOf(document.activeElement);
            var next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links[next].focus();
        });
        langMenu.parentElement.addEventListener('focusout', function (event) {
            if (!langMenu.parentElement.contains(event.relatedTarget)) closeLanguages(false);
        });
    }
})();
