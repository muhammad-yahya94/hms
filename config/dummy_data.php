<?php
require_once 'database.php';

// Function to generate random dates
function getRandomDate($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    $random_timestamp = rand($start, $end);
    return date('Y-m-d', $random_timestamp);
}

// Function to generate random price
function getRandomPrice($min, $max) {
    return number_format(rand($min * 100, $max * 100) / 100, 2);
}

// Clear existing data - Order matters due to foreign key constraints
$tables = ['bookings', 'rooms', 'hotels', 'users'];
foreach ($tables as $table) {
    // Disable foreign key checks temporarily
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_query($conn, "TRUNCATE TABLE $table");
    // Re-enable foreign key checks
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
}

// Insert dummy users
$users = [
    [
        'username' => 'admin',
        'email' => 'admin@jhanghotels.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin'
    ],
    [
        'username' => 'masoma',
        'email' => 'masoma@example.com',
        'password' => password_hash('user123', PASSWORD_DEFAULT),
        'role' => 'user'
    ],
    [
        'username' => 'sania',
        'email' => 'sania@example.com',
        'password' => password_hash('user123', PASSWORD_DEFAULT),
        'role' => 'user'
    ]
];

foreach ($users as $user) {
    $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $user['username'], $user['email'], $user['password'], $user['role']);
    mysqli_stmt_execute($stmt);
}

// Insert dummy hotels
$hotels = [
    [
        'name' => 'Jhang Grand Hotel',
        'description' => 'Luxury hotel in the heart of Jhang with stunning views and premium amenities.',
        'address' => '123 Main Street',
        'city' => 'Jhang',
        'phone' => '+92 123 4567890',
        'email' => 'info@jhanggrand.com',
        'website' => 'www.jhanggrand.com',
        'image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945'
    ],
    [
        'name' => 'Jhang Plaza Hotel',
        'description' => 'Modern hotel offering comfortable stays with excellent service.',
        'address' => '456 Park Avenue',
        'city' => 'Jhang',
        'phone' => '+92 123 4567891',
        'email' => 'info@jhangplaza.com',
        'website' => 'www.jhangplaza.com',
        'image_url' => 'https://images.unsplash.com/photo-1578683014728-c7359938e4b1'
    ],
    [
        'name' => 'Jhang City Hotel',
        'description' => 'Boutique hotel with unique charm and personalized service.',
        'address' => '789 Market Street',
        'city' => 'Jhang',
        'phone' => '+92 123 4567892',
        'email' => 'info@jhangcity.com',
        'website' => 'www.jhangcity.com',
        'image_url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b'
    ]
];

foreach ($hotels as $hotel) {
    $sql = "INSERT INTO hotels (name, description, address, city, phone, email, website, image_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssss", 
        $hotel['name'], $hotel['description'], $hotel['address'], 
        $hotel['city'], $hotel['phone'], $hotel['email'], 
        $hotel['website'], $hotel['image_url']
    );
    mysqli_stmt_execute($stmt);
}

// Insert dummy rooms
$room_types = ['standard', 'deluxe', 'suite', 'presidential_suite'];
$amenities = [
    'Free Wi-Fi, TV, Air Conditioning',
    'Free Wi-Fi, TV, Air Conditioning, Mini Bar',
    'Free Wi-Fi, TV, Air Conditioning, Mini Bar, Work Desk, Living Area',
    'Free Wi-Fi, TV, Air Conditioning, Mini Bar, Work Desk, Living Area, Butler Service, Private Balcony'
];

// Get all hotel IDs
$hotel_ids = [];
$result = mysqli_query($conn, "SELECT id FROM hotels");
while ($row = mysqli_fetch_assoc($result)) {
    $hotel_ids[] = $row['id'];
}

foreach ($hotel_ids as $hotel_id) {
    foreach ($room_types as $index => $room_type) {
        $price = getRandomPrice(50, 500);
        $capacity = rand(1, 4);
        $description = "Comfortable " . ucfirst(str_replace('_', ' ', $room_type)) . " room with {$amenities[$index]}";
        $image_url = "https://images.unsplash.com/photo-" . rand(1000000000000, 9999999999999);
        
        $sql = "INSERT INTO rooms (hotel_id, room_type, description, price_per_night, capacity, image_url, amenities, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'available')";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issdiss", 
            $hotel_id, $room_type, $description, $price, $capacity, $image_url, $amenities[$index]
        );
        mysqli_stmt_execute($stmt);
    }
}

