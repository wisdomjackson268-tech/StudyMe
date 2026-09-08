/**
 * StudyMe AI Platform — Core Application JavaScript
 * Handles Navbar glassmorphism, responsive hamburger animations, mobile drawers, backdrops, and navigation interactions.
 */

document.addEventListener("DOMContentLoaded", () => {
    // ── 1. Navbar Glassmorphism Scroll Handler ─────────────────
    const navbar = document.querySelector(".custom-navbar");
    function handleScroll() {
        if (!navbar) return;
        if (window.scrollY > 30) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    }
    window.addEventListener("scroll", handleScroll, { passive: true });
    handleScroll();

    // ── 2. Public Mobile Navbar Hamburger & Backdrop Handler ────
    const navbarMenu = document.getElementById("navbarMenu");
    const navbarToggler = document.getElementById("navbarTogglerBtn");
    const navbarBackdrop = document.getElementById("navbarMobileBackdrop");

    if (navbarMenu && navbarToggler) {
        navbarMenu.addEventListener("show.bs.collapse", () => {
            navbarToggler.classList.add("is-active");
            if (navbarBackdrop) navbarBackdrop.classList.add("show");
            document.body.classList.add("navbar-open");
        });

        navbarMenu.addEventListener("hide.bs.collapse", () => {
            navbarToggler.classList.remove("is-active");
            if (navbarBackdrop) navbarBackdrop.classList.remove("show");
            document.body.classList.remove("navbar-open");
        });

        if (navbarBackdrop) {
            navbarBackdrop.addEventListener("click", () => {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarMenu);
                if (bsCollapse) bsCollapse.hide();
            });
        }

        // Auto-close mobile menu when clicking any link inside
        const mobileLinks = navbarMenu.querySelectorAll(".nav-link, .btn-login, .btn-start, .nav-cta-btn");
        mobileLinks.forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 992) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarMenu);
                    if (bsCollapse) bsCollapse.hide();
                }
            });
        });
    }

    // ── 3. Dashboard Mobile Sidebar & Backdrop Controller ───────
    const sidebarToggle = document.getElementById("mobileSidebarToggle");
    const sidebar = document.getElementById("dashboardSidebar");
    const sidebarClose = document.getElementById("sidebarCloseBtn");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");

    function openSidebar() {
        if (sidebar) sidebar.classList.add("show");
        if (sidebarBackdrop) sidebarBackdrop.classList.add("show");
        document.body.classList.add("sidebar-open");
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove("show");
        if (sidebarBackdrop) sidebarBackdrop.classList.remove("show");
        document.body.classList.remove("sidebar-open");
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", (e) => {
            e.preventDefault();
            if (sidebar && sidebar.classList.contains("show")) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener("click", (e) => {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener("click", closeSidebar);
    }

    // Auto-close sidebar on Escape key
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closeSidebar();
            if (navbarMenu && navbarMenu.classList.contains("show")) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarMenu);
                if (bsCollapse) bsCollapse.hide();
            }
        }
    });

    // Handle screen resize
    window.addEventListener("resize", () => {
        if (window.innerWidth >= 992) {
            closeSidebar();
            if (navbarBackdrop) navbarBackdrop.classList.remove("show");
            document.body.classList.remove("navbar-open");
        }
    }, { passive: true });

    // ── 4. Smooth Scroll for Anchor Links ───────────────────────
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener("click", function(e) {
            const targetId = this.getAttribute("href");
            if (targetId === "#" || !targetId || targetId.length <= 1) return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const offset = 90;
                const bodyRect = document.body.getBoundingClientRect().top;
                const elementRect = targetElement.getBoundingClientRect().top;
                const elementPosition = elementRect - bodyRect;
                const offsetPosition = elementPosition - offset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: "smooth"
                });
            }
        });
    });

    // ── 5. Dropdown Smooth Transition Enhancer ──────────────────
    const dropdowns = document.querySelectorAll(".dropdown");
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener("show.bs.dropdown", function() {
            const menu = this.querySelector(".dropdown-menu");
            if (menu) {
                menu.classList.add("animate-slide-up");
            }
        });
    });
});