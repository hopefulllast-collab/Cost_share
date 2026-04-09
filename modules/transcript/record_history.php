<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sid = $_POST['student_id'];
    $prev_uni = $_POST['university'];
    $costs = $_POST['total_cost'];

    // Logic: Find student by ID -> Record cost transaction
    // Assuming student exists manually or created by Registrar first
    $stmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = :sid");
    $stmt->execute([':sid' => $sid]);
    $student = $stmt->fetch();

    if ($student) {
        $stmt = $pdo->prepare("INSERT INTO cost_sharing_agreements (student_id, semester, academic_year, tuition_fee, recorded_by, status) 
                               VALUES (:uid, 0, 'Transfer', :amt, :rec, 'ApprovedByCostPro')");
        $stmt->execute([':uid' => $student['user_id'], ':amt' => $costs, ':rec' => $_SESSION['user_id']]);
        // Recalculate cumulative total_amount for this student
        $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ?) as t) WHERE student_id = ?")->execute([$student['user_id'], $student['user_id']]);
        $_SESSION["flash_success"] = "<span data-en='Transfer history recorded.' data-am='የዝውውር ታሪክ ተመዝግቧል።'>Transfer history recorded.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } else {
        $error = "<span data-en='Student ID not found.' data-am='የተማሪ መታወቂያ አልተገኘም።'>Student ID not found.</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Record Transfer History - Transcript Pro" data-am="የዝውውር ታሪክ መዝገብ - ትራንስክሪፕት ባለሙያ">Record Transfer
        History - Transcript Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Record Transfer Cost History" data-am="የተማሪ ዝውውር ወጪ ታሪክ መዝገብ">Record Transfer Cost
                        History</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <?php if (isset($msg))
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if (isset($error))
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label data-en="Student ID (DMU Assigned)" data-am="የተማሪ መታወቂያ (በዲኤምዩ የተሰጠ)">Student ID (DMU
                                Assigned)</label>
                            <input type="text" name="student_id" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Previous University" data-am="ቀደም ሲል የነበሩበት ዩኒቨርሲቲ">Previous
                                University</label>
                            <input type="text" name="university" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Accumulated Cost (Birr)" data-am="የተጠራቀመ ወጪ (ብር)">Accumulated Cost
                                (Birr)</label>
                            <input type="number" name="total_cost" required>
                        </div>
                        <button type="submit" class="btn-primary" data-en="Record History" data-am="ታሪክ መዝግብ">Record
                            History</button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>