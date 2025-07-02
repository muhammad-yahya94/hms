<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';    

// Add required columns if they don't exist
$required_columns = [
    'booking_status' => "ALTER TABLE bookings ADD COLUMN booking_status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending'",
    'check_in_date' => "ALTER TABLE bookings ADD COLUMN check_in_date DATETIME",
    'check_out_date' => "ALTER TABLE bookings ADD COLUMN check_out_date DATETIME",
    'total_price' => "ALTER TABLE bookings ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0.00"
];

// Add status column to rooms table if it doesn't exist
$check_room_status = "SHOW COLUMNS FROM rooms LIKE 'status'";
$result = mysqli_query($conn, $check_room_status);
if (mysqli_num_rows($result) == 0) {
    $sql = "ALTER TABLE rooms ADD COLUMN status ENUM('available', 'not available', 'maintenance') DEFAULT 'available'";
    mysqli_query($conn, $sql);
}

// Update booking status to 'completed' for past checkouts
$current_time = date('Y-m-d H:i:s');
$update_completed = "UPDATE bookings b 
                    JOIN rooms r ON b.room_id = r.id
                    JOIN hotels h ON b.hotel_id = h.id
                    SET b.booking_status = 'completed', r.status = 'available'
                    WHERE b.check_out_date < ? 
                    AND b.booking_status NOT IN ('cancelled', 'completed')
                    AND h.vendor_id = ?";
$stmt = mysqli_prepare($conn, $update_completed);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $current_time, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

foreach ($required_columns as $column => $sql) {
    $check_sql = "SHOW COLUMNS FROM bookings LIKE '$column'";
    $result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, $sql);
    }
}

