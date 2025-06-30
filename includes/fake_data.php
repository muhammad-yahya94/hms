<?php
require_once '../config/database.php';

// Set timezone to Pakistan Standard Time (PKT)   
date_default_timezone_set('Asia/Karachi');

// Get current date and time dynamically (e.g., 2025-05-27 09:37:00 at 09:37 AM PKT)
$current_date = date('Y-m-d H:i:s');

// Remove execution time limit to allow all image downloads to complete
set_time_limit(0);

// Function to execute SQL queries with error handling
function executeQuery($conn, $sql, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo "Prepare failed: " . mysqli_error($conn) . "\nSQL: $sql\n";
        return false;
    }
    if ($params && $types) {
        if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
            echo "Binding parameters failed: " . mysqli_stmt_error($stmt) . "\nSQL: $sql\n";
            mysqli_stmt_close($stmt);
            return false;
        }
    }
    if (mysqli_stmt_execute($stmt)) {
        echo "Query executed successfully: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Error executing query: " . mysqli_stmt_error($stmt) . "\nSQL: $sql\n";
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

// Function to download and save an image with a fallback
function downloadImage($url, $savePath, $relativePath) {
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        echo "Failed to download image from $url\n";
        return $relativePath; // Return the path, front-end can handle missing images
    }
    if (file_put_contents($savePath, $imageData) === false) {
        echo "Failed to save image to $savePath\n";
        return $relativePath;
    }
    echo "Image downloaded and saved to $savePath\n";
    return $relativePath;
}

// Ensure images directory exists and is writable
$imagesDir = __DIR__ . '/images';
$imagesRelativeDir = 'includes/images';
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0755, true);
    echo "Created images directory: $imagesDir\n";
} elseif (!is_writable($imagesDir)) {
    echo "Images directory is not writable: $imagesDir\n";
    exit;
}

// Delete all existing data with foreign key checks disabled
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
$tables = ['bookings', 'rooms', 'hotels', 'users'];
foreach ($tables as $table) {
    $sql = "DELETE FROM `$table`";
    if (mysqli_query($conn, $sql)) {
        echo "Deleted all data from table `$table`.\n";
    } else {
        echo "Error deleting data from table `$table`: " . mysqli_error($conn) . "\n";
    }
}
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

