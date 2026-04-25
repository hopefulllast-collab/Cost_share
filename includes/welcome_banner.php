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
    <div style="position:absolute; top:-40px; right:-20px; width:140px; height:140px; background:rgba(255,255,255,0.25); border-radius:50%;"></div>
    <div style="position:absolute; bottom:-30px; right:80px; width:90px; height:90px; background:rgba(255,255,255,0.15); border-radius:50%;"></div>
    
    <div style="display:flex; align-items:center; gap:16px; position:relative; z-index:2;">
        <!-- Icon container -->
        <div id="welcomeIconBox" style="
            width:50px; height:50px;
            background: rgba(255,255,255,0.9);
            border-radius: 14px;
            display:flex; align-items:center; justify-content:center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            flex-shrink: 0;
        ">
            <i id="welcomeIcon" class="fas fa-sun" style="font-size:1.3rem; color:#f59e0b;"></i>
        </div>
        
        <!-- Text content -->
        <div style="flex:1; min-width:0;">
            <h2 style="margin:0 0 6px 0; font-size:1.3rem; font-weight:700; color:#1e293b; letter-spacing:-0.02em;">
                <span id="greetingText" 
                      data-en="Welcome, <?php echo $user_name; ?>!" 
                      data-am="እንኳን ደህና መጡ, <?php echo $user_name; ?>!">
                    Welcome, <?php echo $user_name; ?>!
                </span>
            </h2>
            <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; font-size:0.82rem; color:#64748b;">
                <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(99,102,241,0.08); padding:3px 10px; border-radius:20px; color:#4f46e5; font-weight:500;">
                    <i class="fas fa-briefcase" style="font-size:0.7rem;"></i>
                    <span data-en="<?php echo $role_en; ?>" data-am="<?php echo $role_am; ?>"><?php echo $role_en; ?></span>
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
(function initWelcomeBanner() {
    const now = new Date();
    const hour = now.getHours();
    const banner = document.getElementById('welcomeBanner');
    const icon = document.getElementById('welcomeIcon');
    const greeting = document.getElementById('greetingText');
    const name = '<?php echo $user_name; ?>';
    
    let greetEn, greetAm, iconClass, iconColor, bgGradient;

    if (hour >= 5 && hour < 12) {
        greetEn = 'Good Morning';
        greetAm = 'እንደምን አደርክ/ሽ';
        iconClass = 'fas fa-sun';
        iconColor = '#f59e0b';
        bgGradient = 'linear-gradient(135deg, #fef9c3 0%, #fde68a 40%, #fed7aa 100%)';
    } else if (hour >= 12 && hour < 17) {
        greetEn = 'Good Afternoon';
        greetAm = 'እንደምን ዋልክ/ሽ';
        iconClass = 'fas fa-cloud-sun';
        iconColor = '#0ea5e9';
        bgGradient = 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 40%, #dbeafe 100%)';
    } else if (hour >= 17 && hour < 21) {
        greetEn = 'Good Evening';
        greetAm = 'እንደምን አመሸህ/ሽ';
        iconClass = 'fas fa-cloud-moon';
        iconColor = '#8b5cf6';
        bgGradient = 'linear-gradient(135deg, #ede9fe 0%, #e0e7ff 40%, #ddd6fe 100%)';
    } else {
        greetEn = 'Welcome Back';
        greetAm = 'እንኳን ተመልሰው መጡ';
        iconClass = 'fas fa-moon';
        iconColor = '#6366f1';
        bgGradient = 'linear-gradient(135deg, #e8eaf6 0%, #e0e7ff 40%, #f1f5f9 100%)';
    }

    // Apply greeting
    greeting.setAttribute('data-en', greetEn + ', ' + name + '!');
    greeting.setAttribute('data-am', greetAm + ', ' + name + '!');
    greeting.textContent = greetEn + ', ' + name + '!';
    
    // Apply icon
    icon.className = iconClass;
    icon.style.color = iconColor;
    
    // Apply gradient
    banner.style.background = bgGradient;

    // Format date  
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const dateEl = document.getElementById('welcome-date');
    dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();

    // Live clock
    function updateClock() {
        const t = new Date();
        let h = t.getHours(), m = t.getMinutes();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        document.getElementById('welcome-time').textContent = h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
    }
    updateClock();
    setInterval(updateClock, 30000);

    // Update bilingual if available
    if (typeof updateLanguage === 'function') {
        setTimeout(updateLanguage, 100);
    }
})();
</script>
