<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = buildWhereClause();
    $query = "SELECT a.id, a.username, a.role, a.action, a.description, a.ip_address, a.created_at FROM audit_logs a " . $where['sql'] . " ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($where['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
    fputcsv($out, ['ID', 'User', 'Role', 'Action', 'Description', 'IP Address', 'Date/Time']);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function buildWhereClause()
{
    $conditions = [];
    $params = [];

    if (!empty($_GET['date_from'])) {
        $conditions[] = "a.created_at >= :date_from";
        $params[':date_from'] = $_GET['date_from'] . ' 00:00:00';
    }
    if (!empty($_GET['date_to'])) {
        $conditions[] = "a.created_at <= :date_to";
        $params[':date_to'] = $_GET['date_to'] . ' 23:59:59';
    }
    if (!empty($_GET['role_filter'])) {
        $conditions[] = "a.role = :role";
        $params[':role'] = $_GET['role_filter'];
    }
    if (!empty($_GET['action_filter'])) {
        $conditions[] = "a.action = :action";
        $params[':action'] = $_GET['action_filter'];
    }
    if (!empty($_GET['search'])) {
        $conditions[] = "(a.username LIKE :search OR a.description LIKE :search2 OR a.ip_address LIKE :search3)";
        $params[':search'] = '%' . $_GET['search'] . '%';
        $params[':search2'] = '%' . $_GET['search'] . '%';
        $params[':search3'] = '%' . $_GET['search'] . '%';
    }

    $sql = '';
    if (!empty($conditions)) {
        $sql = "WHERE " . implode(" AND ", $conditions);
    }

    return ['sql' => $sql, 'params' => $params];
}

// --- Pagination ---
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = buildWhereClause();

