<div class="sidebar">
    <!--  <div class="sidebar-header">
        <h3 data-en="DMU System" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">DMU System</h3>
    </div> -->
    <ul class="nav-links">
        <?php if ($_SESSION['role'] == 'student'): ?>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> <span data-en="Dashboard"
                        data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="agreement_form.php"><i class="fas fa-file-contract"></i> <span data-en="Fill Cost Share"
                        data-am="የወጪ መጋራት ይሙሉ">Fill Cost Share</span></a></li>
            <li><a href="history.php"><i class="fas fa-history"></i> <span data-en="View Cost Share"
                        data-am="የወጪ መጋራት ይመልከቱ">View Cost Share</span></a></li>
            <li><a href="view_agreement.php"><i class="fas fa-file-signature"></i> <span data-en="View Agreement"
                        data-am="ስምምነት ይመልከቱ">View Agreement</span></a></li>
            <li><a href="notices.php"><i class="fas fa-bullhorn"></i> <span data-en="View Notice"
                        data-am="ማስታወቂያ ይመልከቱ">View Notice</span></a></li>
            <li><a href="feedback.php"><i class="fas fa-comment-dots"></i> <span data-en="Send Feedback"
                        data-am="አስተያየት ይላኩ">Send Feedback</span></a></li>
            <li><a href="request_document.php"><i class="fas fa-file-alt"></i> <span data-en="Request Document"
                        data-am="ሰነድ ይጠይቁ">Request Document</span></a></li>
            <li><a href="view_document.php"><i class="fas fa-file-alt"></i> <span data-en="View Document"
                        data-am="ሰነድ ይመልከቱ">View Document</span></a></li>
            <li><a href="update_student_account.php"><i class="fas fa-user-lock"></i> <span data-en="Update Account"
                        data-am="መለያ አዘምን">Update Account</span></a></li>


        <?php
elseif ($_SESSION['role'] === 'department_head'): ?>
            <li><a href="../../modules/department/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span
                        data-en="Dashboard" data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="../../modules/department/approve_agreement.php"><i class="fas fa-file-contract"></i> <span
                        data-en="Approve Cost Share agreement" data-am="የወጪ መጋራት ስምምነት ያጽድቁ">Approve Cost Share
                        agreement</span></a></li>

            <li><a href="../../modules/department/manage_credit_hours.php"><i class="fas fa-tasks"></i> <span
                        data-en="Manage Credit Hours" data-am="ክሬዲት ሰዓት ያስተዳድሩ">Manage Credit Hours</span></a></li>
            <li><a href="../../modules/department/report_cost_sharing.php"><i class="fas fa-chart-bar"></i> <span
                        data-en="Report Cost Share" data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>

            <li><a href="../../modules/department/update_account.php"><i class="fas fa-user-cog"></i> <span
                        data-en="Update Account" data-am="መለያ አዘምን">Update Account</span></a></li>


        <?php
elseif ($_SESSION['role'] === 'registrar'): ?>
            <!-- 1. Approve Cost Share -->
            <li><a href="../../modules/registrar/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span
                        data-en="Dashboard" data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="../../modules/registrar/approve_cost_share.php"><i class="fas fa-check-circle"></i> <span
                        data-en="Approve Cost Share" data-am="የወጪ መጋራት አጽድቅ">Approve Cost Share</span></a></li>
            <!-- 2. Student List -->
            <li>
                <div class="sidebar-dropdown">
                    <a href="#"><i class="fas fa-users"></i> <span data-en="Student List" data-am="የተማሪ ዝርዝር">Student
                            List</span></a>
                    <div class="sidebar-submenu" style="padding-left: 20px;">
                        <a href="../../modules/registrar/view_student_list.php"><i class="fas fa-list"></i> <span
                                data-en="View Student List" data-am="ተማሪዎች ዝርዝር ይመልከቱ">View Student List</span></a>
                        <a href="../../modules/registrar/add_student.php"><i class="fas fa-plus"></i> <span
                                data-en="Add Student" data-am="ተማሪ ጨምር">Add Student</span></a>
                        <a href="../../modules/registrar/promote_students.php"><i class="fas fa-level-up-alt"></i> <span
                                data-en="Promote Students" data-am="ተማሪዎችን አሳድግ">Promote Students</span></a>
                        <a href="../../modules/registrar/manage_student_status.php"><i class="fas fa-user-clock"></i> <span
                                data-en="Manage Status" data-am="ሁኔታን አስተዳድር">Manage Status</span></a>
                    </div>
                </div>
            </li>

            <!-- 4. Order -->
            <li>
                <div class="sidebar-dropdown">
                    <a href="#"><i class="fas fa-user-shield"></i> <span data-en="Order" data-am="ትዕዛዝ">Order</span></a>
                    <div class="sidebar-submenu" style="padding-left: 20px;">
                        <a href="../../modules/registrar/order.php?target=cost_sharing"><i class="fas fa-user-check"></i>
                            <span data-en="To Cost Sharing Pro" data-am="ለወጪ መጋራት ባለሙያ">To Cost Sharing Pro</span></a>
                        <a href="../../modules/registrar/order.php?target=transcript"><i class="fas fa-file-signature"></i>
                            <span data-en="To Transcript Pro" data-am="ለኦፊሴላዊ ትራንስክሪፕት">To Transcript Pro</span></a>
                    </div>
                </div>
            </li>
            <!-- 5. Report Cost Share -->
            <li><a href="../../modules/registrar/report_cost_share.php"><i class="fas fa-chart-line"></i> <span
                        data-en="Report Cost Share" data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>
            <!-- 6. Send Credit Hour (constraints for Dept Head) -->
            <li><a href="../../modules/registrar/send_credit_hour.php"><i class="fas fa-hourglass-half"></i> <span
                        data-en="Send Credit Hour" data-am="ክሬዲት ሰዓት ላክ">Send Credit Hour</span></a></li>

            <!-- add department -->
            <li><a href="../../modules/registrar/add_department.php"><i class="fas fa-university"></i> <span
                        data-en="Add Department" data-am="ትምህርት ክፍል ጨምር">Add Department</span></a></li>
            <!-- 7. Check Cost Share Agreement -->
            <li><a href="../../modules/registrar/check_agreement_status.php"><i class="fas fa-file-contract"></i> <span
                        data-en="Check Cost Share Agreement" data-am="የወጪ መጋራት ስምምነት ያረጋግጡ">Check Cost Share
                        Agreement</span></a></li> <!-- 8. view feedback -->
            <li><a href="../../modules/registrar/view_feedback.php"><i class="fas fa-comments"></i> <span
                        data-en="View Feedback" data-am="አስተያየቶችን ይመልከቱ">View Feedback</span></a></li>
            <!-- 9. Update Account -->

            <li><a href="../../modules/registrar/update_account.php"><i class="fas fa-user-cog"></i> <span
                        data-en="Update Account" data-am="መለያ አዘምን">Update Account</span></a></li>

            <!-- 10. Logout is shared below -->

        <?php
