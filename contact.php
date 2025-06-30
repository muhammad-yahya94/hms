<?php
require_once 'config/database.php';
require_once 'includes/session.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Jhang Hotels</title>
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
        .hero-content {
            text-align: center;
            width: 100%;
        }
        .hero-section h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .section-padding {
            padding: 80px 0;
        }
        .contact-info {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .contact-info i {
            font-size: 2rem;
            color: #d4a017;
            margin-bottom: 15px;
        }
        .form-control, .form-select {
            border: 1px solid #d4a017;
            padding: 10px;
            margin-bottom: 20px;
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
        .btn-contact {
            background-color: #d4a017;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-contact:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .map-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .map-container iframe {
            width: 100%;
            height: 100%;
            min-height: 300px;
            border: 0;
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
                            <a class="nav-link active" href="contact.php">Contact Us</a>
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
        <div class="container">
            <div class="hero-content">
                <h1>Contact Us</h1>
                <p class="lead">We'd love to hear from you. Get in touch with our team.</p>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <h2 class="mb-4">Get In Touch</h2>
                    <p>Have questions or need assistance? Fill out the form and our team will get back to you as soon as possible.</p>
                    
                    <form action="process_contact.php" method="POST" class="mt-5">
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="name" placeholder="Your Name" required>
                            </div>
                            <div class="col-md-6">
                                <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                            </div>
                        </div>
                        <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                        <textarea class="form-control" name="message" rows="5" placeholder="Your Message" required></textarea>
                        <button type="submit" class="btn-contact">Send Message</button>
                    </form>
                </div>
                
                <div class="col-lg-6">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="contact-info text-center">
                                <i class="fas fa-map-marker-alt"></i>
                                <h4>Our Location</h4>
                                <p>123 Hotel Street, Jhang Sadar<br>Jhang, Punjab, Pakistan</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="contact-info text-center">
                                <i class="fas fa-phone-alt"></i>
                                <h4>Phone Number</h4>
                                <p>+92 300 1234567<br>+92 300 7654321</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="contact-info text-center">
                                <i class="fas fa-envelope"></i>
                                <h4>Email Address</h4>
                                <p>info@jhanghotels.com<br>support@jhanghotels.com</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="contact-info text-center">
                                <i class="fas fa-clock"></i>
                                <h4>Working Hours</h4>
                                <p>Monday - Sunday<br>24/7</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map-container">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3418.120662981952!2d72.32212331513202!3d31.07005098151656!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3923788a0a0a0a0a%3A0x0!2zMzHCsDA0JzEyLjIiTiA3MsKwMTknMjUuOCJF!5e0!3m2!1sen!2s!4v1620000000000!5m2!1sen!2s" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>About Jhang Hotels</h5>
                    <p>Experience luxury and comfort at our premium hotels in Jhang. We provide exceptional service and unforgettable experiences.</p>
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
                    <h5>Connect With Us</h5>
                    <div class="social-links">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <p class="mt-3">© 2025 Jhang Hotels. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
