<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
// checkAuth(['registrar']); // Optional: Allow students to print too? User said Registrar verify.

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$req_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, d.college, s.batch 
                       FROM official_transcript dr 
                       JOIN students s ON dr.student_id = s.user_id 
                       JOIN users u ON s.user_id = u.id 
                       LEFT JOIN departments d ON s.department_id = d.id 
                       WHERE dr.id = ?");
$stmt->execute([$req_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("Document not found.");
}

// Fetch Transcript Professional name (role = transcript_pro)
$transcript_pro_name = '';
$stmt_tp = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'transcript_pro' LIMIT 1");
$stmt_tp->execute();
$tp = $stmt_tp->fetch(PDO::FETCH_ASSOC);
if ($tp) {
    $transcript_pro_name = $tp['first_name'] . ' ' . $tp['middle_name'];
}

// Fetch Registrar name (role = registrar)
$registrar_name = '';
$stmt_reg = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'registrar' LIMIT 1");
$stmt_reg->execute();
$reg = $stmt_reg->fetch(PDO::FETCH_ASSOC);
if ($reg) {
    $registrar_name = $reg['first_name'] . ' ' . $reg['middle_name'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Document</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .logo {
            max-width: 100px;
        }

        .content {
            font-size: 14pt;
            line-height: 1.6;
            text-align: justify;
        }

        .footer {
            margin-top: 100px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 45%;
        }

        .signature-img {
            max-height: 80px;
            display: block;
            margin: 0 auto;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <button onclick="window.print()" class="no-print"
        style="padding: 10px 20px; font-size: 16px; margin-bottom: 20px; cursor: pointer;" data-en="Print Page"
        data-am="ገጽ አትም">Print Page</button>

    <div class="header">
        <h2 data-en="DEBRE MARKOS UNIVERSITY" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">DEBRE MARKOS UNIVERSITY</h2>
        <h3 data-en="OFFICE OF THE REGISTRAR" data-am="ሬጅስትራር ጽህፈት ቤት">OFFICE OF THE REGISTRAR</h3>
        <h4 data-en="Official Document" data-am="ይፋዊ ሰነድ">Official Document</h4>
    </div>

    <div class="content">
        <p><strong data-en="Date:" data-am="ቀን:">Date:</strong>
            <?php echo date('d/m/Y'); ?>
        </p>

        <p><strong data-en="To: Whom It May Concern" data-am="ለ: ለሚመለከተው ሁሉ">To: Whom It May Concern</strong></p>

        <p>
            <span data-en="This is to certify that" data-am="ይህ የሚያረጋግጠው">This is to certify that</span> <strong>
                <?php echo strtoupper($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']); ?>
            </strong>
            (<span data-en="ID:" data-am="መታወቂያ:">ID:</span> <strong>
                <?php echo $doc['real_student_id']; ?>
            </strong>) <span data-en="was a bona fide student of Debre Markos University in the Department of"
                data-am="በደብረ ማርቆስ ዩኒቨርሲቲ ተማሪ የነበረ/ች በ">was a bona fide student of Debre Markos University in the
                Department of</span> <strong>
                <?php echo $doc['dept_name']; ?>
            </strong>, <span data-en="College of" data-am="ኮሌጅ">College of</span> <strong>
                <?php echo $doc['college']; ?>
            </strong>.
        </p>

        <p><span data-en="The student has requested:" data-am="ተማሪው የሚከተለውን ጠይቋል:">The student has requested:</span>
            <strong>
                <?php echo $doc['request_type']; ?>
            </strong>.
        </p>

        <p><span data-en="We confirm that the student has cleared all cost sharing obligations / or has an agreement on file as per the university regulations."
                data-am="ተማሪው በዩኒቨርሲቲው ደንብ መሰረት ሁሉንም የወጪ መጋራት ግዴታዎች እንዳጸዳ/ች ወይም ስምምነት እንዳለው/ላት እናረጋግጣለን።">We confirm
                that the student has cleared all cost sharing obligations / or has an agreement on file as per the
                university regulations.</span></p>

        <p><span data-en="This document is issued upon the request of the student for legal purposes."
                data-am="ይህ ሰነድ ለህጋዊ ጉዳዮች በተማሪው ጥያቄ መሰረት የተሰጠ ነው።">This document is issued upon the request of the
                student for legal purposes.</span></p>
    </div>

    <div class="footer">
        <!-- Transcript Professional Signature -->
        <div class="signature-box">
            <p><strong data-en="Transcript Professional:" data-am="ትራንስክሪፕት ባለሙያ:">Transcript Professional:</strong></p>
            <?php if (!empty($doc['transcript_signature'])): ?>
                <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
                <img src="<?php echo $t_src; ?>" class="signature-img"
                    alt="Transcript Signature">
            <?php else: ?>
                <p>__________________________</p>
            <?php endif; ?>
            <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
            <p data-en="Transcript Professional" data-am="ትራንስክሪፕት ባለሙያ">Transcript Professional</p>
        </div>

        <!-- Registrar Head Signature -->
        <div class="signature-box">
            <p><strong data-en="Registrar Head:" data-am="ሬጅስትራር ኃላፊ:">Registrar Head:</strong></p>
            <?php if (!empty($doc['registrar_signature'])): ?>
                <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                <img src="<?php echo $r_src; ?>" class="signature-img"
                    alt="Registrar Signature">
            <?php else: ?>
                <p>__________________________</p>
            <?php endif; ?>
            <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
            <p data-en="Official Seal & Signature" data-am="ይፋዊ ማህተም እና ፊርማ">Official Seal & Signature</p>
        </div>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>