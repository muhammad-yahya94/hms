<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Get filter parameters
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : date('Y-m-d H:i');
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : date('Y-m-d H:i', strtotime('+1 day'));
$adults = isset($_GET['adults']) ? (int)$_GET['adults'] : 2;
$children = isset($_GET['children']) ? (int)$_GET['children'] : 0;
$room_type = isset($_GET['room_type']) ? $_GET['room_type'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : 0;
$hotel_id = isset($_GET['hotel']) ? (int)$_GET['hotel'] : 0;

// Build the SQL query
$sql = "SELECT r.*, h.name as hotel_name, h.city, h.address, h.image_url as hotel_image, h.average_rating,
        CASE 
            WHEN r.status = 'available' AND EXISTS (
                SELECT 1 FROM bookings b 
                WHERE b.room_id = r.id   
                AND b.booking_status IN ('pending', 'confirmed')
                AND (
                    (b.check_in_date <= ? AND b.check_out_date >= ?) OR
                    (b.check_in_date <= ? AND b.check_out_date >= ?) OR
                    (b.check_in_date >= ? AND b.check_out_date <= ?)
                )
            ) THEN 'unavailable'
            ELSE r.status
        END as display_status,   
        (SELECT b.check_in_date 
         FROM bookings b 
         WHERE b.room_id = r.id 
         AND b.booking_status IN ('pending', 'confirmed')
         AND (
             (b.check_in_date <= ? AND b.check_out_date >= ?) OR
             (b.check_in_date <= ? AND b.check_out_date >= ?) OR
             (b.check_in_date >= ? AND b.check_out_date <= ?)
         )
         LIMIT 1) as booking_check_in,
        (SELECT b.check_out_date 
         FROM bookings b 
         WHERE b.room_id = r.id 
         AND b.booking_status IN ('pending', 'confirmed')
         AND (
             (b.check_in_date <= ? AND b.check_out_date >= ?) OR
             (b.check_in_date <= ? AND b.check_out_date >= ?) OR
             (b.check_in_date >= ? AND b.check_out_date <= ?)
         )
         LIMIT 1) as booking_check_out
        FROM rooms r 
        LEFT JOIN hotels h ON r.hotel_id = h.id";
$params = array();
$types = "";

// Add booking check parameters for display_status and booking dates
if (!empty($check_in) && !empty($check_out)) {
    $params = array_merge($params, [
        $check_out, $check_in, $check_in, $check_in, $check_in, $check_out, // for display_status
        $check_out, $check_in, $check_in, $check_in, $check_in, $check_out, // for booking_check_in
        $check_out, $check_in, $check_in, $check_in, $check_in, $check_out  // for booking_check_out
    ]);
    $types .= "ssssssssssssssssss";
}

$where_conditions = array();

// Apply user filters
if ($hotel_id > 0) {
    $where_conditions[] = "r.hotel_id = ?";
    $params[] = $hotel_id;
    $types .= "i";
}

if (!empty($room_type)) {
    $where_conditions[] = "r.room_type = ?";
    $params[] = $room_type;
    $types .= "s";
}

if (!empty($city)) {
    $where_conditions[] = "h.city = ?";
    $params[] = $city;
    $types .= "s";
}

if ($max_price > 0) {
    $where_conditions[] = "r.price_per_hour <= ?";
    $params[] = $max_price;
    $types .= "d";
}

$where_conditions[] = "r.capacity >= ?";
$params[] = $adults + $children;
$types .= "i";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check if any rooms were found
$rooms_found = mysqli_num_rows($result) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jhang Hotels - Room Listing</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), 
                        url('https://images.unsplash.com/photo-1611892440504-42a792e24d32') no-repeat center center/cover;
            color: white;
            padding: 120px 0 100px 0;
            min-height: 600px;
            display: flex;
            align-items: center;
            position: relative;
        }
        .hero-content {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 40px;
            border-radius: 10px;
        }
        .search-form h3 {
            color: #d4a017;
            margin-bottom: 20px;
        }
        .form-control, .form-select {
            border: 1px solid #d4a017;
            padding: 10px;
        }
        .btn-booking {
            background-color: #d4a017;
            color: white;
            font-weight: bold;
            padding: 10px;
            width: 100%;
            border: none;
            transition: all 0.3s;
        }
        .btn-booking:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        .hero-section .container {
            position: relative;
            z-index: 1;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: bold;
        }
        .navbar {
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            padding: 20px 0;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
        }
        .navbar-transparent {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            box-shadow: none;
        }
        .navbar-dark-bg {
            background: linear-gradient(90deg, #1a1a1a, #2a2a2a);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            position: fixed;
            top: 0;
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.8rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        .navbar-brand:hover {
            transform: scale(1.05);
            color: #d4a017 !important;
        }
        .nav-link {
            color: white !important;
            font-weight: 500;
            position: relative;
            margin: 0 15px;
            transition: color 0.3s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: #d4a017;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .nav-link:hover {
            color: #d4a017 !important;
        }
        .navbar-toggler {
            border: none;
            padding: 10px;
        }
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            transition: transform 0.3s ease;
        }
        .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon {
            transform: rotate(90deg);
        }
        .section-padding {
            padding: 60px 0;
        }
        .card-img-left {
            width: 250px;
            height: 223px;
            object-fit: cover;
            max-width: 100%;
            border-radius: 10px 0 0 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .room-card {
            display: flex;
            flex-direction: row;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            min-height: 200px;
            position: relative;
            transition: all 0.3s ease;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .room-card:hover .card-img-left {
            transform: scale(1.05);
        }
        .room-details {
            flex-grow: 1;
            padding: 20px;
            position: relative;
        }
        .room-details h5 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .room-details .description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        .room-details .specs {
            font-size: 0.95rem;
            color: #555;
            margin-bottom: 10px;
        }
        .room-icons {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }
        .room-icons i {
            color: #d4a017;
            font-size: 1.3rem;
        }
        .price-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #d4a017;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
        }
        .status-badge {
            position: absolute;
            top: 150px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            color: white;
        }
        .status-available {
            background-color: #28a745;
        }
        .status-booked {
            background-color: #dc3545;
        }
        .status-maintenance {
            background-color: #fd7e14;
        }
        .booking-dates {
            font-size: 0.85rem;
            color: #555;
            margin-top: 5px;
            font-family: 'Poppins', sans-serif;
        }
        .footer {
            background-color: #1a1a1a;
            color: white;
            padding: 40px 0;
        }
        .btn-custom {
            background-color: #d4a017;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .btn-custom:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .room-card {
                flex-direction: column;
            }
            .card-img-left {
                width: 100%;
                height: 200px;
                border-radius: 10px 10px 0 0;
            }
            .room-details {
                padding: 15px;
            }
            .price-badge {
                top: 10px;
                right: 10px;
            }
            .status-badge {
                top: 50px;
                right: 10px;
            }
            .booking-dates {
                margin-top: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section with Integrated Navbar -->
    <section id="home" class="hero-section">
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark navbar-transparent">
            <div class="container">
                <a class="navbar-brand" href="index.php">Jhang Hotels</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="room-list.php">Rooms</a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link" href="hotels.php">Hotels</a>
                        </li>    
                        <li class="nav-item">
                            <a class="nav-link" href="about.php">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="gallery.php">Gallery</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact.php">Contact Us</a>
                        </li>
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <li><a class="dropdown-item" href="admin/dashboard.php">Admin Dashboard</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="user/dashboard.php">My Dashboard</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Hero Content -->
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div>
                        <h1 class="display-4 fw-bold mb-4">Find Your Perfect Room</h1>
                        <p class="lead fs-4 mb-4">Discover our luxurious accommodations designed for your comfort.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h3 class="text-center">Search Availability</h3>
                        <form action="room-list.php" method="GET" class="search-form">
                            <div class="mb-3">
                                <label for="city" class="form-label">City Name</label>
                                <select class="form-select" id="city" name="city">
                                    <option value="">All Cities</option>
                                    <?php
                                    $cities_sql = "SELECT DISTINCT city FROM hotels ORDER BY city";
                                    $cities_result = mysqli_query($conn, $cities_sql);
                                    while ($city_row = mysqli_fetch_assoc($cities_result)) {
                                        $selected = ($city == $city_row['city']) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($city_row['city']) . '"' . $selected . '>' . htmlspecialchars($city_row['city']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group mt-2">
                                <label for="check_in">Check-in Date & Time</label>
                                <input type="datetime-local" class="form-control" id="check_in" name="check_in" 
                                       value="<?php echo htmlspecialchars($check_in); ?>" required>
                            </div>
                            <div class="form-group mt-2">
                                <label for="check_out">Check-out Date & Time</label>
                                <input type="datetime-local" class="form-control" id="check_out" name="check_out" 
                                       value="<?php echo htmlspecialchars($check_out); ?>" required>
                            </div>
                            <div class="row mb-3 mt-2">
                                <div class="col-md-4">
                                    <label for="room_type" class="form-label">Room Type</label>
                                    <select class="form-select" id="room_type" name="room_type">
                                        <option value="">All Room Types</option>
                                        <?php
                                        $room_types_sql = "SELECT DISTINCT room_type FROM rooms ORDER BY room_type";
                                        $room_types_result = mysqli_query($conn, $room_types_sql);
                                        while($type_row = mysqli_fetch_assoc($room_types_result)) {
                                            $selected = ($room_type == $type_row['room_type']) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($type_row['room_type']) . "' $selected>" . htmlspecialchars(ucfirst($type_row['room_type'])) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="adults" class="form-label">Adults</label>
                                    <input type="number" class="form-control" id="adults" name="adults" 
                                           min="1" max="4" value="<?php echo htmlspecialchars($adults); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="children" class="form-label">Children</label>
                                    <input type="number" class="form-control" id="children" name="children" 
                                           min="0" max="3" value="<?php echo htmlspecialchars($children); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="max_price" class="form-label">Room Price</label>
                                <input type="number" class="form-control" id="max_price" name="max_price" 
                                       min="0" placeholder="Enter price" value="<?php echo htmlspecialchars($max_price > 0 ? $max_price : ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-booking">
                                CHECK AVAILABILITY <i class="fas fa-search ms-2"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Room Listing Section -->
    <section id="rooms" class="section-padding bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Explore Our Luxurious Rooms</h2>
            <div class="row">
                <div class="container">
                    <?php if ($rooms_found): ?>
                        <?php while($room = mysqli_fetch_assoc($result)): ?>
                            <div class="room-card">
                                <img src="<?php echo htmlspecialchars($room['image_url']); ?>" class="card-img-left" alt="<?php echo htmlspecialchars($room['room_type']); ?>" onerror="this.src='https://images.unsplash.com/photo-1618773928121-c32242e63f39';">
                                <div class="room-details">
                                    <h5><?php echo htmlspecialchars($room['room_type']); ?> >>> Room no<?php echo $room['id']; ?></h5>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            <i class="fas fa-hotel"></i> <?php echo htmlspecialchars($room['hotel_name']); ?>
                                            <?php if (!empty($room['average_rating'])): ?>
                                                <span class="ms-2">
                                                    <?php 
                                                    $rating = round($room['average_rating'] * 2) / 2; // Round to nearest 0.5
                                                    for ($i = 1; $i <= 5; $i++): 
                                                        if ($i <= floor($rating)): ?>
                                                            <i class="fas fa-star text-warning"></i>
                                                        <?php elseif ($i - 0.5 <= $rating && $rating < $i): ?>
                                                            <i class="fas fa-star-half-alt text-warning"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star text-warning"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    <span class="text-muted">(<?php echo number_format($room['average_rating'], 1); ?>)</span>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </p>
                                    <p class="description"><?php echo htmlspecialchars($room['description']); ?></p>
                                    <p class="specs">
                                        <?php 
                                        $specs = [];
                                        if (!empty($room['size_sqft'])) {
                                            $specs[] = $room['size_sqft'] . ' sq ft';
                                        }
                                        if (!empty($room['bed_type'])) {
                                            $specs[] = $room['bed_type'];
                                        }
                                        $specs[] = $room['capacity'] . ' Guests';
                                        echo implode(' | ', $specs);
                                        ?>
                                    </p>
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
                                                echo "<i class='fas $icon' title='" . htmlspecialchars(trim($amenity)) . "'></i>";
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php if (isLoggedIn() && $room['display_status'] == 'available'): ?>
                                        <a href="booking.php?room_id=<?php echo $room['id']; ?>&check_in=<?php echo urlencode($check_in); ?>&check_out=<?php echo urlencode($check_out); ?>&adults=<?php echo $adults; ?>&children=<?php echo $children; ?>" class="btn btn-custom">Book Now</a>
                                    <?php elseif (!isLoggedIn() && $room['display_status'] == 'available'): ?>
                                        <a href="login.php?redirect=room-list.php&room_id=<?php echo $room['id']; ?>&check_in=<?php echo urlencode($check_in); ?>&check_out=<?php echo urlencode($check_out); ?>&adults=<?php echo $adults; ?>&children=<?php echo $children; ?>" class="btn btn-custom">Login to Book</a>
                                    <?php else: ?>
                                        <button class="btn btn-custom" disabled><?php echo ucfirst($room['display_status']); ?></button>
                                    <?php endif; ?>
                                    <div class="price-badge">PKR <?php echo number_format($room['price_per_hour'], 2); ?>/Hour</div>
                                    <div class="status-badge <?php echo $room['display_status'] == 'available' ? 'status-available' : ($room['display_status'] == 'unavailable' ? 'status-booked' : 'status-maintenance'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($room['display_status'])); ?>
                                    </div>
                                    <?php if ($room['display_status'] == 'booked' && !empty($room['booking_check_in']) && !empty($room['booking_check_out'])): ?>
                                        <div class="booking-dates">
                                            Booked from <?php echo date('M d, Y h:i A', strtotime($room['booking_check_in'])); ?> 
                                            to <?php echo date('M d, Y h:i A', strtotime($room['booking_check_out'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if ($max_price > 0): ?>
                                No rooms found within the price range of PKR <?php echo number_format($max_price, 2); ?>. Please try a higher price range.
                            <?php else: ?>
                                No rooms found for the selected criteria. Please try different filters.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Jhang Hotels</h5>
                    <p>Experience luxury and comfort in the heart of Jhang.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="hotels.php" class="text-white">Hotels</a></li>
                        <li><a href="about.php" class="text-white">About Us</a></li>
                        <li><a href="gallery.php" class="text-white">Gallery</a></li>
                        <li><a href="contact.php" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Follow Us</h5>
                    <div class="social-links">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">© <?php echo date('Y'); ?> Jhang Hotels. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
                // Navbar scroll effect
                window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            const heroSection = document.querySelector('.hero-section');
            const heroBottom = heroSection.offsetTop + heroSection.offsetHeight - 50;

            if (window.scrollY >= heroBottom) {
                navbar.classList.remove('navbar-transparent');
                navbar.classList.add('navbar-dark-bg');
            } else {
                navbar.classList.add('navbar-transparent');
                navbar.classList.remove('navbar-dark-bg');
            }
        });
    document.addEventListener('DOMContentLoaded', function() {
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        
        // Only set default values if inputs are empty
        if (!checkInInput.value) {
            const now = new Date();
            checkInInput.value = now.toISOString().slice(0, 16);
        }
        
        if (!checkOutInput.value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            checkOutInput.value = tomorrow.toISOString().slice(0, 16);
        }
        
        // Validate check-out is after check-in
        checkInInput.addEventListener('change', function() {
            if (checkOutInput.value && checkOutInput.value <= this.value) {
                checkOutInput.value = '';
                alert('Check-out time must be after check-in time');
            }
        });
        
        checkOutInput.addEventListener('change', function() {
            if (this.value <= checkInInput.value) {
                this.value = '';
                alert('Check-out time must be after check-in time');
            }
        });
    });
    </script>
</body>
</html>