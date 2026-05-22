<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['academic_vp']);

require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Handle Forward / Reject Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['vp_action'])) {
    $action = $_POST['vp_action'];
    $req_id = $_POST['request_id'];

    if ($action == 'reject') {
        $reason = $_POST['rejection_reason'] ?? 'No reason provided';
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Rejected', rejection_reason = ? WHERE id = ? AND request_type = 'Transfer-Out'");
        if ($stmt->execute([$reason, $req_id])) {
            $_SESSION['flash_success'] = "<span data-en='Document request rejected successfully.' data-am='የሰነድ ጥያቄ በተሳካ ሁኔታ ውድቅ ተደርጓል።'>Document request rejected successfully.</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "<span data-en='Failed to reject request.' data-am='ጥያቄውን ውድቅ ማድረግ አልተቻለም።'>Failed to reject request.</span>";
        }
    }
}

// Fetch "Transfer-Out" Document Requests (ALL statuses for history)
$transfer_docs = $pdo->query("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                             FROM official_transcript dr 
                             JOIN students s ON dr.student_id = s.user_id 
                             JOIN users u ON s.user_id = u.id 
                             LEFT JOIN departments d ON s.department_id = d.id 
                             WHERE dr.request_type = 'Transfer-Out' 
                             ORDER BY FIELD(dr.status, 'Pending', 'Forwarded', 'Pending Transcript', 'Delivered', 'Rejected'), dr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Send Referral Letter - DMU" data-am="የማጣቀሻ ደብዳቤ ላክ - DMU">Send Referral Letter - DMU</title>
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
                    <h2 data-en="Send Referral Letter" data-am="የማጣቀሻ ደብዳቤ ላክ">Send Referral Letter</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <h3 data-en="Transfer-Out Cost Share Debt Requests" data-am="ግቢ ለመቀየር ወጪ ዕዳ ጥያቄዎች">Transfer-Out Cost
                        Share Debt Requests</h3>

                    <?php if (empty($transfer_docs)): ?>
                        <p data-en="No Transfer-Out document requests found." data-am="ምንም ዓይነት የዝውውር ሰነድ ጥያቄዎች አልተገኙም።">No
                            Transfer-Out document requests found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                                <thead>
                                    <tr style="background:#f9f9f9; text-align:left;">
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Date" data-am="ቀን">Date
                                        </th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Student ID"
                                            data-am="የተማሪ መታወቂያ">Student ID</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Name" data-am="ስም">Name
                                        </th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Department"
                                            data-am="ትምህርት ክፍል">Department</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Clearance"
                                            data-am="ክሊራንስ">Clearance</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Status" data-am="ሁኔታ">
                                            Status</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Action" data-am="እርምጃ">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transfer_docs as $doc): ?>
                                        <tr>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($doc['real_student_id']); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . trim($doc['middle_name'] . ' ' . $doc['last_name'])); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php
                                                $d_name_en = $doc['dept_name'] ?: 'N/A';
                                                $d_name_am = $academic_translations[$d_name_en] ?? $d_name_en;
                                                ?>
                                                <span data-en="<?php echo htmlspecialchars($d_name_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($d_name_am); ?>">
                                                    <?php echo htmlspecialchars($d_name_en); ?>
                                                </span>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php if ($doc['clearance_file']): ?>
                                                    <a href="../../uploads/clearances/<?php echo $doc['clearance_file']; ?>"
                                                        target="_blank" class="btn-primary"
                                                        style="padding:4px 10px; font-size:12px; text-decoration:none; display:inline-block;"
                                                        data-en="View" data-am="ይመልከቱ"><i class="fas fa-eye"></i> View</a>
                                                <?php else: ?>
                                                    <span data-en="N/A" data-am="የለም">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php
                                                $statusClass = '';
                                                $st = $doc['status'];
                                                if ($st == 'Pending')
                                                    $statusClass = 'color: #856404; background-color: #fff3cd; padding: 3px 8px; border-radius: 4px; border: 1px solid #ffc107;';
                                                elseif ($st == 'Forwarded')
                                                    $statusClass = 'color: #0c5460; background-color: #d1ecf1; padding: 3px 8px; border-radius: 4px; border: 1px solid #bee5eb;';
                                                elseif ($st == 'Pending Transcript')
                                                    $statusClass = 'color: #383d41; background-color: #e2e3e5; padding: 3px 8px; border-radius: 4px; border: 1px solid #d6d8db;';
                                                elseif ($st == 'Delivered')
                                                    $statusClass = 'color: #155724; background-color: #d4edda; padding: 3px 8px; border-radius: 4px; border: 1px solid #c3e6cb;';
                                                elseif ($st == 'Rejected')
                                                    $statusClass = 'color: #721c24; background-color: #f8d7da; padding: 3px 8px; border-radius: 4px; border: 1px solid #f5c6cb;';
                                                ?>
                                                <span style="font-weight:bold; font-size:0.9em; <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($st); ?>
                                                </span>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php if ($st == 'Pending'): ?>
                                                    <!-- Process Document Link -->
                                                    <a href="process_transfer.php?id=<?php echo $doc['id']; ?>" class="btn-primary"
                                                        style="padding:5px 10px; font-size:12px; margin-right:5px; text-decoration:none; display:inline-block;"
                                                        data-en="Process Document" data-am="ሰነድ አዘጋጅ">
                                                        <i class="fas fa-file-signature"></i> Process Document</a>
                                                    <!-- Reject -->
                                                    <form method="POST" style="display:inline;"
                                                        id="rejectForm_<?php echo $doc['id']; ?>">
                                                        <input type="hidden" name="request_id" value="<?php echo $doc['id']; ?>">
                                                        <input type="hidden" name="vp_action" value="reject">
                                                        <input type="hidden" name="rejection_reason"
                                                            id="rej_reason_<?php echo $doc['id']; ?>" value="">
                                                        <button type="button" class="btn-danger"
                                                            style="padding:5px 10px; font-size:12px; background:#dc3545; color:white; border:none; cursor:pointer;"
                                                            onclick="openRejectModal('<?php echo $doc['id']; ?>')" data-en="Reject"
                                                            data-am="ውድቅ"><i class="fas fa-times"></i>
                                                            Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color:#888; font-size:12px;" data-en="—" data-am="—">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <!-- Reject Modal -->
    <div id="rejectModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%;">
            <h3 data-en="Rejection Reason" data-am="የውድቅ ማድረጊያ ምክንያት" style="margin-top:0; color:#dc3545;">Rejection
                Reason</h3>
            <p data-en="Please enter the reason for rejection (required):"
                data-am="እባክዎ የውድቅ ማድረጊያ ምክን ያትዎን ይፃፉ (ግዴታ):">Please enter the reason for rejection (required):</p>
            <textarea id="modalRejectionReason" rows="4"
                style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; margin-bottom:15px;"></textarea>
            <div style="text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeRejectModal()" data-en="Cancel"
                    data-am="ሰርዝ">Cancel</button>
                <button type="button" class="btn-danger"
                    style="background:#dc3545; color:white; border:none; margin-left:10px;"
                    onclick="submitRejectModal()" data-en="Confirm Reject" data-am="ማረጋገጫ አረጋግጥ">Confirm Reject</button>
            </div>
        </div>
    </div>

    <script>
        let currentRejectId = null;

        function openRejectModal(id) {
            currentRejectId = id;
            document.getElementById('modalRejectionReason').value = '';
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            currentRejectId = null;
            document.getElementById('rejectModal').style.display = 'none';
        }

        function submitRejectModal() {
            if (!currentRejectId) return;
            const reason = document.getElementById('modalRejectionReason').value.trim();
            if (reason === '') {
                alert(localStorage.getItem('dmu_lang') === 'am' ? 'ምክንያት መጻፍ ግዴታ ነው!' : 'Rejection reason is required!');
                return;
            }

            document.getElementById('rej_reason_' + currentRejectId).value = reason;
            document.getElementById('rejectForm_' + currentRejectId).submit();
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>