// Insert fake users (2 admins, 3 regular users)
$users = [
    ['username' => 'admin1', 'email' => 'admin1@jhanghotels.com', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin', 'first_name' => 'Ali', 'last_name' => 'Hassan', 'phone' => '03001234567', 'address' => 'Jhang, Punjab, Pakistan'],
    ['username' => 'admin2', 'email' => 'admin2@jhanghotels.com', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin', 'first_name' => 'Zainab', 'last_name' => 'Khan', 'phone' => '03001234568', 'address' => 'Jhang, Punjab, Pakistan'],
    ['username' => 'user1', 'email' => 'user1@jhanghotels.com', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'role' => 'user', 'first_name' => 'Ahmed', 'last_name' => 'Khan', 'phone' => '03111234567', 'address' => '123 Main St, Jhang'],
    ['username' => 'user2', 'email' => 'user2@jhanghotels.com', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'role' => 'user', 'first_name' => 'Sara', 'last_name' => 'Malik', 'phone' => '03211234567', 'address' => '456 Garden Rd, Jhang'],
    ['username' => 'user3', 'email' => 'user3@jhanghotels.com', 'password' => password_hash('user123', PASSWORD_DEFAULT), 'role' => 'user', 'first_name' => 'Usman', 'last_name' => 'Riaz', 'phone' => '03311234567', 'address' => '789 Park Ave, Jhang']
];

echo "Starting user insertion...\n";
foreach ($users as $index => $user) {
    $sql = "INSERT INTO users (id, username, email, password, role, first_name, last_name, phone, address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [
        $index + 1,
        $user['username'],
        $user['email'],
        $user['password'],
        $user['role'],
        $user['first_name'],
        $user['last_name'],
        $user['phone'],
        $user['address'],
        $current_date
    ];
    $types = "isssssssss";
    if (!executeQuery($conn, $sql, $params, $types)) {
        echo "Failed to insert user at index $index. Stopping.\n";
        exit;
    }
}
echo "User insertion completed.\n";

// Define hotels (2 hotels, one per admin)
$admin_hotels = [
    1 => 1, // Admin 1 (id=1): 1 hotel
    2 => 1  // Admin 2 (id=2): 1 hotel
];

// List of free image URLs from Pexels
$hotel_image_urls = [
    'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg',
    'https://images.pexels.com/photos/258154/pexels-photo-258154.jpeg'
];

// Insert fake hotels (2 hotels)
$hotel_id_counter = 1;
$hotels = [];
echo "Starting hotel insertion...\n";
foreach ($admin_hotels as $admin_id => $num_hotels) {
    $admin_name = $users[$admin_id - 1]['first_name'];
    for ($i = 1; $i <= $num_hotels; $i++) {
        $hotel_name = "$admin_name Hotel No. $i";
        $image_url = $hotel_image_urls[($hotel_id_counter - 1) % count($hotel_image_urls)];
        $filename = strtolower(str_replace(' ', '_', $hotel_name)) . '.jpg';
        $save_path = $imagesDir . '/' . $filename;
        $relative_path = "$imagesRelativeDir/$filename";
        $downloaded_path = downloadImage($image_url, $save_path, $relative_path);
        $hotels[] = [
            'id' => $hotel_id_counter,
            'name' => $hotel_name,
            'description' => "A luxurious hotel in Jhang owned by $admin_name.",
            'address' => "$hotel_id_counter" . "0" . ($i * 10) . " St, Jhang",
            'city' => 'Jhang',
            'phone' => '0300' . sprintf("%06d", $hotel_id_counter * 100 + $i),
            'email' => strtolower(str_replace(' ', '', $hotel_name)) . '@jhanghotels.com',
            'website' => 'www.' . strtolower(str_replace(' ', '', $hotel_name)) . '.com',
            'image_url' => $downloaded_path,
            'vendor_id' => $admin_id
        ];
        $hotel_id_counter++;
    }
}

foreach ($hotels as $hotel) {
    $sql = "INSERT INTO hotels (id, name, description, address, city, phone, email, website, image_url, vendor_id, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [
        $hotel['id'],
        $hotel['name'],
        $hotel['description'],
        $hotel['address'],
        $hotel['city'],
        $hotel['phone'],
        $hotel['email'],
        $hotel['website'],
        $hotel['image_url'],
        $hotel['vendor_id'],
        $current_date,
        $current_date
    ];
    $types = "issssssssiis";
    if (!executeQuery($conn, $sql, $params, $types)) {
        echo "Failed to insert hotel ID {$hotel['id']}. Stopping.\n";
        exit;
    }
}
echo "Hotel insertion completed.\n";

// Insert fake rooms (2 rooms per hotel, total 4 rooms)
$room_types = ['standard', 'deluxe'];
$amenities_list = [
    'standard' => 'WiFi, TV, AC',
    'deluxe' => 'WiFi, TV, AC, Minibar'
];
$price_per_hour_list = [
    'standard' => 4800.00,
    'deluxe' => 7200.00
];
$capacity_list = [
    'standard' => 2,
    'deluxe' => 3
];
$status_list = ['available', 'maintenance'];
$room_image_urls = [
    'https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg',
    'https://images.pexels.com/photos/164595/pexels-photo-164595.jpeg'
];

$room_id_counter = 1;
$rooms = [];
echo "Starting room insertion...\n";
foreach ($hotels as $hotel) {
    $num_rooms = 2; // 2 rooms per hotel
    $hotel_name_prefix = str_replace(' ', ' ', $hotel['name']);
    for ($i = 1; $i <= $num_rooms; $i++) {
        $room_type = $room_types[($i - 1) % 2];
        $room_number = 100 + $i;
        $image_url = $room_image_urls[($room_id_counter - 1) % count($room_image_urls)];
        $filename = 'room_' . strtolower($room_type) . '_' . $hotel['id'] . '_' . $i . '.jpg';
        $save_path = $imagesDir . '/' . $filename;
        $relative_path = "$imagesRelativeDir/$filename";
        $downloaded_path = downloadImage($image_url, $save_path, $relative_path);
        $rooms[] = [
            'id' => $room_id_counter,
            'hotel_id' => $hotel['id'],
            'room_type' => $room_type,
            'description' => "$room_type room in {$hotel['name']}.",
            'price_per_hour' => $price_per_hour_list[$room_type],
            'capacity' => $capacity_list[$room_type],
            'image_url' => $downloaded_path,
            'amenities' => $amenities_list[$room_type],
            'status' => $status_list[array_rand($status_list)],
            'created_at' => $current_date,
            'updated_at' => $current_date // Added to match the INSERT query
        ];
        $room_id_counter++;
    }
}

