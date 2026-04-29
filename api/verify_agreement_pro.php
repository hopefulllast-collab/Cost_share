<?php
require_once '../config/db_connect.php';
require_once '../includes/auth_check.php';

checkAuth(['cost_sharing_pro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $agreement_id = $_POST['agreement_id'];
    $verifier_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        $stmt_sig = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
        $stmt_sig->execute([$verifier_id]);
        $signature = $stmt_sig->fetchColumn();

        if (empty($signature)) {
            throw new Exception("You have not set up your digital signature. Please update your profile.");
        }

        // 1. Check current status strictly
        $stmt = $pdo->prepare("SELECT status FROM cost_sharing_agreements WHERE id = ?");
        $stmt->execute([$agreement_id]);
        $status = $stmt->fetchColumn();

        if ($status !== 'VerifiedByDept') {
            throw new Exception("Agreement verified by the department or already approved.");
        }

        // 2. Update Status and Signature
        $sql = "UPDATE cost_sharing_agreements 
                SET status = 'ApprovedByCostPro', 
                    signature_cost_pro = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$signature, $agreement_id]);

        $pdo->commit();

        header("Location: ../modules/cost_sharing/approve_cost_share.php?msg=" . urlencode("Agreement approved successfully."));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = urlencode("Error: " . $e->getMessage());
        header("Location: ../modules/cost_sharing/view_agreement_detail.php?id=$agreement_id&error=$error");
        exit();
    }
} else {
    header("Location: ../modules/cost_sharing/dashboard.php");
    exit();
}
?>