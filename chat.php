<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$hotel_id = isset($_GET['hotel_id']) ? intval($_GET['hotel_id']) : 0;

// Get or create conversation
$conversation = null;
if ($hotel_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE user_id = ? AND hotel_id = ?");
    $stmt->bind_param("ii", $user_id, $hotel_id);
    $stmt->execute();
    $conversation = $stmt->get_result()->fetch_assoc();
    
    if (!$conversation) {
        $stmt = $conn->prepare("INSERT INTO conversations (user_id, hotel_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $hotel_id);
        if ($stmt->execute()) {
            $conversation_id = $conn->insert_id;
            $conversation = ['id' => $conversation_id, 'user_id' => $user_id, 'hotel_id' => $hotel_id];
        }
    }
}

// Get hotel info
$hotel = null;
if ($hotel_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmt->bind_param("i", $hotel_id);
    $stmt->execute();
    $hotel = $stmt->get_result()->fetch_assoc();
}

// Get messages
$messages = [];
if (!empty($conversation['id'])) {
    $stmt = $conn->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $conversation['id']);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($hotel['name'] ?? 'Hotel'); ?> - Jhang Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .chat-container {
            max-width: 800px;
            margin: 30px auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .chat-header {
            background-color: #d4a017;
            color: white;
            padding: 15px;
            text-align: center;
        }
        .chat-messages {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            background-color: #f9f9f9;
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
        }
        .message.sent .message-content {
            background-color: #d4a017;
            color: white;
        }
        .message-time {
            font-size: 0.8em;
            color: #6c757d;
            margin-top: 5px;
        }
        .chat-input {
            display: flex;
            padding: 15px;
            background-color: white;
            border-top: 1px solid #ddd;
        }
        .chat-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            margin-right: 10px;
        }
        .chat-input button {
            padding: 10px 20px;
            background-color: #d4a017;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }
        .chat-input button:hover {
            background-color: #b38b12;
        }
    </style>  
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <?php if (empty($hotel)): ?>
            <div class="alert alert-danger">Hotel not found.</div>
        <?php else: ?>
            <div class="chat-container">
                <div class="chat-header" style="margin-top:75px">
                    <h4>Chat with <?php echo htmlspecialchars($hotel['name']); ?></h4>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['sender_type'] === 'user' ? 'sent' : 'received'; ?>">
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                <div class="message-time">
                                    <?php echo date('M j, g:i a', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input">
                    <input type="text" id="messageInput" placeholder="Type your message...">
                    <button id="sendMessage">Send</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const chatMessages = $('#chatMessages');
            const messageInput = $('#messageInput');
            const sendButton = $('#sendMessage');
            const conversationId = <?php echo $conversation['id'] ?? 0; ?>;
            
            if (conversationId === 0) {
                alert('Error: Could not start conversation');
                return;
            }

            // Function to scroll to bottom of chat
            function scrollToBottom() {
                chatMessages.scrollTop(chatMessages[0].scrollHeight);
            }

            // Send message
            function sendMessage() {
                const message = messageInput.val().trim();
                if (message === '') return;

                // Disable send button to prevent multiple submissions
                sendButton.prop('disabled', true).text('Sending...');
                
                console.log('Sending message:', message);
                
                $.ajax({
                    url: 'ajax/send_message.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        conversation_id: conversationId,
                        message: message,
                        sender_type: 'user'
                    },
                    success: function(response) {
                        console.log('Server response:', response);
                        if (response.success) {
                            messageInput.val('');
                            loadMessages();
                        } else {
                            alert('Failed to send message: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response text:', xhr.responseText);
                        alert('Failed to send message. Please check the console for details.');
                    },
                    complete: function() {
                        // Re-enable send button
                        sendButton.prop('disabled', false).text('Send');
                    }
                });
            }

            // Load messages
            function loadMessages() {
                console.log('Loading messages for conversation:', conversationId);
                $.ajax({
                    url: 'ajax/get_messages.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { conversation_id: conversationId },
                    success: function(response) {
                        console.log('Messages loaded:', response);
                        if (response.success) {
                            let messagesHtml = '';
                            response.messages.forEach(function(msg) {
                                const isSent = msg.sender_type === 'user';
                                messagesHtml += `
                                    <div class="message ${isSent ? 'sent' : 'received'}">
                                        <div class="message-content">
                                            ${msg.message.replace(/\n/g, '<br>')}
                                            <div class="message-time">
                                                ${new Date(msg.created_at).toLocaleString('en-US', { 
                                                    month: 'short', 
                                                    day: 'numeric', 
                                                    hour: 'numeric', 
                                                    minute: '2-digit',
                                                    hour12: true 
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            chatMessages.html(messagesHtml);
                            scrollToBottom();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading messages:', status, error);
                        console.error('Response text:', xhr.responseText);
                    }
                });
            }

            // Event listeners
            sendButton.on('click', function(e) {
                e.preventDefault();
                sendMessage();
            });
            
            messageInput.on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) { // Enter key without shift
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Debug info
            console.log('Chat initialized with conversation ID:', conversationId);

            // Poll for new messages every 3 seconds
            setInterval(loadMessages, 3000);
            
            // Initial scroll to bottom
            scrollToBottom();
        });
    </script>
</body>
</html>
