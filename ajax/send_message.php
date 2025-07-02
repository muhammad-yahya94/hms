<?php
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$response = ['success' => false];

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$conversation_id = isset($data['conversation_id']) ? intval($data['conversation_id']) : 0;
$message = isset($data['message']) ? trim($data['message']) : '';
$sender_type = isset($data['sender_type']) && $data['sender_type'] === 'admin' ? 'admin' : 'user';
$user_id = $_SESSION['user_id'];

if ($conversation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid conversation ID']);
    exit();
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

// Verify user is admin if sender_type is admin
if ($sender_type === 'admin') {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $role_result = $stmt->get_result()->fetch_assoc();
    if (!$role_result || $role_result['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized sender type']);
        exit();
    }
    $stmt->close();
}

// Verify conversation access
$stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND (user_id = ? OR admin_id = ?)");
$stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
$stmt->execute();
$conversation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$conversation) {
    echo json_encode(['success' => false, 'message' => 'Conversation not found or access denied']);
    exit();
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $conversation_id, $user_id, $sender_type, $message);

if ($stmt->execute()) {
    $update = $conn->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $update->bind_param("i", $conversation_id);
    $update->execute();
    $update->close();
    
    $response['success'] = true;
    $response['message_id'] = $conn->insert_id;
} else {
    $response['message'] = 'Failed to send message';
    $response['error'] = $stmt->error;
}
$stmt->close();

echo json_encode($response);
?>