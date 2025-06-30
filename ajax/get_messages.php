<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors for production

require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Log the request
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] GET Request: ' . print_r($_GET, true) . "\n", FILE_APPEND);

if (!isLoggedIn()) {
    $response = ['success' => false, 'message' => 'Not logged in'];
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . json_encode($response) . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

$response = ['success' => false];
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;

// Debug log
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Getting messages for conversation: ' . $conversation_id . "\n", FILE_APPEND);

if ($conversation_id <= 0) {
    $response['message'] = 'Invalid conversation ID';
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . $response['message'] . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Debug log before conversation check
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Verifying conversation access for user: ' . $user_id . "\n", FILE_APPEND);

// First, get the hotel admin ID for this conversation
$hotel_admin_id = null;
$hotel_stmt = $conn->prepare("SELECT h.vendor_id as admin_id 
                           FROM conversations c 
                           JOIN hotels h ON c.hotel_id = h.id 
                           WHERE c.id = ?");
if ($hotel_stmt === false) {
    $response['message'] = 'Database error preparing hotel query';
    $response['error'] = $conn->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Prepare failed: ' . $conn->error . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}
$hotel_stmt->bind_param("i", $conversation_id);
$hotel_stmt->execute();
$hotel_result = $hotel_stmt->get_result();
if ($hotel_row = $hotel_result->fetch_assoc()) {
    $hotel_admin_id = $hotel_row['admin_id'];
}
$hotel_stmt->close();

// Verify conversation exists and user has access to it
$stmt = $conn->prepare("SELECT c.*, u.username as user_name, h.name as hotel_name 
                      FROM conversations c
                      LEFT JOIN users u ON c.user_id = u.id
                      LEFT JOIN hotels h ON c.hotel_id = h.id
                      WHERE c.id = ? AND (c.user_id = ? OR h.vendor_id = ?)");
if ($stmt === false) {
    $response['message'] = 'Database error preparing conversation query';
    $response['error'] = $conn->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Prepare failed: ' . $conn->error . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}
$stmt->bind_param("iii", $conversation_id, $user_id, $hotel_admin_id);
if (!$stmt->execute()) {
    $response['message'] = 'Database error executing conversation query';
    $response['error'] = $stmt->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Execute failed: ' . $stmt->error . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}
$conversation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$conversation) {
    $response['message'] = 'Conversation not found or access denied';
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . $response['message'] . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

// Debug log conversation data
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Conversation data: ' . print_r($conversation, true) . "\n", FILE_APPEND);

// Get messages
$stmt = $conn->prepare("
    SELECT m.*, u.username, u.profile_image, u.role
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ? 
    ORDER BY m.created_at ASC
");
if ($stmt === false) {
    $response['message'] = 'Database error preparing messages query';
    $response['error'] = $conn->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Prepare failed: ' . $conn->error . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}
$stmt->bind_param("i", $conversation_id);
if (!$stmt->execute()) {
    $response['message'] = 'Database error executing messages query';
    $response['error'] = $stmt->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Execute failed: ' . $stmt->error . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}
$result = $stmt->get_result();

// Debug: Log the number of messages found
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Found ' . $result->num_rows . ' messages\n', FILE_APPEND);

$messages = [];
while ($row = $result->fetch_assoc()) {
    $message = [
        'id' => $row['id'],
        'message' => $row['message'],
        'sender_type' => $row['sender_type'],
        'created_at' => $row['created_at'],
        'username' => $row['username'] ?? 'Unknown',
        'profile_image' => $row['profile_image'] ?? 'default-avatar.png',
        'is_read' => (bool)$row['is_read'],
        'role' => $row['role'] ?? 'user'
    ];
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Message: ' . print_r($message, true) . "\n", FILE_APPEND);
    $messages[] = $message;
}
$stmt->close();

// Mark messages as read if user is the recipient
if (!empty($messages)) {
    $user_type = ($hotel_admin_id == $user_id) ? 'admin' : 'user';
    $update = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE conversation_id = ? AND sender_type != ? AND is_read = FALSE");
    if ($update) {
        $update->bind_param("is", $conversation_id, $user_type);
        $update->execute();
        $update->close();
    } else {
        file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Failed to prepare update statement: ' . $conn->error . "\n", FILE_APPEND);
    }
}

$response['success'] = true;
$response['messages'] = $messages;

echo json_encode($response);
?>