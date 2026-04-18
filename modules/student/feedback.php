<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/encryption.php';
checkAuth(['student']);

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Banned keywords list (English + Amharic insults, vulgar, offensive)
$banned_keywords = [
    // English
    'fuck', 'shit', 'damn', 'bitch', 'asshole', 'bastard', 'stupid', 'idiot', 'dumb',
    'moron', 'fool', 'ugly', 'hate', 'kill', 'die', 'suck', 'crap', 'hell', 'ass',
    'dick', 'penis', 'vagina', 'sex', 'porn', 'nude', 'naked', 'whore', 'slut',
    'retard', 'loser', 'trash','useless', 'worthless', 'disgusting','leba', 'egelhalehu','tegbehal','ahya',
    // Amharic
    'ደንቆሮ', 'ሞኝ', 'ቂል', 'ከብት', 'አህያ', 'ውሻ', 'ሰካራም', 'ቆሻሻ', 'መርዘኛ',
    'ሌባ', 'ዋሸሸ', 'ባለጌ', 'ሰርቂ', 'ጉድ', 'ክፉ', 'ቅጥፈት', 'አላዋቂ', 'ደደብ',
    'ጅል', 'ጠላት', 'ዝሙት', 'ሴሰኛ', 'አመንዝራ', 'ረብ የለሽ', 'ቡዳ','ሌባ',
];

function containsBannedWord($text, $banned_keywords) {
    $text_lower = mb_strtolower($text, 'UTF-8');
    foreach ($banned_keywords as $word) {
        $word_lower = mb_strtolower($word, 'UTF-8');
        if (mb_strpos($text_lower, $word_lower) !== false) {
            return $word;
        }
    }
    return false;
}

