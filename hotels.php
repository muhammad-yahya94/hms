<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Process review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please login to submit a review.';
        header('Location: login.php');
        exit();
    }
    
    $hotel_id = intval($_POST['hotel_id']);
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Please select a valid rating between 1 and 5.';
    } else {
        // Insert review
        $stmt = $conn->prepare("INSERT INTO reviews (hotel_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $hotel_id, $user_id, $rating, $comment);
        
        if ($stmt->execute()) {
            // Update hotel's average rating
            $update_sql = "UPDATE hotels h 
                          SET average_rating = (
                              SELECT COALESCE(AVG(rating), 0) 
                              FROM reviews 
                              WHERE hotel_id = ?
                          ) 
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $hotel_id, $hotel_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $_SESSION['success'] = 'Thank you for your review!';
        } else {
            $_SESSION['error'] = 'Failed to submit review. Please try again.';
        }
        $stmt->close();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '#hotel-' . $hotel_id);
    exit();
}

// Fetch all hotels with their ratings
$all_hotels = [];
$sql = "SELECT h.*, 
               COALESCE((SELECT AVG(rating) FROM reviews WHERE hotel_id = h.id), 0) as average_rating,
               (SELECT COUNT(*) FROM reviews WHERE hotel_id = h.id) as review_count
        FROM hotels h 
        ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $all_hotels[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Error fetching hotels: " . mysqli_error($conn));
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
            height: 100%;
            position: relative;
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
        .rating-stars {
            color: #ffc107;
            margin-bottom: 10px;
        }
        .review-count {
            font-size: 0.9em;
            color: #6c757d;
            margin-left: 5px;
        }
        .review-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fff;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .review-user {
            font-weight: 600;
        }
        .review-date {
            color: #6c757d;
            font-size: 0.9em;
        }
        .review-rating {
            color: #ffc107;
            margin-bottom: 5px;
        }
        .review-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .star-rating {
            display: inline-block;
        }
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            color: #ddd;
            font-size: 24px;
            padding: 0 5px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input[type="radio"]:checked + label,
        .star-rating input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffca2c;
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
        .footer {
            background-color: #1a1a1a;
            color: white;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <!-- Hero Section with Integrated Navbar.rectangle

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
                            <div class="card" id="hotel-<?php echo $hotel['id']; ?>">
                                <img src="<?php echo htmlspecialchars($hotel['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($hotel['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($hotel['name']); ?></h5>
                                    <div class="mb-2">
                                        <div class="rating-stars">
                                            <?php
                                            $rating = round($hotel['average_rating'], 1);
                                            $fullStars = floor($rating);
                                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                                            $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                            for ($i = 0; $i < $fullStars; $i++) {
                                                echo '<i class="fas fa-star"></i>';
                                            }
                                            if ($hasHalfStar) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            }
                                            for ($i = 0; $i < $emptyStars; $i++) {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                            <span class="review-count">(<?php echo $hotel['review_count']; ?>)</span>
                                        </div>
                                        <div class="text-muted small"><?php echo number_format($rating, 1); ?> out of 5</div>
                                    </div>
                                    <p class="card-text"><?php echo htmlspecialchars($hotel['description']); ?></p>
                                    <p class="card-text">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($hotel['city']); ?>
                                    </p>
                                    <a href="room-list.php?hotel=<?php echo $hotel['id']; ?>" class="btn btn-custom mb-2">View Rooms</a>
                                    
                                    <!-- Review Form (only for logged-in users) -->
                                    <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-outline-secondary btn-sm w-100 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#reviewForm<?php echo $hotel['id']; ?>">
                                            Write a Review
                                        </button>
                                        
                                        <div class="collapse" id="reviewForm<?php echo $hotel['id']; ?>">
                                            <form method="POST" class="review-form">
                                                <input type="hidden" name="hotel_id" value="<?php echo $hotel['id']; ?>">
                                                <h6>Rate this hotel:</h6>
                                                <div class="mb-3">
                                                    <div class="star-rating" id="star-rating-<?php echo $hotel['id']; ?>">
                                                        <input type="radio" id="star5-<?php echo $hotel['id']; ?>" name="rating" value="5" required>
                                                        <label for="star5-<?php echo $hotel['id']; ?>" title="5 stars">★</label>
                                                        <input type="radio" id="star4-<?php echo $hotel['id']; ?>" name="rating" value="4">
                                                        <label for="star4-<?php echo $hotel['id']; ?>" title="4 stars">★</label>
                                                        <input type="radio" id="star3-<?php echo $hotel['id']; ?>" name="rating" value="3">
                                                        <label for="star3-<?php echo $hotel['id']; ?>" title="3 stars">★</label>
                                                        <input type="radio" id="star2-<?php echo $hotel['id']; ?>" name="rating" value="2">
                                                        <label for="star2-<?php echo $hotel['id']; ?>" title="2 stars">★</label>
                                                        <input type="radio" id="star1-<?php echo $hotel['id']; ?>" name="rating" value="1">
                                                        <label for="star1-<?php echo $hotel['id']; ?>" title="1 star">★</label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="comment-<?php echo $hotel['id']; ?>" class="form-label">Your Review</label>
                                                    <textarea class="form-control" id="comment-<?php echo $hotel['id']; ?>" name="comment" rows="3" required></textarea>
                                                </div>
                                                <button type="submit" name="submit_review" class="btn btn-primary btn-sm">Submit Review</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Display Reviews -->
                                    <?php
                                    $reviews_sql = "SELECT r.*, u.username, u.profile_image 
                                                  FROM reviews r 
                                                  JOIN users u ON r.user_id = u.id 
                                                  WHERE r.hotel_id = ? 
                                                  ORDER BY r.created_at DESC 
                                                  LIMIT 3";
                                    $stmt = $conn->prepare($reviews_sql);
                                    $stmt->bind_param("i", $hotel['id']);
                                    $stmt->execute();
                                    $reviews_result = $stmt->get_result();
                                    $reviews = [];
                                    while ($review = $reviews_result->fetch_assoc()) {
                                        $reviews[] = $review;
                                    }
                                    $stmt->close();
                                    
                                    if (!empty($reviews)): ?>
                                        <hr>
                                        <h6 class="mt-3">Recent Reviews</h6>
                                        <?php foreach ($reviews as $review): ?>
                                            <div class="review-card">
                                                <div class="review-header">
                                                    <div class="review-user">
                                                        <?php if (!empty($review['profile_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($review['profile_image']); ?>" class="rounded-circle me-2" width="30" height="30" alt="Profile">
                                                        <?php else: ?>
                                                            <i class="fas fa-user-circle me-2"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($review['username']); ?>
                                                    </div>
                                                    <div class="review-date">
                                                        <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                                    </div>
                                                </div>
                                                <div class="review-rating">
                                                    <?php
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $review['rating']) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <div class="review-comment">
                                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                <p class="mb-0">© 2025 Jhang Hotels. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Show success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                alert('<?php echo addslashes($_SESSION['success']); ?>');
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                alert('<?php echo addslashes($_SESSION['error']); ?>');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            // Initialize star rating for each hotel
            document.querySelectorAll('.star-rating').forEach(ratingContainer => {
                const radioInputs = ratingContainer.querySelectorAll('input[type="radio"]');
                const labels = ratingContainer.querySelectorAll('label');
                
                // Initialize star colors based on checked input
                const checkedInput = ratingContainer.querySelector('input[type="radio"]:checked');
                if (checkedInput) {
                    const checkedValue = parseInt(checkedInput.value);
                    labels.forEach(label => {
                        const starValue = parseInt(label.getAttribute('for').match(/\d+/)[0]);
                        label.style.color = starValue <= checkedValue ? '#ffc107' : '#ddd';
                    });
                }
                
                // Handle radio input change
                radioInputs.forEach(radio => {
                    radio.addEventListener('change', function() {
                        const rating = parseInt(this.value);
                        labels.forEach(label => {
                            const starValue = parseInt(label.getAttribute('for').match(/\d+/)[0]);
                            label.style.color = starValue <= rating ? '#ffc107' : '#ddd';
                        });
                    });
                });
                
                // Handle hover effect
                labels.forEach(label => {
                    label.addEventListener('mouseover', function() {
                        const hoverValue = parseInt(this.getAttribute('for').match(/\d+/)[0]);
                        labels.forEach(l => {
                            const starValue = parseInt(l.getAttribute('for').match(/\d+/)[0]);
                            l.style.color = starValue <= hoverValue ? '#ffca2c' : '#ddd';
                        });
                    });
                    
                    label.addEventListener('mouseout', function() {
                        const checkedInput = ratingContainer.querySelector('input[type="radio"]:checked');
                        if (checkedInput) {
                            const checkedValue = parseInt(checkedInput.value);
                            labels.forEach(l => {
                                const starValue = parseInt(l.getAttribute('for').match(/\d+/)[0]);
                                l.style.color = starValue <= checkedValue ? '#ffc107' : '#ddd';
                            });
                        } else {
                            labels.forEach(l => l.style.color = '#ddd');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>