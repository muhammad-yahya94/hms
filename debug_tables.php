<?php
require_once 'config/database.php';

header('Content-Type: text/plain');

echo "=== Checking Database Structure ===\n\n";

// Check hotels table structure
echo "=== Hotels Table ===\n";
$result = $conn->query("SHOW COLUMNS FROM hotels");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Check conversations table structure
echo "\n=== Conversations Table ===\n";
$result = $conn->query("SHOW COLUMNS FROM conversations");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// Check foreign key relationships
echo "\n=== Foreign Key Relationships ===\n";
$result = $conn->query("
    SELECT 
        TABLE_NAME, COLUMN_NAME, 
        REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE 
        TABLE_SCHEMA = DATABASE() AND
        REFERENCED_TABLE_NAME IS NOT NULL AND
        TABLE_NAME IN ('conversations', 'hotels', 'messages')
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['TABLE_NAME']}.{$row['COLUMN_NAME']} references {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
    }
} else {
    echo "No foreign key relationships found or error: " . ($conn->error ?? "No error") . "\n";
}

echo "\n=== Done ===\n";
?>
