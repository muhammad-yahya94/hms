<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get user data
$user = getUserData();

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$current_time = date('Y-m-d H:i:s'); // Current time: 2025-06-11 16:45:00 PKT

// Check for active booking
$active_booking = null;
$sql = "SELECT b.hotel_id, h.name AS hotel_name 
        FROM bookings b 
        JOIN hotels h ON b.hotel_id = h.id 
        WHERE b.user_id = ? 
        AND b.booking_status IN ('pending', 'confirmed') 
        AND b.check_in_date <= ? 
        AND b.check_out_date >= ? 
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $current_time, $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $active_booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
} else {
    $error = "Error checking booking status: " . mysqli_error($conn);
}

// Handle order cancellation (only if active booking exists)
if (isset($_POST['cancel_order']) && !empty($active_booking)) {
    $order_id = (int)$_POST['order_id'];
    
    // Verify order belongs to user
    $sql = "SELECT * FROM food_orders WHERE id = ? AND user_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql);
    if ($stmt_verify) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $order_id, $user_id);
        mysqli_stmt_execute($stmt_verify);
        $result = mysqli_stmt_get_result($stmt_verify);
        
        if ($order = mysqli_fetch_assoc($result)) {
            // Only allow cancellation of pending or confirmed orders
            if (in_array($order['order_status'], ['pending', 'confirmed'])) {
                $sql = "UPDATE food_orders SET order_status = 'cancelled' WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql);
                if ($stmt_update) {
                    mysqli_stmt_bind_param($stmt_update, "i", $order_id);
                    if (mysqli_stmt_execute($stmt_update)) {
                        $success = "Order cancelled successfully.";
                    } else {
                        $error = "Error cancelling order: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $error = "Error preparing update statement: " . mysqli_error($conn);
                }
            } else {
                $error = "Cannot cancel this order.";
            }
        } else {
            $error = "Order not found or unauthorized.";
        }
        mysqli_stmt_close($stmt_verify);
    } else {
        $error = "Error preparing verify statement: " . mysqli_error($conn);
    }
}

// Get all food orders for the user (only if active booking exists)
$food_orders = [];
if (!empty($active_booking)) {
    $sql = "SELECT fo.*, h.name AS hotel_name, hm.item_name, hm.image_url 
            FROM food_orders fo 
            JOIN hotels h ON fo.hotel_id = h.id 
            JOIN hotel_menu hm ON fo.menu_item_id = hm.id 
            WHERE fo.user_id = ? 
            ORDER BY fo.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $food_orders[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Error fetching orders: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Food Orders - Jhang Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
            width: 15px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
        }
        .order-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .order-card:hover {
            color: red;
            transform: translateY(-5px);
        }
        .order-card img {
            width: 100px;
            height: 100px;
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
        .status-delivered {
            background-color: #cce5ff;
            color: #004085;
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
        .order-details {
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
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-alt"></i> My Bookings
                    </a>
                    <a class="nav-link" href="food_menu.php">
                        <i class="fas fa-utensils"></i> Food Menu
                    </a>
                    <a class="nav-link active" href="food_orders.php">
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
                <h2 class="mb-4">My Food Orders</h2>
                
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

                <?php if (empty($active_booking)): ?>
                    <div class="alert alert-info">
                        You don't have an active room booking. Please <a href="../room-list.php" class="alert-link">book a room</a> to view or manage food orders.
                    </div>
                <?php elseif (empty($food_orders)): ?>
                    <div class="alert alert-info">
                        You haven't placed any food orders yet. 
                        <a href="food_menu.php" class="alert-link">Browse our menu</a> to place an order!
                    </div>
                <?php else: ?>
                    <?php foreach ($food_orders as $order): ?>
                        <div class="order-card">
                            <div class="row">
                                <div class="col-md-3">
                                    <?php if (!empty($order['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" alt="<?php echo htmlspecialchars($order['item_name']); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h4><?php echo htmlspecialchars($order['item_name']); ?></h4>
                                            <p><strong>Hotel:</strong> <?php echo htmlspecialchars($order['hotel_name']); ?></p>
                                            <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <h5>PKR <?php echo number_format($order['total_price'], 2); ?></h5>
                                            <small class="text-muted">Ordered on <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="order-details">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Quantity:</strong> <?php echo $order['quantity']; ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Price per Item:</strong> PKR <?php echo number_format($order['total_price'] / $order['quantity'], 2); ?></p>
                                            </div>
                                        </div>
                                        
                                        <?php if (in_array($order['order_status'], ['pending', 'confirmed'])): ?>
                                            <div class="mt-3">
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <button type="submit" name="cancel_order" class="btn btn-cancel">
                                                        <i class="fas fa-times"></i> Cancel Order
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.querySelectorAll('.alert-dismissible').forEach(alert => {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                });
            }, 5000);
        });
    </script>
</body>
</html>