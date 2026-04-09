<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once '../../includes/session_manager.php';
}
require_once __DIR__ . '/../../config/db_connect.php';

/**
 * Get users available for chat based on the current user's role.
 * 
 * Rules:
 * - Registrar: Can chat with 'admin', 'department_head', 'transcript_pro', 'cost_sharing_pro'.
 * - Others: Can ONLY chat with 'registrar'.
 */
function getChatUsers($pdo, $current_user_id, $current_role)
{
    $users = [];

    if ($current_role === 'registrar') {
        // Registrar can see everyone except students
        // Specifically requested roles: Official Transcript Professional, Department Head, Admin, cost sharing professional
        $sql = "SELECT u.id, u.first_name, u.last_name, u.role, u.last_seen,
                (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = :current_user_id AND is_read = 0) as unread_count,
                TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) as seconds_ago,
                d.name as dept_name
                FROM users u
                LEFT JOIN departments d ON u.id = d.head_user_id
                WHERE u.role IN ('admin', 'department_head', 'transcript_pro', 'cost_sharing_pro') 
                AND u.status = 'active'
                ORDER BY u.role, u.first_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['current_user_id' => $current_user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Everyone else can only see the Registrar
        $sql = "SELECT id, first_name, last_name, role, last_seen,
                (SELECT COUNT(*) FROM messages WHERE sender_id = users.id AND receiver_id = :current_user_id AND is_read = 0) as unread_count,
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
                FROM users 
                WHERE role = 'registrar' 
                AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['current_user_id' => $current_user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $users;
}

/**
 * Get messages between two users.
 */
function getMessages($pdo, $user1_id, $user2_id)
{
    $sql = "SELECT m.*, u.first_name, u.last_name, u.role
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send a message.
 */
function sendMessage($pdo, $sender_id, $receiver_id, $message)
{
    // Basic validation
    if (empty(trim($message))) {
        return false;
    }

    $sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$sender_id, $receiver_id, trim($message)]);
}

/**
 * Mark messages as read.
 */
/**
 * Mark messages as read.
 */
function markMessagesRead($pdo, $sender_id, $receiver_id)
{
    $sql = "UPDATE messages SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sender_id, $receiver_id]);
}

/**
 * Delete a message (Hard Delete).
 * Only the sender can delete their own message.
 */
function deleteMessage($pdo, $message_id, $user_id)
{
    $sql = "DELETE FROM messages WHERE id = ? AND sender_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$message_id, $user_id]);
}

/**
 * Edit a message.
 * Only the sender can edit their own message.
 */
function editMessage($pdo, $message_id, $user_id, $new_content)
{
    if (empty(trim($new_content)))
        return false;

    // Check if message exists and is within 1 minute (using DB time to avoid timezone mismatch)
    // TIMESTAMPDIFF(SECOND, created_at, NOW()) returns seconds elapsed
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as diff_seconds FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$message_id, $user_id]);
    $msg = $stmt->fetch();

    if (!$msg)
        return false;

    // Check time difference (24 hours = 86400 seconds)
    if ($msg['diff_seconds'] > 86400) {
        return 'timeout';
    }

    $sql = "UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([trim($new_content), $message_id, $user_id]);
}
/**
 * Delete old messages (older than 1 week).
 */
function deleteOldMessages($pdo)
{
    // Delete messages older than 7 days
    $sql = "DELETE FROM messages WHERE created_at < NOW() - INTERVAL 7 DAY";
    $pdo->query($sql);
}
?>