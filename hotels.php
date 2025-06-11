<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Fetch all hotels
$all_hotels = [];
$sql = "SELECT * FROM hotels ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $all_hotels[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Hotels - Jhang Hotels</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)), 
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80') no-repeat center center/cover;
            color: white;
            padding: 120px 0 100px 0;
            min-height: 400px;
            display: flex;
            align-items: center;
            position: relative;
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
        .card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        .card-body {
            padding: 20px;
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
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-custom:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Hero Section with Integrated Navbar -->
    <section class="hero-section">
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
                        <li class="nav-item">
                            <a class="nav-link" href="room-list.php">Rooms</a>
                        </li>
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
                            <a class="nav-link" href="index.php#contact">Contact Us</a>
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
                                <a class="nav-link" href="register.php">Register</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        <div class="container text-center">
            <h1 class="display-4 fw-bold">All Hotels</h1>
        </div>
    </section>

    <!-- All Hotels Section -->
    <section class="section-padding">
        <div class="container">
            <h2 class="text-center mb-5">Our Hotels</h2>
            <div class="row">
                <?php if (empty($all_hotels)): ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No hotels available at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_hotels as $hotel): ?>
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
                <?php endif; ?>
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
                        <li><a href="room-list.php" class="text-white text-decoration-none">Rooms</a></li>
                        <li><a href="about.php" class="text-white text-decoration-none">About Us</a></li>
                        <li><a href="gallery.php" class="text-white text-decoration-none">Gallery</a></li>
                        <li><a href="index.php#contact" class="text-white text-decoration-none">Contact Us</a></li>
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
    </script>
</body>
</html>