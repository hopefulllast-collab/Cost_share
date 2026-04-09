<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$user_id = $_SESSION['user_id'];

// Fetch Transactions
$stmt = $pdo->prepare("SELECT * FROM cost_sharing_agreements WHERE student_id = ? ORDER BY academic_year DESC, semester DESC");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals
$total_acc = 0;
foreach ($transactions as $t) {
    // Total amount is stored or calculated
    if ($t['status'] !== 'Suspended') {
        $total_acc += $t['total_amount_semester'] ?? ($t['tuition_fee'] + $t['food_expense'] + $t['bed_expense'] + $t['medication_expense']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Cost Sharing History - DMU" data-am="የወጪ መጋራት ታሪክ - DMU">Cost Sharing History - DMU</title>
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
                    <h2 data-en="Cost Sharing History" data-am="የወጪ መጋራት ታሪክ">Cost Sharing History</h2>
                    <div class="card info-card" style="width: auto; padding: 10px 20px;">
                        <h3><span data-en="Total Debt" data-am="ጠቅላላ እዳ">Total Debt</span>:
                            <?php echo number_format($total_acc, 2); ?> <span data-en="Birr" data-am="ብር">Birr</span>
                        </h3>
                    </div>
                </div>

                <div class="card">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Year" data-am="ዓመት">Year</th>
                                <th data-en="Semester" data-am="ሴሚስተር">Semester</th>
                                <th data-en="Tuition" data-am="የትምህርት ክፍያ">Tuition</th>
                                <th data-en="Food" data-am="ምግብ">Food</th>
                                <th data-en="Bed" data-am="መኝታ">Bed</th>
                                <th data-en="Medication" data-am="ህክምና">Medication</th>
                                <th data-en="Total" data-am="ጠቅላላ">Total</th>
                                <th data-en="Date Recorded" data-am="የተመዘገበበት ቀን">Date Recorded</th>
                                <th data-en="Status" data-am="ሁኔታ">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center;"
                                        data-en="No history found. Fill the Cost Share Agreement first."
                                        data-am="ምንም ታሪክ አልተገኘም። አስቀድመው የወጪ መጋራት ውል ይሙሉ::">No history found. Fill the Cost
                                        Share Agreement first.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t):
                                    $row_total = $t['total_amount_semester'] ?? ($t['tuition_fee'] + $t['food_expense'] + $t['bed_expense'] + $t['medication_expense']);
                                    $is_susp = ($t['status'] === 'Suspended');
                                    ?>
                                    <tr style="<?php echo $is_susp ? 'text-decoration: line-through; opacity: 0.6;' : ''; ?>">
                                        <td><?php echo $t['academic_year']; ?></td>
                                        <td><?php echo $t['semester']; ?></td>
                                        <td><?php echo number_format($t['tuition_fee'], 2); ?></td>
                                        <td><?php echo number_format($t['food_expense'], 2); ?></td>
                                        <td><?php echo number_format($t['bed_expense'], 2); ?></td>
                                        <td><?php echo number_format($t['medication_expense'], 2); ?></td>
                                        <td><strong><?php echo number_format($row_total, 2); ?> <span data-en="Birr"
                                                    data-am="ብር">Birr</span></strong></td>
                                        <td>
                                            <?php
                                            $date_obj = new DateTime($t['created_at']);
                                            $en_date = $date_obj->format('d M Y');

                                            $months = [
                                                'Jan' => 'ጃን',
                                                'Feb' => 'ፌብ',
                                                'Mar' => 'ማር',
                                                'Apr' => 'ኤፕ',
                                                'May' => 'ሜይ',
                                                'Jun' => 'ጁን',
                                                'Jul' => 'ጁላይ',
                                                'Aug' => 'ኦገ',
                                                'Sep' => 'ሴፕ',
                                                'Oct' => 'ኦክ',
                                                'Nov' => 'ኖቬ',
                                                'Dec' => 'ዲሴ'
                                            ];
                                            $am_month = $months[$date_obj->format('M')] ?? $date_obj->format('M');
                                            $am_date = $date_obj->format('d') . ' ' . $am_month . ' ' . $date_obj->format('Y');
                                            ?>
                                            <span data-en="<?php echo $en_date; ?>"
                                                data-am="<?php echo $am_date; ?>"><?php echo $en_date; ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_labels = [
                                                'SignedByStudent' => ['Pending Dept Approval', 'የክፍል ሃላፊ ማፅደቅ ይጠባበቃል', '#f39c12'],
                                                'VerifiedByDept' => ['Pending Cost Pro Approval', 'የወጪ መጋራት ባለሙያ ማፅደቅ ይጠባበቃል', '#3498db'],
                                                'ApprovedByCostPro' => ['Approved', 'ፀድቋል', '#27ae60'],
                                                'Suspended' => ['Suspended', 'ታግዷል', '#e74c3c']
                                            ];
                                            $sl = $status_labels[$t['status']] ?? ['Unknown', 'ያልታወቀ', '#999'];
                                            ?>
                                            <span style="background:<?php echo $sl[2]; ?>; color:#fff; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:bold;"
                                                data-en="<?php echo $sl[0]; ?>"
                                                data-am="<?php echo $sl[1]; ?>"><?php echo $sl[0]; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>