elseif ($_SESSION['role'] === 'cost_sharing_pro'): ?>
            <li><a href="../../modules/cost_sharing/index.php"><i class="fas fa-tachometer-alt"></i> <span
                        data-en="Dashboard" data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="../../modules/cost_sharing/approve_cost_share.php"><i class="fas fa-check-double"></i> <span
                        data-en="Verify Agreements" data-am="ውሎችን ያረጋግጡ">Verify Agreements</span></a></li>
            <li><a href="../../modules/cost_sharing/update_cost_share.php"><i class="fas fa-user-check"></i> <span
                        data-en="Manage Cost Share" data-am="የወጪ መጋራት አዘምን">Manage Cost Share</span></a></li>
            <li><a href="../../modules/cost_sharing/report_cost_share.php"><i class="fas fa-chart-line"></i> <span
                        data-en="Report Cost Share" data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>
            <li><a href="../../modules/cost_sharing/view_student_list.php"><i class="fas fa-file-signature"></i> <span
                        data-en="View Student List" data-am="የተማሪ ዝርዝር ይመልከቱ">View Student List</span></a></li>
            <li><a href="../../modules/cost_sharing/manage_tuition_rates.php"><i class="fas fa-money-bill-wave"></i> <span
                        data-en="manage tuition rates" data-am="የትምህርት ክፍያ መጠን አስተዳድር">manage tuition rates</span></a></li>
            <li><a href="../../modules/cost_sharing/notices.php"><i class="fas fa-bullhorn"></i> <span data-en="Notices"
                        data-am="ማስታወቂያዎች">Notices</span></a> </li>
            <li><a href="../../modules/cost_sharing/view_feedback.php"><i class="fas fa-comments"></i> <span
                        data-en="View Feedback" data-am="አስተያየቶችን ይመልከቱ">View Feedback</span></a></li>

            <li><a href="../../modules/cost_sharing/update_account.php"><i class="fas fa-user-cog"></i> <span
                        data-en="Update Account" data-am="መለያ አዘምን">Update Account</span></a></li>

        <?php
elseif ($_SESSION['role'] === 'transcript_pro'): ?>
            <li><a href="../../modules/transcript/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span
                        data-en="Dashboard" data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="../../modules/transcript/issue_document.php"><i class="fas fa-file-signature"></i> <span
                        data-en="Give Document" data-am="ሰነድ ይስጡ">Give Document</span></a></li>
            <li><a href="../../modules/transcript/accept_cost_share.php"><i class="fas fa-hand-holding-usd"></i> <span
                        data-en="Accept Cost Share" data-am="ወጪ መጋራት ይቀበሉ">Accept Cost Share</span></a></li>
            <li><a href="../../modules/transcript/report_cost_share.php"><i class="fas fa-chart-line"></i> <span
                        data-en="Report Cost Share" data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>
            <li><a href="../../modules/transcript/update_account.php"><i class="fas fa-user-cog"></i> <span
                        data-en="Update Account" data-am="መለያ አዘምን">Update Account</span></a></li>

        <?php
