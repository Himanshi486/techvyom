document.addEventListener('DOMContentLoaded', () => {
    const toggleButtons = document.querySelectorAll('.view-toggle-btn');
    const menus = document.querySelectorAll('.view-toggle-menu');

    const closeMenus = () => {
        menus.forEach(menu => menu.classList.remove('is-visible'));
        toggleButtons.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
    };

    toggleButtons.forEach(button => {
        const target = button.dataset.viewTarget || '';
        const menu = document.querySelector(`.view-toggle-menu[data-view-menu="${target}"]`);

        button.addEventListener('click', event => {
            event.stopPropagation();
            menus.forEach(otherMenu => {
                if (otherMenu !== menu) {
                    otherMenu.classList.remove('is-visible');
                }
            });
            const isOpen = menu.classList.toggle('is-visible');
            button.setAttribute('aria-expanded', String(isOpen));
        });
    });

    document.querySelectorAll('.view-option').forEach(option => {
        option.addEventListener('click', () => {
            const menu = option.closest('.view-toggle-menu');
            const target = menu.dataset.viewMenu || '';
            const view = option.dataset.view || 'table';

            menu.querySelectorAll('.view-option').forEach(opt => opt.classList.remove('active'));
            option.classList.add('active');
            menu.classList.remove('is-visible');

            const containers = document.querySelectorAll(`.table-responsive[data-view-target="${target}"], .alumni-card-list[data-view-target="${target}"]`);
            containers.forEach(container => {
                const matches = container.dataset.view === view;
                container.classList.toggle('is-visible', matches);
                if (matches) {
                    container.removeAttribute('hidden');
                } else {
                    container.setAttribute('hidden', 'hidden');
                }
            });
        });
    });

    // LinkedIn validation
    const isValidLinkedIn = url => {
        try {
            const parsed = new URL(url);
            return /^(www\.)?linkedin\.com$/i.test(parsed.hostname);
        } catch {
            return false;
        }
    };

    document.querySelectorAll('[data-linkedin]').forEach(link => {
        const url = (link.dataset.linkedin || '').trim();
        if (!url) {
            link.remove();
            return;
        }

        if (!isValidLinkedIn(url)) {
            link.addEventListener('click', event => {
                event.preventDefault();
                alert('The LinkedIn profile link appears to be invalid.');
            });
        } else {
            link.href = url;
        }
    });

    document.addEventListener('click', () => closeMenus());
});