// Handle new feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_feedback'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Check for banned keywords
    $found_word = containsBannedWord($message, $banned_keywords);
    if ($found_word) {
        $error = "<span data-en='Your message contains inappropriate language. Please use respectful words when submitting feedback.' data-am='መልዕክትዎ ተገቢ ያልሆነ ቋንቋ ይዟል። እባክዎ አስተያየት ሲልኩ አክብሮት ያለው ቃላት ይጠቀሙ።'>Your message contains inappropriate language. Please use respectful words when submitting feedback.</span>";
    }
    // Check if student already has a pending feedback on the same subject
    elseif (empty($error)) {
        $dup_check = $pdo->prepare("SELECT id FROM feedback WHERE student_id = ? AND subject = ? AND status = 'Pending'");
        $dup_check->execute([$_SESSION['user_id'], $subject]);
        if ($dup_check->fetch()) {
            $error = "<span data-en='You already have a pending feedback on \"" . htmlspecialchars($subject) . "\". Please wait until it is resolved before submitting another.' data-am='በ\"" . htmlspecialchars($subject) . "\" ላይ ገና ያልተፈታ አስተያየት አለዎት። እባክዎ ሌላ ከመላክዎ በፊት እስኪፈታ ይጠብቁ።'>You already have a pending feedback on \"" . htmlspecialchars($subject) . "\". Please wait until it is resolved before submitting another.</span>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO feedback (student_id, subject, message) VALUES (:uid, :sub, :msg)");
            $stmt->execute([':uid' => $_SESSION['user_id'], ':sub' => $subject, ':msg' => encryptData($message)]);
            $_SESSION['flash_success'] = "<span data-en='Feedback sent successfully.' data-am='አስተያየትዎ በተሳካ ሁኔታ ተልኳል።'>Feedback sent successfully.</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Handle delete feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_feedback'])) {
    $fb_id = (int) $_POST['feedback_id'];
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ? AND student_id = ?");
    $stmt->execute([$fb_id, $_SESSION['user_id']]);
    $_SESSION['flash_success'] = "<span data-en='Feedback deleted.' data-am='አስተያየቱ ተሰርዟል።'>Feedback deleted.</span>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all feedback submitted by this student
$my_feedbacks = $pdo->prepare("SELECT * FROM feedback WHERE student_id = :uid ORDER BY created_at DESC");
$my_feedbacks->execute([':uid' => $_SESSION['user_id']]);
$my_feedbacks = $my_feedbacks->fetchAll(PDO::FETCH_ASSOC);

// Build list of subjects that have pending feedback (for JS warning)
$pending_subjects = [];
foreach ($my_feedbacks as $fb) {
    if (($fb['status'] ?? 'Pending') === 'Pending') {
        $pending_subjects[] = $fb['subject'];
    }
}
$pending_subjects_json = json_encode(array_unique($pending_subjects));
$banned_keywords_json = json_encode($banned_keywords);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Feedback - DMU" data-am="አስተያየት - DMU">Feedback - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .feedback-history-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .feedback-history-item:last-child {
            border-bottom: none;
        }
        .feedback-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .badge-resolved {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending {
            background: #fff3e0;
            color: #ef6c00;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .feedback-subject {
            font-weight: bold;
            margin-right: 8px;
        }
        .feedback-date {
            color: #888;
            font-size: 13px;
        }
        .feedback-message {
            margin-top: 5px;
            color: #555;
        }
        .btn-delete-fb {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 8px;
        }
        .btn-delete-fb:hover {
            background: #c82333;
        }
        .warning-msg {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .warning-msg i {
            font-size: 18px;
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
                    <h2 data-en="Feedback" data-am="አስተያየት">Feedback</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <form method="POST" id="feedbackForm">
                        <div class="form-group">
                            <label data-en="Subject" data-am="ርዕሰ ጉዳይ">Subject</label>
                            <select name="subject" id="subjectSelect" onchange="checkPendingSubject()">
                                <option value="" data-en="-- Select Subject --" data-am="-- ርዕሰ ጉዳይ ይምረጡ --">-- Select Subject --</option>
                                <option value="System Performance" data-en="System Performance" data-am="ስርዓት አፈጻጸም">System Performance</option>
                                <option value="Cost Sharing Issue" data-en="Cost Sharing Issue" data-am="የውጪ መጋራት ችግር">Cost Sharing Issue</option>
                                <option value="Tuition Rate" data-en="Tuition Rate" data-am="የትምህርት ተመን">Tuition Rate</option>
                                <option value="Other" data-en="Other" data-am="ሌላ">Other</option>
                            </select>
                        </div>

                        <!-- Warning for duplicate subject -->
                        <div class="warning-msg" id="pendingWarning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="pendingWarningText"></span>
                        </div>

                        <!-- Warning for inappropriate language -->
                        <div class="warning-msg" id="keywordWarning" style="background:#f8d7da; color:#721c24; border-color:#f5c6cb;">
                            <i class="fas fa-ban"></i>
                            <span id="keywordWarningText"></span>
                        </div>

                        <div class="form-group">
                            <label data-en="Message" data-am="መልዕክት">Message</label>
                            <textarea name="message" id="messageArea" required
                                style="width:100%; height:100px; padding:10px;" oninput="checkKeywords()"></textarea>
                        </div>
                        <button type="submit" name="send_feedback" id="submitBtn" class="btn-primary" data-en="Send Feedback" data-am="አስተያየት ላክ"
                            style="color:#ffffff; background-color:#000000;">Send Feedback</button>
                    </form>
                </div>

                <!-- My Feedback History -->
                <div class="top-bar" style="margin-top: 20px;">
                    <h2 data-en="My Feedback" data-am="የእኔ አስተያየቶች">My Feedback</h2>
                </div>
                <div class="card">
                    <?php if (empty($my_feedbacks)): ?>
                        <p data-en="No feedback submitted yet." data-am="እስካሁን ምንም አስተያየት አልተላከም።">No feedback submitted yet.</p>
                    <?php else: ?>
                        <?php foreach ($my_feedbacks as $fb): ?>
                            <div class="feedback-history-item">
                                <div class="feedback-history-header">
                                    <div>
                                        <span class="feedback-subject"><?php echo htmlspecialchars($fb['subject']); ?></span>
                                        <span class="<?php echo $fb['status'] == 'Resolved' ? 'badge-resolved' : 'badge-pending'; ?>"
                                            data-en="<?php echo $fb['status'] ?? 'Pending'; ?>"
                                            data-am="<?php echo ($fb['status'] == 'Resolved') ? 'ተፈቷል' : 'በመጠባበቅ ላይ'; ?>">
                                            <?php echo $fb['status'] ?? 'Pending'; ?>
                                        </span>
                                        <form method="POST" style="display:inline;" id="deleteForm_<?php echo $fb['id']; ?>">
                                            <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                            <input type="hidden" name="delete_feedback" value="1">
                                            <button type="button" class="btn-delete-fb"
                                                data-en="Delete" data-am="ሰርዝ"
                                                onclick="showBilingualConfirm('Are you sure you want to delete this feedback?', 'እርግጠኛ ነዎት ይህን አስተያየት መሰረዝ ይፈልጋሉ?', function(){ document.getElementById('deleteForm_<?php echo $fb['id']; ?>').submit(); })">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <span class="feedback-date">
                                        <i class="fas fa-clock"></i> <?php echo $fb['created_at']; ?>
                                    </span>
                                </div>
                                <p class="feedback-message"><?php echo nl2br(htmlspecialchars(decryptData($fb['message']))); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        var pendingSubjects = <?php echo $pending_subjects_json; ?>;
        var bannedKeywords = <?php echo $banned_keywords_json; ?>;
        var hasKeywordIssue = false;

        function checkKeywords() {
            var messageArea = document.getElementById('messageArea');
            var warning = document.getElementById('keywordWarning');
            var warningText = document.getElementById('keywordWarningText');
            var submitBtn = document.getElementById('submitBtn');
            var text = messageArea.value.toLowerCase();

            hasKeywordIssue = false;
            for (var i = 0; i < bannedKeywords.length; i++) {
                if (text.indexOf(bannedKeywords[i].toLowerCase()) !== -1) {
                    hasKeywordIssue = true;
                    break;
                }
            }

            if (hasKeywordIssue) {
                var lang = localStorage.getItem('dmu_lang') || 'en';
                if (lang === 'am') {
                    warningText.textContent = 'መልዕክትዎ ተገቢ ያልሆነ ቋንቋ ይዟል። እባክዎ አክብሮት ያለው ቃላት ይጠቀሙ።';
                } else {
                    warningText.textContent = 'Your message contains inappropriate language. Please use respectful words.';
                }
                warning.style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                warning.style.display = 'none';
                // Only re-enable if no pending subject issue either
                if (!document.getElementById('pendingWarning').style.display || document.getElementById('pendingWarning').style.display === 'none') {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }

        function checkPendingSubject() {
            var select = document.getElementById('subjectSelect');
            var warning = document.getElementById('pendingWarning');
            var warningText = document.getElementById('pendingWarningText');
            var submitBtn = document.getElementById('submitBtn');
            var messageArea = document.getElementById('messageArea');
            var selectedSubject = select.value;

            if (selectedSubject && pendingSubjects.indexOf(selectedSubject) !== -1) {
                var lang = localStorage.getItem('dmu_lang') || 'en';
                if (lang === 'am') {
                    warningText.textContent = 'በ"' + selectedSubject + '" ላይ ገና ያልተፈታ አስተያየት አለዎት። እባክዎ ሌላ ከመላክዎ በፊት እስኪፈታ ይጠብቁ።';
                } else {
                    warningText.textContent = 'You already have a pending feedback on "' + selectedSubject + '". Please wait until it is resolved before submitting another.';
                }
                warning.style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
                messageArea.disabled = true;
                messageArea.style.opacity = '0.5';
            } else {
                warning.style.display = 'none';
                messageArea.disabled = false;
                messageArea.style.opacity = '1';
                // Only re-enable submit if no keyword issue
                if (!hasKeywordIssue) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            checkPendingSubject();
            checkKeywords();
        });
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>