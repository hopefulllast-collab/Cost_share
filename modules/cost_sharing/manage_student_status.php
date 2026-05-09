<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $student_user_id = $_POST['student_user_id']; // This is users.id
    $new_status = $_POST['status'];

    // Update students table status (academic status, not account status)
    $stmt = $pdo->prepare("UPDATE students SET status = :s WHERE user_id = :id");
    $stmt->execute([':s' => $new_status, ':id' => $student_user_id]);

    $_SESSION["flash_success"] = "<span data-en='Student status updated to " . $new_status . "' data-am='የተማሪ ሁኔታ ወደ " . $new_status . " ተዘምኗል'>Student status updated to " . $new_status . "</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// Search for students
$students = [];
if (isset($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.username, s.status, s.student_id 
                           FROM users u 
                           JOIN students s ON u.id = s.user_id 
                           WHERE u.username LIKE :s OR u.first_name LIKE :s OR s.student_id LIKE :s");
    $stmt->execute([':s' => $search]);
    $students = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Student Status - DMU" data-am="የተማሪ ሁኔታ ያስተዳድሩ - ዲ.ማ.ዩ">Manage Student Status - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Manage Student Status" data-am="የተማሪ ሁኔታ ያስተዳድሩ">Manage Student Status</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

            <?php if ($msg)
                echo "<div class='success-msg'>$msg</div>"; ?>

            <div class="card">
                <form method="GET" style="display:flex; gap:10px;">
                    <input type="text" name="search" placeholder="Search by Name or ID..." required data-en="Search by Name or ID..." data-en-placeholder="Search by Name or ID..." data-am-placeholder="በስም ወይም በመለያ ይፈልጉ..." value="<?php echo $_GET['search'] ?? ''; ?>">
                    <button type="submit" class="btn-primary" data-en="Search" data-am="ፈልግ">Search</button>
                </form>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card mt-20">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="ID" data-am="መለያ">ID</th>
                                <th data-en="Current Status" data-am="የአሁኑ ሁኔታ">Current Status</th>
                                <th data-en="Action" data-am="ድርጊት">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($s['student_id']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_map = [
                                            'active' => 'ንቁ',
                                            'suspended' => 'ታግዷል',
                                            'withdrawn' => 'ያቋረጠ',
                                            'graduated' => 'የተመረቀ'
                                        ];
                                        $status_key = strtolower($s['status']);
                                        $status_am = $status_map[$status_key] ?? $s['status'];
                                        ?>
                                        <span data-en="<?php echo $s['status']; ?>"
                                            data-am="<?php echo $status_am; ?>">
                                            <?php echo $s['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:flex; gap:5px;">
                                            <input type="hidden" name="student_user_id" value="<?php echo $s['id']; ?>">
                                            <select name="status">
                                                <option value="active" <?php echo $s['status'] == 'active' ? 'selected' : ''; ?>
                                                    data-en="Active" data-am="ንቁ">Active</option>
                                                <option value="suspended" <?php echo $s['status'] == 'suspended' ? 'selected' : ''; ?> 
                                                    data-en="Suspended" data-am="ታግዷል">Suspended</option>
                                                <option value="withdrawn" <?php echo $s['status'] == 'withdrawn' ? 'selected' : ''; ?>
                                                    data-en="Withdrawn" data-am="ያቋረጠ">Withdrawn</option>
                                            </select>
                                            <button type="submit" name="update_status"
                                                class="btn-sm btn-primary" data-en="Update" data-am="አዘምን">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>