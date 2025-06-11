<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get user data
$user = getUserData();

// Get booking statistics
$user_id = $_SESSION['user_id'];

// Total bookings
$sql = "SELECT COUNT(*) as total_bookings FROM bookings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_bookings = mysqli_fetch_assoc($result)['total_bookings'];

// Cancelled bookings
$sql = "SELECT COUNT(*) as cancelled_bookings FROM bookings WHERE user_id = ? AND booking_status = 'cancelled'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cancelled_bookings = mysqli_fetch_assoc($result)['cancelled_bookings'];

// Pending bookings
$sql = "SELECT COUNT(*) as pending_bookings FROM bookings WHERE user_id = ? AND booking_status = 'pending'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pending_bookings = mysqli_fetch_assoc($result)['pending_bookings'];

// Confirmed bookings
$sql = "SELECT COUNT(*) as confirmed_bookings FROM bookings WHERE user_id = ? AND booking_status = 'confirmed'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$confirmed_bookings = mysqli_fetch_assoc($result)['confirmed_bookings'];

// Recent bookings
$sql = "SELECT b.*, r.room_type, r.image_url 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC 
        LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_bookings = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Jhang Hotels</title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2rem;
            color: #d4a017;
            margin-bottom: 10px;
        }
        .booking-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-5px);
        }
        .booking-card img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h4 class="mb-4">User Dashboard</h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i> My Bookings
                    </a>
                    <a class="nav-link" href="food_menu.php">
                        <i class="fas fa-utensils"></i> Food Menu
                    </a>
                    <a class="nav-link" href="food_orders.php">
                        <i class="fas fa-shopping-cart"></i> My Food Orders
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
                <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-check"></i>
                            <h3><?php echo $total_bookings; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $pending_bookings; ?></h3>
                            <p>Pending Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $confirmed_bookings; ?></h3>
                            <p>Confirmed Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-times-circle"></i>
                            <h3><?php echo $cancelled_bookings; ?></h3>
                            <p>Cancelled Bookings</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <h4 class="mb-3">Recent Bookings</h4>
                <?php if (mysqli_num_rows($recent_bookings) > 0): ?>
                    <?php while($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                        <div class="booking-card">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" alt="<?php echo htmlspecialchars($booking['room_type']); ?>" onerror="this.src='https://images.unsplash.com/photo-1618773928121-c32242e63f39';">
                                </div>
                                <div class="col-md-7">
                                    <h5><?php echo htmlspecialchars($booking['room_type']); ?></h5>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?> - 
                                        <?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-users"></i> 
                                        <?php echo $booking['adults']; ?> Adult<?php echo $booking['adults'] > 1 ? 's' : ''; ?>
                                        <?php echo $booking['children'] > 0 ? ', ' . $booking['children'] . ' Child' . ($booking['children'] > 1 ? 'ren' : '') : ''; ?>
                                    </p>
                                </div>
                                <div class="col-md-3 text-end">
                                    <span class="status-badge status-<?php echo strtolower($booking['booking_status']); ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                    <p class="mt-2 mb-0">
                                        <strong>PKR <?php echo number_format($booking['total_price'], 2); ?></strong>
                                    </p>
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