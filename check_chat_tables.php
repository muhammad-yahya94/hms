<?php
require_once 'config/database.php';

header('Content-Type: text/plain');

echo "Checking database connection...\n";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Database connection successful\n\n";

// Check if conversations table exists
$result = $conn->query("SHOW TABLES LIKE 'conversations'");
if ($result->num_rows === 0) {
    echo "✗ 'conversations' table does not exist. Please run the SQL script to create it.\n";
} else {
    echo "✓ 'conversations' table exists\n";
    
    // Check table structure
    $result = $conn->query("DESCRIBE conversations");
    echo "\nConversations table structure:\n";
    echo str_pad("Field", 20) . str_pad("Type", 25) . str_pad("Null", 10) . "Key\n";
    echo str_repeat("-", 60) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['Field'], 20) . 
             str_pad($row['Type'], 25) . 
             str_pad($row['Null'], 10) . 
             $row['Key'] . "\n";
    }
}

echo "\n";

// Check if messages table exists
$result = $conn->query("SHOW TABLES LIKE 'messages'");
if ($result->num_rows === 0) {
    echo "✗ 'messages' table does not exist. Please run the SQL script to create it.\n";
} else {
    echo "✓ 'messages' table exists\n";
    
    // Check table structure
    $result = $conn->query("DESCRIBE messages");
    echo "\nMessages table structure:\n";
    echo str_pad("Field", 20) . str_pad("Type", 25) . str_pad("Null", 10) . "Key\n";
    echo str_repeat("-", 60) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['Field'], 20) . 
             str_pad($row['Type'], 25) . 
             str_pad($row['Null'], 10) . 
             $row['Key'] . "\n";
    }
}

// Check if there are any conversations
$result = $conn->query("SELECT COUNT(*) as count FROM conversations");
$row = $result->fetch_assoc();
echo "\nTotal conversations: " . $row['count'] . "\n";

// Check if there are any messages
$result = $conn->query("SELECT COUNT(*) as count FROM messages");
$row = $result->fetch_assob();
echo "Total messages: " . $row['count'] . "\n";
?>
