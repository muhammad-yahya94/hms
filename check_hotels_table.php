<?php
require_once 'config/database.php';

header('Content-Type: text/plain');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Checking hotels table structure...\n";

// Check if hotels table exists
$result = $conn->query("SHOW TABLES LIKE 'hotels'");
if ($result->num_rows === 0) {
    die("Error: 'hotels' table does not exist\n");
}

echo "Hotels table exists. Structure:\n";

// Get table structure
$result = $conn->query("SHOW COLUMNS FROM hotels");
echo str_pad("Field", 30) . str_pad("Type", 20) . str_pad("Null", 10) . str_pad("Key", 10) . "Default\n";
echo str_repeat("-", 80) . "\n";

while ($row = $result->fetch_assoc()) {
    echo str_pad($row['Field'], 30) . 
         str_pad($row['Type'], 20) . 
         str_pad($row['Null'], 10) . 
         str_pad($row['Key'], 10) . 
         ($row['Default'] ?? 'NULL') . "\n";
}

// Check if we can find the admin user column
echo "\nLooking for admin/user relationship columns...\n";
$result = $conn->query("SHOW COLUMNS FROM hotels WHERE Field LIKE '%admin%' OR Field LIKE '%user%'");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Found related column: " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "No admin/user related columns found in hotels table.\n";
}

// Check if there's a users table with admin role
echo "\nChecking users table for admin role...\n";
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "Users table exists. Checking for role column...\n";
    $result = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
    if ($result->num_rows > 0) {
        echo "Found 'role' column in users table.\n";
    } else {
        echo "No 'role' column in users table.\n";
    }
    
    // Check for any admin-related columns
    $result = $conn->query("SHOW COLUMNS FROM users WHERE Field LIKE '%admin%'");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Found admin-related column in users: " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "No admin-related columns found in users table.\n";
    }
} else {
    echo "Users table does not exist.\n";
}

echo "\nDone.\n";
?>