// Get filter parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from_filter = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to_filter = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Handle booking status update
if (isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    // Verify that the booking belongs to the admin's hotel
    $sql = "SELECT b.id FROM bookings b 
            JOIN hotels h ON b.hotel_id = h.id 
            WHERE b.id = ? AND h.vendor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $error = "Booking not found or you do not have permission to update it.";
    } else {
        // Update booking status
        $sql = "UPDATE bookings SET booking_status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // If booking is confirmed, update room status
            if ($status === 'confirmed') {
                $sql = "UPDATE rooms r 
                        JOIN bookings b ON r.id = b.room_id 
                        JOIN hotels h ON b.hotel_id = h.id
                        SET r.status = 'not available' 
                        WHERE b.id = ? AND h.vendor_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $booking_id, $_SESSION['user_id']);
                mysqli_stmt_execute($stmt);
            }
            // If booking is cancelled, make room available again
            elseif ($status === 'cancelled') {
                $sql = "UPDATE rooms r 
                        JOIN bookings b ON r.id = b.room_id 
                        JOIN hotels h ON b.hotel_id = h.id
                        SET r.status = 'available' 
                        WHERE b.id = ? AND h.vendor_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $booking_id, $_SESSION['user_id']);
                mysqli_stmt_execute($stmt);
            }
            $success = "Booking status updated successfully.";
        } else {
            $error = "Error updating booking status: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle booking date update
if (isset($_POST['update_dates'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_check_in = $_POST['check_in_date'];
    $new_check_out = $_POST['check_out_date'];
    
    // Validate dates
    $check_in_date = new DateTime($new_check_in);
    $check_out_date = new DateTime($new_check_out);
    
    if ($check_out_date <= $check_in_date) {
        $error = "Check-out date must be after check-in date.";
    } else {
        // Verify that the booking belongs to the admin's hotel
        $sql = "SELECT b.room_id FROM bookings b 
                JOIN hotels h ON b.hotel_id = h.id 
                WHERE b.id = ? AND h.vendor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $booking_id, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 0) {
            $error = "Booking not found or you do not have permission to update it.";
        } else {
            $room_id = mysqli_fetch_assoc($result)['room_id'];
            
            // Check for conflicts with other non-cancelled bookings
            $sql = "SELECT id FROM bookings 
                    WHERE room_id = ? 
                    AND booking_status IN ('pending', 'confirmed') 
                    AND id != ? 
                    AND (
                        (check_in_date <= ? AND check_out_date >= ?) OR
                        (check_in_date <= ? AND check_out_date >= ?) OR
                        (check_in_date >= ? AND check_out_date <= ?)
                    )";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iissssss", $room_id, $booking_id, $new_check_out, $new_check_in, $new_check_in, $new_check_in, $new_check_in, $new_check_out);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "The new dates conflict with another booking for this room.";
            } else {
                // Calculate new total price based on hours with fixed hourly rate of 200 PKR
                $interval = $check_in_date->diff($check_out_date);
                $total_hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
                $total_hours = max(1, ceil($total_hours)); // Minimum 1 hour, round up
                $hourly_rate = 200; // Fixed hourly rate
                $new_total_price = round($hourly_rate * $total_hours, 2);
                
                // Update booking
                $sql = "UPDATE bookings 
                        SET check_in_date = ?, check_out_date = ?, total_price = ? 
                        WHERE id = ? AND EXISTS (
                            SELECT 1 FROM hotels h WHERE h.id = bookings.hotel_id AND h.vendor_id = ?
                        )";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssdii", $new_check_in, $new_check_out, $new_total_price, $booking_id, $_SESSION['user_id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Booking dates and price updated successfully. New total: PKR " . number_format($new_total_price, 2) . " for $total_hours hours.";
                } else {
                    $error = "Error updating booking details: " . mysqli_error($conn);
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Build the SQL query for bookings
$sql = "SELECT b.*, h.name AS hotel_name, r.room_type, u.username, u.email
        FROM bookings b 
        JOIN hotels h ON b.hotel_id = h.id 
        JOIN rooms r ON b.room_id = r.id 
        JOIN users u ON b.user_id = u.id 
        WHERE h.vendor_id = ?";

$params = array($_SESSION['user_id']);
$types = "i";

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
    mysqli_stmt_close($user_stmt);
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
        .status-completed {
            background-color:rgb(40, 63, 167);
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
        .btn-edit {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-edit:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            background-color: #d4a017;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .modal-title {
            font-weight: 600;
        }
        .form-control {
            border: 1px solid #d4a017;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875rem;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback {
            display: block;
        }
        #price-preview {
            margin-top: 10px;
            font-weight: 500;
            color: #d4a017;
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
                    <a class="nav-link" href="food.php"><i class="fas fa-utensils"></i> Food Menu</a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Employee
                    </a>
                    <a class="nav-link active" href="reservations.php">
                        <i class="fas fa-calendar-check"></i> Reservations
                    </a>
                    <a class="nav-link" href="food_orders.php"><i class="fas fa-shopping-cart"></i> Food Orders</a>
                    <a class="nav-link position-relative" href="chat.php">
                        <i class="fas fa-comments"></i> Customer Chats
                        <?php
                        // Get unread message count for the admin
                        $unread_count = 0;
                        $admin_id = $_SESSION['user_id'];
                        $stmt = $conn->prepare("
                            SELECT COUNT(m.id) as unread_count
                            FROM messages m
                            JOIN conversations c ON m.conversation_id = c.id
                            WHERE c.admin_id = ? AND m.sender_type = 'user' AND m.is_read = FALSE
                        ");
                        $stmt->bind_param("i", $admin_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $unread_count = $row['unread_count'];
                        }
                        $stmt->close();
                        if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
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
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
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
                                   value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
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
                                    <h5 class="mb-0">Room Number <?php echo htmlspecialchars($booking['room_id']); ?></h5>
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
                                
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($booking['booking_status'] === 'pending'): ?>
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
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editDatesModal<?php echo $booking['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit Dates
                                    </button>
                                </div>
                            </div>

                            <!-- Edit Dates Modal -->
                            <div class="modal fade" id="editDatesModal<?php echo $booking['id']; ?>" tabindex="-1" aria-labelledby="editDatesModalLabel<?php echo $booking['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editDatesModalLabel<?php echo $booking['id']; ?>">Edit Booking Dates #<?php echo $booking['id']; ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="" class="needs-validation" novalidate>
                                            <div class="modal-body">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <div class="mb-3">
                                                    <label for="check_in_date<?php echo $booking['id']; ?>" class="form-label">Check-in Date & Time</label>
                                                    <input type="datetime-local" class="form-control" id="check_in_date<?php echo $booking['id']; ?>" name="check_in_date" 
                                                           value="<?php echo date('Y-m-d\TH:i', strtotime($booking['check_in_date'])); ?>" required>
                                                    <div class="invalid-feedback">
                                                        Please select a valid check-in date and time.
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="check_out_date<?php echo $booking['id']; ?>" class="form-label">Check-out Date & Time</label>
                                                    <input type="datetime-local" class="form-control" id="check_out_date<?php echo $booking['id']; ?>" name="check_out_date" 
                                                           value="<?php echo date('Y-m-d\TH:i', strtotime($booking['check_out_date'])); ?>" required>
                                                    <div class="invalid-feedback">
                                                        Please select a valid check-out date and time.
                                                    </div>
                                                </div>
                                                <div id="price-preview<?php echo $booking['id']; ?>" class="text-muted">
                                                    Estimated price will be shown here.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_dates" class="btn btn-custom">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation and price preview
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form.needs-validation').forEach(form => {
                const checkInInput = form.querySelector('[name="check_in_date"]');
                const checkOutInput = form.querySelector('[name="check_out_date"]');
                const pricePreview = form.querySelector('[id^="price-preview"]');

                function updatePricePreview() {
                    const checkIn = new Date(checkInInput.value);
                    const checkOut = new Date(checkOutInput.value);

                    if (checkOut > checkIn && !isNaN(checkIn.getTime()) && !isNaN(checkOut.getTime())) {
                        const diffMs = checkOut - checkIn;
                        const totalSeconds = Math.floor(diffMs / 1000);
                        const totalMinutes = Math.floor(totalSeconds / 60);
                        const totalHours = Math.floor(totalMinutes / 60);
                        const remainingMinutes = totalMinutes % 60;
                        const remainingSeconds = totalSeconds % 60;

                        let hours = totalHours;
                        if (remainingMinutes > 0 || remainingSeconds > 0) {
                            hours++; // Round up like in PHP
                        }

                        const hourlyRate = 200; // Fixed hourly rate of 200 PKR
                        const totalPrice = (hourlyRate * hours).toFixed(2);

                        pricePreview.textContent = `Estimated price: PKR ${totalPrice} for ${hours} hour${hours > 1 ? 's' : ''}`;
                        pricePreview.style.color = '#d4a017';
                    } else {
                        pricePreview.textContent = 'Please select valid dates.';
                        pricePreview.style.color = '#dc3545';
                    }
                }

                checkInInput.addEventListener('change', updatePricePreview);
                checkOutInput.addEventListener('change', updatePricePreview);

                form.addEventListener('submit', function(event) {
                    checkInInput.classList.remove('is-invalid');
                    checkOutInput.classList.remove('is-invalid');

                    const checkIn = new Date(checkInInput.value);
                    const checkOut = new Date(checkOutInput.value);

                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    } else if (isNaN(checkIn.getTime()) || isNaN(checkOut.getTime())) {
                        event.preventDefault();
                        alert('Please select valid dates.');
                        checkInInput.classList.add('is-invalid');
                        checkOutInput.classList.add('is-invalid');
                    } else if (checkOut <= checkIn) {
                        event.preventDefault();
                        alert('Check-out date must be after check-in date.');
                        checkOutInput.classList.add('is-invalid');
                    }

                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>