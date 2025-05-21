<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';

// Get filter parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_from_filter = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to_filter = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';

// Add required columns if they don't exist
$required_columns = [
    'status' => "ALTER TABLE bookings ADD COLUMN status VARCHAR(20) DEFAULT 'pending'",
    'check_in' => "ALTER TABLE bookings ADD COLUMN check_in DATETIME",
    'check_out' => "ALTER TABLE bookings ADD COLUMN check_out DATETIME",
    'total_price' => "ALTER TABLE bookings ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0.00"
];

// Add status column to rooms table if it doesn't exist
$check_room_status = "SHOW COLUMNS FROM rooms LIKE 'status'";
$result = mysqli_query($conn, $check_room_status);
if (mysqli_num_rows($result) == 0) {
    $sql = "ALTER TABLE rooms ADD COLUMN status ENUM('available', 'booked', 'maintenance') DEFAULT 'available'";
    mysqli_query($conn, $sql);
}

foreach ($required_columns as $column => $sql) {
    $check_sql = "SHOW COLUMNS FROM bookings LIKE '$column'";
    $result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Handle booking status update
if (isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    // Update booking status
    $sql = "UPDATE bookings SET booking_status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // If booking is confirmed, update room status
        if ($status === 'confirmed') {
            $sql = "UPDATE rooms r 
                    JOIN bookings b ON r.id = b.room_id 
                    SET r.status = 'booked' 
                    WHERE b.id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $booking_id);
            mysqli_stmt_execute($stmt);
        }
        // If booking is cancelled, make room available again
        elseif ($status === 'cancelled') {
            $sql = "UPDATE rooms r 
                    JOIN bookings b ON r.id = b.room_id 
                    SET r.status = 'available' 
                    WHERE b.id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $booking_id);
            mysqli_stmt_execute($stmt);
        }
        $success = "Booking status updated successfully.";
    } else {
        $error = "Error updating booking status.";
    }
}

// Build the SQL query
$sql = "SELECT b.*, h.name as hotel_name, r.room_type, u.username, u.email
        FROM bookings b 
        JOIN hotels h ON b.hotel_id = h.id 
        JOIN rooms r ON b.room_id = r.id 
        JOIN users u ON b.user_id = u.id 
        WHERE 1=1";

$params = array();
$types = "";

if ($user_id !== null) {
    $sql .= " AND b.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
    // Fetch username for the title if filtering by user
    $user_sql = "SELECT username FROM users WHERE id = ? LIMIT 1";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user_row = mysqli_fetch_assoc($user_result);
    $username_filter = $user_row ? " for " . htmlspecialchars($user_row['username']) : '';
} else {
    $username_filter = '';
}

// Add status filter
if (!empty($status_filter)) {
    $sql .= " AND b.booking_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date range filter
if (!empty($date_from_filter)) {
    $sql .= " AND b.check_in_date >= ?";
    $params[] = $date_from_filter;
    $types .= "s";
}

if (!empty($date_to_filter)) {
    $sql .= " AND b.check_in_date <= ?";
    $params[] = $date_to_filter;
    $types .= "s";
}

$sql .= " ORDER BY b.booking_status = 'pending' DESC, b.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$bookings = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Jhang Hotels</title>
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
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .booking-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #ffc107;
            color: #000;
        }
        .status-confirmed {
            background-color: #28a745;
            color: white;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
        }
        .btn-custom {
            background-color: #d4a017;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-custom:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h4 class="mb-4">Admin Panel</h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="hotels.php">
                        <i class="fas fa-hotel"></i> Hotels
                    </a>
                    <a class="nav-link" href="rooms.php">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link active" href="reservations.php">
                        <i class="fas fa-calendar-check"></i> Reservations
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
                <h2 class="mb-4">Reservation Management<?php echo $username_filter; ?></h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-custom me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="reservations.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bookings List -->
                <div class="row">
                    <?php while($booking = mysqli_fetch_assoc($bookings)): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="booking-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="mb-0">Booking #<?php echo $booking['id']; ?></h5>
                                    <span class="status-badge status-<?php echo strtolower($booking['booking_status']); ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </div>
                                
                                <p class="mb-2">
                                    <i class="fas fa-hotel"></i> 
                                    <?php echo htmlspecialchars($booking['hotel_name']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-bed"></i> 
                                    <?php echo htmlspecialchars($booking['room_type']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($booking['username']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-envelope"></i> 
                                    <?php echo htmlspecialchars($booking['email']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-calendar"></i> 
                                    Check-in: <?php echo date('M d, Y h:i A', strtotime($booking['check_in_date'])); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-calendar"></i> 
                                    Check-out: <?php echo date('M d, Y h:i A', strtotime($booking['check_out_date'])); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-users"></i> 
                                    Guests: <?php echo $booking['adults']; ?> Adult<?php echo $booking['adults'] > 1 ? 's' : ''; ?>
                                    <?php echo $booking['children'] > 0 ? ', ' . $booking['children'] . ' Child' . ($booking['children'] > 1 ? 'ren' : '') : ''; ?>
                                </p>
                                <p class="mb-3">
                                    <i class="fas fa-rupee-sign"></i> 
                                    Total: PKR <?php echo number_format($booking['total_price'], 2); ?>
                                </p>
                                
                                <?php if ($booking['booking_status'] === 'pending'): ?>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" name="update_status" class="btn btn-success">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" name="update_status" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 