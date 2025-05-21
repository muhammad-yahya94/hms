<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Fetch featured hotels
$featured_hotels = [];
$sql = "SELECT * FROM hotels ORDER BY id DESC LIMIT 3";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $featured_hotels[] = $row;
}

// Fetch room categories
$room_categories = [];
$sql = "SELECT DISTINCT room_type, MIN(price_per_night) as min_price, MAX(price_per_night) as max_price 
        FROM rooms GROUP BY room_type ORDER BY min_price";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $room_categories[] = $row;
}

$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : date('Y-m-d 14:00');
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : date('Y-m-d 12:00', strtotime('+1 day'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jhang Hotels - Luxury in Jhang</title>
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
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80') no-repeat center center/cover;
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
        .search-form {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            color: #333;
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
        .card-img-top {
            height: 200px;
            object-fit: cover;
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
        }
        .btn-custom:hover {
            background-color: #b38b12;
        }
        .room-category {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .room-category:hover {
            transform: translateY(-5px);
        }
        .price-range {
            color: #d4a017;
            font-weight: 600;
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
                            <a class="nav-link" href="room-list.php">Rooms</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#features">Features</a>
                        </li>
                        <li class="nav-item">   
                            <a class="nav-link" href="#contact">Contact</a>
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
                                    <!-- <li><a class="dropdown-item" href="user/bookings.php">My Bookings</a></li>
                                    <li><a class="dropdown-item" href="user/profile.php">Profile</a></li> -->
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="login.php">Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">Register</a>
                            </li>
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
                        <h1 class="display-4 fw-bold mb-4">Your 5-Star Retreat in Jhang</h1>
                        <p class="lead fs-4 mb-4">Experience exquisite luxury where modern elegance meets timeless comfort.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="room-list.php" class="btn btn-custom btn-lg px-4 py-2">
                                Explore Rooms <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                            <a href="#contact" class="btn btn-outline-light btn-lg px-4 py-2">
                                Contact Us
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h3 class="text-center">Find Your Perfect Stay</h3>
                        <form action="room-list.php" method="GET" class="search-form">
                            <div class="mb-3">
                                <label for="city" class="form-label">City Name</label>
                                <select class="form-select" id="city" name="city">
                                    <option value="">All Cities</option>
                                    <?php
                                    $cities_sql = "SELECT DISTINCT city FROM hotels ORDER BY city";
                                    $cities_result = mysqli_query($conn, $cities_sql);
                                    while ($city_row = mysqli_fetch_assoc($cities_result)) {
                                        echo '<option value="' . htmlspecialchars($city_row['city']) . '">' . htmlspecialchars($city_row['city']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="check_in">Check-in Date & Time</label>
                                <input type="datetime-local" class="form-control" id="check_in" name="check_in" required>
                            </div>
                            <div class="form-group">
                                <label for="check_out">Check-out Date & Time</label>
                                <input type="datetime-local" class="form-control" id="check_out" name="check_out" required>
                            </div>
                            <div class="row mb-3">
                                 <div class="col-md-4">
                                    <label for="room_type" class="form-label">Room Type</label>
                                    <select class="form-select" id="room_type" name="room_type">
                                        <option value="">All Types</option>
                                        <?php
                                        $room_types_sql = "SELECT DISTINCT room_type FROM rooms ORDER BY room_type";
                                        $room_types_result = mysqli_query($conn, $room_types_sql);
                                        while($type_row = mysqli_fetch_assoc($room_types_result)) {
                                            $selected = (isset($_GET['room_type']) && $_GET['room_type'] == $type_row['room_type']) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($type_row['room_type']) . "' $selected>" . htmlspecialchars(ucfirst($type_row['room_type'])) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="adults" class="form-label">Adults</label>
                                    <input type="number" class="form-control" id="adults" name="adults" 
                                           min="1" max="4" value="2" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="children" class="form-label">Children</label>
                                    <input type="number" class="form-control" id="children" name="children" 
                                           min="0" max="3" value="0" required>
                                </div>
                            </div>
                             <div class="mb-3">
                                <label for="max_price" class="form-label">Room Price</label>
                                <input type="number" class="form-control" id="max_price" name="max_price" 
                                       min="0" placeholder="Enter price" value="">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-booking">
                                    CHECK AVAILABILITY <i class="fas fa-search ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Hotels Section -->
    <section class="section-padding bg-light" id="hotels">
        <div class="container">
            <h2 class="text-center mb-5">Featured Hotels</h2>
            <div class="row">
                <?php foreach ($featured_hotels as $hotel): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <img src="<?php echo htmlspecialchars($hotel['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($hotel['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($hotel['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($hotel['description']); ?></p>
                            <p class="card-text">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hotel['city']); ?>
                            </p>
                            <a href="room-list.php?hotel=<?php echo $hotel['id']; ?>" class="btn btn-custom">View Rooms</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Room Categories Section -->
    <section class="section-padding" id="rooms">
        <div class="container">
            <h2 class="text-center mb-5">Room Categories</h2>
            <div class="row">
                <?php foreach ($room_categories as $category): ?>
                <div class="col-md-4">
                    <div class="room-category">
                        <h3><?php echo htmlspecialchars($category['room_type']); ?></h3>
                        <p class="price-range">
                            From Pkr<?php echo number_format($category['min_price'], 2); ?> to Pkr<?php echo number_format($category['max_price'], 2); ?> per night
                        </p>
                        <a href="room-list.php?type=<?php echo urlencode($category['room_type']); ?>" class="btn btn-custom">View Rooms</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section-padding bg-light" id="features">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose Us</h2>
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <h3>Premium Service</h3>
                    <p>24/7 concierge service and room service available</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <h3>Modern Amenities</h3>
                    <p>High-speed WiFi and modern facilities in all rooms</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Fine Dining</h3>
                    <p>Multiple restaurants serving local and international cuisine</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section-padding" id="contact">
        <div class="container">
            <h2 class="text-center mb-5">Contact Us</h2>
            <div class="row">
                <div class="col-md-6">
                    <form>
                        <div class="mb-3">
                            <input type="text" class="form-control" placeholder="Your Name" required>
                        </div>
                        <div class="mb-3">
                            <input type="email" class="form-control" placeholder="Your Email" required>
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" rows="5" placeholder="Your Message" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-custom">Send Message</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <div class="p-4">
                        <h4>Get in Touch</h4>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 123 Main Street, Jhang, Pakistan</p>
                        <p><i class="fas fa-phone me-2"></i> +92 123 4567890</p>
                        <p><i class="fas fa-envelope me-2"></i> info@jhanghotels.com</p>
                    </div>
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
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="room-list.php" class="text-white">Rooms</a></li>
                        <li><a href="#features" class="text-white">Features</a></li>
                        <li><a href="#contact" class="text-white">Contact</a></li>
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
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Jhang Hotels. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript for Navbar Scroll Effect -->
    <script>
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
            
            // Set default values to current date and time
            const now = new Date();
            const tomorrow = new Date(now);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            checkInInput.value = now.toISOString().slice(0, 16);
            checkOutInput.value = tomorrow.toISOString().slice(0, 16);
            
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