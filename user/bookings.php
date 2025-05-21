<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get user data
$user = getUserData();

// Handle booking cancellation
if (isset($_POST['cancel_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify booking belongs to user
    $sql = "SELECT * FROM bookings WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($booking = mysqli_fetch_assoc($result)) {
        // Only allow cancellation of pending or confirmed bookings
        if (in_array($booking['booking_status'], ['pending', 'confirmed'])) {
            $sql = "UPDATE bookings SET booking_status = 'cancelled' WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $booking_id);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Get all bookings
$user_id = $_SESSION['user_id'];
$sql = "SELECT b.*, r.room_type, r.image_url 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$bookings = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Jhang Hotels</title>
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
        }
        .sidebar {
            background: #1a1a1a;
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            background: #d4a017;
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
        }
        .booking-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-5px);
        }
        .booking-card img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-cancel:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .booking-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h4 class="mb-4">User Dashboard</h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i> My Bookings
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">My Bookings</h2>
                
                <?php if (mysqli_num_rows($bookings) > 0): ?>
                    <?php while($booking = mysqli_fetch_assoc($bookings)): ?>
                        <div class="booking-card">
                            <div class="row">
                                <div class="col-md-3">
                                    <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" alt="<?php echo htmlspecialchars($booking['room_type']); ?>" onerror="this.src='https://images.unsplash.com/photo-1618773928121-c32242e63f39';">
                                </div>
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4><?php echo htmlspecialchars($booking['room_type']); ?></h4>
                                            <span class="status-badge status-<?php echo strtolower($booking['booking_status']); ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <h5>PKR <?php echo number_format($booking['total_price'], 2); ?></h5>
                                            <small class="text-muted">Booked on <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-details">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></p>
                                                <p><strong>Check-out:</strong> <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Guests:</strong> <?php echo $booking['adults']; ?> Adult<?php echo $booking['adults'] > 1 ? 's' : ''; ?><?php echo $booking['children'] > 0 ? ', ' . $booking['children'] . ' Child' . ($booking['children'] > 1 ? 'ren' : '') : ''; ?></p>
                                                <p><strong>Nights:</strong> <?php echo (strtotime($booking['check_out_date']) - strtotime($booking['check_in_date'])) / (60 * 60 * 24); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (in_array($booking['booking_status'], ['pending', 'confirmed'])): ?>
                                            <form method="POST" action="" class="mt-3" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" class="btn btn-cancel">
                                                    <i class="fas fa-times"></i> Cancel Booking
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        You haven't made any bookings yet. 
                        <a href="../room-list.php" class="alert-link">Browse our rooms</a> to make your first booking!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 