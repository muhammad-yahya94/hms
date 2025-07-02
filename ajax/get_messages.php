<?php
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$response = ['success' => false];
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
    exit();
}

// Check if user is admin
$is_admin = false;
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$role_result = $stmt->get_result()->fetch_assoc();
if ($role_result && $role_result['role'] === 'admin') {
    $is_admin = true;
}
$stmt->close();

// Verify conversation access
$stmt = $conn->prepare("SELECT c.*, u1.username as user_name, u2.username as admin_name 
                       FROM conversations c
                       LEFT JOIN users u1 ON c.user_id = u1.id
                       LEFT JOIN users u2 ON c.admin_id = u2.id
                       WHERE c.id = ? AND (c.user_id = ? OR c.admin_id = ?)");
$stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
$stmt->execute();
$conversation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$conversation) {
    echo json_encode(['success' => false, 'message' => 'Conversation not found or access denied']);
    exit();
}

// Get messages
$stmt = $conn->prepare("
    SELECT m.*, u.username, u.profile_image, u.role
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ? 
    ORDER BY m.created_at ASC
");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'message' => $row['message'],
        'sender_type' => $row['sender_type'],
        'created_at' => $row['created_at'],
        'username' => $row['username'] ?? 'Unknown',
        'profile_image' => $row['profile_image'] ?? 'default-avatar.png',
        'is_read' => (bool)$row['is_read'],
        'role' => $row['role'] ?? 'user'
    ];
}
$stmt->close();

// Mark messages as read
$recipient_type = $is_admin ? 'user' : 'admin';
$update = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE conversation_id = ? AND sender_type = ? AND is_read = FALSE");
$update->bind_param("is", $conversation_id, $recipient_type);
$update->execute();
$update->close();

$response['success'] = true;
$response['messages'] = $messages;
echo json_encode($response);
?>