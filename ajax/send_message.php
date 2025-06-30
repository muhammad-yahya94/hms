<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Log the incoming request
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Request: ' . print_r($_REQUEST, true) . "\n", FILE_APPEND);

if (!isLoggedIn()) {
    $response = ['success' => false, 'message' => 'Not logged in'];
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . json_encode($response) . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

$response = ['success' => false];

// Check if it's a JSON request or form data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = [];
    }
} else {
    $data = $_POST;
}

file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Data: ' . print_r($data, true) . "\n", FILE_APPEND);

$conversation_id = isset($data['conversation_id']) ? intval($data['conversation_id']) : 0;
$message = isset($data['message']) ? trim($data['message']) : '';
$sender_type = isset($data['sender_type']) && $data['sender_type'] === 'admin' ? 'admin' : 'user';

// Debug log input data
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Processing - Conversation ID: ' . $conversation_id . ', Sender Type: ' . $sender_type . "\n", FILE_APPEND);

if ($conversation_id <= 0) {
    $response['message'] = 'Invalid conversation ID';
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . $response['message'] . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

if (empty($message)) {
    $response['message'] = 'Message cannot be empty';
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . $response['message'] . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Debug log before conversation check
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Checking conversation - User ID: ' . $user_id . "\n", FILE_APPEND);

// Verify conversation exists and user has access to it
$stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND (user_id = ? OR ? = 'admin')");
if ($stmt === false) {
    $error = $conn->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Prepare failed: ' . $error . "\n", FILE_APPEND);
    $response['message'] = 'Database error';
    $response['error'] = $error;
    echo json_encode($response);
    exit();
}

$stmt->bind_param("iis", $conversation_id, $user_id, $sender_type);
if (!$stmt->execute()) {
    $error = $stmt->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Execute failed: ' . $error . "\n", FILE_APPEND);
    $response['message'] = 'Database error';
    $response['error'] = $error;
    echo json_encode($response);
    exit();
}

$conversation = $stmt->get_result()->fetch_assoc();

if (!$conversation) {
    $response['message'] = 'Conversation not found or access denied';
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error: ' . $response['message'] . "\n", FILE_APPEND);
    echo json_encode($response);
    exit();
}

// Insert message
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Attempting to insert message...' . "\n", FILE_APPEND);

$stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
if ($stmt === false) {
    $error = $conn->error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Prepare failed: ' . $error . "\n", FILE_APPEND);
    $response['message'] = 'Database error';
    $response['error'] = $error;
    echo json_encode($response);
    exit();
}

$stmt->bind_param("iiss", $conversation_id, $user_id, $sender_type, $message);

if ($stmt->execute()) {
    // Update conversation's updated_at timestamp
    $update = $conn->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    if ($update === false) {
        file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Update prepare failed: ' . $conn->error . "\n", FILE_APPEND);
    } else {
        $update->bind_param("i", $conversation_id);
        if (!$update->execute()) {
            file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Update execute failed: ' . $update->error . "\n", FILE_APPEND);
        }
    }
    
    $response['success'] = true;
    $response['message_id'] = $conn->insert_id;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Message sent successfully. ID: ' . $response['message_id'] . "\n", FILE_APPEND);
} else {
    $error = $stmt->error;
    $response['message'] = 'Failed to send message';
    $response['error'] = $error;
    file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Error sending message: ' . $error . "\n", FILE_APPEND);
}

// Log the response
file_put_contents('../chat_debug.log', '[' . date('Y-m-d H:i:s') . '] Response: ' . json_encode($response) . "\n\n", FILE_APPEND);

echo json_encode($response);
?>
