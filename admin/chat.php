<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once 'includes/auth.php';

// Ensure admin is logged in
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get hotel ID for the admin
$admin_id = $_SESSION['user_id'];
$hotel_id = 0;

// Get hotel managed by this admin
$stmt = $conn->prepare("SELECT id FROM hotels WHERE vendor_id = ? LIMIT 1");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error, 3, '../error.log');
    die("Database error. Please try again later.");
}
$stmt->bind_param("i", $admin_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error, 3, '../error.log');
    die("Database error. Please try again later.");
}
$result = $stmt->get_result();

if ($hotel = $result->fetch_assoc()) {
    $hotel_id = $hotel['id'];
} else {
    $error_message = "No hotel assigned to this admin account.";
    error_log("No hotel found for admin_id: $admin_id", 3, '../error.log');
}
$stmt->close();

// Get conversations for this hotel
$conversations = [];
if ($hotel_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, u.username, u.profile_image,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.is_read = FALSE) as unread_count
        FROM conversations c
        JOIN users u ON c.user_id = u.id
        WHERE c.hotel_id = ?
        ORDER BY c.updated_at DESC
    ");
    if ($stmt === false) {
        error_log("Prepare failed for conversations: " . $conn->error, 3, '../error.log');
        die("Database error. Please try again later.");
    }
    $stmt->bind_param("i", $hotel_id);
    $stmt->execute();
    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get messages for the selected conversation
$selected_conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$messages = [];
$recipient = null;

