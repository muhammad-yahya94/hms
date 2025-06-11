<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$error = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];

// Get filter parameters
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from_filter = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to_filter = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Handle order status update
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    // Validate status
    if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'delivered'])) {
        $error = "Invalid order status.";
    } else {
        // Verify the order belongs to the admin's hotel
        $sql = "SELECT fo.id FROM food_orders fo 
                JOIN hotels h ON fo.hotel_id = h.id 
                WHERE fo.id = ? AND h.vendor_id = ?";
        $stmt_verify = mysqli_prepare($conn, $sql);
        if ($stmt_verify) {
            mysqli_stmt_bind_param($stmt_verify, "ii", $order_id, $user_id);
            mysqli_stmt_execute($stmt_verify);
            $result = mysqli_stmt_get_result($stmt_verify);
            
            if (mysqli_num_rows($result) == 0) {
                $error = "Order not found or you do not have permission to update it.";
            } else {
                // Update order status
                $sql = "UPDATE food_orders SET order_status = ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $sql);
                if ($stmt_update) {
                    mysqli_stmt_bind_param($stmt_update, "si", $status, $order_id);
                    if (mysqli_stmt_execute($stmt_update)) {
                        $success = "Order status updated successfully.";
                    } else {
                        $error = "Error updating order status: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $error = "Error preparing update statement: " . mysqli_error($conn);
                }
            }
            mysqli_stmt_close($stmt_verify);
        } else {
            $error = "Error preparing verify statement: " . mysqli_error($conn);
        }
    }
}

// Build the SQL query for food orders
$sql = "SELECT fo.*, h.name AS hotel_name, hm.item_name AS menu_item_name, hm.image_url, u.username, u.email
        FROM food_orders fo
        JOIN hotels h ON fo.hotel_id = h.id
        JOIN hotel_menu hm ON fo.menu_item_id = hm.id
        JOIN users u ON fo.user_id = u.id
        WHERE h.vendor_id = ?";

$params = [$user_id];
$types = "i";

if ($filter_user_id !== null) {
    $sql .= " AND fo.user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
    // Fetch username for the title if filtering by user
    $user_sql = "SELECT username FROM users WHERE id = ? LIMIT 1";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    if ($user_stmt) {
        mysqli_stmt_bind_param($user_stmt, "i", $filter_user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user_row = mysqli_fetch_assoc($user_result);
        $username_filter = $user_row ? " for " . htmlspecialchars($user_row['username']) : '';
        mysqli_stmt_close($user_stmt);
    } else {
        $error = "Error fetching user: " . mysqli_error($conn);
        $username_filter = '';
    }
} else {
    $username_filter = '';
}

// Add status filter
if (!empty($status_filter) && in_array($status_filter, ['pending', 'confirmed', 'cancelled', 'delivered'])) {
    $sql .= " AND fo.order_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date range filter
if (!empty($date_from_filter)) {
    $sql .= " AND DATE(fo.created_at) >= ?";
    $params[] = $date_from_filter;
    $types .= "s";
}

if (!empty($date_to_filter)) {
    $sql .= " AND DATE(fo.created_at) <= ?";
    $params[] = $date_to_filter;
    $types .= "s";
}

$sql .= " ORDER BY fo.order_status = 'pending' DESC, fo.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $food_orders = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $error = "Error preparing query: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Order Management - Jhang Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            width: 20px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
        }
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .order-card:hover {
            transform: translateY(-5px);
        }
        .order-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
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
        .status-delivered {
            background-color: #17a2b8;
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
        .alert-dismissible {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="hotels.php"><i class="fas fa-hotel"></i> Hotels</a>
                    <a class="nav-link" href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
                    <a class="nav-link" href="food.php"><i class="fas fa-utensils"></i> Food Menu</a>
                    <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a>
                    <a class="nav-link active" href="food_orders.php"><i class="fas fa-shopping-cart"></i> Food Orders</a>
                    <a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Food Order Management<?php echo $username_filter; ?></h2>

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
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo htmlspecialchars($date_from_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to"
                                   value="<?php echo htmlspecialchars($date_to_filter); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-custom me-2">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="food_orders.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Food Orders List -->
                <div class="row">
                    <?php if ($food_orders && mysqli_num_rows($food_orders) > 0): ?>
                        <?php while($order = mysqli_fetch_assoc($food_orders)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="order-card">
                                    <?php if (!empty($order['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" alt="Food Image">
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="mb-0">Order #<?php echo $order['id']; ?></h5>
                                        <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </div>
                                    <p class="mb-2">
                                        <i class="fas fa-hotel"></i> 
                                        <?php echo htmlspecialchars($order['hotel_name']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-utensils"></i> 
                                        <?php echo htmlspecialchars($order['menu_item_name']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-shopping-cart"></i> 
                                        Quantity: <?php echo $order['quantity']; ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($order['username']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo htmlspecialchars($order['email']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar"></i> 
                                        Ordered: <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                                    </p>
                                    <p class="mb-3">
                                        <i class="fas fa-rupee-sign"></i> 
                                        Total: PKR <?php echo number_format($order['total_price'], 2); ?>
                                    </p>
                                    <?php if ($order['order_status'] === 'pending'): ?>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" name="update_status" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Confirm
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" name="update_status" class="return confirm('Are you sure you want to mark this order as delivered?');" class="btn btn-info btn-sm">
                                                    <i class="fas fa-truck"></i> Delivered
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this order?');">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($order['order_status'] === 'confirmed'): ?>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" name="update_status" class="btn btn-info btn-sm" onclick="return confirm('Are you sure you want to mark this order as delivered?');">
                                                    <i class="fas fa-truck"></i> Delivered
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" name="update_status" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this order?');">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No food orders found. Apply different filters or wait for new orders.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                });
            }, 5000);
        });
    </script>
</body>
</html>