<?php
require_once 'config/database.php';

header('Content-Type: text/plain');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Database connection successful\n\n";

// List all tables
$tables = ['conversations', 'messages'];

foreach ($tables as $table) {
    echo "Checking table: $table\n";
    echo str_repeat("=", 30) . "\n";
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        echo "✗ Table '$table' does not exist\n\n";
        continue;
    }
    
    echo "✓ Table '$table' exists\n";
    
    // Get table structure
    $result = $conn->query("DESCRIBE $table");
    echo "\nTable structure:\n";
    echo str_pad("Field", 20) . str_pad("Type", 20) . str_pad("Null", 8) . str_pad("Key", 8) . "Default\n";
    echo str_repeat("-", 60) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['Field'], 20) . 
             str_pad($row['Type'], 20) . 
             str_pad($row['Null'], 8) . 
             str_pad($row['Key'], 8) . 
             ($row['Default'] ?? 'NULL') . "\n";
    }
    
    // Get row count
    $count = $conn->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
    echo "\nRow count: $count\n";
    
    // Show sample data for non-empty tables
    if ($count > 0) {
        echo "\nSample data (first 5 rows):\n";
        $sample = $conn->query("SELECT * FROM $table ORDER BY id DESC LIMIT 5");
        while ($row = $sample->fetch_assoc()) {
            print_r($row);
            echo "\n";
        }
    }
    
    echo "\n\n";
}

// Check for recent errors in the error log
$error_log = ini_get('error_log');
if (file_exists($error_log)) {
    echo "Recent errors from error log:\n";
    echo file_get_contents($error_log);
} else {
    echo "Error log not found at: $error_log\n";
}
?>
