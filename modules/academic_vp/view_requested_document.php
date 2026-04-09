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

    if ($action == 'forward') {
        $referral_note = $_POST['referral_note'] ?? '';

        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Forwarded', description = ? WHERE id = ? AND request_type = 'Transfer-Out'");
        if ($stmt->execute([$referral_note, $req_id])) {
            $_SESSION['flash_success'] = "<span data-en='Document request forwarded to Registrar successfully.' data-am='የሰነድ ጥያቄ ወደ ሬጅስትራር በተሳካ ሁኔታ ተላልፏል።'>Document request forwarded to Registrar successfully.</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "<span data-en='Failed to forward request.' data-am='ጥያቄውን ማስተላለፍ አልተቻለም።'>Failed to forward request.</span>";
        }
    } elseif ($action == 'reject') {
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Rejected' WHERE id = ? AND request_type = 'Transfer-Out'");
        if ($stmt->execute([$req_id])) {
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

                <?php if ($msg) echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error) echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <h3 data-en="Transfer-Out Cost Share Debt Requests" data-am="ግቢ ለመቀየር ወጪ ዕዳ ጥያቄዎች">Transfer-Out Cost Share Debt Requests</h3>
                    
                    <?php if (empty($transfer_docs)): ?>
                        <p data-en="No Transfer-Out document requests found." data-am="ምንም ዓይነት የዝውውር ሰነድ ጥያቄዎች አልተገኙም።">No Transfer-Out document requests found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                                <thead>
                                    <tr style="background:#f9f9f9; text-align:left;">
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Date" data-am="ቀን">Date</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Student ID" data-am="የተማሪ መታወቂያ">Student ID</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Name" data-am="ስም">Name</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Clearance" data-am="ክሊራንስ">Clearance</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Status" data-am="ሁኔታ">Status</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Action" data-am="እርምጃ">Action</th>
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
                                                <span data-en="<?php echo htmlspecialchars($d_name_en); ?>" data-am="<?php echo htmlspecialchars($d_name_am); ?>">
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
                                                    if($st == 'Pending') $statusClass = 'color: #856404; background-color: #fff3cd; padding: 3px 8px; border-radius: 4px; border: 1px solid #ffc107;';
                                                    elseif($st == 'Forwarded') $statusClass = 'color: #0c5460; background-color: #d1ecf1; padding: 3px 8px; border-radius: 4px; border: 1px solid #bee5eb;';
                                                    elseif($st == 'Pending Transcript') $statusClass = 'color: #383d41; background-color: #e2e3e5; padding: 3px 8px; border-radius: 4px; border: 1px solid #d6d8db;';
                                                    elseif($st == 'Delivered') $statusClass = 'color: #155724; background-color: #d4edda; padding: 3px 8px; border-radius: 4px; border: 1px solid #c3e6cb;';
                                                    elseif($st == 'Rejected') $statusClass = 'color: #721c24; background-color: #f8d7da; padding: 3px 8px; border-radius: 4px; border: 1px solid #f5c6cb;';
                                                ?>
                                                <span style="font-weight:bold; font-size:0.9em; <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($st); ?>
                                                </span>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php if ($st == 'Pending'): ?>
                                                    <!-- Forward to Registrar Button -->
                                                    <button type="button" class="btn-primary"
                                                        style="padding:5px 10px; font-size:12px; margin-right:5px; cursor:pointer;"
                                                        onclick="openReferralModal(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['first_name'] . ' ' . trim($doc['middle_name'] . ' ' . $doc['last_name'])); ?>', '<?php echo addslashes($doc['real_student_id']); ?>', '<?php echo addslashes($d_name_en); ?>')"
                                                        data-en="Forward to Registrar" data-am="ወደ ሬጅስትራር ያስተላልፉ">
                                                        <i class="fas fa-share"></i> Forward to Registrar</button>
                                                    <!-- Reject -->
                                                    <form method="POST" style="display:inline;" id="rejectForm_<?php echo $doc['id']; ?>">
                                                        <input type="hidden" name="request_id" value="<?php echo $doc['id']; ?>">
                                                        <input type="hidden" name="vp_action" value="reject">
                                                        <button type="button" class="btn-danger"
                                                            style="padding:5px 10px; font-size:12px; background:#dc3545; color:white; border:none; cursor:pointer;"
                                                            onclick="this.style.display='none'; this.nextElementSibling.style.display='inline';"
                                                            data-en="Reject" data-am="ውድቅ"><i class="fas fa-times"></i> Reject</button>
                                                        <span style="display:none;">
                                                            <span style="font-size:12px; color:#856404; font-weight:bold;"
                                                                data-en="Reject?" data-am="ውድቅ?">Reject?</span>
                                                            <button type="submit" class="btn-danger"
                                                                style="padding:3px 8px; font-size:11px; background:#dc3545; color:white; border:none; margin-left:5px; cursor:pointer;"
                                                                data-en="Yes" data-am="አዎ">Yes</button>
                                                            <button type="button" class="btn-secondary"
                                                                style="padding:3px 8px; font-size:11px; margin-left:3px; cursor:pointer;"
                                                                onclick="this.parentElement.style.display='none'; this.parentElement.previousElementSibling.style.display='inline';"
                                                                data-en="No" data-am="አይ">No</button>
                                                        </span>
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
    <!-- Referral Modal -->
    <div id="referralModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; overflow-y:auto;">
        <div style="background:#fff; width:90%; max-width:600px; margin:50px auto; padding:30px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <div style="text-align:center; border-bottom:2px solid #0056b3; padding-bottom:15px; margin-bottom:25px;">
                <h3 style="margin:0; font-size:24px; color:#0056b3;">Debre Markos University</h3>
                <h4 style="margin:8px 0 0 0; font-size:18px; color:#333; font-weight:normal;">Office of the Academic Vice President</h4>
            </div>
            <form method="POST">
                <input type="hidden" name="request_id" id="modal_request_id">
                <input type="hidden" name="vp_action" value="forward">
                
                <div style="margin-bottom:15px; text-align:right; font-family:'Times New Roman', Times, serif;">
                    <strong>Date:</strong> <?php echo date('F d, Y'); ?>
                </div>
                
                <div style="margin-bottom:25px; font-family:'Times New Roman', Times, serif; font-size:16px;">
                    <strong>To:</strong> Registrar Head, DMU<br><br>
                    <strong>Subject:</strong> <span style="text-decoration:underline;">Referral for Transfer-Out Clearance</span>
                </div>
                
                <div style="margin-bottom:20px; line-height:1.6; font-family:'Times New Roman', Times, serif; font-size:16px;">
                    This is to confirm that the student <strong><span id="modal_student_name"></span></strong> 
                    (ID: <strong><span id="modal_student_id"></span></strong>) from the 
                    <strong><span id="modal_dept"></span></strong> department has requested a transfer-out clearance.<br><br>
                    Based on the attached documentation and initial review, I am formally referring this request to your office 
                    to proceed with the transcript and documentation process.<br><br>
                    <strong>Additional Remarks:</strong>
                    <textarea name="referral_note" rows="5" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-top:8px; font-family:inherit; font-size:15px; resize:vertical;" placeholder="Optional remarks..."></textarea>
                </div>
                
                <div style="text-align:right; border-top:1px solid #ddd; padding-top:15px; margin-top:10px;">
                    <button type="button" class="btn-secondary" onclick="closeReferralModal()" style="padding:10px 20px; margin-right:10px; font-size:14px; border:none; background:#6c757d; color:#fff; border-radius:6px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding:10px 20px; font-size:14px; border:none; background:#0056b3; color:#fff; border-radius:6px; cursor:pointer;"><i class="fas fa-paper-plane"></i> Submit Referral Letter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReferralModal(id, name, stu_id, dept) {
            document.getElementById('modal_request_id').value = id;
            document.getElementById('modal_student_name').textContent = name;
            document.getElementById('modal_student_id').textContent = stu_id;
            document.getElementById('modal_dept').textContent = dept;
            document.getElementById('referralModal').style.display = 'block';
        }
        function closeReferralModal() {
            document.getElementById('referralModal').style.display = 'none';
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
