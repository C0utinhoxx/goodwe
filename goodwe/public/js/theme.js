(function() {
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
        function getTheme() {
            return localStorage.getItem('theme') || 'dark';
        }
        function setTheme(theme) {
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        toggle.addEventListener('click', function() {
            setTheme(getTheme() === 'dark' ? 'light' : 'dark');
        });
        setTheme(getTheme());
    }

    var langBtn = document.getElementById('lang-toggle');
    var langMenu = document.getElementById('lang-menu');
    if (langBtn && langMenu) {
        langBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            langMenu.classList.toggle('hidden');
        });
        document.addEventListener('click', function() {
            langMenu.classList.add('hidden');
        });
        langMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
})();
