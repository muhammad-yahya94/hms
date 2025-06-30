<?php
// Set default timezone to Pakistan Standard Time (PKT)
date_default_timezone_set('Asia/Karachi');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

/**
 * Get user data from session or database
 */
function getUserData() {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Return from session if already loaded
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    
    // Load from database
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Store in session for future use
    if ($user) {
        $_SESSION['user_data'] = $user;
    }
    
    return $user;
}
?> 