if ($selected_conversation_id > 0 && $hotel_id > 0) {
    // Verify this conversation belongs to the admin's hotel
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? AND hotel_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for conversation check: " . $conn->error, 3, '../error.log');
        die("Database error. Please try again later.");
    }
    $stmt->bind_param("ii", $selected_conversation_id, $hotel_id);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($conversation) {
        // Get user info
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        if ($stmt === false) {
            error_log("Prepare failed for user info: " . $conn->error, 3, '../error.log');
            die("Database error. Please try again later.");
        }
        $stmt->bind_param("i", $conversation['user_id']);
        $stmt->execute();
        $recipient = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Get messages
        $stmt = $conn->prepare("
            SELECT m.*, u.username, u.profile_image 
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE m.conversation_id = ? 
            ORDER BY m.created_at ASC
        ");
        if ($stmt === false) {
            error_log("Prepare failed for messages: " . $conn->error, 3, '../error.log');
            die("Database error. Please try again later.");
        }
        $stmt->bind_param("i", $selected_conversation_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Mark messages as read
        $update = $conn->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE conversation_id = ? 
            AND sender_type = 'user' 
            AND is_read = FALSE
        ");
        if ($update) {
            $update->bind_param("i", $selected_conversation_id);
            $update->execute();
            $update->close();
        } else {
            error_log("Prepare failed for marking messages read: " . $conn->error, 3, '../error.log');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Chat - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .chat-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
            overflow: hidden;
            display: flex;
            height: calc(100vh - 200px);
            transition: transform 0.3s;
        }
        .chat-container:hover {
            transform: translateY(-5px);
        }
        .conversation-list {
            width: 300px;
            border-right: 1px solid #ddd;
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        .chat-messages {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }
        .chat-header {
            background-color: #d4a017;
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            display: flex;
            align-items: center;
        }
        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .chat-header .fw-bold {
            font-size: 1.2rem;
        }
        .chat-header .text-muted {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }
        .message-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fff;
        }
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        .message.sent {
            margin-left: auto;
            text-align: right;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 15px;
            background-color: #e9ecef;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .message.sent .message-content {
            background-color: #d4a017;
            color: white;
        }
        .message-time {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
        .chat-input {
            display: flex;
            padding: 15px;
            background-color: #fff;
            border-top: 1px solid #ddd;
        }
        .chat-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            margin-right: 10px;
            font-family: 'Poppins', sans-serif;
        }
        .chat-input button {
            background-color: #d4a017;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        .chat-input button:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 5px;
            margin: 5px;
        }
        .conversation-item:hover {
            background-color: #f1f1f1;
        }
        .conversation-item.active {
            background-color: #d4a017;
            color: white;
        }
        .conversation-item .fw-bold {
            font-size: 1.1rem;
        }
        .conversation-item .text-muted {
            font-size: 0.9rem;
            color: #666;
        }
        .conversation-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8);
        }
        .unread-count {
            background-color: #dc3545;
            color: white;
            border-radius: 15px;
            padding: 5px 10px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .alert {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid main-content">
        <div class="row">
            <div class="col-12">
                <h2>Customer Chats</h2>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($error_message); ?></div>
                <?php else: ?>
                    <div class="chat-container">
                        <!-- Conversation List -->
                        <div class="conversation-list">
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?conversation_id=<?php echo $conv['id']; ?>" 
                                   class="text-decoration-none text-dark">
                                    <div class="conversation-item <?php echo $selected_conversation_id == $conv['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="fw-bold"><?php echo htmlspecialchars($conv['username']); ?></div>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="unread-count"><?php echo $conv['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('M j, g:i a', strtotime($conv['updated_at'])); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($conversations)): ?>
                                <div class="p-3 text-muted text-center">No conversations yet</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Chat Area -->
                        <div class="chat-messages">
                            <?php if ($selected_conversation_id > 0 && $recipient): ?>
                                <!-- Chat Header -->
                                <div class="chat-header">
                                    <img src="<?php echo !empty($recipient['profile_image']) ? '../' . htmlspecialchars($recipient['profile_image']) : 'https://via.placeholder.com/40'; ?>" 
                                         alt="<?php echo htmlspecialchars($recipient['username']); ?>">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($recipient['username']); ?></div>
                                        <div class="small text-muted">Online</div>
                                    </div>
                                </div>
                                
                                <!-- Messages -->
                                <div class="message-list" id="messageList">
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo $message['sender_type'] === 'admin' ? 'sent' : 'received'; ?>">
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                                <div class="message-time">
                                                    <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Message Input -->
                                <div class="chat-input">
                                    <form id="messageForm" class="d-flex">
                                        <input type="hidden" name="conversation_id" value="<?php echo $selected_conversation_id; ?>">
                                        <input type="text" name="message" class="form-control me-2" placeholder="Type your message..." required>
                                        <button type="submit" class="btn btn-custom">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                    Select a conversation to start chatting
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-scroll to bottom of messages
            function scrollToBottom() {
                const messageList = document.getElementById('messageList');
                if (messageList) {
                    messageList.scrollTop = messageList.scrollHeight;
                }
            }
            
            // Send message
            $('#messageForm').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const messageInput = form.find('input[name="message"]');
                const message = messageInput.val().trim();
                
                if (message === '') return;
                
                $.post('../ajax/send_message.php', {
                    conversation_id: form.find('input[name="conversation_id"]').val(),
                    message: message,
                    sender_type: 'admin'
                }, function(response) {
                    if (response.success) {
                        // Reload the page to show the new message
                        location.reload();
                    } else {
                        alert('Failed to send message: ' + (response.message || 'Unknown error'));
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('Send message error:', status, error, xhr.responseText);
                    alert('Failed to send message. Please check the console for details.');
                });
                
                // Clear input
                messageInput.val('');
            });
            
            // Poll for new messages every 5 seconds if in a conversation
            <?php if ($selected_conversation_id > 0): ?>
                setInterval(function() {
                    $.get('../ajax/get_messages.php', {
                        conversation_id: <?php echo $selected_conversation_id; ?>
                    }, function(response) {
                        if (response.success) {
                            // Only reload if we have new messages
                            if (response.messages.length > <?php echo count($messages); ?>) {
                                location.reload();
                            }
                        }
                    }, 'json').fail(function(xhr, status, error) {
                        console.error('Poll messages error:', status, error, xhr.responseText);
                    });
                }, 5000);
            <?php endif; ?>
            
            // Initial scroll to bottom
            scrollToBottom();
        });
    </script>
</body>
</html>  