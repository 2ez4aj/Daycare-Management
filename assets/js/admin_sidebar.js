document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const openBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const navLinks = document.querySelectorAll('.sidebar .nav-link');

    if (!sidebar) {
        return;
    }

    const closeSidebar = () => {
        sidebar.classList.remove('show');
        if (overlay) {
            overlay.classList.remove('show');
        }
        document.body.classList.remove('sidebar-open');
    };

    const openSidebar = () => {
        sidebar.classList.add('show');
        if (overlay) {
            overlay.classList.add('show');
        }
        document.body.classList.add('sidebar-open');
    };

    openBtn?.addEventListener('click', () => {
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
});

