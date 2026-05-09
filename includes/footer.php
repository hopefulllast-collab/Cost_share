<footer class="admin-footer">
    <p data-en="&copy; 2026 Debre Markos University. All Rights Reserved."
        data-am="&copy; 2026 ደብረ ማርቆስ ዩኒቨርሲቲ. ሁሉም መብቶች የተጠበቁ ናቸው።">&copy; 2026 Debre Markos University. All Rights
        Reserved.</p>
</footer>

<!-- Global Auto-Refresh & Alert Management -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var alertElements = document.querySelectorAll('.success-msg, .error-msg, .warning-msg, .info-msg');
        var activeAlerts = [];
        alertElements.forEach(function (el) {
            if (el.innerText.trim() !== '' && window.getComputedStyle(el).display !== 'none') {
                activeAlerts.push(el);
            }
        });

        if (activeAlerts.length > 0) {
            // Fade out messages after 5 seconds
            activeAlerts.forEach(function (el) {
                el.style.transition = 'opacity 0.5s ease';
                setTimeout(function () {
                    el.style.opacity = '0';
                    setTimeout(function () { el.style.display = 'none'; }, 500);
                }, 4500);
            });
        }
    });

    // Intercept F5 to do a clean GET reload (avoiding POST form resubmission prompts)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F5' || (e.ctrlKey && (e.key === 'r' || e.key === 'R'))) {
            var alertElements = document.querySelectorAll('.success-msg, .error-msg, .warning-msg, .info-msg');
            var activeAlerts = [];
            alertElements.forEach(function (el) {
                if (el.innerText.trim() !== '' && window.getComputedStyle(el).display !== 'none') {
                    activeAlerts.push(el);
                }
            });

            // Check if we are showing alerts (indicates a flashed state to clear safely)
            if (activeAlerts.length > 0) {
                e.preventDefault();
                var newUrl = window.location.href;
                if (newUrl.indexOf('error=') > -1) {
                    newUrl = newUrl.replace(/([&?])error=[^&]*(&|$)/, function (m, p1, p2) {
                        return (p1 === '?' && p2 === '&') ? '?' : (p2 === '&' ? '&' : '');
                    });
                    newUrl = newUrl.replace(/\?$/, '');
                }
                window.location.href = newUrl;
            }
        }
    });
</script>

<script>
    // Sidebar Dropdown Logic
    document.addEventListener('DOMContentLoaded', function () {
        const dropdowns = document.querySelectorAll('.sidebar-dropdown > a');
        dropdowns.forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const parent = this.parentElement;
                const submenu = parent.querySelector('.sidebar-submenu');

                // Close others (optional - "accordion" style)
                // document.querySelectorAll('.sidebar-dropdown').forEach(item => {
                //     if(item !== parent) {
                //         item.classList.remove('active');
                //         item.querySelector('.sidebar-submenu').classList.remove('show');
                //     }
                // });

                parent.classList.toggle('active');
                if (submenu) {
                    submenu.classList.toggle('show');
                }
            });
        });
    });
</script>

<!-- Responsive Layout Script -->
<script src="../../assets/js/responsive.js?v=1"></script>

<!-- Floating Chat Widget -->
<?php include_once __DIR__ . '/../modules/common/chat_overlay.php'; ?>