foreach ($rooms as $room) {
    $sql = "INSERT INTO rooms (id, hotel_id, room_type, description, price_per_hour, capacity, image_url, amenities, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [
        $room['id'],
        $room['hotel_id'],
        $room['room_type'],
        $room['description'],
        $room['price_per_hour'],
        $room['capacity'],
        $room['image_url'],
        $room['amenities'],
        $room['status'],
        $room['created_at'],
        $room['updated_at']
    ];
    $types = "iissdississ"; // Updated to match: i (id), i (hotel_id), s (room_type), s (description), d (price_per_hour), i (capacity), s (image_url), s (amenities), s (status), s (created_at), s (updated_at)
    if (!executeQuery($conn, $sql, $params, $types)) {
        echo "Failed to insert room ID {$room['id']}. Stopping.\n";
        exit;
    }
}
echo "Room insertion completed.\n";

// Insert fake bookings (2 bookings, one per hotel)
$booking_statuses = ['pending', 'confirmed'];
$hourly_rate = 200;
$booking_id_counter = 1;
$bookings = [];
echo "Starting booking insertion...\n";
foreach ($hotels as $index => $hotel) {
    $num_bookings = 1; // 1 booking per hotel
    $hotel_rooms = array_filter($rooms, fn($room) => $room['hotel_id'] == $hotel['id']);
    $hotel_room_ids = array_column($hotel_rooms, 'id');
    
    for ($i = 0; $i < $num_bookings; $i++) {
        $room_id = $hotel_room_ids[array_rand($hotel_room_ids)];
        $status = $booking_statuses[$index % 2]; // First booking pending, second confirmed
        $user_id = rand(3, 5); // Select a regular user (id 3, 4, or 5)
        
        $days_offset = rand(-2, 2);
        $check_in = new DateTime($current_date);
        $check_in->modify("$days_offset days");
        $check_in->setTime(rand(8, 14), rand(0, 59));
        
        $check_out = clone $check_in;
        $hours = rand(4, 48);
        $check_out->modify("+$hours hours");
        
        $interval = $check_in->diff($check_out);
        $total_hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
        $total_hours = max(1, ceil($total_hours));
        $total_price = round($hourly_rate * $total_hours, 2);
        
        $bookings[] = [
            'id' => $booking_id_counter,
            'user_id' => $user_id,
            'hotel_id' => $hotel['id'],
            'room_id' => $room_id,
            'check_in_date' => $check_in->format('Y-m-d H:i:s'),
            'check_out_date' => $check_out->format('Y-m-d H:i:s'),
            'adults' => rand(1, 3),
            'children' => rand(1, 2), // Ensure non-zero
            'total_price' => $total_price,
            'booking_status' => $status,
            'status' => $status
        ];
        $booking_id_counter++;
    }
}

foreach ($bookings as $booking) {
    $sql = "INSERT INTO bookings (id, user_id, hotel_id, room_id, check_in_date, check_out_date, adults, children, total_price, booking_status, status, created_at, check_in, check_out) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $params = [
        $booking['id'],
        $booking['user_id'],
        $booking['hotel_id'],
        $booking['room_id'],
        $booking['check_in_date'],
        $booking['check_out_date'],
        $booking['adults'],
        $booking['children'],
        $booking['total_price'],
        $booking['booking_status'],
        $booking['status'],
        $current_date,
        $booking['booking_status'] === 'confirmed' ? $booking['check_in_date'] : null,
        $booking['booking_status'] === 'confirmed' ? $booking['check_out_date'] : null
    ];
    $types = "iiiissiidsssss";
    if (!executeQuery($conn, $sql, $params, $types)) {
        echo "Failed to insert booking ID {$booking['id']}. Stopping.\n";
        exit;
    }
}
echo "Booking insertion completed.\n";

// Close the database connection
mysqli_close($conn);
echo "Fake data insertion completed.\n";
?>