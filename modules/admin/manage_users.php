<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/audit_logger.php';
checkAuth(['admin']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_error"]);

// Handle Update Own Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_own_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "<span data-en='Please fill all fields.' data-am='እባክዎ ሁሉንም ቦታዎች ይሙሉ።'>Please fill all fields.</span>";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "<span data-en='Passwords do not match.' data-am='የይለፍ ቃሎች አይዛመዱም።'>Passwords do not match.</span>";
    } elseif (strlen($new_pass) < 4) {
        $error = "<span data-en='Password must be at least 4 characters.' data-am='የይለፍ ቃል ቢያንስ 4 ቁምፊዎች መሆን አለበት።'>Password must be at least 4 characters.</span>";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        logAudit($pdo, 'PASSWORD_UPDATED', 'Admin updated own password');
        $_SESSION["flash_success"] = "<span data-en='Password updated successfully.' data-am='የይለፍ ቃል በተሳካ ሁኔታ ተዘምኗል።'>Password updated successfully.</span>";
        header("Location: " . $_SERVER["PHP_SELF"] . (isset($_GET['role']) ? '?role=' . $_GET['role'] : ''));
        exit();
    }
}

// Handle Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action == 'delete') {
        try {
            $pdo->beginTransaction();
            
            $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtRole->execute([$id]);
            $userRole = $stmtRole->fetchColumn();

            if ($userRole === 'student') {
                $pdo->prepare("DELETE FROM cost_sharing_agreements WHERE student_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM feedback WHERE student_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM official_transcript WHERE student_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$id]);
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            logAudit($pdo, 'USER_DELETED', 'Deleted user ID: ' . $id);
            $_SESSION["flash_success"] = "<span data-en='Account deleted successfully.' data-am='መለያው በተሳካ ሁኔታ ተሰርዟል።'>Account deleted.</span>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION["flash_error"] = "<span data-en='Cannot delete account. Ensure related records are removed.' data-am='መለያን መሰረዝ አልተቻለም። ተዛማጅ መረጃዎች መወገዳቸውን ያረጋግጡ።'>Cannot delete account. Ensure related records are removed.</span>";
        }
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } elseif ($action == 'enable') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        logAudit($pdo, 'USER_ENABLED', 'Enabled user ID: ' . $id);
        $_SESSION["flash_success"] = "<span data-en='User enabled.' data-am='ተጠቃሚው ነቅቷል'>User enabled.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } elseif ($action == 'disable') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$id]);
        logAudit($pdo, 'USER_DISABLED', 'Disabled user ID: ' . $id);
        $_SESSION["flash_success"] = "<span data-en='User disabled.' data-am='ተጠቃሚው ተሰናክሏል'>User disabled.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}


// Filter Logic
$where = "";
$params = [];
$role_filter = $_GET['role'] ?? '';
$student_id_filter = $_GET['student_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$conditions = [];
if ($role_filter) {
    $conditions[] = "u.role = :role";
    $params[':role'] = $role_filter;
}

if ($search && $role_filter == 'student') {
    $conditions[] = "s.student_id LIKE :student_id";
    $params[':student_id'] = '%' . $search . '%';
}

if (!empty($conditions)) {
    $where = "WHERE " . implode(" AND ", $conditions);
}

// Fetch students eligible for deletion (Transfer-Out or Original Document delivered)
$deletable_query = $pdo->query("
    SELECT DISTINCT ot.student_id as user_id, ot.request_type, ot.status as doc_status
    FROM official_transcript ot 
    WHERE ot.request_type IN ('Transfer-Out', 'Original') 
    AND ot.status = 'Delivered'
")->fetchAll(PDO::FETCH_ASSOC);

// Build lookup: user_id => request_type
$deletable_users = [];
foreach ($deletable_query as $d) {
    $deletable_users[$d['user_id']] = $d['request_type'];
}

// Handle 'deletable' filter
$show_deletable = isset($_GET['filter']) && $_GET['filter'] === 'deletable';

// Query with LEFT JOIN to get student_id for students
$query = "SELECT u.*, s.student_id 
          FROM users u 
          LEFT JOIN students s ON u.id = s.user_id 
          $where 
          ORDER BY u.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If deletable filter is active, only show eligible students
if ($show_deletable) {
    $users = array_filter($all_users, function($u) use ($deletable_users) {
        return isset($deletable_users[$u['id']]);
    });
} else {
    $users = $all_users;
}

$departments = $pdo->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$deletable_count = count($deletable_users);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Accounts - DMU" data-am="የመለያዎች አያያዝ - DMU">Manage Accounts - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Manage Accounts" data-am="የመለያዎች አያያዝ">Manage Accounts</h2>
                    <div class="filter-area">
                        <form method="GET" id="filterForm">
                            <select name="role" id="roleFilter" onchange="toggleStudentIdFilter()">
                                <option value="" data-en="All Roles" data-am="ሁሉንም">All Roles</option>
                                <option value="student" data-en="Student" data-am="ተማሪ" <?php if ($role_filter == 'student')
                                    echo 'selected'; ?>>Student
                                </option>
                                <option value="department_head" data-en="Dept Head" data-am="የትምህርት ክፍሉ ኃላፊ" <?php if ($role_filter == 'department_head')
                                    echo 'selected'; ?>>Dept Head</option>
                                <option value="registrar" data-en="Registrar" data-am="የመመዝገቢያ ጽሕፈት" <?php if ($role_filter == 'registrar')
                                    echo 'selected'; ?>>Registrar
                                </option>
                                <option value="cost_sharing_pro" data-en="Cost Sharing Pro" data-am="የወጪ መጋራት ባለሙያ"
                                    <?php if ($role_filter == 'cost_sharing_pro')
                                        echo 'selected'; ?>>Cost Sharing Pro
                                </option>
                                <option value="transcript_pro" data-en="Transcript Pro" data-am="የትራንስክሪፕት ባለሙያ" <?php if ($role_filter == 'transcript_pro')
                                    echo 'selected'; ?>>Transcript Pro</option>
                                <option value="academic_vp" data-en="Academic VP" data-am="የአካዳሚክ ም/ፕሬዚዳንት" <?php if ($role_filter == 'academic_vp')
                                    echo 'selected'; ?>>Academic VP</option>
                                <option value="admin" data-en="Admin" data-am="አስተዳዳሪ" <?php if ($role_filter == 'admin')
                                    echo 'selected'; ?>>Admin</option>
                            </select>

                            <div id="studentIdFilterDiv"
                                style="display: <?php echo ($role_filter == 'student') ? 'inline-block' : 'none'; ?>; margin-left: 10px;">
                                <input type="text" name="search" id="studentIdInput"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search by Student ID..." data-en="Search by Student ID..."
                                    data-en-placeholder="Search by Student ID..." data-am-placeholder="በተማሪ መለያ ይፈልጉ..."
                                    style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <button type="submit" class="btn-sm btn-primary" style="margin-left: 5px;">
                                    <i class="fas fa-search"></i> <span data-en="Search" data-am="ፈልግ">Search</span>
                                </button>
                                <?php if ($search): // Changed from $student_id_filter to $search ?>
                                    <a href="?role=student" class="btn-sm btn-secondary" style="margin-left: 5px;">
                                        <i class="fas fa-times"></i> <span data-en="Clear" data-am="አጽዳ">Clear</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($deletable_count > 0): ?>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; padding:10px 16px; background:#fff5f5; border:1px solid #fecaca; border-radius:10px;">
                    <i class="fas fa-info-circle" style="color:#dc2626; font-size:1rem;"></i>
                    <span style="font-size:0.88rem; color:#991b1b; font-weight:500;" data-en="<?php echo $deletable_count; ?> student(s) with delivered Transfer-Out / Original Document" data-am="<?php echo $deletable_count; ?> ተማሪ(ዎች) የተላለፉ ዝውውር / ኦሪጅናል ሰነድ"><?php echo $deletable_count; ?> student(s) with delivered Transfer-Out / Original Document</span>
                    <?php if (!$show_deletable): ?>
                        <a href="?filter=deletable" style="margin-left:auto; background:#dc2626; color:#fff; padding:6px 16px; border-radius:6px; text-decoration:none; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                            <i class="fas fa-filter"></i>
                            <span data-en="Show Only" data-am="ብቻ አሳይ">Show Only</span>
                        </a>
                    <?php else: ?>
                        <a href="manage_users.php" style="margin-left:auto; background:#6c757d; color:#fff; padding:6px 16px; border-radius:6px; text-decoration:none; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:5px;">
                            <i class="fas fa-times"></i>
                            <span data-en="Show All Users" data-am="ሁሉንም ተጠቃሚዎች አሳይ">Show All Users</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="Username" data-am="የተጠቃሚ ስም">Username</th>
                                <?php if ($role_filter == 'student'): ?>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                <?php endif; ?>
                                <th data-en="Role" data-am="ሚና">Role</th>
                                <th data-en="Status" data-am="ሁኔታ">Status</th>
                                <th data-en="Doc Status" data-am="የሰነድ ሁኔታ">Doc Status</th>
                                <th data-en="Actions" data-am="ድርጊቶች">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                    <td><?php echo $u['username']; ?></td>
                                    <?php if ($role_filter == 'student'): ?>
                                        <td><?php echo $u['student_id'] ?? 'N/A'; ?></td>
                                    <?php endif; ?>
                                    <td><?php echo $u['role']; ?></td>
                                    <td>
                                        <span
                                            class="status-badge <?php echo $u['status']; ?>"><?php echo $u['status']; ?></span>
                                    </td>
                                    <td>
                                        <?php if (isset($deletable_users[$u['id']])): ?>
                                            <?php $doc_type = $deletable_users[$u['id']]; ?>
                                            <span style="background:#fef2f2; color:#dc2626; padding:3px 8px; border-radius:4px; font-size:0.78rem; font-weight:600; border:1px solid #fecaca;">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <span data-en="<?php echo $doc_type; ?> - Delivered" data-am="<?php echo $doc_type === 'Transfer-Out' ? 'ዝውውር' : 'ኦሪጅናል'; ?> - ተሰጥቷል">
                                                    <?php echo $doc_type; ?> - Delivered
                                                </span>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-size:0.8rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['status'] == 'active'): ?>
                                            <a href="?action=disable&id=<?php echo $u['id']; ?>" class="btn-sm btn-warning"
                                                title="Disable">
                                                <i class="fas fa-ban"></i> <span data-en="Disable"
                                                    data-am="አሰናክል">Disable</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=enable&id=<?php echo $u['id']; ?>" class="btn-sm btn-success"
                                                title="Enable">
                                                <i class="fas fa-check"></i> <span data-en="Enable" data-am="አንቃ">Enable</span>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <a href="javascript:void(0);" class="btn-sm btn-info"
                                            onclick="openUpdatePasswordModal()"
                                            title="Update My Password" style="background-color: #17a2b8; color: white;">
                                            <i class="fas fa-key"></i> <span data-en="Update Password" data-am="የይለፍ ቃል አዘምን">Update Password</span>
                                        </a>
                                        <?php endif; ?>

                                        <a href="javascript:void(0);" class="btn-sm btn-danger"
                                            onclick="var c=this.nextElementSibling; c.style.display='inline'; this.style.display='none';"
                                            title="Delete">
                                            <i class="fas fa-trash"></i> <span data-en="Delete" data-am="ሰርዝ">Delete</span>
                                        </a>
                                        <span style="display:none;">
                                            <span style="font-size:12px; color:#856404; font-weight:bold;"
                                                data-en="Delete this account?" data-am="ይህን መለያ ይሰርዝ?">Delete?</span>
                                            <a href="?action=delete&id=<?php echo $u['id']; ?>" class="btn-sm btn-danger"
                                                style="margin-left:5px;" data-en="Yes" data-am="አዎ">Yes</a>
                                            <a href="javascript:void(0);" class="btn-sm btn-secondary"
                                                style="margin-left:3px;"
                                                onclick="this.parentElement.style.display='none'; this.parentElement.previousElementSibling.style.display='inline';"
                                                data-en="No" data-am="አይ">No</a>
                                        </span>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>

        function toggleStudentIdFilter() {
            const roleSelect = document.getElementById('roleFilter');
            const studentIdDiv = document.getElementById('studentIdFilterDiv');
            const studentIdInput = document.getElementById('studentIdInput');

            if (roleSelect.value === 'student') {
                studentIdDiv.style.display = 'inline-block';
                studentIdInput.placeholder = 'Search by Student ID...';
            } else {
                studentIdDiv.style.display = 'none';
                studentIdInput.value = '';
                // Auto-submit when changing away from student role
                document.getElementById('filterForm').submit();
            }
        }
    </script>

    <!-- Update My Password Modal -->
    <div id="updatePasswordModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 450px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-key" style="font-size: 48px; color: #17a2b8;"></i>
            </div>
            <h3 data-en="Update My Password" data-am="የእኔን የይለፍ ቃል አዘምን" style="text-align: center;">Update My Password</h3>
            <form method="POST" id="updatePasswordForm">
                <div class="form-group">
                    <label data-en="New Password" data-am="አዲስ የይለፍ ቃል">New Password</label>
                    <input type="password" name="new_password" required minlength="4"
                        placeholder="Enter new password" data-en-placeholder="Enter new password"
                        data-am-placeholder="አዲስ የይለፍ ቃል ያስገቡ">
                </div>
                <div class="form-group">
                    <label data-en="Confirm Password" data-am="የይለፍ ቃል ያረጋግጡ">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="4"
                        placeholder="Confirm new password" data-en-placeholder="Confirm new password"
                        data-am-placeholder="አዲሱን የይለፍ ቃል ያረጋግጡ">
                </div>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <button type="submit" name="update_own_password" class="btn-primary"
                        style="background-color: #17a2b8; border: none; padding: 10px 30px; cursor: pointer;">
                        <i class="fas fa-check"></i> <span data-en="Update Password" data-am="የይለፍ ቃል አዘምን">Update Password</span>
                    </button>
                    <button type="button" onclick="closeUpdatePasswordModal()" class="btn-secondary"
                        style="background-color: #6c757d; border: none; padding: 10px 30px; cursor: pointer; color: white;">
                        <i class="fas fa-times"></i> <span data-en="Cancel" data-am="ሰርዝ">Cancel</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdatePasswordModal() {
            document.getElementById('updatePasswordModal').style.display = 'flex';
        }

        function closeUpdatePasswordModal() {
            document.getElementById('updatePasswordModal').style.display = 'none';
            document.getElementById('updatePasswordForm').reset();
        }

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('updatePasswordModal');
            if (event.target === modal) {
                closeUpdatePasswordModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeUpdatePasswordModal();
            }
        });
    </script>

    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>