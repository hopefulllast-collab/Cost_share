/**
 * Dashboard & Site Responsive Layout Script
 * Detects screen width and dynamically enables layouts for ANY device size.
 * Uses CSS custom properties (--vars) for fluid, proportional sizing
 * so the layout always looks right even on odd screen sizes.
 *
 * Breakpoint Classes Applied to <body>:
 *   mobile-sm-layout   : 0–480px     (small phones)
 *   mobile-layout      : 481–767px   (large phones / phablets)
 *   tablet-layout      : 768–1024px  (tablets / small laptops)
 *   desktop-layout     : 1025–1439px (desktops / laptops)
 *   pc-layout          : 1440px+     (large PC monitors / wide screens)
 *
 * Additionally, CSS custom properties are injected on <html> so
 * individual elements can scale fluidly:
 *   --sidebar-width, --content-padding, --header-font, --nav-font,
 *   --card-padding, --nav-icon-size, --bottom-nav-height
 */

document.addEventListener('DOMContentLoaded', () => {

    const ALL_LAYOUTS = [
        'mobile-sm-layout',
        'mobile-layout',
        'tablet-layout',
        'desktop-layout',
        'pc-layout'
    ];

    function applyResponsiveLayout() {
        const w = window.innerWidth;
        const body = document.body;
        const root = document.documentElement;

        // --- 1. Determine category ---
        let activeClass;
        if (w <= 480) {
            activeClass = 'mobile-sm-layout';
        } else if (w <= 767) {
            activeClass = 'mobile-layout';
        } else if (w <= 1024) {
            activeClass = 'tablet-layout';
        } else if (w <= 1439) {
            activeClass = 'desktop-layout';
        } else {
            activeClass = 'pc-layout';
        }

        // Only modify DOM when the category actually changes
        const current = ALL_LAYOUTS.find(c => body.classList.contains(c));
        if (current !== activeClass) {
            ALL_LAYOUTS.forEach(c => body.classList.remove(c));
            body.classList.add(activeClass);

            // Handle sidebar DOM changes
            if (activeClass === 'mobile-sm-layout' || activeClass === 'mobile-layout') {
                flattenDropdownsForMobile();
            } else {
                restoreDropdownsIfNeeded();
            }
        }

        // --- 2. Set fluid CSS custom properties ---
        // These scale proportionally inside each breakpoint range
        // so there are no jarring "jumps" between sizes.

        if (w <= 480) {
            // Small mobiles: everything compact
            root.style.setProperty('--sidebar-width', '100%');
            root.style.setProperty('--content-padding', Math.max(8, Math.round(w * 0.03)) + 'px');
            root.style.setProperty('--header-font', Math.max(0.85, w / 480 * 1.1).toFixed(2) + 'rem');
            root.style.setProperty('--nav-font', '0.65rem');
            root.style.setProperty('--nav-icon-size', '1rem');
            root.style.setProperty('--card-padding', Math.max(8, Math.round(w * 0.025)) + 'px');
            root.style.setProperty('--bottom-nav-height', '65px');

        } else if (w <= 767) {
            // Large phones / phablets
            root.style.setProperty('--sidebar-width', '100%');
            root.style.setProperty('--content-padding', '15px');
            root.style.setProperty('--header-font', '1.1rem');
            root.style.setProperty('--nav-font', '0.75rem');
            root.style.setProperty('--nav-icon-size', '1.2rem');
            root.style.setProperty('--card-padding', '15px');
            root.style.setProperty('--bottom-nav-height', '70px');

        } else if (w <= 1024) {
            // Tablets: sidebar scales from 170px at 768 to 240px at 1024
            const sidebarW = Math.round(170 + (w - 768) / (1024 - 768) * 70);
            const fontSize = (0.75 + (w - 768) / (1024 - 768) * 0.15).toFixed(2);
            root.style.setProperty('--sidebar-width', sidebarW + 'px');
            root.style.setProperty('--content-padding', '20px');
            root.style.setProperty('--header-font', '1.2rem');
            root.style.setProperty('--nav-font', fontSize + 'rem');
            root.style.setProperty('--nav-icon-size', '1rem');
            root.style.setProperty('--card-padding', '20px');
            root.style.setProperty('--bottom-nav-height', '0');

        } else if (w <= 1439) {
            // Desktops / Laptops
            root.style.setProperty('--sidebar-width', '260px');
            root.style.setProperty('--content-padding', '30px');
            root.style.setProperty('--header-font', '1.3rem');
            root.style.setProperty('--nav-font', '0.95rem');
            root.style.setProperty('--nav-icon-size', '1.1rem');
            root.style.setProperty('--card-padding', '25px');
            root.style.setProperty('--bottom-nav-height', '0');

        } else {
            // Large PC Monitors (1440px+)
            const sidebarW = Math.min(320, Math.round(260 + (w - 1440) / (1920 - 1440) * 60));
            root.style.setProperty('--sidebar-width', sidebarW + 'px');
            root.style.setProperty('--content-padding', '40px');
            root.style.setProperty('--header-font', '1.5rem');
            root.style.setProperty('--nav-font', '1rem');
            root.style.setProperty('--nav-icon-size', '1.2rem');
            root.style.setProperty('--card-padding', '30px');
            root.style.setProperty('--bottom-nav-height', '0');
        }
    }

    // ---- Mobile sidebar helpers ----

    function flattenDropdownsForMobile() {
        const dropdowns = document.querySelectorAll('.sidebar-submenu');
        dropdowns.forEach(sub => {
            if (!sub.classList.contains('mobile-flattened')) {
                sub.style.display = 'flex';
                sub.style.flexDirection = 'row';
                sub.classList.add('mobile-flattened');
            }
        });

        const dropdownLinks = document.querySelectorAll('.sidebar-dropdown > a');
        dropdownLinks.forEach(link => {
            link.style.display = 'none';
        });
    }

    function restoreDropdownsIfNeeded() {
        const dropdowns = document.querySelectorAll('.sidebar-submenu');
        dropdowns.forEach(sub => {
            if (sub.classList.contains('mobile-flattened')) {
                sub.style.display = '';
                sub.style.flexDirection = '';
                sub.classList.remove('mobile-flattened');
            }
        });

        const dropdownLinks = document.querySelectorAll('.sidebar-dropdown > a');
        dropdownLinks.forEach(link => {
            link.style.display = '';
        });
    }

    // ---- Initialization ----
    applyResponsiveLayout();

    // Debounced resize for performance
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyResponsiveLayout, 100);
    });
});
