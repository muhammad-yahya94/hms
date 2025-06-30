<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get user data
$user = getUserData();

$error = '';
$success = '';

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
            if (mysqli_stmt_execute($stmt)) {
                $success = "Booking cancelled successfully.";
            } else {
                $error = "Error cancelling booking.";
            }
        } else {
            $error = "Cannot cancel this booking.";
        }
    } else {
        $error = "Booking not found or unauthorized.";
    }
}

// Handle booking edit
if (isset($_POST['edit_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $user_id = $_SESSION['user_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $adults = (int)$_POST['adults'];
    $children = (int)$_POST['children'];

    // Validate dates
    $check_in_date = new DateTime($check_in);
    $check_out_date = new DateTime($check_out);
    $now = new DateTime(); // Current time (timezone set in session.php)

    if ($check_out_date <= $check_in_date) {
        $error = "Check-out date and time must be after check-in.";
    } elseif ($check_in_date < $now) {
        $error = "Check-in date and time cannot be in the past.";
    } elseif ($adults < 1) {
        $error = "At least one adult is required.";
    } else {
        // Verify booking belongs to user and get room details
        $sql = "SELECT b.*, r.price_per_hour, r.capacity 
                FROM bookings b 
                JOIN rooms r ON b.room_id = r.id 
                WHERE b.id = ? AND b.user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($booking = mysqli_fetch_assoc($result)) {
            // Only allow editing of pending or confirmed bookings
            if (!in_array($booking['booking_status'], ['pending', 'confirmed'])) {
                $error = "Cannot edit a cancelled booking.";
            } elseif ($adults + $children > $booking['capacity']) {
                $error = "Total guests exceed room capacity of {$booking['capacity']}.";
            } else {
                // Check for overlapping bookings (excluding current booking)
                $sql = "SELECT id FROM bookings 
                        WHERE room_id = ? AND booking_status IN ('pending', 'confirmed') 
                        AND id != ? 
                        AND ((check_in_date <= ? AND check_out_date >= ?) 
                             OR (check_in_date <= ? AND check_out_date >= ?) 
                             OR (check_in_date >= ? AND check_out_date <= ?))";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iissssss", $booking['room_id'], $booking_id, 
                    $check_out, $check_in, $check_out, $check_in, $check_in, $check_out);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    $error = "The room is unavailable for the selected dates and times.";
                } else {
                    // Calculate new total price based on hours
                    $interval = $check_in_date->diff($check_out_date);
                    $hours = ($interval->days * 24) + $interval->h;
                    if ($interval->i > 0 || $interval->s > 0) {
                        $hours++; // Round up partial hours
                    }
                    $total_price = $booking['price_per_hour'] * $hours;

                    // Update booking
                    $sql = "UPDATE bookings 
                            SET check_in_date = ?, check_out_date = ?, adults = ?, children = ?, total_price = ? 
                            WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssiidi", $check_in, $check_out, $adults, $children, $total_price, $booking_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Booking updated successfully.";
                    } else {
                        $error = "Error updating booking: " . mysqli_error($conn);  
                    }
                }
            }
        } else {
            $error = "Booking not found or unauthorized.";
        }
    }
}

// Update past bookings to 'completed' status and free up rooms
$current_time = date('Y-m-d H:i:s'); // Uses timezone set in session.php
$update_completed = "UPDATE bookings b 
                    JOIN rooms r ON b.room_id = r.id
                    JOIN hotels h ON b.hotel_id = h.id
                    SET b.booking_status = 'completed', r.status = 'available'
                    WHERE b.check_out_date < ? 
                    AND b.booking_status NOT IN ('cancelled', 'completed')
                    AND b.user_id = ?";
