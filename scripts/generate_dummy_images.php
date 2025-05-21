<?php
require_once __DIR__ . '/../config/database.php';

// Create directories if they don't exist
$directories = [
    __DIR__ . '/../uploads/hotels',
    __DIR__ . '/../uploads/rooms'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Function to download image
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

// Hotel images
$hotel_images = [
    'https://images.unsplash.com/photo-1566073771259-6a8506099945',
    'https://images.unsplash.com/photo-1582719508461-905c673771fd',
    'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4'
];

// Room images
$room_images = [
    'https://images.unsplash.com/photo-1590490359683-658d3d23f972',
    'https://images.unsplash.com/photo-1618773928121-c32242e63f39',
    'https://images.unsplash.com/photo-1591088398332-8a7791972843',
    'https://images.unsplash.com/photo-1590490359683-658d3d23f972',
    'https://images.unsplash.com/photo-1618773928121-c32242e63f39',
    'https://images.unsplash.com/photo-1591088398332-8a7791972843'
];

// Download and update hotel images
$sql = "SELECT id FROM hotels";
$result = mysqli_query($conn, $sql);
$hotel_count = 0;

while ($hotel = mysqli_fetch_assoc($result)) {
    if (isset($hotel_images[$hotel_count])) {
        $image_url = $hotel_images[$hotel_count];
        $image_path = __DIR__ . '/../uploads/hotels/hotel_' . $hotel['id'] . '.jpg';
        
        if (downloadImage($image_url, $image_path)) {
            $relative_path = 'uploads/hotels/hotel_' . $hotel['id'] . '.jpg';
            $update_sql = "UPDATE hotels SET image = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $relative_path, $hotel['id']);
            mysqli_stmt_execute($stmt);
            echo "Updated image for hotel ID: " . $hotel['id'] . "\n";
        }
        $hotel_count++;
    }
}

// Download and update room images
$sql = "SELECT id FROM rooms";
$result = mysqli_query($conn, $sql);
$room_count = 0;

while ($room = mysqli_fetch_assoc($result)) {
    if (isset($room_images[$room_count % count($room_images)])) {
        $image_url = $room_images[$room_count % count($room_images)];
        $image_path = __DIR__ . '/../uploads/rooms/room_' . $room['id'] . '.jpg';
        
        if (downloadImage($image_url, $image_path)) {
            $relative_path = 'uploads/rooms/room_' . $room['id'] . '.jpg';
            $update_sql = "UPDATE rooms SET image = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $relative_path, $room['id']);
            mysqli_stmt_execute($stmt);
            echo "Updated image for room ID: " . $room['id'] . "\n";
        }
        $room_count++;
    }
}

echo "Dummy images have been downloaded and applied successfully!\n";
?> 