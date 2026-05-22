<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_first_login = $_SESSION['is_first_login'] ?? 0;
$fp_error = '';

if ($is_first_login == 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_change_password'])) {
    $new_pw = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];

    if ($new_pw !== $confirm_pw) {
        $fp_error = "Passwords do not match.";
    } elseif (strlen($new_pw) < 6) {
        $fp_error = "Password must be at least 6 characters.";
    } else {
        require_once(__DIR__ . '/../config/db_connect.php');
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_first_login = 0 WHERE id = ?");
        if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
            $_SESSION['is_first_login'] = 0;
            echo "<script>window.location.href = window.location.href;</script>";
            exit();
        } else {
            $fp_error = "Database error. Please try again.";
        }
    }
}
?>

<?php if ($is_first_login == 1): ?>
    <!-- Full Page Overlay for Forced Password Change -->
    <div id="firstLoginModal"
        style="position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 999999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
        <div
            style="background: #fff; padding: 35px; border-radius: 8px; width: 100%; max-width: 420px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); font-family: 'Plus Jakarta Sans', sans-serif;">
            <div style="text-align: center; margin-bottom: 25px;">
                <i class="fas fa-user-shield" style="font-size: 45px; color: #2e7d32; margin-bottom: 12px;"></i>
                <h2 data-en="Update Your Password" data-am="የይለፍ ቃልዎን ይቀይሩ"
                    style="margin:0; margin-bottom:8px; color:#222; font-size: 22px;">Update Your Password</h2>
                <p data-en="Welcome! For your security, you must securely change your password before accessing the system."
                    data-am="እንኳን በደህና መጡ! ለደህንነትዎ ሲባል ሲስተሙን ከመጠቀምዎ በፊት የይለፍ ቃልዎን ደህንነቱ በተጠበቀ ሁኔታ መቀየር አለብዎት።"
                    style="color: #666; font-size: 14px; line-height: 1.5; margin:0;">
                    Welcome! For your security, you must securely change your password before accessing the system.
                </p>
            </div>

            <?php if ($fp_error): ?>
                <div
                    style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; border-left: 4px solid #c62828; margin-bottom: 20px; text-align: center; font-size: 14px;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i> <?php echo $fp_error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 18px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #444;"
                        data-en="New Password" data-am="አዲስ የይለፍ ቃል">New Password</label>
                    <div style="position: relative;">
                        <i class="fas fa-lock"
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                        <input type="password" name="new_password" required minlength="6"
                            style="width: 100%; padding: 12px 12px 12px 35px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; outline: none; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#2e7d32'" onblur="this.style.borderColor='#ddd'">
                    </div>
                </div>
                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #444;"
                        data-en="Confirm Password" data-am="የይለፍ ቃል ያረጋግጡ">Confirm Password</label>
                    <div style="position: relative;">
                        <i class="fas fa-lock"
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                        <input type="password" name="confirm_password" required minlength="6"
                            style="width: 100%; padding: 12px 12px 12px 35px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; outline: none; transition: border-color 0.3s;"
                            onfocus="this.style.borderColor='#2e7d32'" onblur="this.style.borderColor='#ddd'">
                    </div>
                </div>
                <button type="submit" name="force_change_password"
                    style="width: 100%; padding: 14px; background: #2e7d32; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;"
                    onmouseover="this.style.background='#1b5e20'" onmouseout="this.style.background='#2e7d32'">
                    <i class="fas fa-save" style="margin-right: 5px;"></i> <span data-en="Update Password"
                        data-am="ፓስዎርድ ቀይር">Update Password</span>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<header class="admin-header" style="background-color: #bbb2b2;">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <button id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar"
            style="background: none; border: none; font-size: 1.4rem; color: #000; cursor: pointer; padding: 5px; display: flex; align-items: center; justify-content: center; transition: background 0.3s; border-radius: 4px;">
            <i class="fas fa-bars"></i>
        </button>
        <?php
        $role = $_SESSION['role'] ?? 'guest';
        $titles = [
            'admin' => ['en' => 'DMU Admin Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ አድሚን ፓናል'],
            'student' => ['en' => 'DMU Student Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ የተማሪዎች አስተዳደር'],
            'registrar' => ['en' => 'DMU Registrar Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ ሬጅስትራር አስተዳደር'],
            'department_head' => ['en' => 'DMU Department Head Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ የትምህርት ክፍል ኃላፊ'],
            'cost_sharing_pro' => ['en' => 'DMU Cost Sharing Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ የወጪ መጋራት ባለሙያ'],
            'transcript_pro' => ['en' => 'DMU Transcript Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ ትራንስክሪፕት ባለሙያ'],
            'academic_vp' => ['en' => 'DMU Academic VP Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ የአካዳሚክ ም/ፕሬዚዳንት']
        ];
        $currentTitle = $titles[$role] ?? ['en' => 'DMU Panel', 'am' => 'ደብረ ማርቆስ ዩኒቨርሲቲ ፓናል'];
        ?>
        <h2 data-en="<?php echo $currentTitle['en']; ?>" data-am="<?php echo $currentTitle['am']; ?>">
            <?php echo $currentTitle['en']; ?>
        </h2>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 20px;">
        <button id="lang-btn" onclick="toggleLanguage()"
            style="padding: 8px 16px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: white; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-globe" style="color: #000000ff;"></i> <span style="color: #000000ff;">Eng/Amh</span>
        </button>
        <div class="user-profile" onclick="showProfileModal()"
            style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(255,255,255,0.1); border-radius: 6px; cursor: pointer; transition: background 0.3s;">
            <img src="../../assets/images/dmulogo.png" alt="Profile"
                style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
            <span style="font-weight: 500;">
                <?php echo $_SESSION['username'] ?? $role; ?>
            </span>
        </div>
    </div>
