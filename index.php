<?php
require_once 'includes/session_manager.php';
require_once 'config/db_connect.php';
require_once 'includes/audit_logger.php';

// Login Logic (retained)
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check in users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] !== 'active') {
            $error = "Account is " . $user['status'];
        } else {
            session_write_close();
            session_name("DMU_" . strtoupper($user['role']));
            session_id(session_create_id());
            session_start();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['username'] = $user['username'];

            logAudit($pdo, 'LOGIN_SUCCESS', 'User logged in as ' . $user['role']);

            // Redirect based on role
            switch ($user['role']) {
                case 'student':
                    header("Location: modules/student/dashboard.php");
                    break;
                case 'registrar':
                    header("Location: modules/registrar/dashboard.php");
                    break;
                case 'department_head':
                    header("Location: modules/department/dashboard.php");
                    break;
                case 'cost_sharing_pro':
                    header("Location: modules/cost_sharing/index.php");
                    break;
                case 'transcript_pro':
                    header("Location: modules/transcript/dashboard.php");
                    break;
                case 'admin':
                    header("Location: modules/admin/dashboard.php");
                    break;
                case 'academic_vp':
                    header("Location: modules/academic_vp/dashboard.php");
                    break;
                default:
                    $error = "Unknown role.";
            }
            exit();
        }
    } else {
        $error = "Invalid username or password.";
        // Log failed login - no session yet, so insert directly
        try {
            $stmt_log = $pdo->prepare("INSERT INTO audit_logs (user_id, username, role, action, description, ip_address) VALUES (NULL, ?, 'unknown', 'LOGIN_FAILED', ?, ?)");
            $stmt_log->execute([$username, 'Failed login attempt for username: ' . $username, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        } catch (Exception $e) { /* silent */
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Debre Markos University - Cost Sharing" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ - ወጪ መጋራት">Debre Markos University
        - Cost Sharing</title>
    <link rel="stylesheet" href="assets/css/index.css?v=11">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="landing-page">

    <!-- Navbar -->
    <nav class="navbar">
        <div class="logo-area">
            <a href="index.php"><img src="assets/images/dmulogo.png" style="border-radius: 50%;" alt="DMU Logo"></a>
            <div class="system-name">
                <h1 data-en="DMU Cost Sharing" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ ወጪ መጋራት ስርዓት">DMU Cost Sharing</h1>
            </div>
        </div>

        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="index.php" data-en="Home" data-am="ዋና ገጽ">Home</a></li>
                <li><a href="#services" data-en="Services" data-am="አገልግሎቶች">Services</a></li>
                <li><a href="#about" data-en="About Us" data-am="ስለ እኛ">About Us</a></li>
                <li><a href="#contact" data-en="Contact Us" data-am="ያግኙን">Contact Us</a></li>
            </ul>

            <button data-en="Eng/Amh" data-am="እንግሊዝኛ/አማርኛ" id="lang-btn" onclick="toggleLanguage()">
                <i class="fas fa-globe"></i> Eng/Amh
            </button>

            <button data-en="Login" data-am="ግባ" class="btn-primary" style="margin-left: 10px;" onclick="openLogin()">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </div>
    </nav>

    <!-- Hero Slider -->
    <div class="hero-slider">
        <div class="slide active" style="background-image: url('assets/images/dmu_hero_campus.png');">
            <div class="hero-caption">
                <h2 data-en="Welcome to Debre Markos University" data-am="እንኳን ወደ ደብረ ማርቆስ ዩኒቨርሲቲ በደህና መጡ">Welcome to
                    Debre Markos University</h2>
                <p data-en="Excellence in Education and Research" data-am="በትምህርት እና ምርምር የላቀ">Excellence in Education
                    and Research</p>
            </div>
        </div>
        <div class="slide" style="background-image: url('assets/images/dmu_hero_students.png');">
            <div class="hero-caption">
                <h2 data-en="Student Centered Learning" data-am="ተማሪ ተኮር ትምህርት">Student Centered Learning</h2>
                <p data-en="Empowering the next generation of leaders." data-am="ለሀገሪቱ የሰው ካፒታል ማዳበር">Empowering the
                    next generation of leaders.</p>
            </div>
        </div>
        <div class="slide" style="background-image: url('assets/images/dmu_hero_library.png');">
            <div class="hero-caption">
                <h2 data-en="World Class Library" data-am="የአለም ደረጃ ቤተ-መጽሐፍት">World Class Library</h2>
                <p data-en="Resources for every discipline." data-am="ለሀገሪቱ የሰው ካፒታል ማዳበር">Resources for every
                    discipline.</p>
            </div>
        </div>
        <div class="slide" style="background-image: url('assets/images/dmu_hero_graduation.png');">
            <div class="hero-caption">
                <h2 data-en="Celebrating Success" data-am="ስኬትን ማክበር">Celebrating Success</h2>
                <p data-en="Developing human capital for the nation." data-am="ለሀገሪቱ የሰው ካፒታል ማዳበር">Developing human
                    capital for the nation.</p>
            </div>
        </div>
        <div class="slide" style="background-image: url('assets/images/dmu_hero_lab.png');">
            <div class="hero-caption">
                <h2 data-en="Advanced Technology" data-am="የላቀ ቴክኖሎጂ">Advanced Technology</h2>
                <p data-en="State of the art laboratories." data-am="የዘመናዊ ላቦራቶሪዎች">State of the art laboratories.</p>
            </div>
        </div>
    </div>

    <!-- Info Section (Definitions, Mission, Vision) -->
    <section id="about" class="info-section">
        <div class="container">
            <div class="card info-card">
                <h2 data-en="Cost Sharing Definition" data-am="የወጪ መጋራት ትርጉም">Cost Sharing Definition</h2>
                <p data-en="Cost sharing is a scheme where the costs of higher education are shared between the government and the beneficiaries (students). It aims to ensure equitable access to higher education and sustainable financing."
                    data-am="የወጪ መጋራት የከፍተኛ ትምህርት ወጪዎች በመንግስት እና በተጠቃሚዎች (ተማሪዎች) የሚጋሩበት አሰራር ነው። ለከፍተኛ ትምህርት ፍትሃዊ ተደራሽነትን እና ቀጣይነት ያለው የፋይናንስ ድጋፍን ለማረጋገጥ ያለመ ነው።">
                    Cost sharing is a scheme where the costs of higher education are shared between the government and
                    the beneficiaries (students). It aims to ensure equitable access to higher education and sustainable
                    financing.
                </p>
            </div>

            <div class="card info-card">
                <h2 data-en="DMU Cost Sharing System" data-am="የደብረ ማርቆስ ዩኒቨርሲቲ ወጪ መጋራት ስርዓት">DMU Cost Sharing System
                </h2>
                <p data-en="The DMU Cost Sharing Management System is a digital platform designed to streamline the agreement process, track expenses, and manage student cost data efficiently, replacing manual paper-based workflows."
                    data-am="የደብረ ማርቆስ ዩኒቨርሲቲ የወጪ መጋራት አስተዳደር ስርዓት የስምምነት ሂደቱን ለማቀላጠፍ፣ ወጪዎችን ለመከታተል እና የተማሪ ወጪ መረጃን በብቃት ለማስተዳደር የተነደፈ ዲጂታል መድረክ ነው።">
                    The DMU Cost Sharing Management System is a digital platform designed to streamline the agreement
                    process, track expenses, and manage student cost data efficiently, replacing manual paper-based
                    workflows.
                </p>
            </div>

            <div class="mission-vision-grid">
                <div class="card mv-card">
                    <i class="fas fa-bullseye"></i>
                    <h3 data-en="Mission" data-am="ተልዕኮ">Mission</h3>
                    <p data-en="To provide quality education, conduct problem-solving research, and deliver community-based services."
                        data-am="ጥራት ያለው ትምህርት መስጠት፣ ችግር ፈቺ ምርምር ማካሄድ እና ማህበረሰብ አቀፍ አገልግሎቶችን መስጠት።">
                        To provide quality education, conduct problem-solving research, and deliver community-based
                        services.
                    </p>
                </div>
                <div class="card mv-card">
                    <i class="fas fa-eye"></i>
                    <h3 data-en="Vision" data-am="ራዕይ">Vision</h3>
                    <p data-en="To become one of the top ten universities in Africa by 2030."
                        data-am="በ2030 ዓ.ም ከአፍሪካ ምርጥ አስር ዩኒቨርሲቲዎች አንዱ መሆን።">
                        To become one of the top ten universities in Africa by 2030 in E.C.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-section">
                <h3 data-en="Contact Address" data-am="የእውቂያ አድራሻ">Contact Address</h3>
                <p data-en="Debre Markos University, Ethiopia" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ, ኢትዮጵያ"><i
                        class="fas fa-map-marker-alt"></i> Debre Markos, Ethiopia</p>
                <p data-en="+251-58-771-1234" data-am="+251-58-771-1234"><i class="fas fa-phone"></i> +251-58-771-1234
                </p>
                <p data-en="costsharing@gmail.com" data-am="costsharing@gmail.com"><i class="fas fa-envelope"></i>
                    costsharing@gmail.com</p>
            </div>

            <div class="footer-section">
                <h3 data-en="Quick Links" data-am="ፈጣን አገናኞች">Quick Links</h3>
                <p><a href="https://www.moe.gov.et/" target="_blank" data-en="Ministry of Education"
                        data-am="የመንግስት ትምህርት ሚኒስቴር">Ministry of Education</a>
                </p>
                <p><a href="https://www.dmu.edu.et/" target="_blank" data-en="University Official Site"
                        data-am="የዩኒቨርሲቲው ኦፊሴላዊ ድረ-ገጽ">University Official
                        Site</a></p>
                <p><a href="https://portal.dmu.edu.et/" target="_blank" data-en="Student Portal"
                        data-am="የተማሪዎች ፖርታል">Student Portal</a></p>
            </div>

            <div class="footer-section">
                <h3 data-en="Developers" data-am="ገንቢዎች">Developers</h3>
                <p data-en="Designed and Developed by 2026 Graduaters" data-am="የተዘጋጀው እና የተሰራው በ 2026 ተመራቂ ተማሪዎች ነው።">
                    Designed and Developed by <strong>2026 Graduaters</strong></p>
                    <p style="color: #fff; font-weight:bold; font-family:tahoma;" data-en="For more information, contact us at:" data-am="ለተጨማሪ መረጃ፣ እኛን ያነጋግሩን:">For more information, contact us at: </p>
                <div class="social-links">
                    <a href="https://web.facebook.com/dmu.edu?_rdc=1&_rdr" target="_blank"><i
                            class="fab fa-facebook"></i></a>
                    <a href="https://x.com/dmu_ethiopia" target="_blank"><i class="fab fa-twitter"></i></a>
                    <a href="https://t.me/Debre_Markos_University" target="_blank"><i class="fab fa-telegram"></i></a>
                    <a href="https://www.linkedin.com/in/dmu_ethiopia/" target="_blank"><i
                            class="fab fa-linkedin"></i></a>
                    <a href="https://www.youtube.com/@debremarkosuniversityoffic5578" target="_blank"><i
                            class="fab fa-youtube"></i></a>
                    <a href="https://www.instagram.com/dmu_ethiopia" target="_blank"><i
                            class="fab fa-instagram"></i></a>
                    <a href="https://www.dmu.edu.et/" target="_blank"><i
                            class="fas fa-globe"></i></a>
                </div>
                <!-- Login removed from footer as requested, moved to navbar -->
            </div>
        </div>
        <div class="footer-bottom">
            <p data-en="&copy; 2026 Debre Markos University. All Rights Reserved."
                data-am="&copy; 2026 ደብረ ማርቆስ ዩኒቨርሲቲ. ሁሉም መብቶች የተጠበቁ ናቸው።">&copy; 2026 Debre Markos University. All
                Rights Reserved.</p>
        </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="login-modal" <?php if ($error)
        echo 'style="display:flex;"'; ?>>
        <!-- Animated Background -->
        <div class="login-bg">
            <div class="login-bg-gradient"></div>
            <div class="login-particles">
                <div class="particle p1"></div>
                <div class="particle p2"></div>
                <div class="particle p3"></div>
                <div class="particle p4"></div>
                <div class="particle p5"></div>
                <div class="particle p6"></div>
            </div>
        </div>

        <!-- Close Button -->
        <button class="login-close" onclick="closeLogin()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <!-- Glass Card -->
        <div class="login-glass-card">
            <!-- Logo & Branding -->
            <div class="login-brand">
                <div class="login-logo-glow">
                    <img src="assets/images/dmulogo.png" style="border-radius: 50%;" alt="DMU Logo" class="login-logo">
                </div>
                <h1 class="login-title" data-en="Sign In" data-am="ግባ">Sign In</h1>
                <p class="login-subtitle" data-en="DMU Cost Sharing Portal" data-am="የ DMU ወጪ መጋራት ፖርታል" style="color: #000000ff;">DMU Cost
                    Sharing Portal</p>
                <div class="login-divider" style="color: #000000ff;">
                    <span style="color: #000000ff;"></span>
                    <i class="fas fa-shield-alt" style="color: #000000ff; font-size: 15px;"></i>
                    <span style="color: #000000ff; font-size: 15px;"></span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['forgot_pw_success'])): ?>
                <div class="login-error" style="background-color: #e8f5e9; color: #2e7d32; border-left-color: #2e7d32;">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['forgot_pw_success']; unset($_SESSION['forgot_pw_success']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="login-form" autocomplete="off">
                <div class="login-field">
                    <div class="login-input-box">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" name="username" id="login-username" required placeholder="Username"
                            data-en-placeholder="Enter Username" data-am-placeholder="የተጠቃሚ ስም ያስገቡ">
                        <div class="login-input-highlight"></div>
                    </div>
                </div>

                <div class="login-field">
                    <div class="login-input-box">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="login-password" required placeholder="Password"
                            data-en-placeholder="Enter Password" data-am-placeholder="የይለፍ ቃል ያስገቡ">
                        <div class="login-input-highlight"></div>
                        <button type="button" class="login-eye-toggle" onclick="togglePassword()" tabindex="-1">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div style="text-align: right; margin-top: 8px;">
                        <a href="forgot_password.php" style="color: #2e7d32; font-weight: 700; text-decoration: none; font-size: 15px; transition: color 0.3s;" onmouseover="this.style.color='#16ac20ff'" onmouseout="this.style.color='#2e7d32'" data-en="Forgot Password?" data-am="የይለፍ ቃል ረሱ?">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" name="login" class="login-submit-btn" data-en="Sign In" data-am="ግባ">
                    <span class="login-btn-text">Sign In</span>
                    <span class="login-btn-icon"><i class="fas fa-arrow-right"></i></span>
                    <div class="login-btn-shine"></div>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-card-footer">
                <p style="color: black; weight: bold;" data-en="Debre Markos University © 2026" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ © 2026">
                    <i class="fas fa-university"></i> Debre Markos University © 2026
                </p>
            </div>
        </div>
    </div>

    <script src="assets/js/bilingual.js?v=2"></script>
    <script>
        // Start Slider
        let slides = document.querySelectorAll('.slide');
        let currentSlide = 0;

        function nextSlide() {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }
        setInterval(nextSlide, 5000);

        // Modal Logic
        function openLogin() {
            document.getElementById('loginModal').style.display = 'flex';
        }

        function closeLogin() {
            document.getElementById('loginModal').style.display = 'none';
        }

        // Close if clicked outside
        window.onclick = function (event) {
            let modal = document.getElementById('loginModal');
            if (event.target == modal) {
                closeLogin();
            }
        }

        // Password Visibility Toggle
        function togglePassword() {
            let passInput = document.getElementById('login-password');
            let icon = document.getElementById('toggleIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    <script src="assets/js/responsive.js?v=1"></script>
</body>

</html>