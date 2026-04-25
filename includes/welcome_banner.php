<?php
/**
 * Welcome Banner - Dynamic greeting based on local time
 * Include this on any dashboard page after session is active.
 * Shows: Good Morning/Afternoon/Evening + user name + login time
 */

// Get current hour for greeting (Africa/Addis_Ababa timezone)
date_default_timezone_set('Africa/Addis_Ababa');
$hour = (int) date('H');
$current_time = date('h:i A');
$current_date = date('l, M d, Y');

if ($hour >= 5 && $hour < 12) {
    $greeting_en = 'Good Morning';
    $greeting_am = 'እንደምን አደርክ/ሽ';
    $icon = 'fas fa-sun';
    $gradient = 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)';
    $icon_color = '#f59e0b';
} elseif ($hour >= 12 && $hour < 17) {
    $greeting_en = 'Good Afternoon';
    $greeting_am = 'እንደምን ዋልክ/ሽ';
    $icon = 'fas fa-cloud-sun';
    $gradient = 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)';
    $icon_color = '#3b82f6';
} elseif ($hour >= 17 && $hour < 21) {
    $greeting_en = 'Good Evening';
    $greeting_am = 'እንደምን አመሸህ/ሽ';
    $icon = 'fas fa-moon';
    $gradient = 'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)';
    $icon_color = '#8b5cf6';
} else {
    $greeting_en = 'Welcome';
    $greeting_am = 'እንኳን ደህና መጡ';
    $icon = 'fas fa-stars';
    $gradient = 'linear-gradient(135deg, #c3cfe2 0%, #f5f7fa 100%)';
    $icon_color = '#6366f1';
}

$user_name = htmlspecialchars($_SESSION['name'] ?? 'User');
$role = $_SESSION['role'] ?? 'user';

// Map role to display name
$role_labels = [
    'student' => ['en' => 'Student', 'am' => 'ተማሪ'],
    'registrar' => ['en' => 'Registrar Head', 'am' => 'ሬጅስትራር ኃላፊ'],
    'department_head' => ['en' => 'Department Head', 'am' => 'የትምህርት ክፍል ኃላፊ'],
    'cost_sharing_pro' => ['en' => 'Cost Sharing Professional', 'am' => 'የወጪ መጋራት ባለሙያ'],
    'transcript_pro' => ['en' => 'Transcript Professional', 'am' => 'የግልባጭ ባለሙያ'],
    'admin' => ['en' => 'System Administrator', 'am' => 'ስርዓት አስተዳዳሪ'],
    'academic_vp' => ['en' => 'Academic Vice President', 'am' => 'የአካዳሚክ ም/ፕሬዝዳንት'],
];

$role_en = $role_labels[$role]['en'] ?? ucfirst($role);
$role_am = $role_labels[$role]['am'] ?? ucfirst($role);
?>

<div class="welcome-banner" style="
    background: <?php echo $gradient; ?>;
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #1a1a2e;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
">
    <!-- Decorative circles -->
    <div style="position:absolute; top:-30px; right:-30px; width:120px; height:120px; background:rgba(255,255,255,0.15); border-radius:50%;"></div>
    <div style="position:absolute; bottom:-20px; right:60px; width:80px; height:80px; background:rgba(255,255,255,0.1); border-radius:50%;"></div>
    
    <div style="display:flex; align-items:center; gap:16px; position:relative; z-index:2;">
        <div style="
            width:52px; height:52px;
            background:rgba(255,255,255,0.85);
            border-radius:14px;
            display:flex; align-items:center; justify-content:center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        ">
            <i class="<?php echo $icon; ?>" style="font-size:1.4rem; color:<?php echo $icon_color; ?>;"></i>
        </div>
        <div style="flex:1;">
            <h2 style="margin:0 0 4px 0; font-size:1.35rem; font-weight:700; color:#1a1a2e; letter-spacing:-0.02em;">
                <span data-en="<?php echo $greeting_en; ?>, <?php echo $user_name; ?>!" 
                      data-am="<?php echo $greeting_am; ?>, <?php echo $user_name; ?>!">
                    <?php echo $greeting_en; ?>, <?php echo $user_name; ?>!
                </span>
            </h2>
            <p style="margin:0; font-size:0.88rem; color:rgba(26,26,46,0.7); display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-user-shield" style="font-size:0.75rem;"></i>
                    <span data-en="<?php echo $role_en; ?>" data-am="<?php echo $role_am; ?>"><?php echo $role_en; ?></span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-calendar-alt" style="font-size:0.75rem;"></i>
                    <span id="welcome-date"><?php echo $current_date; ?></span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:4px;">
                    <i class="fas fa-clock" style="font-size:0.75rem;"></i>
                    <span id="welcome-time"><?php echo $current_time; ?></span>
                </span>
            </p>
        </div>
    </div>
</div>

<script>
// Live clock update
(function updateWelcomeClock() {
    const timeEl = document.getElementById('welcome-time');
    if (timeEl) {
        const now = new Date();
        let h = now.getHours(), m = now.getMinutes();
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        timeEl.textContent = h + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
    }
    setTimeout(updateWelcomeClock, 30000); // Update every 30 seconds
})();
</script>
