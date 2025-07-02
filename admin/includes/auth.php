<?php
// Check if user is logged in and is an admin
if (!isLoggedIn()) {
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Check if user is an admin (assuming role is stored in session)
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'You do not have permission to access the admin panel.';
    header('Location: ../index.php');
    exit();
}

// Get admin ID
$admin_id = $_SESSION['user_id'];

// Function to check if the current admin has access to a specific hotel
function hasAccessToHotel($hotelId) {
    global $conn, $admin_id;
    
    $stmt = $conn->prepare("SELECT id FROM hotels WHERE id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $hotelId, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Function to check if the current admin has access to a specific conversation
function hasAccessToConversation($conversationId) {
    global $conn, $admin_id;
    
    $stmt = $conn->prepare("
        SELECT c.id 
        FROM conversations c
        WHERE c.id = ? AND c.admin_id = ?
    ");
    $stmt->bind_param("ii", $conversationId, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
?>