// Insert dummy bookings
$booking_statuses = ['pending', 'confirmed', 'cancelled'];
$user_ids = [];
$result = mysqli_query($conn, "SELECT id FROM users WHERE role = 'user'");
while ($row = mysqli_fetch_assoc($result)) {
    $user_ids[] = $row['id'];
}

$room_ids = [];
$result = mysqli_query($conn, "SELECT id, hotel_id FROM rooms");
while ($row = mysqli_fetch_assoc($result)) {
    $room_ids[] = $row;
}

// Create 20 random bookings
for ($i = 0; $i < 20; $i++) {
    $user_id = $user_ids[array_rand($user_ids)];
    $room = $room_ids[array_rand($room_ids)];
    $room_id = $room['id'];
    $hotel_id = $room['hotel_id'];
    
    $check_in = getRandomDate('2024-01-01', '2024-12-31');
    $check_in_time = date('H:i:s', rand(0, 23 * 3600)); // Random time between 00:00:00 and 23:59:59
    $check_in = $check_in . ' ' . $check_in_time;
    
    $check_out = date('Y-m-d H:i:s', strtotime($check_in . ' + ' . rand(1, 7) . ' days'));
    
    $adults = rand(1, 2);
    $children = rand(0, 2);
    
    // Calculate total price
    $sql = "SELECT price_per_night FROM rooms WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $price_per_night = mysqli_fetch_assoc($result)['price_per_night'];
    
    $nights = (strtotime($check_out) - strtotime($check_in)) / (60 * 60 * 24);
    $total_price = $price_per_night * $nights;
    
    $status = $booking_statuses[array_rand($booking_statuses)];
    
    $sql = "INSERT INTO bookings (user_id, hotel_id, room_id, check_in_date, check_out_date, 
            adults, children, total_price, booking_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiissiids", 
        $user_id, $hotel_id, $room_id, $check_in, $check_out, 
        $adults, $children, $total_price, $status
    );
    mysqli_stmt_execute($stmt);
}

// --- Download and apply local images for hotels and rooms ---
function downloadImage($url, $path) {
    $ch = curl_init($url);
    $fp = fopen($path, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return file_exists($path);
}

// Create directories if they don't exist
$hotel_dir = __DIR__ . '/../uploads/hotels';
$room_dir = __DIR__ . '/../uploads/rooms';
if (!file_exists($hotel_dir)) mkdir($hotel_dir, 0777, true);
if (!file_exists($room_dir)) mkdir($room_dir, 0777, true);

// Hotel images (Unsplash)
$hotel_images = [
    'https://images.unsplash.com/photo-1566073771259-6a8506099945',
    'https://images.unsplash.com/photo-1578683014728-c7359938e4b1',
    'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b'
];

// Room images (Unsplash)
$room_images = [
    'https://images.unsplash.com/photo-1590490359683-658d3d23f972',
    'https://images.unsplash.com/photo-1618773928121-c32242e63f39',
    'https://images.unsplash.com/photo-1591088398332-8a7791972843',
    'https://images.unsplash.com/photo-1590490359683-658d3d23f972',
    'https://images.unsplash.com/photo-1618773928121-c32242e63f39',
    'https://images.unsplash.com/photo-1591088398332-8a7791972843'
];

// --- Update hotel images to local files ---
$hotel_ids = [];
$result = mysqli_query($conn, "SELECT id FROM hotels");
while ($row = mysqli_fetch_assoc($result)) {
    $hotel_ids[] = $row['id'];
}
foreach ($hotel_ids as $i => $hotel_id) {
    $img_url = $hotel_images[$i % count($hotel_images)];
    $img_path = $hotel_dir . "/hotel_{$hotel_id}.jpg";
    if (downloadImage($img_url, $img_path)) {
        $relative_path = 'uploads/hotels/hotel_' . $hotel_id . '.jpg';
        mysqli_query($conn, "UPDATE hotels SET image_url = '$relative_path' WHERE id = $hotel_id");
    }
}

// --- Update room images to local files ---
$room_ids = [];
$result = mysqli_query($conn, "SELECT id FROM rooms");
while ($row = mysqli_fetch_assoc($result)) {
    $room_ids[] = $row['id'];
}
foreach ($room_ids as $i => $room_id) {
    $img_url = $room_images[$i % count($room_images)];
    $img_path = $room_dir . "/room_{$room_id}.jpg";
    if (downloadImage($img_url, $img_path)) {
        $relative_path = 'uploads/rooms/room_' . $room_id . '.jpg';
        mysqli_query($conn, "UPDATE rooms SET image_url = '$relative_path' WHERE id = $room_id");
    }
}

echo "Dummy data has been successfully inserted into the database!";
?> 