elseif ($_SESSION['role'] == 'admin'): ?>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span data-en="Dashboard"
                        data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="create_account.php"><i class="fas fa-user-plus"></i> <span data-en="Create Account"
                        data-am="መለያ ፍጠር">Create Account</span></a></li>
            <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> <span data-en="Manage Accounts"
                        data-am="መለያዎችን አስተዳድር">Manage Accounts</span></a></li>
            <li><a href="view_students.php"><i class="fas fa-list"></i> <span data-en="Download Student List"
                        data-am="የተማሪ ዝርዝር ያውርዱ">Download Student List</span></a></li>
            <li><a href="report_cost_share.php"><i class="fas fa-chart-line"></i> <span data-en="Report Cost Share"
                        data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>
            <li><a href="feedback_list.php"><i class="fas fa-comments"></i> <span data-en="View Feedback"
                        data-am="አስተያየቶችን ይመልከቱ">View Feedback</span></a></li>
            <li><a href="audit_logs.php"><i class="fas fa-shield-alt"></i> <span data-en="Audit Logs" data-am="ኦዲት ሎግ">Audit
                        Logs</span></a></li>
            <li><a href="suspicious_activity.php"><i class="fas fa-exclamation-triangle"></i> <span
                        data-en="Suspicious Activity" data-am="አጠራጣሪ እንቅስቃሴ">Suspicious Activity</span></a></li>

        <?php
elseif ($_SESSION['role'] === 'academic_vp'): ?>
            <li><a href="../../modules/academic_vp/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span
                        data-en="Dashboard" data-am="ዳሽቦርድ">Dashboard</span></a></li>
            <li><a href="../../modules/academic_vp/view_requested_document.php"><i class="fas fa-envelope-open-text"></i> <span
                        data-en="Send Referral Letter" data-am="የማጣቀሻ ደብዳቤ ላክ">Send Referral Letter</span></a></li>
            <li><a href="../../modules/academic_vp/report_cost_share.php"><i class="fas fa-chart-line"></i> <span
                        data-en="Report Cost Share" data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span></a></li>
            <li><a href="../../modules/academic_vp/update_account.php"><i class="fas fa-user-cog"></i> <span
                        data-en="Update Account" data-am="መለያ አዘምን">Update Account</span></a></li>

        <?php
endif; ?>

        <!-- Shared Links for other roles (Department, Registrar, etc. can be added here) -->

        <li><a href="javascript:void(0)" class="logout-btn" onclick="showLogoutModal()"><i
                    class="fas fa-sign-out-alt"></i> <span data-en="Logout" data-am="ውጣ">Logout</span></a></li>
    </ul>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div style="margin-bottom: 20px;">
            <i class="fas fa-sign-out-alt" style="font-size: 48px; color: #dc3545;"></i>
        </div>
        <h3 id="logoutModalTitle" data-en="Confirm Logout" data-am="መውጣትን ያረጋግጡ">Confirm Logout</h3>
        <p id="logoutModalMessage" data-en="Are you sure you want to logout?" data-am="እርግጠኛ ነዎት መውጣት ይፈልጋሉ?"
            style="margin: 20px 0; font-size: 16px;">
            Are you sure you want to logout?
        </p>
        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
            <button onclick="confirmLogout()" class="btn-primary"
                style="background-color: #dc3545; border: none; padding: 10px 30px; cursor: pointer;">
                <i class="fas fa-check"></i> <span data-en="Yes, Logout" data-am="አዎ፣ ውጣ">Yes, Logout</span>
            </button>
            <button onclick="closeLogoutModal()" class="btn-secondary"
                style="background-color: #6c757d; border: none; padding: 10px 30px; cursor: pointer; color: white;">
                <i class="fas fa-times"></i> <span data-en="Cancel" data-am="ሰርዝ">Cancel</span>
            </button>
        </div>
    </div>
</div>

<script>
    function showLogoutModal() {
        document.getElementById('logoutModal').style.display = 'flex';
    }

    function closeLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
    }

    function confirmLogout() {
        window.location.href = '../../logout.php?role=<?php echo $_SESSION['role'] ?? ''; ?>';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('logoutModal');
        if (event.target === modal) {
            closeLogoutModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLogoutModal();
        }
    });

    document.addEventListener("DOMContentLoaded", function() {
        const currentUrl = window.location.href.split('#')[0].split('?')[0]; // Ignore URL hashes and queries for base match
        const sidebarLinks = document.querySelectorAll('.sidebar .nav-links a:not(.logout-btn)');
        
        sidebarLinks.forEach(link => {
            const rawHref = link.getAttribute('href');
            // Skip dropdown toggle links which have href="#"
            if (!rawHref || rawHref === '#' || rawHref.startsWith('javascript:')) return;

            const linkUrl = link.href.split('#')[0].split('?')[0];
            
            // Highlight if exact match of base URL
            if (currentUrl === linkUrl) {
                link.classList.add('active');
                
                // If it's a dropdown menu item, open its parent menu
                const submenu = link.closest('.sidebar-submenu');
                if (submenu) {
                    submenu.classList.add('show');
                    const dropdown = submenu.closest('.sidebar-dropdown');
                    if (dropdown) {
                        dropdown.classList.add('active');
                    }
                }
            }
        });
    });
</script>