// Count total
$countQuery = "SELECT COUNT(*) FROM audit_logs a " . $where['sql'];
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($where['params']);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch rows
$query = "SELECT a.* FROM audit_logs a " . $where['sql'] . " ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($where['params']);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get current filter values
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';
$action_filter = $_GET['action_filter'] ?? '';
$search = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Audit Logs - DMU" data-am="ኦዲት ሎግ - DMU">Audit Logs - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .audit-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .audit-filters .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .audit-filters label {
            font-size: 12px;
            font-weight: 600;
            color: #555;
        }

        .audit-filters input,
        .audit-filters select {
            padding: 7px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 13px;
            min-width: 130px;
        }

        .audit-filters .filter-actions {
            display: flex;
            gap: 6px;
            align-items: flex-end;
        }

        .audit-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .audit-stats .stat-card {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 150px;
            text-align: center;
        }

        .audit-stats .stat-card h4 {
            margin: 0 0 5px;
            font-size: 13px;
            opacity: 0.85;
        }

        .audit-stats .stat-card .stat-number {
            font-size: 24px;
            font-weight: 700;
        }

        .audit-stats .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #0f3460, #533483);
        }

        .audit-stats .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #2d6a4f, #40916c);
        }

        .audit-stats .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #6a040f, #d00000);
        }

        .action-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .action-LOGIN_SUCCESS {
            background: #d4edda;
            color: #155724;
        }

        .action-LOGIN_FAILED {
            background: #f8d7da;
            color: #721c24;
        }

        .action-LOGOUT {
            background: #e2e3f1;
            color: #383d6e;
        }

        .action-USER_CREATED {
            background: #cce5ff;
            color: #004085;
        }

        .action-USER_DELETED {
            background: #f8d7da;
            color: #721c24;
        }

        .action-USER_ENABLED,
        .action-USER_DISABLED {
            background: #fff3cd;
            color: #856404;
        }

        .action-DEPT_HEAD_REASSIGNED {
            background: #d1ecf1;
            color: #0c5460;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
        }

        .pagination .active {
            background: #1a1a2e;
            color: #fff;
            border-color: #1a1a2e;
        }

        .pagination a:hover {
            background: #e9ecef;
        }

        .export-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .export-btn:hover {
            background: #218838;
        }

        .table-list td {
            font-size: 13px;
        }

        .table-list th {
            font-size: 13px;
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #ccc;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Audit Logs / System Usage Report" data-am="ኦዲት ሎግ / የስርዓት አጠቃቀም ሪፖርት">Audit Logs /
                        System Usage Report</h2>
                </div>

                <!-- Stats Cards -->
                <?php
                $totalAll = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
                $todayCount = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                $loginCount = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'LOGIN_SUCCESS'")->fetchColumn();
                $failedCount = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'LOGIN_FAILED'")->fetchColumn();
                ?>
                <div class="audit-stats">
                    <div class="stat-card">
                        <h4 data-en="Total Logs" data-am="ጠቅላላ ሎግ">Total Logs</h4>
                        <div class="stat-number"><?php echo number_format($totalAll); ?></div>
                    </div>
                    <div class="stat-card">
                        <h4 data-en="Today's Activity" data-am="የዛሬ እንቅስቃሴ">Today's Activity</h4>
                        <div class="stat-number"><?php echo number_format($todayCount); ?></div>
                    </div>
                    <div class="stat-card">
                        <h4 data-en="Total Logins" data-am="ጠቅላላ ግብኣት">Total Logins</h4>
                        <div class="stat-number"><?php echo number_format($loginCount); ?></div>
                    </div>
                    <div class="stat-card">
                        <h4 data-en="Failed Logins" data-am="ያልተሳካ ግብኣት">Failed Logins</h4>
                        <div class="stat-number"><?php echo number_format($failedCount); ?></div>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="audit-filters" id="filterForm">
                    <div class="filter-group">
                        <label data-en="Date From" data-am="ከቀን">Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label data-en="Date To" data-am="እስከ ቀን">Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label data-en="Role" data-am="ሚና">Role</label>
                        <select name="role_filter">
                            <option value="" data-en="All Roles" data-am="ሁሉም ሚናዎች">All Roles</option>
                            <option value="admin" <?php if ($role_filter === 'admin') echo 'selected'; ?>
                                data-en="Admin" data-am="አስተዳዳሪ">Admin</option>
                            <option value="student" <?php if ($role_filter === 'student') echo 'selected'; ?>
                                data-en="Student" data-am="ተማሪ">Student</option>
                            <option value="registrar" <?php if ($role_filter === 'registrar') echo 'selected'; ?>
                                data-en="Registrar" data-am="ሬጂስትራር">Registrar</option>
                            <option value="department_head"
                                <?php if ($role_filter === 'department_head') echo 'selected'; ?> data-en="Dept Head"
                                data-am="የመምሪያ ኃላፊ">Dept Head</option>
                            <option value="cost_sharing_pro"
                                <?php if ($role_filter === 'cost_sharing_pro') echo 'selected'; ?>
                                data-en="Cost Sharing Pro" data-am="የወጪ መጋራት ባለሙያ">Cost Sharing Pro</option>
                            <option value="transcript_pro"
                                <?php if ($role_filter === 'transcript_pro') echo 'selected'; ?>
                                data-en="Transcript Pro" data-am="የትራንስክሪፕት ባለሙያ">Transcript Pro</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label data-en="Action" data-am="ድርጊት">Action</label>
                        <select name="action_filter">
                            <option value="" data-en="All Actions" data-am="ሁሉም ድርጊቶች">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>"
                                    <?php if ($action_filter === $act) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label data-en="Search" data-am="ፈልግ">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="User, description, IP..." data-en-placeholder="User, description, IP..."
                            data-am-placeholder="ተጠቃሚ፣ መግለጫ፣ IP...">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-primary"
                            style="padding: 7px 16px; font-size: 13px; background: #1a1a2e; border:none; color:#fff; border-radius:5px; cursor:pointer;">
                            <i class="fas fa-filter"></i> <span data-en="Filter" data-am="አጣራ">Filter</span>
                        </button>
                        <a href="audit_logs.php" class="btn-secondary"
                            style="padding: 7px 16px; font-size: 13px; border-radius:5px; text-decoration:none; color:#333; background:#e9ecef; border:1px solid #ccc;">
                            <i class="fas fa-redo"></i> <span data-en="Reset" data-am="ዳግም አስጀምር">Reset</span>
                        </a>
                    </div>
                </form>

                <!-- Top Actions -->
                <div class="top-actions">
                    <span style="font-size:13px; color:#666;">
                        <span data-en="Showing" data-am="በማሳየት ላይ">Showing</span>
                        <strong><?php echo count($logs); ?></strong>
                        <span data-en="of" data-am="ከ">of</span>
                        <strong><?php echo number_format($totalRows); ?></strong>
                        <span data-en="records" data-am="ምዝገባዎች">records</span>
                        (<?php echo $page; ?>/<?php echo $totalPages; ?>)
                    </span>
                    <?php
                    // Build export URL preserving filters
                    $exportParams = $_GET;
                    $exportParams['export'] = 'csv';
                    unset($exportParams['page']);
                    $exportUrl = 'audit_logs.php?' . http_build_query($exportParams);
                    ?>
                    <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="export-btn">
                        <i class="fas fa-file-csv"></i> <span data-en="Export CSV" data-am="CSV ላክ">Export CSV</span>
                    </a>
                </div>

                <!-- Table -->
                <div class="card">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <p data-en="No audit logs found." data-am="ምንም ኦዲት ሎግ አልተገኘም።">No audit logs found.</p>
                        </div>
                    <?php else: ?>
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="#" data-am="#">#</th>
                                    <th data-en="User" data-am="ተጠቃሚ">User</th>
                                    <th data-en="Role" data-am="ሚና">Role</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                    <th data-en="Description" data-am="መግለጫ">Description</th>
                                    <th data-en="IP Address" data-am="IP አድራሻ">IP Address</th>
                                    <th data-en="Date/Time" data-am="ቀን/ሰዓት">Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></td>
                                        <td><span
                                                class="status-badge"><?php echo htmlspecialchars($log['role'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td><span
                                                class="action-badge action-<?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                        </td>
                                        <td style="max-width:300px; word-wrap:break-word;">
                                            <?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                        <td><code><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></code></td>
                                        <td style="white-space:nowrap;">
                                            <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = 'audit_logs.php?' . http_build_query($queryParams);

                        if ($page > 1): ?>
                            <a href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>" data-en="Prev" data-am="ቀዳሚ">Prev</a>
                        <?php endif;

                        // Show page range
                        $startPage = max(1, $page - 3);
                        $endPage = min($totalPages, $page + 3);

                        if ($startPage > 1): ?>
                            <a href="<?php echo $baseUrl . '&page=1'; ?>">1</a>
                            <?php if ($startPage > 2): ?><span>...</span><?php endif;
                        endif;

                        for ($i = $startPage; $i <= $endPage; $i++):
                            if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl . '&page=' . $i; ?>"><?php echo $i; ?></a>
                            <?php endif;
                        endfor;

                        if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><span>...</span><?php endif; ?>
                            <a href="<?php echo $baseUrl . '&page=' . $totalPages; ?>"><?php echo $totalPages; ?></a>
                        <?php endif;

                        if ($page < $totalPages): ?>
                            <a href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>" data-en="Next"
                                data-am="ቀጣይ">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
