<?php
require_once 'config/database.php';

/**
 * Get user data by ID
 */
function getUserById($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get hotel data by ID
 */
function getHotelById($hotelId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get average rating for a hotel
 */
function getAverageRating($hotelId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
        FROM reviews 
        WHERE hotel_id = ?
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Check if a conversation exists between user and hotel
 */
function getConversationId($userId, $hotelId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT id FROM conversations 
        WHERE user_id = ? AND hotel_id = ?
    ");
    $stmt->bind_param("ii", $userId, $hotelId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc()['id'] : null;
}

/**
 * Create a new conversation
 */
function createConversation($userId, $hotelId) {
    global $conn;
    $stmt = $conn->prepare("
        INSERT INTO conversations (user_id, hotel_id) 
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $userId, $hotelId);
    $stmt->execute();
    return $conn->insert_id;
}

/**
 * Send a message
 */
function sendMessage($conversationId, $senderId, $message, $senderType) {
    global $conn;
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, sender_type) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiss", $conversationId, $senderId, $message, $senderType);
    $stmt->execute();
    
    // Update conversation timestamp
    $update = $conn->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $update->bind_param("i", $conversationId);
    $update->execute();
    
    return $conn->insert_id;
}

/**
 * Get messages for a conversation
 */
function getMessages($conversationId, $limit = 50) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.profile_image 
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $conversationId, $limit);
    $stmt->execute();
    return array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

/**
 * Get unread message count for a user
 */
function getUnreadMessageCount($userId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM messages m 
        JOIN conversations c ON m.conversation_id = c.id 
        WHERE c.user_id = ? AND m.sender_type = 'admin' AND m.is_read = FALSE"
    );
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['count'] : 0;
}

/**
 * Mark messages as read
 */
function markMessagesAsRead($conversationId, $userId) {
    global $conn;
    $stmt = $conn->prepare("
        UPDATE messages m 
        JOIN conversations c ON m.conversation_id = c.id 
        SET m.is_read = TRUE 
        WHERE c.id = ? AND c.user_id = ? AND m.sender_type = 'admin' AND m.is_read = FALSE"
    );
    
    $stmt->bind_param("ii", $conversationId, $userId);
    return $stmt->execute();
}
?>
