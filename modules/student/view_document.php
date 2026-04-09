<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$user_id = $_SESSION['user_id'];

// Fetch Student Info
$stmt = $pdo->prepare("SELECT s.student_id, u.first_name, u.middle_name, u.last_name, d.name as dept_name 
                       FROM students s 
                       JOIN users u ON s.user_id = u.id 
                       LEFT JOIN departments d ON s.department_id = d.id 
                       WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$std_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Latest Request (any type)
$reqStmt = $pdo->prepare("SELECT * FROM official_transcript WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$reqStmt->execute([$user_id]);
$request = $reqStmt->fetch(PDO::FETCH_ASSOC);

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

// Fetch Academic VP name
$academic_vp_name = '';
$stmt_vp = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'academic_vp' LIMIT 1");
$stmt_vp->execute();
$vp = $stmt_vp->fetch(PDO::FETCH_ASSOC);
if ($vp) {
    $academic_vp_name = $vp['first_name'] . ' ' . $vp['middle_name'];
}

// Determine if this is a Transfer-Out request
$is_transfer_out = ($request && $request['request_type'] == 'Transfer-Out');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Document - DMU" data-am="ሰነድ ይመልከቱ - DMU">View Document - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-doc {
            background: #fff;
            padding: 50px;
            max-width: 800px;
            margin: 20px auto;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            font-family: 'Times New Roman', serif;
            color: #000;
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .doc-header h2 {
            margin: 5px 0;
            text-transform: uppercase;
        }

        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .doc-subject {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 20px;
            text-decoration: underline;
        }

        .doc-body {
            line-height: 1.6;
            font-size: 1.1em;
            min-height: 200px;
        }

        .doc-footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .stamp-box {
            border: 2px dashed #ccc;
            width: 150px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-weight: bold;
            transform: rotate(-5deg);
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .sig-block {
            text-align: center;
            width: 40%;
        }

        /* Generated Doc Styles */
        .generated-doc .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }

        .generated-doc h2 {
            margin-top: 5px;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            .paper-doc,
            .paper-doc * {
                visibility: visible;
            }

            .paper-doc {
                position: absolute;
                left: 0;
                top: 0;
                box-shadow: none;
                border: none;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar no-print">
                    <h2 data-en="View Requested Document" data-am="የተጠየቀ ሰነድ ይመልከቱ">View Requested Document</h2>
                    <?php if ($request && ($request['status'] == 'Delivered' || $request['status'] == 'Approved')): ?>
                        <button style="color:#ffffff; background-color:#000000;" class="btn-primary"
                            onclick="window.print()" data-en="Print" data-am="አትም"><i class="fas fa-print"></i> <span
                                data-en="Print Document" data-am="ሰነድ አትም">Print
                                Document</span></button>
                    <?php endif; ?>
                </div>

                <?php if ($request): ?>
                    <div class="paper-doc">

                        <!-- If Delivered (Official Document View) -->
                        <?php if ($request['status'] == 'Delivered'): ?>
                            <div class="doc-header">
                                <h2 data-en="DEBRE MARKOS UNIVERSITY" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">DEBRE MARKOS UNIVERSITY</h2>
                                <h3 data-en="OFFICE OF THE REGISTRAR" data-am="ሬጅስትራር ጽህፈት ቤት">OFFICE OF THE REGISTRAR</h3>
                                <h4 data-en="Official Document" data-am="ኦፊሴላዊ ሰነድ">Official Document</h4>
                            </div>

                            <div class="doc-body" style="text-align: justify;">
                                <p><strong data-en="Date:" data-am="ቀን:">Date:</strong> <?php echo date('d/m/Y'); ?></p>

                                <p><strong data-en="To: Whom It May Concern" data-am="ለ: ጉዳዩ ለሚመለከተው ሁሉ">To: Whom It May
                                        Concern</strong></p>

                                <p data-en="This is to certify that" data-am="ይህ የምስክር ወረቀት የሚያረጋግጠው">This is to certify that
                                    <strong><?php echo strtoupper($std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']); ?></strong>
                                    (ID: <strong><?php echo $std_info['student_id']; ?></strong>) <span
                                        data-en="was a bona fide student of Debre Markos University in the Department of"
                                        data-am="በደብረ ማርቆስ ዩኒቨርሲቲ በትምህርት ክፍል ተማሪ ነበሩ">was a bona fide student of
                                        Debre Markos University
                                        in the Department of</span> <strong><?php echo $std_info['dept_name']; ?></strong>.
                                </p>

                                <p><span data-en="The student has requested:" data-am="ተማሪው የጠየቀው:">The student has
                                        requested:</span> <strong><?php echo $request['request_type']; ?></strong>.</p>

                                <p data-en="We confirm that the student has cleared all cost sharing obligations / or has an agreement on file as per the university regulations."
                                    data-am="ተማሪው ሁሉንም የወጪ መጋራት ግዴታዎች ማጠናቀቁን / ወይም በዩኒቨርሲቲው ደንቦች መሠረት በፋይል ላይ ስምምነት እንዳለው እናረጋግጣለን።">
                                    We confirm that the student has cleared all cost sharing obligations / or has an agreement
                                    on
                                    file as per the university regulations.</p>

                                <p data-en="This document is issued upon the request of the student for legal purposes."
                                    data-am="ይህ ሰነድ በተማሪው ጥያቄ መሰረት ለህጋዊ ዓላማዎች ተሰጥቷል።">
                                    This document is issued upon the request of the student for legal purposes.</p>
                            </div>

                            <div class="signatures">
                                <!-- Transcript Professional Signature -->
                                <div class="sig-block">
                                    <p><strong data-en="Transcript Professional:" data-am="ትራንስክሪፕት ባለሙያ:">Transcript Professional:</strong></p>
                                    <?php if (!empty($request['transcript_signature'])): ?>
                                        <img src="../../uploads/signatures/<?php echo $request['transcript_signature']; ?>"
                                            style="max-height: 80px; display: block; margin: 0 auto;" alt="Transcript Signature">
                                    <?php else: ?>
                                        <p>__________________________</p>
                                    <?php endif; ?>
                                    <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                                    <p data-en="Transcript Professional" data-am="ትራንስክሪፕት ባለሙያ">Transcript Professional</p>
                                </div>

                                <!-- Registrar Head Signature -->
                                <div class="sig-block">
                                    <p><strong data-en="Registrar Head:" data-am="ሬጅስትራር ኃላፊ:">Registrar Head:</strong></p>
                                    <?php if (!empty($request['registrar_signature'])): ?>
                                        <img src="../../uploads/signatures/<?php echo $request['registrar_signature']; ?>"
                                            style="max-height: 80px; display: block; margin: 0 auto;" alt="Registrar Signature">
                                    <?php else: ?>
                                        <p>__________________________</p>
                                    <?php endif; ?>
                                    <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                                    <p data-en="Official Seal & Signature" data-am="የቢሮ ማህተም እና ፊርማ">Official Seal & Signature</p>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- Fallback / Request Status View -->
                            <div class="doc-header">
                                <h3 data-en="Debre Markos University" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">Debre Markos University</h3>
                                <?php if ($is_transfer_out): ?>
                                    <h4 data-en="Office of the Academic Vice President" data-am="የአካዳሚክ ም/ፕሬዝዳንት ጽህፈት ቤት">Office of the Academic Vice President</h4>
                                <?php else: ?>
                                    <h4 data-en="Office of the Registrar" data-am="ሬጅስትራር ጽህፈት ቤት">Office of the Registrar</h4>
                                <?php endif; ?>
                                <h5 data-en="Student Document Request Form" data-am="የተማሪ ሰነድ መጠየቂያ ቅጽ">Student Document Request
                                    Form</h5>
                            </div>

                            <div class="doc-meta">
                                <div>
                                    <strong data-en="Date:" data-am="ቀን:">Date:</strong>
                                    <?php echo date('F j, Y', strtotime($request['created_at'] ?? 'now')); ?><br>
                                    <strong data-en="Ref No:" data-am="ማጣቀሻ ቁጥር:">Ref No:</strong> DMU/REQ/
                                    <?php echo $request['id']; ?>
                                </div>
                                <div style="text-align: right;">
                                    <strong data-en="Status:" data-am="ሁኔታ:">Status:</strong>
                                    <span
                                        style="color: <?php echo $request['status'] == 'Delivered' ? 'green' : 'orange'; ?>; font-weight:bold;">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="doc-info">
                                <p><strong data-en="Student Name:" data-am="የተማሪ ስም:">Student Name:</strong>
                                    <?php echo $std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']; ?>
                                </p>
                                <p><strong data-en="Student ID:" data-am="የተማሪ መለያ:">Student ID:</strong>
                                    <?php echo $std_info['student_id']; ?>
                                </p>
                                <p><strong data-en="Department:" data-am="ትምህርት ክፍል:">Department:</strong>
                                    <?php echo $std_info['dept_name']; ?>
                                </p>
                            </div>

                            <div class="doc-subject">
                                <span data-en="Subject: Request for" data-am="ርዕሰ ጉዳይ: የጠየቁት ሰነድ">Subject: Request for</span>
                                <?php echo $request['request_type']; ?>
                            </div>

                            <div class="doc-body">
                                <?php if ($is_transfer_out): ?>
                                    <p data-en="To: Academic Vice President," data-am="ለ: አካዳሚክ ም/ፕሬዝዳንት,">To: Academic Vice President,</p>
                                <?php else: ?>
                                    <p data-en="To: Distro/Registrar Head," data-am="ለ: ስርጭት/ሬጅስትራር ኃላፊ,">To: Distro/Registrar Head,</p>
                                <?php endif; ?>
                                <p data-en="I, the undersigned student, have requested the issuance of my"
                                    data-am="እኔ, ከዚህ በታች የፈረምኩት ተማሪ, የሚከተለው ሰነድ እንዲሰጠኝ ጠይቄአለሁ">I, the undersigned student, have
                                    requested the issuance of my</p> <strong>
                                    <?php echo $request['request_type']; ?>
                                </strong>.</p>

                                <p><em><span data-en="Current Status:" data-am="የአሁኑ ሁኔታ:">Current Status:</span>
                                        <?php echo $request['status']; ?></em></p>
                                <?php if ($is_transfer_out && $request['status'] == 'Pending'): ?>
                                    <p data-en="Your request is being reviewed by the Academic Vice President."
                                        data-am="ጥያቄዎ በአካዳሚክ ም/ፕሬዝዳንት እየታየ ነው።">Your request is being reviewed by the Academic Vice President.</p>
                                <?php elseif ($is_transfer_out && $request['status'] == 'Forwarded'): ?>
                                    <p data-en="Your request has been forwarded to the Registrar by the Academic Vice President."
                                        data-am="ጥያቄዎ በአካዳሚክ ም/ፕሬዝዳንት ወደ ሬጅስትራር ተላልፏል።">Your request has been forwarded to the Registrar by the Academic Vice President.</p>
                                <?php elseif ($request['status'] == 'Pending Transcript'): ?>
                                    <p data-en="Your request is being processed by the Official Transcript Professional."
                                        data-am="ጥያቄዎ በኦፊሴላዊው ትራንስክሪፕት ባለሙያ እየተስተናገደ ነው።">Your request is being processed by the
                                        Official Transcript Professional.</p>
                                <?php elseif ($request['status'] == 'Pending Registrar Signature'): ?>
                                    <p data-en="Your document has been generated and is awaiting final signature from the Registrar."
                                        data-am="ሰነድዎ ተዘጋጅቷል እና ከሬጅስትራር የመጨረሻ ፊርማ እየጠበቀ ነው።">Your document has been generated and is
                                        awaiting final signature from the Registrar.</p>
                                <?php endif; ?>
                            </div>

                            <div class="doc-footer">
                                <div>
                                    <p data-en="Sincerely," data-am="ከሰላምታ ጋር,">Sincerely,</p>
                                    <br>
                                    <strong>
                                        <?php echo $std_info['first_name']; ?>
                                    </strong><br>
                                    <em data-en="(Digital Signature)" data-am="(ዲጂታል ፊርማ)">(Digital Signature)</em>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 50px;">
                        <h3 data-en="No Document Requests Found" data-am="ምንም የሰነድ ጥያቄዎች አልተገኙም">No Document Requests Found
                        </h3>
                        <p data-en="You haven't submitted any requests yet." data-am="እስካሁን ምንም ጥያቄ አላቀረቡም።">You haven't
                            submitted any requests yet.</p>
                        <a href="request_document.php" class="btn-primary" style="color:#ffffff; background-color:#000000;"
                            data-en="Request Document" data-am="ሰነድ ይጠይቁ">Request Document</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>