</header>

<!-- Profile Information Modal -->
<div id="profileModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-btn" onclick="closeProfileModal()">&times;</span>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="../../assets/images/dmulogo.png" alt="Profile"
                style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
            <h2 id="profileFullName" style="margin: 10px 0 5px 0;">Loading...</h2>
            <p id="profileRole" style="color: #7f8c8d; margin: 0;"></p>
        </div>
        <div id="profileDetails" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
            <div class="profile-info-grid" style="display: grid; gap: 15px;"></div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <?php
            // Route to the correct module wrapper so sidebar relative links work
            $role = $_SESSION['role'] ?? '';
            $change_pw_map = [
                'student' => '../student/update_student_account.php',
                'registrar' => '../registrar/update_account.php',
                'department_head' => '../department/update_account.php',
                'cost_sharing_pro' => '../cost_sharing/update_account.php',
                'admin' => '../admin/update_account.php',
                'academic_vp' => '../academic_vp/update_account.php',
                'transcript' => '../transcript/update_account.php',
            ];
            $pw_href = $change_pw_map[$role] ?? '../common/update_profile.php';
            ?>
            <a href="<?php echo $pw_href; ?>" class="btn-primary"
                style="text-decoration: none; padding: 10px 20px; display: inline-block;">
                <i class="fas fa-key"></i> <span data-en="Change Password" data-am="የመለያ ቁልፉን አድስ">Change
                    Password</span>
            </a>
        </div>
    </div>
</div>

<style>
    .user-profile:hover {
        background: rgba(12, 102, 39, 0.98) !important;
    }

    .profile-info-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px;
        background: white;
        border-radius: 6px;
    }

    .profile-info-item i {
        color: #3498db;
        font-size: 1.1rem;
        margin-top: 2px;
        width: 20px;
    }

    .profile-info-content {
        flex: 1;
    }

    .profile-info-label {
        font-size: 0.85rem;
        color: #7f8c8d;
        margin-bottom: 3px;
    }

    .profile-info-value {
        font-weight: 500;
        color: #2c3e50;
    }
</style>