$stmt = mysqli_prepare($conn, $update_completed);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "si", $current_time, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Get all bookings
$user_id = $_SESSION['user_id'];
$sql = "SELECT b.*, r.room_type, r.image_url, r.price_per_hour, h.name as hotel_name, r.status as room_status
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        JOIN hotels h ON b.hotel_id = h.id 
        WHERE b.user_id = ? 
        ORDER BY FIELD(b.booking_status, 'pending', 'confirmed', 'completed', 'cancelled'), b.created_at DESC";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .status-completed {
            background-color: #17a2b8;
            color: white;
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
        .btn-edit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-edit:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .booking-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .alert {
            margin-bottom: 20px;
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
                <h2 class="mb-4">My Bookings</h2>
                
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

                
                <?php if (mysqli_num_rows($bookings) > 0): ?>
                    <?php while($booking = mysqli_fetch_assoc($bookings)): ?>
                        <?php
                        // Calculate hours for display
                        $check_in_date = new DateTime($booking['check_in_date']);
                        $check_out_date = new DateTime($booking['check_out_date']);
                        $interval = $check_in_date->diff($check_out_date);
                        $hours = ($interval->days * 24) + $interval->h;
                        if ($interval->i > 0 || $interval->s > 0) {
                            $hours++; // Round up partial hours
                        }
                        ?>
                        <div class="booking-card">
                            <div class="row">
                                <div class="col-md-3">
                                    <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" alt="<?php echo htmlspecialchars($booking['room_type']); ?>" onerror="this.src='https://images.unsplash.com/photo-1618773928121-c32242e63f39';">
                                </div>
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($booking['room_type']))); ?></h4>
                                            <p class="mb-2"><i class="fas fa-hotel me-2"></i> <?php echo htmlspecialchars($booking['hotel_name']); ?></p>
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
                                                <p><strong>Check-in:</strong> <?php echo date('M d, Y h:i A', strtotime($booking['check_in_date'])); ?></p>
                                                <p><strong>Check-out:</strong> <?php echo date('M d, Y h:i A', strtotime($booking['check_out_date'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Guests:</strong> <?php echo $booking['adults']; ?> Adult<?php echo $booking['adults'] > 1 ? 's' : ''; ?><?php echo $booking['children'] > 0 ? ', ' . $booking['children'] . ' Child' . ($booking['children'] > 1 ? 'ren' : '') : ''; ?></p>
                                                <p><strong>Hours:</strong> <?php echo $hours; ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (in_array($booking['booking_status'], ['pending', 'confirmed'])): ?>
                                            <div class="mt-3 d-flex gap-2">
                                                <button type="button" class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editBookingModal<?php echo $booking['id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit Booking
                                                </button>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" name="cancel_booking" class="btn btn-cancel">
                                                        <i class="fas fa-times"></i> Cancel Booking
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Booking Modal -->
                        <div class="modal fade" id="editBookingModal<?php echo $booking['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header" style="background-color: #d4a017; color: white;">
                                        <div class="w-100">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h5 class="modal-title mb-1">
                                                        <i class="fas fa-edit me-2"></i>Edit Booking
                                                    </h5>
                                                    <p class="mb-0 small">
                                                        <i class="fas fa-hotel me-2"></i><?php echo htmlspecialchars($booking['hotel_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-door-open me-1"></i><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($booking['room_type']))); ?>
                                                    </p>
                                                </div>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="d-flex flex-wrap gap-3 mt-3 pt-2 border-top border-light border-opacity-25">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-light text-dark me-2">
                                                        <i class="fas fa-calendar-day me-1"></i>
                                                        <?php echo $check_in_date->format('M j, Y'); ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right mx-2"></i>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo $check_out_date->format('M j, Y'); ?>
                                                    </span>
                                                </div>
                                                <div class="badge bg-light text-dark">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $hours; ?> hours
                                                </div>
                                                <div class="badge bg-light text-dark">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php echo $booking['adults'] . ($booking['adults'] > 1 ? ' Adults' : ' Adult'); ?>
                                                    <?php if($booking['children'] > 0): ?>
                                                        + <?php echo $booking['children'] . ($booking['children'] > 1 ? ' Children' : ' Child'); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-body p-4">
                                        <form method="POST" action="" class="needs-validation" novalidate>
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            
                                            <h6 class="mb-3 text-muted">Update Booking Details</h6>
                                            
                                            <div class="mb-4">
                                                <div class="form-group mb-3">
                                                    <label for="check_in_<?php echo $booking['id']; ?>" class="form-label fw-medium">
                                                        <i class="fas fa-sign-in-alt me-2" style="color: #d4a017;"></i>Check-in Date & Time *
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i class="far fa-calendar-alt text-muted"></i></span>
                                                        <input type="datetime-local" class="form-control" id="check_in_<?php echo $booking['id']; ?>" 
                                                               name="check_in" value="<?php echo date('Y-m-d\TH:i', strtotime($booking['check_in_date'])); ?>" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group mb-3">
                                                    <label for="check_out_<?php echo $booking['id']; ?>" class="form-label fw-medium">
                                                        <i class="fas fa-sign-out-alt me-2" style="color: #d4a017;"></i>Check-out Date & Time *
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i class="far fa-calendar-alt text-muted"></i></span>
                                                        <input type="datetime-local" class="form-control" id="check_out_<?php echo $booking['id']; ?>" 
                                                               name="check_out" value="<?php echo date('Y-m-d\TH:i', strtotime($booking['check_out_date'])); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label for="adults_<?php echo $booking['id']; ?>" class="form-label fw-medium">
                                                        <i class="fas fa-user me-2" style="color: #d4a017;"></i>Adults *
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i class="fas fa-user-friends text-muted"></i></span>
                                                        <input type="number" class="form-control" id="adults_<?php echo $booking['id']; ?>" 
                                                               name="adults" min="1" value="<?php echo $booking['adults']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="children_<?php echo $booking['id']; ?>" class="form-label fw-medium">
                                                        <i class="fas fa-child me-2" style="color: #d4a017;"></i>Children
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text bg-light"><i class="fas fa-child text-muted"></i></span>
                                                        <input type="number" class="form-control" id="children_<?php echo $booking['id']; ?>" 
                                                               name="children" min="0" value="<?php echo $booking['children']; ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-4">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </button>
                                                <button type="submit" name="edit_booking" class="btn px-4" style="background-color: #d4a017; color: white; border: none;">
                                                    <i class="fas fa-save me-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </form>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript to validate check-out is after check-in
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const checkIn = new Date(this.querySelector('[name="check_in"]').value);
                const checkOut = new Date(this.querySelector('[name="check_out"]').value);
                if (checkOut <= checkIn) {
                    e.preventDefault();
                    alert('Check-out date and time must be after check-in date and time.');
                }
            });
        });
    </script>
</body>
</html>