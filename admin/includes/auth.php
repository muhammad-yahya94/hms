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

// Get admin's hotel ID
$admin_id = $_SESSION['user_id'];
$hotel_id = 0;

// Get hotel managed by this admin
$stmt = $conn->prepare("SELECT id FROM hotels WHERE vendor_id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($hotel = $result->fetch_assoc()) {
    $hotel_id = $hotel['id'];
}

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
        JOIN hotels h ON c.hotel_id = h.id
        WHERE c.id = ? AND h.vendor_id = ?
    ");
    $stmt->bind_param("ii", $conversationId, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
?>
