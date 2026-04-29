<?php
/**
 * Welcome Banner - Dynamic greeting based on CLIENT local time (JS)
 * Professional, warm, user-friendly design
 */

$user_name = htmlspecialchars($_SESSION['name'] ?? 'User');
$role = $_SESSION['role'] ?? 'user';

$role_labels = [
    'student' => ['en' => 'Student Portal', 'am' => 'የተማሪ መግቢያ'],
    'registrar' => ['en' => 'Registrar Office', 'am' => 'ሬጅስትራር ቢሮ'],
    'department_head' => ['en' => 'Department Office', 'am' => 'የትምህርት ክፍል ቢሮ'],
    'cost_sharing_pro' => ['en' => 'Cost Sharing Office', 'am' => 'የወጪ መጋራት ቢሮ'],
    'transcript_pro' => ['en' => 'Transcript Office', 'am' => 'የግልባጭ ቢሮ'],
    'admin' => ['en' => 'System Administration', 'am' => 'ስርዓት አስተዳደር'],
    'academic_vp' => ['en' => 'Academic Affairs', 'am' => 'የአካዳሚክ ጉዳዮች'],
];

$role_en = $role_labels[$role]['en'] ?? ucfirst($role);
$role_am = $role_labels[$role]['am'] ?? ucfirst($role);
?>

<div id="welcomeBanner" class="welcome-banner" style="
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 16px;
    padding: 22px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    border: 1px solid rgba(255,255,255,0.8);
">
    <!-- Decorative elements -->
    <div
        style="position:absolute; top:-40px; right:-20px; width:140px; height:140px; background:rgba(255,255,255,0.25); border-radius:50%;">
    </div>
    <div
        style="position:absolute; bottom:-30px; right:80px; width:90px; height:90px; background:rgba(255,255,255,0.15); border-radius:50%;">
    </div>

    <div style="display:flex; align-items:center; gap:16px; position:relative; z-index:2;">
        <!-- Text content -->
        <div style="flex:1; min-width:0;">
            <h2 style="margin:0 0 6px 0; font-size:1.3rem; font-weight:700; color:#1e293b; letter-spacing:-0.02em;">
                <span id="greetingText" data-en="Welcome, <?php echo $user_name; ?>!"
                    data-am="እንኳን ደህና መጡ, <?php echo $user_name; ?>!">
                    Welcome, <?php echo $user_name; ?>!
                </span>
            </h2>
            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:0.82rem; color:#64748b;">
                <span
                    style="display:inline-flex; align-items:center; gap:5px; background:rgba(99,102,241,0.08); padding:3px 10px; border-radius:20px; color:#4f46e5; font-weight:500;">
                    <i class="fas fa-id-card"></i> <i class="fas fa-briefcase" style="font-size:0.7rem;"></i>
                    <span data-en="<?php echo $role_en; ?>"
                        data-am="<?php echo $role_am; ?>"><?php echo $role_en; ?></span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-calendar-day" style="font-size:0.7rem; color:#94a3b8;"></i>
                    <span id="welcome-date" style="color:#475569;"></span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-clock" style="font-size:0.7rem; color:#94a3b8;"></i>
                    <span id="welcome-time" style="color:#475569; font-variant-numeric:tabular-nums;"></span>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
    // Store greeting data globally so bilingual.js can't override it
    window._welcomeGreeting = null;
    var _clockStarted = false;

    function applyWelcomeGreeting() {
        const now = new Date();
        const hour = now.getHours();
        const banner = document.getElementById('welcomeBanner');
        const greeting = document.getElementById('greetingText');
        const name = '<?php echo $user_name; ?>';

        if (!banner || !greeting) return;

        let greetEn, greetAm, bgGradient;

        if (hour >= 5 && hour < 12) {
            greetEn = 'Good Morning';
            greetAm = 'እንደምን አደርክ/ሽ';

            bgGradient = 'linear-gradient(135deg, #fef9c3 0%, #fde68a 40%, #fed7aa 100%)';
        } else if (hour >= 12 && hour < 17) {
            greetEn = 'Good Afternoon';
            greetAm = 'እንደምን ዋልክ/ሽ';

            bgGradient = 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 40%, #dbeafe 100%)';
        } else if (hour >= 17 && hour < 21) {
            greetEn = 'Good Evening';
            greetAm = 'እንደምን አመሸህ/ሽ';

            bgGradient = 'linear-gradient(135deg, #ede9fe 0%, #e0e7ff 40%, #ddd6fe 100%)';
        } else {
            greetEn = 'Welcome Back';
            greetAm = 'እንኳን ተመልሰው መጡ';

            bgGradient = 'linear-gradient(135deg, #e8eaf6 0%, #e0e7ff 40%, #f1f5f9 100%)';
        }

        // Store for re-use
        window._welcomeGreeting = { en: greetEn + ', ' + name + '!', am: greetAm + ', ' + name + '!' };

        // Apply greeting text & data attributes
        greeting.setAttribute('data-en', window._welcomeGreeting.en);
        greeting.setAttribute('data-am', window._welcomeGreeting.am);

        // Set text based on current language
        const lang = localStorage.getItem('dmu_lang') || 'en';
        greeting.textContent = lang === 'am' ? window._welcomeGreeting.am : window._welcomeGreeting.en;


        // Apply gradient
        banner.style.background = bgGradient;

        // Format date  
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateEl = document.getElementById('welcome-date');
        if (dateEl) dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();

        // Live clock (only start once)
        if (!_clockStarted) {
            _clockStarted = true;
            function updateClock() {
                const t = new Date();
                let h = t.getHours(), m = t.getMinutes();
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                const el = document.getElementById('welcome-time');
                if (el) el.textContent = h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
            }
            updateClock();
            setInterval(updateClock, 30000);
        }
    }

    // Run immediately (inline - elements are already in DOM above)
    applyWelcomeGreeting();

    // Also run on DOMContentLoaded and window.load as fallbacks
    document.addEventListener('DOMContentLoaded', applyWelcomeGreeting);
    window.addEventListener('load', function () {
        applyWelcomeGreeting();
        setTimeout(applyWelcomeGreeting, 500);
    });
</script>