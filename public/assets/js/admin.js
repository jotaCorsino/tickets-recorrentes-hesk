(() => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-menu-backdrop]');

    if (!toggle || !sidebar || !backdrop) return;

    const closeMenu = () => {
        document.body.classList.remove('menu-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu');
        backdrop.hidden = true;
    };

    const openMenu = () => {
        document.body.classList.add('menu-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Fechar menu');
        backdrop.hidden = false;
        sidebar.querySelector('a')?.focus();
    };

    toggle.addEventListener('click', () => {
        if (document.body.classList.contains('menu-open')) closeMenu();
        else openMenu();
    });

    backdrop.addEventListener('click', () => {
        closeMenu();
        toggle.focus();
    });

    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a')) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
            closeMenu();
            toggle.focus();
        }
    });

    window.matchMedia('(min-width: 761px)').addEventListener('change', (event) => {
        if (event.matches) closeMenu();
    });
})();