<script>
    function showProfileModal() { document.getElementById('profileModal').style.display = 'flex'; loadProfileData(); }
    function closeProfileModal() { document.getElementById('profileModal').style.display = 'none'; }

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('sidebar_collapsed') === 'true') {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    });

    function loadProfileData() {
        let apiPath = '../../api/get_profile.php';
        if (window.location.pathname.split('/').length < 4) {
            // Logic for root or other depths if needed
        }

        fetch(apiPath).then(r => r.json()).then(data => {
            if (data.success) {
                const u = data.user;
                document.getElementById('profileFullName').textContent = u.full_name;

                // Role
                const roleMap = {
                    'admin': { en: 'Administrator', am: 'አስተዳዳሪ' },
                    'student': { en: 'Student', am: 'ተማሪ' },
                    'registrar': { en: 'Registrar', am: 'ሬጅስትራር' },
                    'department_head': { en: 'Department Head', am: 'የትምህርት ክፍል ኃላፊ' },
                    'cost_sharing_pro': { en: 'Cost Sharing Professional', am: 'የወጪ መጋራት ባለሙያ' },
                    'transcript_pro': { en: 'Transcript Professional', am: 'ትራንስክሪፕት ባለሙያ' },
                    'academic_vp': { en: 'Academic Vice President', am: 'የአካዳሚክ ም/ፕሬዚዳንት' }
                };
                const roleObj = roleMap[u.role] || { en: u.role, am: u.role };
                const roleEl = document.getElementById('profileRole');
                roleEl.setAttribute('data-en', roleObj.en);
                roleEl.setAttribute('data-am', roleObj.am);
                roleEl.textContent = localStorage.getItem('dmu_lang') === 'am' ? roleObj.am : roleObj.en;

                let html = '';
                html += createProfileItem('fas fa-user', 'Username', 'የተጠቃሚ ስም', u.username);
                html += createProfileItem('fas fa-envelope', 'Email', 'ኢሜይል', u.email);
                html += createProfileItem('fas fa-phone', 'Phone', 'ስልክ ቁጥር', u.phone);

                if (u.student_id) html += createProfileItem('fas fa-id-card', 'Student ID', 'የተማሪ መለያ', u.student_id);
                if (u.department) html += createProfileItem('fas fa-building', 'Department', 'የትምህርት ክፍል', u.department); // Note: Department name might need DB translation later
                if (u.college) html += createProfileItem('fas fa-university', 'College', 'ኮሌጅ', u.college);
                if (u.batch_year) html += createProfileItem('fas fa-calendar', 'Batch Year', 'የገባበት ዓመት', u.batch_year);
                if (u.semester) html += createProfileItem('fas fa-book', 'Semester', 'ሴሚስተር', u.semester);

                // Status
                const statusEn = u.status;
                const statusAm = u.status === 'active' ? 'ንቁ' : (u.status === 'suspended' ? 'ታግዷል' : u.status);
                const statusColor = u.status === 'active' ? '#27ae60' : '#e74c3c';
                const statusVal = `<span style="color:${statusColor};text-transform:capitalize;" data-en="${statusEn}" data-am="${statusAm}">${localStorage.getItem('dmu_lang') === 'am' ? statusAm : statusEn}</span>`;

                html += createProfileItem('fas fa-check-circle', 'Status', 'ሁኔታ', statusVal);
                html += createProfileItem('fas fa-clock', 'Member Since', 'አባል የሆነበት ጊዜ', u.member_since);

                document.querySelector('.profile-info-grid').innerHTML = html;
            }
        }).catch(e => { console.error('Error:', e); document.getElementById('profileFullName').textContent = 'Error loading profile'; });
    }

    function createProfileItem(icon, labelEn, labelAm, value) {
        const currentLang = localStorage.getItem('dmu_lang') || 'en';
        const displayLabel = currentLang === 'am' ? labelAm : labelEn;
        return `<div class="profile-info-item">
            <i class="${icon}"></i>
            <div class="profile-info-content">
                <div class="profile-info-label" data-en="${labelEn}" data-am="${labelAm}">${displayLabel}</div>
                <div class="profile-info-value">${value}</div>
            </div>
        </div>`;
    }

    window.addEventListener('click', e => { if (e.target === document.getElementById('profileModal')) closeProfileModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeProfileModal(); });
</script>