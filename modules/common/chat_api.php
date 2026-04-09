<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once 'chat_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

header('Content-Type: application/json');

try {
    if (isset($_GET['action'])) {

        // Heartbeat (Update Online Status)
        if ($_GET['action'] === 'heartbeat') {
            $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
            $stmt->execute([$current_user_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        // Fetch Users (Registrar logic)
        if ($_GET['action'] === 'fetch_users') {
            $users = getChatUsers($pdo, $current_user_id, $_SESSION['role']);
            // Add relative time logic if needed here or in frontend
            echo json_encode($users);
            exit;
        }

        // Fetch Single User Status (for Chat Header)
        if ($_GET['action'] === 'get_user_status') {
            if (!isset($_GET['user_id']))
                exit;
            $stmt = $pdo->prepare("SELECT last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago FROM users WHERE id = ?");
            $stmt->execute([$_GET['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode($user);
            } else {
                echo json_encode(['error' => 'User not found']);
            }
            exit;
        }

        // Fetch Messages
        if ($_GET['action'] === 'fetch_messages') {
            if (!isset($_GET['partner_id'])) {
                echo json_encode([]);
                exit;
            }
            $partner_id = $_GET['partner_id'];

            // Cleanup old messages
            deleteOldMessages($pdo);

            // Security check: Non-registrars can only fetch messages with registrar
            // But getMessages() logic inherently restricts to current user's messages anyway

            $messages = getMessages($pdo, $current_user_id, $partner_id);
            echo json_encode($messages);
            exit;
        }

        // Send Message
        if ($_GET['action'] === 'send_message') {
            if (!isset($_POST['receiver_id']) || !isset($_POST['message'])) {
                echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                exit;
            }

            $receiver_id = $_POST['receiver_id'];
            $message = $_POST['message'];

            $success = sendMessage($pdo, $current_user_id, $receiver_id, $message);
            echo json_encode(['success' => $success]);
            exit;
        }

        // Mark Read
        if ($_GET['action'] === 'mark_read') {
            if (!isset($_POST['sender_id'])) {
                echo json_encode(['success' => false]);
                exit;
            }
            $sender_id = $_POST['sender_id'];
            markMessagesRead($pdo, $sender_id, $current_user_id);
            echo json_encode(['success' => true]);
            exit;
        }

        // Delete Message
        if ($_GET['action'] === 'delete_message') {
            if (!isset($_POST['message_id'])) {
                echo json_encode(['success' => false, 'error' => 'Missing message_id']);
                exit;
            }
            $success = deleteMessage($pdo, $_POST['message_id'], $current_user_id);
            echo json_encode(['success' => $success]);
            exit;
        }

        // Edit Message
        if ($_GET['action'] === 'edit_message') {
            if (!isset($_POST['message_id']) || !isset($_POST['message'])) {
                echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                exit;
            }
            $result = editMessage($pdo, $_POST['message_id'], $current_user_id, $_POST['message']);

            if ($result === 'timeout') {
                echo json_encode(['success' => false, 'error' => 'Time limit exceeded. You can only edit within 24 hours.']);
            } else {
                echo json_encode(['success' => $result]);
            }
            exit;
        }

        // Check Unread Count (Total)
        if ($_GET['action'] === 'unread_count') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
            $stmt->execute([$current_user_id]);
            $count = $stmt->fetchColumn();
            echo json_encode(['count' => $count]);
            exit;
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>