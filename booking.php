<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Require login for booking
requireLogin();

$error = '';
$success = '';

// Get room details
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';
$adults = isset($_GET['adults']) ? (int)$_GET['adults'] : 0;
$children = isset($_GET['children']) ? (int)$_GET['children'] : 0;

if (!$room_id || !$check_in || !$check_out || !$adults) {
    header("Location: room-list.php");
    exit();
}

// Get room details
$sql = "SELECT * FROM rooms WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$room = mysqli_fetch_assoc($result);

if (!$room) {
    header("Location: room-list.php");
    exit();
}

// Calculate total nights and price
$check_in_date = new DateTime($check_in);
$check_out_date = new DateTime($check_out);
$nights = $check_in_date->diff($check_out_date)->days;
$total_price = $room['price_per_night'] * $nights;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if room is still available
    $sql = "SELECT id FROM bookings WHERE room_id = ? AND 
            ((check_in_date <= ? AND check_out_date >= ?) OR 
             (check_in_date <= ? AND check_out_date >= ?) OR 
             (check_in_date >= ? AND check_out_date <= ?))";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issssss", $room_id, $check_out, $check_in, $check_in, $check_in, $check_in, $check_out);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $error = "Sorry, this room is no longer available for the selected dates.";
    } else {
        // Create booking
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO bookings (user_id, room_id, hotel_id, check_in_date, check_out_date, adults, children, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiissiid", $user_id, $room_id, $room['hotel_id'], $check_in, $check_out, $adults, $children, $total_price);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Booking successful! Your room has been reserved.";
        } else {
            $error = "Something went wrong. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - Jhang Hotels</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .booking-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .room-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .booking-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .price-details {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-book {
            background-color: #d4a017;
            color: white;
            padding: 12px 30px;
            border: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-book:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="booking-container">
            <h2 class="text-center mb-4">Book Your Room</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <img src="<?php echo htmlspecialchars($room['image_url']); ?>" class="room-image" alt="<?php echo htmlspecialchars($room['room_type']); ?>" onerror="this.src='https://images.unsplash.com/photo-1618773928121-c32242e63f39';">
                    <div class="booking-details">
                        <h4><?php echo htmlspecialchars($room['room_type']); ?></h4>
                        <p><?php echo htmlspecialchars($room['description']); ?></p>
                        <?php if (!empty($room['size_sqft'])): ?>
                            <p><strong>Room Size:</strong> <?php echo htmlspecialchars($room['size_sqft']); ?> sq ft</p>
                        <?php endif; ?>
                        <?php if (!empty($room['bed_type'])): ?>
                            <p><strong>Bed Type:</strong> <?php echo htmlspecialchars($room['bed_type']); ?></p>
                        <?php endif; ?>
                        <p><strong>Capacity:</strong> <?php echo htmlspecialchars($room['capacity']); ?> Guests</p>
                        <div class="room-icons">
                            <?php
                            $amenities = explode(',', $room['amenities']);
                            foreach($amenities as $amenity) {
                                $icon = '';
                                switch(trim($amenity)) {
                                    case 'WiFi':
                                        $icon = 'fa-wifi';
                                        break;
                                    case 'Smart TV':
                                        $icon = 'fa-tv';
                                        break;
                                    case 'Minibar':
                                        $icon = 'fa-wine-glass';
                                        break;
                                    case 'Living Area':
                                        $icon = 'fa-couch';
                                        break;
                                    case 'Work Desk':
                                        $icon = 'fa-briefcase';
                                        break;
                                    case 'Butler Service':
                                        $icon = 'fa-concierge-bell';
                                        break;
                                    case 'Private Bathroom':
                                        $icon = 'fa-bath';
                                        break;
                                }
                                if($icon) {
                                    echo "<i class='fas $icon' title='" . htmlspecialchars(trim($amenity)) . "'></i> ";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="booking-details">
                        <h4>Booking Details</h4>
                        <p><strong>Check-in:</strong> <?php echo date('F j, Y', strtotime($check_in)); ?></p>
                        <p><strong>Check-out:</strong> <?php echo date('F j, Y', strtotime($check_out)); ?></p>
                        <p><strong>Number of Nights:</strong> <?php echo $nights; ?></p>
                        <p><strong>Guests:</strong> <?php echo $adults; ?> Adult<?php echo $adults > 1 ? 's' : ''; ?><?php echo $children > 0 ? ', ' . $children . ' Child' . ($children > 1 ? 'ren' : '') : ''; ?></p>
                    </div>
                    
                    <div class="price-details">
                        <h4>Price Details</h4>
                        <p><strong>Price per Night:</strong> PKR <?php echo number_format($room['price_per_night'], 2); ?></p>
                        <p><strong>Number of Nights:</strong> <?php echo $nights; ?></p>
                        <hr>
                        <h5><strong>Total Price:</strong> PKR <?php echo number_format($total_price, 2); ?></h5>
                    </div>
                    
                    <?php if (!$success): ?>
                        <form method="POST" action="">
                            <div class="text-center">
                                <button type="submit" class="btn btn-book">Confirm Booking</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <a href="room-list.php" class="btn btn-book">Back to Rooms</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 