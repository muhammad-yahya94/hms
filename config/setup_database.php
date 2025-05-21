<?php
// Database connection parameters
$server = 'localhost';
$username = 'root';
$password = '';

// Create connection without database
$conn = mysqli_connect($server, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS hms_db";
if (mysqli_query($conn, $sql)) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . mysqli_error($conn) . "<br>";
}

// Select the database
mysqli_select_db($conn, "hms_db");

// Drop existing tables in reverse order of dependencies
$tables = ['bookings', 'rooms', 'hotels', 'users'];
foreach ($tables as $table) {
    // Disable foreign key checks temporarily
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    $sql = "DROP TABLE IF EXISTS $table";
    if (mysqli_query($conn, $sql)) {
        echo "Table $table dropped successfully<br>";
    } else {
        echo "Error dropping table $table: " . mysqli_error($conn) . "<br>";
    }
    // Re-enable foreign key checks
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
}

// Get the full path to the SQL file
$sql_file_path = __DIR__ . '/database.sql';

// Check if the SQL file exists
if (!file_exists($sql_file_path)) {
    die("Error: SQL file not found at: " . $sql_file_path);
}

// Read and execute the SQL file
$sql_file = file_get_contents($sql_file_path);
if ($sql_file === false) {
    die("Error: Could not read SQL file");
}

$queries = explode(';', $sql_file);

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        if (mysqli_query($conn, $query)) {
            echo "Query executed successfully<br>";
        } else {
            echo "Error executing query: " . mysqli_error($conn) . "<br>";
            echo "Query was: " . $query . "<br>";
        }
    }
}

echo "Database setup completed!";
mysqli_close($conn);
?> 