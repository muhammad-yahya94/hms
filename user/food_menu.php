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
$current_time = date('Y-m-d H:i:s'); // Current time: 2025-06-11 17:00:00 PKT

// Check for any booking where checkout date is in the future
$active_booking = null;
$sql = "SELECT b.hotel_id, h.name AS hotel_name, b.check_out_date 
        FROM bookings b 
        JOIN hotels h ON b.hotel_id = h.id 
        WHERE b.user_id = ? 
        AND b.check_out_date >= ? 
        ORDER BY b.check_out_date DESC 
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $user_id, $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $active_booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // If no active booking found, check for any past bookings
    if (!$active_booking) {
        $sql = "SELECT b.hotel_id, h.name AS hotel_name, b.check_out_date 
                FROM bookings b 
                JOIN hotels h ON b.hotel_id = h.id 
                WHERE b.user_id = ? 
                ORDER BY b.check_out_date DESC 
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $past_booking = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            
            if ($past_booking) {
                $error = "Your last booking ended on " . date('M d, Y', strtotime($past_booking['check_out_date'])) . ". Please book a room to order food.";
            } else {
                $error = "You don't have any bookings. Please book a room to order food.";
            }
        }
    }
} else {
    $error = "Error checking booking status: " . mysqli_error($conn);
}

// Handle order placement (only if active booking exists)
if (isset($_POST['place_order']) && !empty($active_booking)) {
    $items = $_POST['items'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $hotel_id = $active_booking['hotel_id'];
    $order_placed = false;

    // Validate inputs
    if (empty($items)) {
        $error = "Please select at least one item to order.";
    } else {
        foreach ($items as $index => $menu_item_id) {
            $quantity = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
            $menu_item_id = (int)$menu_item_id;

            if ($quantity < 1) {
                continue; // Skip items with zero quantity
            }

            // Verify menu item exists and is available
            $sql = "SELECT price FROM hotel_menu 
                    WHERE id = ? AND hotel_id = ? AND is_available = 1";
            $stmt_verify = mysqli_prepare($conn, $sql);
            if ($stmt_verify) {
                mysqli_stmt_bind_param($stmt_verify, "ii", $menu_item_id, $hotel_id);
                mysqli_stmt_execute($stmt_verify);
                $result = mysqli_stmt_get_result($stmt_verify);
                
                if ($menu_item = mysqli_fetch_assoc($result)) {
                    $total_price = $menu_item['price'] * $quantity;

                    // Insert order
                    $sql = "INSERT INTO food_orders (user_id, hotel_id, menu_item_id, quantity, total_price, order_status) 
                            VALUES (?, ?, ?, ?, ?, 'pending')";
                    $stmt_insert = mysqli_prepare($conn, $sql);
                    if ($stmt_insert) {
                        mysqli_stmt_bind_param($stmt_insert, "iiidd", $user_id, $hotel_id, $menu_item_id, $quantity, $total_price);
                        if (mysqli_stmt_execute($stmt_insert)) {
                            $order_placed = true;
                        } else {
                            $error = "Error placing order: " . mysqli_error($conn);
                            mysqli_stmt_close($stmt_insert);
                            break;
                        }
                        mysqli_stmt_close($stmt_insert);
                    } else {
                        $error = "Error preparing insert statement: " . mysqli_error($conn);
                        mysqli_stmt_close($stmt_verify);
                        break;
                    }
                } else {
                    $error = "Invalid or unavailable menu item selected.";
                    mysqli_stmt_close($stmt_verify);
                    break;
                }
                mysqli_stmt_close($stmt_verify);
            } else {
                $error = "Error preparing verify statement: " . mysqli_error($conn);
                break;
            }
        }

        if ($order_placed && empty($error)) {
            $success = "Order placed successfully. View your orders <a href='food_orders.php'>here</a>.";
        }
    }
}

// Fetch menu items only if active booking exists
$menu_items = [];
if (!empty($active_booking)) {
    $hotel_id = $active_booking['hotel_id'];
    $sql = "SELECT id, item_name, price, image_url 
            FROM hotel_menu 
            WHERE hotel_id = ? AND is_available = 1 
            ORDER BY item_name ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $hotel_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $menu_items[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Error fetching menu: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Menu - Jhang Hotels</title>
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
            width: 20px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
        }
        .menu-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .menu-card:hover {
            transform: translateY(-5px);
        }
        .menu-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }
        .btn-order {
            background-color: #d4a017;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-order:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .quantity-input {
            width: 60px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .disabled-form {
            pointer-events: none;
            opacity: 0.6;
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
                    <a class="nav-link active" href="food_menu.php">
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
                <h2 class="mb-4">Food Menu<?php echo !empty($active_booking) ? ' - ' . htmlspecialchars($active_booking['hotel_name']) : ''; ?></h2>

                <?php if ($error): ?>
                    <div class="alert alert-warning">
                        <?php echo $error; ?>
                        <?php if (strpos($error, 'booking has ended') !== false || strpos($error, 'You must have an active room booking') !== false): ?>
                            <div class="mt-2">
                                <a href="../room-list.php" class="btn btn-sm btn-primary">Book a Room</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (empty($active_booking)): ?>
                    <div class="alert alert-info">
                        You don't have an active room booking with 'confirmed' status. Please <a href="../room-list.php" class="alert-link">book a room</a> to order food.
                        <div class="mt-2">
                            <a href="../room-list.php" class="btn btn-sm btn-primary">Book a Room</a>
                        </div>
                    </div>
                <?php elseif (empty($menu_items)): ?>
                    <div class="alert alert-info">
                        No food items are currently available at this hotel. Please check back later.
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="orderForm" class="<?php echo empty($active_booking) ? 'disabled-form' : ''; ?>">
                        <div class="row">
                            <?php foreach ($menu_items as $index => $item): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="menu-card">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                        <?php endif; ?>
                                        <h5><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                        <p><strong>Price:</strong> PKR <?php echo number_format($item['price'], 2); ?></p>
                                        <div class="d-flex align-items-center gap-2">
                                            <label for="quantity_<?php echo $item['id']; ?>" class="form-label mb-0">Quantity:</label>
                                            <input type="number" class="form-control quantity-input" id="quantity_<?php echo $item['id']; ?>" 
                                                   name="quantities[]" min="0" value="0" onchange="updateTotal()">
                                            <input type="hidden" name="items[]" value="<?php echo $item['id']; ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4">
                            <h5>Total: PKR <span id="totalPrice">0.00</span></h5>
                            <button type="submit" name="place_order" class="btn btn-order" <?php echo empty($active_booking) ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i> Place Order
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTotal() {
            let total = 0;
            const quantities = document.querySelectorAll('.quantity-input');
            <?php foreach ($menu_items as $index => $item): ?>
                if (quantities[<?php echo $index; ?>]) {
                    const qty = parseInt(quantities[<?php echo $index; ?>].value) || 0;
                    total += qty * <?php echo $item['price']; ?>;
                }
            <?php endforeach; ?>
            document.getElementById('totalPrice').textContent = total.toFixed(2);
        }

        document.getElementById('orderForm')?.addEventListener('submit', function(e) {
            <?php if (empty($active_booking)): ?>
                e.preventDefault();
                alert('You must have an active room booking with "booked" status to place a food order.');
                return;
            <?php endif; ?>
            const quantities = document.querySelectorAll('.quantity-input');
            let hasQuantity = false;
            quantities.forEach(qty => {
                if (parseInt(qty.value) > 0) {
                    hasQuantity = true;
                }
            });
            if (!hasQuantity) {
                e.preventDefault();
                alert('Please select at least one item with a quantity greater than 0.');
            }
        });

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