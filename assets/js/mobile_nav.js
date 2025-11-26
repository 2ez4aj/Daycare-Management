// assets/js/mobile_nav.js
function initMobileNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navItems = document.querySelectorAll('.mobile-nav-item');

    // Set active link
    navItems.forEach(item => {
        const link = item.querySelector('a');
        if (link && link.getAttribute('href') === currentPage) {
            item.classList.add('active');
            link.classList.add('active');
        }
    });

    // Let Bootstrap handle dropdown toggling. Close any open dropdown when another opens.
    const dropdownToggles = document.querySelectorAll('.mobile-nav-item.dropdown [data-bs-toggle="dropdown"]');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('show.bs.dropdown', () => {
            // Close other dropdowns
            document.querySelectorAll('.mobile-nav-item.dropdown .dropdown-menu.show').forEach(menu => {
                const parent = menu.closest('.mobile-nav-item.dropdown');
                const parentToggle = parent.querySelector('[data-bs-toggle="dropdown"]');
                if (parentToggle !== toggle) {
                    bootstrap.Dropdown.getOrCreateInstance(parentToggle).hide();
                }
            });
        });
    });

    // Close dropdown when clicking outside (fallback for some devices)
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.mobile-nav-item.dropdown')) {
            document.querySelectorAll('.mobile-nav-item.dropdown [data-bs-toggle="dropdown"]').forEach(tgl => {
                const inst = bootstrap.Dropdown.getInstance(tgl);
                if (inst) inst.hide();
            });
        }
    });

    // Adjust body padding for nav height
    function adjustBodyPadding() {
        const nav = document.querySelector('.mobile-nav');
        if (nav && window.innerWidth < 992) {
            document.body.style.paddingBottom = nav.offsetHeight + 'px';
        } else {
            document.body.style.paddingBottom = '';
        }
    }
    adjustBodyPadding();
    window.addEventListener('resize', adjustBodyPadding);
}

// Custom dropdown toggle for More button
function initDropdownToggles(){
    document.querySelectorAll('.mobile-nav-item.dropdown > .nav-link').forEach(toggle=>{
        toggle.addEventListener('click',function(e){
            e.preventDefault();
            const menu=this.nextElementSibling;
            const isOpen=menu.classList.contains('show');
            document.querySelectorAll('.mobile-nav-item.dropdown .dropdown-menu.show').forEach(m=>m.classList.remove('show'));
            if(!isOpen){menu.classList.add('show');}
        });
    });

    document.querySelectorAll('.mobile-nav-item.dropdown .dropdown-item').forEach(item=>{
        item.addEventListener('click',()=>{
            document.querySelectorAll('.mobile-nav-item.dropdown .dropdown-menu.show').forEach(m=>m.classList.remove('show'));
        });
    });

    // close on esc
    document.addEventListener('keydown',e=>{
        if(e.key==='Escape'){
            document.querySelectorAll('.mobile-nav-item.dropdown .dropdown-menu.show').forEach(m=>m.classList.remove('show'));
        }
    });
}

// Initialize after DOM & Bootstrap ready
function _initWhenReady() {
    if (window.bootstrap) {
        initMobileNav();
        initDropdownToggles();
    } else {
        setTimeout(_initWhenReady, 50);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initWhenReady);
} else {
    _initWhenReady();
}
