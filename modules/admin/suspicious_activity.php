<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/audit_logger.php';
checkAuth(['admin']);

// Log page view
logAudit($pdo, 'VIEW_SUSPICIOUS_ACTIVITY', 'Admin viewed suspicious activity report');

// --- Configuration: Thresholds ---
$FAILED_LOGIN_THRESHOLD = 3;       // Failed logins within the time window
$FAILED_LOGIN_WINDOW = 30;          // Minutes
$UNUSUAL_HOUR_START = 23;           // 11 PM
$UNUSUAL_HOUR_END = 5;              // 5 AM
$RAPID_ACTION_COUNT = 10;           // Actions within rapid window
$RAPID_ACTION_WINDOW = 5;           // Minutes
$MULTI_IP_THRESHOLD = 2;            // Different IPs for same user in a day
$LOOKBACK_DAYS = 7;                 // How far back to analyze

// --- 1. Multiple Failed Login Attempts (Brute Force Detection) ---
$failed_logins = $pdo->prepare("
    SELECT username, ip_address, COUNT(*) as attempt_count, 
           MIN(created_at) as first_attempt, MAX(created_at) as last_attempt
    FROM audit_logs 
    WHERE action = 'LOGIN_FAILED' 
      AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
    GROUP BY username, ip_address
    HAVING attempt_count >= :threshold
    ORDER BY attempt_count DESC
");
$failed_logins->execute([':days' => $LOOKBACK_DAYS, ':threshold' => $FAILED_LOGIN_THRESHOLD]);
$brute_force_alerts = $failed_logins->fetchAll(PDO::FETCH_ASSOC);

// --- 2. Logins at Unusual Hours ---
$unusual_hours = $pdo->prepare("
    SELECT username, role, action, ip_address, created_at,
           HOUR(created_at) as login_hour
    FROM audit_logs 
    WHERE action = 'LOGIN_SUCCESS'
      AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
      AND (HOUR(created_at) >= :start OR HOUR(created_at) < :end)
    ORDER BY created_at DESC
    LIMIT 50
");
$unusual_hours->execute([':days' => $LOOKBACK_DAYS, ':start' => $UNUSUAL_HOUR_START, ':end' => $UNUSUAL_HOUR_END]);
$unusual_hour_alerts = $unusual_hours->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Rapid Successive Actions (Bot/Script Detection) ---
$rapid_actions = $pdo->prepare("
    SELECT user_id, username, role, COUNT(*) as action_count,
           MIN(created_at) as window_start, MAX(created_at) as window_end
    FROM audit_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
      AND user_id IS NOT NULL
    GROUP BY user_id, username, role, 
             FLOOR(UNIX_TIMESTAMP(created_at) / (:window * 60))
    HAVING action_count >= :threshold
    ORDER BY action_count DESC
    LIMIT 20
");
$rapid_actions->execute([
    ':days' => $LOOKBACK_DAYS,
    ':window' => $RAPID_ACTION_WINDOW,
    ':threshold' => $RAPID_ACTION_COUNT
]);
$rapid_action_alerts = $rapid_actions->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Multiple IPs for Same User (Account Sharing / Hijack) ---
$multi_ip = $pdo->prepare("
    SELECT user_id, username, role, 
           COUNT(DISTINCT ip_address) as ip_count,
           GROUP_CONCAT(DISTINCT ip_address SEPARATOR ', ') as ip_list,
           DATE(created_at) as activity_date
    FROM audit_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
      AND user_id IS NOT NULL
      AND action = 'LOGIN_SUCCESS'
    GROUP BY user_id, username, role, DATE(created_at)
    HAVING ip_count >= :threshold
    ORDER BY ip_count DESC
    LIMIT 20
");
$multi_ip->execute([':days' => $LOOKBACK_DAYS, ':threshold' => $MULTI_IP_THRESHOLD]);
$multi_ip_alerts = $multi_ip->fetchAll(PDO::FETCH_ASSOC);

// --- 5. Account Manipulation (Sensitive Admin Actions) ---
$sensitive_actions = $pdo->prepare("
    SELECT username, role, action, description, ip_address, created_at
    FROM audit_logs
    WHERE action IN ('USER_DELETED', 'USER_DISABLED', 'DEPT_HEAD_REASSIGNED')
      AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
    ORDER BY created_at DESC
    LIMIT 30
");
$sensitive_actions->execute([':days' => $LOOKBACK_DAYS]);
$sensitive_alerts = $sensitive_actions->fetchAll(PDO::FETCH_ASSOC);

// --- Total alert counts ---
$total_alerts = count($brute_force_alerts) + count($unusual_hour_alerts) + count($rapid_action_alerts) + count($multi_ip_alerts) + count($sensitive_alerts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Suspicious Activity - DMU" data-am="አጠራጣሪ እንቅስቃሴ - DMU">Suspicious Activity - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .alert-summary {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .alert-card {
            flex: 1;
            min-width: 160px;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .alert-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .alert-card h4 {
            margin: 0 0 6px;
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-card .count {
            font-size: 28px;
            font-weight: 700;
        }

        .alert-card.danger {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
        }

        .alert-card.warning {
            background: linear-gradient(135deg, #d35400, #e67e22);
        }

        .alert-card.info {
            background: linear-gradient(135deg, #2980b9, #3498db);
        }

        .alert-card.purple {
            background: linear-gradient(135deg, #6c3483, #8e44ad);
        }

        .alert-card.dark {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        .section-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            cursor: pointer;
            background: #f8f9fa;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
        }

        .section-header:hover {
            background: #edf2f7;
        }

        .section-header h3 {
            margin: 0;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
        }

        .badge-danger {
            background: #e74c3c;
        }

        .badge-warning {
            background: #e67e22;
        }

        .badge-info {
            background: #3498db;
        }

        .badge-purple {
            background: #8e44ad;
        }

        .badge-dark {
            background: #1a1a2e;
        }

        .section-body {
            padding: 0;
        }

        .section-body.collapsed {
            display: none;
        }

        .section-header .toggle-icon {
            transition: transform 0.3s;
        }

        .section-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .alert-table {
            width: 100%;
            border-collapse: collapse;
        }

        .alert-table th {
            background: #f1f5f9;
            padding: 10px 14px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .alert-table td {
            padding: 10px 14px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .alert-table tr:hover {
            background: #fafbfc;
        }

        .severity-high {
            color: #c0392b;
            font-weight: 700;
        }

        .severity-medium {
            color: #e67e22;
            font-weight: 600;
        }

        .severity-low {
            color: #2980b9;
            font-weight: 600;
        }

        .severity-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .severity-badge.high {
            background: #fde8e8;
            color: #c0392b;
        }

        .severity-badge.medium {
            background: #fef3e2;
            color: #d35400;
        }

        .severity-badge.low {
            background: #e8f4fd;
            color: #2980b9;
        }

        .ip-tag {
            display: inline-block;
            background: #edf2f7;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 1px;
        }

        .empty-alert {
            text-align: center;
            padding: 30px;
            color: #999;
        }

        .empty-alert i {
            font-size: 36px;
            color: #28a745;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .lookback-info {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #edf2f7;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            color: #555;
            margin-bottom: 15px;
        }

        .overall-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .overall-status.safe {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .overall-status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .overall-status.danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                    <h2 data-en="Suspicious Activity Monitor" data-am="አጠራጣሪ እንቅስቃሴ ክትትል">Suspicious Activity
                        Monitor</h2>
                </div>

                <div class="lookback-info">
                    <i class="fas fa-clock"></i>
                    <span data-en="Analyzing last" data-am="የመጨረሻውን በመተንተን ላይ">Analyzing last</span>
                    <strong><?php echo $LOOKBACK_DAYS; ?></strong>
                    <span data-en="days of activity" data-am="ቀናት እንቅስቃሴ">days of activity</span>
                </div>

                <!-- Overall Status -->
                <?php
                $statusClass = 'safe';
                $statusIcon = 'fa-check-circle';
                $statusEn = 'System is secure. No suspicious activity detected.';
                $statusAm = 'ስርዓቱ ደህና ነው። ምንም አጠራጣሪ እንቅስቃሴ አልተገኘም።';

                if (count($brute_force_alerts) > 0 || count($multi_ip_alerts) > 0) {
                    $statusClass = 'danger';
                    $statusIcon = 'fa-exclamation-triangle';
                    $statusEn = 'Critical: Potential security threats detected. Immediate review required.';
                    $statusAm = 'ወሳኝ: ሊሆኑ የሚችሉ የደህንነት ስጋቶች ተገኝተዋል። ፈጣን ግምገማ ያስፈልጋል።';
                } elseif (count($unusual_hour_alerts) > 0 || count($sensitive_alerts) > 0) {
                    $statusClass = 'warning';
                    $statusIcon = 'fa-exclamation-circle';
                    $statusEn = 'Warning: Some unusual activities detected. Please review.';
                    $statusAm = 'ማስጠንቀቂያ: አንዳንድ ያልተለመዱ እንቅስቃሴዎች ተገኝተዋል። እባክዎ ይገምግሙ።';
                }
                ?>
                <div class="overall-status <?php echo $statusClass; ?>">
                    <i class="fas <?php echo $statusIcon; ?>" style="font-size: 22px;"></i>
                    <span data-en="<?php echo $statusEn; ?>" data-am="<?php echo $statusAm; ?>">
                        <?php echo $statusEn; ?>
                    </span>
                </div>

                <!-- Summary Cards -->
                <div class="alert-summary">
                    <div class="alert-card danger">
                        <h4 data-en="Brute Force" data-am="ተደጋጋሚ ሙከራ">Brute Force</h4>
                        <div class="count"><?php echo count($brute_force_alerts); ?></div>
                    </div>
                    <div class="alert-card warning">
                        <h4 data-en="Unusual Hours" data-am="ያልተለመደ ሰዓት">Unusual Hours</h4>
                        <div class="count"><?php echo count($unusual_hour_alerts); ?></div>
                    </div>
                    <div class="alert-card info">
                        <h4 data-en="Rapid Actions" data-am="ፈጣን ድርጊቶች">Rapid Actions</h4>
                        <div class="count"><?php echo count($rapid_action_alerts); ?></div>
                    </div>
                    <div class="alert-card purple">
                        <h4 data-en="Multi-IP Login" data-am="ብዙ IP ግብኣት">Multi-IP Login</h4>
                        <div class="count"><?php echo count($multi_ip_alerts); ?></div>
                    </div>
                    <div class="alert-card dark">
                        <h4 data-en="Sensitive Actions" data-am="ስሱ ድርጊቶች">Sensitive Actions</h4>
                        <div class="count"><?php echo count($sensitive_alerts); ?></div>
                    </div>
                </div>

                <!-- 1. Brute Force Attempts -->
                <div class="section-card">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3>
                            <i class="fas fa-skull-crossbones" style="color:#c0392b;"></i>
                            <span data-en="Brute Force / Multiple Failed Logins"
                                data-am="ተደጋጋሚ ያልተሳካ የግብኣት ሙከራ">Brute Force / Multiple Failed Logins</span>
                            <span
                                class="badge badge-danger"><?php echo count($brute_force_alerts); ?></span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="section-body">
                        <?php if (empty($brute_force_alerts)): ?>
                            <div class="empty-alert">
                                <i class="fas fa-check-circle"></i>
                                <p data-en="No brute force attempts detected."
                                    data-am="ምንም ተደጋጋሚ ያልተሳካ ሙከራ አልተገኘም።">No brute force attempts detected.</p>
                            </div>
                        <?php else: ?>
                            <table class="alert-table">
                                <thead>
                                    <tr>
                                        <th data-en="Severity" data-am="ክብደት">Severity</th>
                                        <th data-en="Username" data-am="ተጠቃሚ ስም">Username</th>
                                        <th data-en="IP Address" data-am="IP አድራሻ">IP Address</th>
                                        <th data-en="Attempts" data-am="ሙከራዎች">Attempts</th>
                                        <th data-en="First Attempt" data-am="የመጀመሪያ ሙከራ">First Attempt</th>
                                        <th data-en="Last Attempt" data-am="የመጨረሻ ሙከራ">Last Attempt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($brute_force_alerts as $bf):
                                        $severity = $bf['attempt_count'] >= 10 ? 'high' : ($bf['attempt_count'] >= 5 ? 'medium' : 'low');
                                    ?>
                                        <tr>
                                            <td><span
                                                    class="severity-badge <?php echo $severity; ?>"><?php echo strtoupper($severity); ?></span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($bf['username']); ?></strong></td>
                                            <td><code class="ip-tag"><?php echo htmlspecialchars($bf['ip_address']); ?></code>
                                            </td>
                                            <td class="severity-<?php echo $severity; ?>">
                                                <?php echo $bf['attempt_count']; ?> <span data-en="times"
                                                    data-am="ጊዜ">times</span></td>
                                            <td><?php echo date('M d, H:i', strtotime($bf['first_attempt'])); ?></td>
                                            <td><?php echo date('M d, H:i', strtotime($bf['last_attempt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Unusual Hour Access -->
                <div class="section-card">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3>
                            <i class="fas fa-moon" style="color:#e67e22;"></i>
                            <span data-en="Unusual Hour Access (11PM - 5AM)"
                                data-am="ያልተለመደ ሰዓት ግብኣት (11PM - 5AM)">Unusual Hour Access (11PM - 5AM)</span>
                            <span
                                class="badge badge-warning"><?php echo count($unusual_hour_alerts); ?></span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="section-body collapsed">
                        <?php if (empty($unusual_hour_alerts)): ?>
                            <div class="empty-alert">
                                <i class="fas fa-check-circle"></i>
                                <p data-en="No unusual hour logins detected."
                                    data-am="ያልተለመደ ሰዓት ግብኣት አልተገኘም።">No unusual hour logins detected.</p>
                            </div>
                        <?php else: ?>
                            <table class="alert-table">
                                <thead>
                                    <tr>
                                        <th data-en="User" data-am="ተጠቃሚ">User</th>
                                        <th data-en="Role" data-am="ሚና">Role</th>
                                        <th data-en="IP Address" data-am="IP አድራሻ">IP Address</th>
                                        <th data-en="Login Time" data-am="የግብኣት ሰዓት">Login Time</th>
                                        <th data-en="Hour" data-am="ሰዓት">Hour</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unusual_hour_alerts as $uh): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($uh['username']); ?></strong></td>
                                            <td><span
                                                    class="status-badge"><?php echo htmlspecialchars($uh['role']); ?></span>
                                            </td>
                                            <td><code
                                                    class="ip-tag"><?php echo htmlspecialchars($uh['ip_address']); ?></code>
                                            </td>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($uh['created_at'])); ?></td>
                                            <td style="font-weight:600; color:#e67e22;">
                                                <?php echo date('g:i A', strtotime($uh['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. Rapid Successive Actions -->
                <div class="section-card">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3>
                            <i class="fas fa-bolt" style="color:#3498db;"></i>
                            <span data-en="Rapid Successive Actions (Possible Bot/Script)"
                                data-am="ተከታታይ ፈጣን ድርጊቶች (ቦት/ስክሪፕት ሊሆን ይችላል)">Rapid Successive Actions</span>
                            <span
                                class="badge badge-info"><?php echo count($rapid_action_alerts); ?></span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="section-body collapsed">
                        <?php if (empty($rapid_action_alerts)): ?>
                            <div class="empty-alert">
                                <i class="fas fa-check-circle"></i>
                                <p data-en="No rapid action patterns detected."
                                    data-am="ፈጣን ድርጊት ቅጦች አልተገኙም።">No rapid action patterns detected.</p>
                            </div>
                        <?php else: ?>
                            <table class="alert-table">
                                <thead>
                                    <tr>
                                        <th data-en="User" data-am="ተጠቃሚ">User</th>
                                        <th data-en="Role" data-am="ሚና">Role</th>
                                        <th data-en="Actions" data-am="ድርጊቶች">Actions</th>
                                        <th data-en="Window Start" data-am="መጀመሪያ">Window Start</th>
                                        <th data-en="Window End" data-am="መጨረሻ">Window End</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapid_action_alerts as $ra): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ra['username']); ?></strong></td>
                                            <td><span
                                                    class="status-badge"><?php echo htmlspecialchars($ra['role']); ?></span>
                                            </td>
                                            <td class="severity-high"><?php echo $ra['action_count']; ?>
                                                <span data-en="actions in" data-am="ድርጊቶች በ">actions
                                                    in</span> <?php echo $RAPID_ACTION_WINDOW; ?> <span data-en="min"
                                                    data-am="ደቂቃ">min</span>
                                            </td>
                                            <td><?php echo date('M d, H:i:s', strtotime($ra['window_start'])); ?></td>
                                            <td><?php echo date('M d, H:i:s', strtotime($ra['window_end'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4. Multiple IPs for Same User -->
                <div class="section-card">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3>
                            <i class="fas fa-network-wired" style="color:#8e44ad;"></i>
                            <span data-en="Multiple IPs per User (Account Sharing/Hijack)"
                                data-am="ለአንድ ተጠቃሚ ብዙ IP (መለያ መጋራት/ጠለፋ)">Multiple IPs per User</span>
                            <span
                                class="badge badge-purple"><?php echo count($multi_ip_alerts); ?></span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="section-body collapsed">
                        <?php if (empty($multi_ip_alerts)): ?>
                            <div class="empty-alert">
                                <i class="fas fa-check-circle"></i>
                                <p data-en="No multi-IP login patterns detected."
                                    data-am="ብዙ IP ግብኣት ቅጦች አልተገኙም።">No multi-IP login patterns detected.</p>
                            </div>
                        <?php else: ?>
                            <table class="alert-table">
                                <thead>
                                    <tr>
                                        <th data-en="User" data-am="ተጠቃሚ">User</th>
                                        <th data-en="Role" data-am="ሚና">Role</th>
                                        <th data-en="IP Count" data-am="IP ብዛት">IP Count</th>
                                        <th data-en="IP Addresses" data-am="IP አድራሻዎች">IP Addresses</th>
                                        <th data-en="Date" data-am="ቀን">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($multi_ip_alerts as $mi): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($mi['username']); ?></strong></td>
                                            <td><span
                                                    class="status-badge"><?php echo htmlspecialchars($mi['role']); ?></span>
                                            </td>
                                            <td class="severity-high"><?php echo $mi['ip_count']; ?></td>
                                            <td>
                                                <?php
                                                $ips = explode(', ', $mi['ip_list']);
                                                foreach ($ips as $ip):
                                                ?>
                                                    <code class="ip-tag"><?php echo htmlspecialchars($ip); ?></code>
                                                <?php endforeach; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($mi['activity_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 5. Sensitive Admin Actions -->
                <div class="section-card">
                    <div class="section-header" onclick="toggleSection(this)">
                        <h3>
                            <i class="fas fa-user-shield" style="color:#1a1a2e;"></i>
                            <span data-en="Sensitive Admin Actions (Delete/Disable/Reassign)"
                                data-am="ስሱ የአስተዳዳሪ ድርጊቶች (ሰርዝ/አሰናክል/ድግግሞሽ)">Sensitive Admin Actions</span>
                            <span class="badge badge-dark"><?php echo count($sensitive_alerts); ?></span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="section-body collapsed">
                        <?php if (empty($sensitive_alerts)): ?>
                            <div class="empty-alert">
                                <i class="fas fa-check-circle"></i>
                                <p data-en="No sensitive actions recorded."
                                    data-am="ምንም ስሱ ድርጊቶች አልተመዘገቡም።">No sensitive actions recorded.</p>
                            </div>
                        <?php else: ?>
                            <table class="alert-table">
                                <thead>
                                    <tr>
                                        <th data-en="Admin" data-am="አስተዳዳሪ">Admin</th>
                                        <th data-en="Action" data-am="ድርጊት">Action</th>
                                        <th data-en="Description" data-am="መግለጫ">Description</th>
                                        <th data-en="IP Address" data-am="IP አድራሻ">IP Address</th>
                                        <th data-en="Date/Time" data-am="ቀን/ሰዓት">Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sensitive_alerts as $sa): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($sa['username']); ?></strong></td>
                                            <td>
                                                <?php
                                                $actionClass = 'badge-dark';
                                                if ($sa['action'] === 'USER_DELETED') $actionClass = 'badge-danger';
                                                elseif ($sa['action'] === 'USER_DISABLED') $actionClass = 'badge-warning';
                                                ?>
                                                <span
                                                    class="badge <?php echo $actionClass; ?>"><?php echo htmlspecialchars($sa['action']); ?></span>
                                            </td>
                                            <td style="max-width:250px; word-wrap:break-word;">
                                                <?php echo htmlspecialchars($sa['description'] ?? ''); ?></td>
                                            <td><code
                                                    class="ip-tag"><?php echo htmlspecialchars($sa['ip_address']); ?></code>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?php echo date('M d, Y H:i', strtotime($sa['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Link to full Audit Logs -->
                <div style="text-align:center; margin-top:20px;">
                    <a href="audit_logs.php"
                        style="display:inline-flex; align-items:center; gap:8px; padding:10px 24px; background:#1a1a2e; color:#fff; border-radius:6px; text-decoration:none; font-size:14px;">
                        <i class="fas fa-list"></i>
                        <span data-en="View Full Audit Logs" data-am="ሙሉ ኦዲት ሎግ ይመልከቱ">View Full Audit Logs</span>
                    </a>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        function toggleSection(header) {
            const body = header.nextElementSibling;
            body.classList.toggle('collapsed');
            header.classList.toggle('collapsed');
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
