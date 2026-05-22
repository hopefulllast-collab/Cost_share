<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                      FROM official_transcript dr 
                      JOIN students s ON dr.student_id = s.user_id 
                      JOIN users u ON s.user_id = u.id 
                      LEFT JOIN departments d ON s.department_id = d.id 
                      WHERE dr.id = ?");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    die("Document not found");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Request Form</title>
    <!-- Simple styles borrowed from view_document.php -->
    <style>
        body {
            font-family: 'Times New Roman', serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
        }

        .paper-doc {
            background: #fff;
            padding: 40px;
            margin: 0 auto;
            max-width: 700px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            color: #000;
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .doc-header h3,
        .doc-header h4,
        .doc-header h5 {
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
            font-size: 1.1em;
            margin-bottom: 20px;
            text-decoration: underline;
        }

        .doc-info p {
            margin: 5px 0;
        }

        .doc-body {
            line-height: 1.6;
            font-size: 1.05em;
            min-height: 150px;
        }

        .doc-footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>

<body>
    <div class="paper-doc">
        <div class="doc-header">
            <h3 data-en="Debre Markos University" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">Debre Markos University</h3>
            <h4 data-en="Office of the Registrar" data-am="ሬጅስትራር ጽህፈት ቤት">Office of the Registrar</h4>
            <h5 data-en="Student Document Request Form" data-am="የተማሪ ሰነድ መጠየቂያ ቅጽ">Student Document Request Form</h5>
        </div>

        <div class="doc-meta">
            <div>
                <strong data-en="Date:" data-am="ቀን:">Date:</strong>
                <?php echo date('F j, Y', strtotime($request['created_at'] ?? 'now')); ?><br>
                <strong data-en="Ref No:" data-am="ማጣቀሻ ቁጥር:">Ref No:</strong> DMU/REQ/
                <?php echo $request['id']; ?>
            </div>
        </div>

        <div class="doc-info" style="margin-bottom:20px;">
            <p><strong data-en="Student Name:" data-am="የተማሪ ስም:">Student Name:</strong>
                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['middle_name'] . ' ' . $request['last_name']); ?>
            </p>
            <p><strong data-en="Student ID:" data-am="የተማሪ መለያ:">Student ID:</strong>
                <?php echo htmlspecialchars($request['real_student_id']); ?>
            </p>
            <p><strong data-en="Department:" data-am="ትምህርት ክፍል:">Department:</strong>
                <?php echo htmlspecialchars($request['dept_name']); ?>
            </p>
        </div>

        <div class="doc-subject">
            <span data-en="Subject: Request for" data-am="ርዕሰ ጉዳይ: የጠየቁት ሰነድ">Subject: Request for</span>
            <?php echo htmlspecialchars($request['request_type']); ?>
        </div>

        <div class="doc-body">
            <p data-en="To: Distro/Registrar Head," data-am="ለ: ስርጭት/ሬጅስትራር ኃላፊ,">To: Distro/Registrar Head,</p>
            <p data-en="I, the undersigned student, have requested the issuance of my"
                data-am="እኔ, ከዚህ በታች የፈረምኩት ተማሪ, የሚከተለው ሰነድ እንዲሰጠኝ ጠይቄአለሁ">I, the undersigned student, have
                requested the issuance of my <strong>
                    <?php echo htmlspecialchars($request['request_type']); ?>
                </strong>.</p>
        </div>

        <div class="doc-footer">
            <div>
                <p data-en="Sincerely," data-am="ከሰላምታ ጋር,">Sincerely,</p>
                <br>
                <strong>
                    <?php echo htmlspecialchars($request['first_name']); ?>
                </strong><br>
                <em data-en="(Digital Signature)" data-am="(ዲጂታል ፊርማ)">(Digital Signature)</em>
            </div>
        </div>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>