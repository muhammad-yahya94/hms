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

// Get admin's vendor_id and role
$admin_query = "SELECT vendor_id, role FROM users WHERE id = ?";
$admin_stmt = mysqli_prepare($conn, $admin_query);
mysqli_stmt_bind_param($admin_stmt, "i", $user_id);
mysqli_stmt_execute($admin_stmt);
$admin_result = mysqli_stmt_get_result($admin_stmt);
$admin = mysqli_fetch_assoc($admin_result);
$admin_vendor_id = $admin['vendor_id'];
$is_admin = ($admin['role'] === 'admin');

// Get filter parameters
$filter_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from_filter = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to_filter = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Handle order status update
if (isset($_POST['update_status']) && isset($_POST['status'])) {
    $order_ids = isset($_POST['order_id']) ? (array)$_POST['order_id'] : [];
    $status = $_POST['status'];
    
    // Log submitted data for debugging
    error_log("Submitted Order IDs: " . json_encode($order_ids));
    error_log("Submitted Status: " . $status);
    
    // Validate status
    if (!in_array($status, ['pending', 'confirmed', 'cancelled', 'delivered'])) {
        $error = "Invalid order status.";
    } elseif (empty($order_ids)) {
        $error = "No orders selected.";
    } else {
        // Convert all order IDs to integers for safety
        $order_ids = array_map('intval', $order_ids);
        $placeholders = rtrim(str_repeat('?,', count($order_ids)), ',');
        
        // Verify the orders belong to hotels the user can manage
        $sql = "SELECT fo.id FROM food_orders fo 
                JOIN hotels h ON fo.hotel_id = h.id 
                WHERE fo.id IN ($placeholders)";
        if (!$is_admin) {
            $sql .= " AND h.vendor_id = ?";
            $params = array_merge($order_ids, [$admin_vendor_id]);
            $types = str_repeat('i', count($order_ids)) . 'i';
        } else {
            $params = $order_ids;
            $types = str_repeat('i', count($order_ids));
        }
        
        $stmt_verify = mysqli_prepare($conn, $sql);
        
        if ($stmt_verify) {
            mysqli_stmt_bind_param($stmt_verify, $types, ...$params);
            mysqli_stmt_execute($stmt_verify);
            $result = mysqli_stmt_get_result($stmt_verify);
            
            $valid_order_ids = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $valid_order_ids[] = $row['id'];
            }
            mysqli_stmt_close($stmt_verify);
            
            error_log("Valid Order IDs: " . json_encode($valid_order_ids));
            
            if (empty($valid_order_ids)) {
                $error = "No valid orders found or you don't have permission to update them.";
            } else {
                // Update order status for all valid orders
                $placeholders = rtrim(str_repeat('?,', count($valid_order_ids)), ',');
                $sql = "UPDATE food_orders SET order_status = ? WHERE id IN ($placeholders)";
                $stmt_update = mysqli_prepare($conn, $sql);
                
                if ($stmt_update) {
                    $params = array_merge([$status], $valid_order_ids);
                    $types = 's' . str_repeat('i', count($valid_order_ids));
                    mysqli_stmt_bind_param($stmt_update, $types, ...$params);
                    
                    if (mysqli_stmt_execute($stmt_update)) {
                        $count = mysqli_stmt_affected_rows($stmt_update);
                        $success = "Successfully updated $count order(s) to " . ucfirst($status) . " status.";
                        header("Location: food_orders.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
                        exit();
                    } else {
                        $error = "Error updating order status: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $error = "Error preparing update statement: " . mysqli_error($conn);
                }
            }
        } else {
            $error = "Error preparing verify statement: " . mysqli_error($conn);
        }
    }
}

// Build the base SQL query for food orders
$sql = "SELECT fo.*, h.name AS hotel_name, hm.item_name, hm.image_url, 
               CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.phone, u.email
        FROM food_orders fo
        JOIN hotels h ON fo.hotel_id = h.id
        JOIN hotel_menu hm ON fo.menu_item_id = hm.id
        JOIN users u ON fo.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

// Add vendor filter if not admin
if (!$is_admin) {
    $sql .= " AND h.vendor_id = ?";
    $params[] = $admin_vendor_id;
    $types .= "i";
}

// Add user filter
if ($filter_user_id) {
    $sql .= " AND fo.user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
    
    $user_sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    if ($user_stmt) {
        mysqli_stmt_bind_param($user_stmt, "i", $filter_user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $username_filter = ($user_row = mysqli_fetch_assoc($user_result)) ? " for " . htmlspecialchars($user_row['full_name']) : '';
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
    error_log("Fetched Orders: " . mysqli_num_rows($food_orders));
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
        .table-responsive {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #6c757d;
            white-space: nowrap;
        }
        .table tbody tr {
            transition: background-color 0.2s;
        }
        .table tbody tr:hover {
            background-color: rgba(212, 160, 23, 0.05);
        }
        .table tbody tr.status-pending {
            background-color: rgba(255, 193, 7, 0.05);
        }
        .table tbody tr.status-confirmed {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .table tbody tr.status-delivered {
            background-color: rgba(40, 167, 69, 0.05);
        }
        .table tbody tr.status-cancelled {
            background-color: rgba(220, 53, 69, 0.05);
        }
        .status-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: capitalize;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background-color: #cce5ff;
            color: #1976d2;
        }
        .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .order-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .select-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .table-responsive {
                border: 0;
            }
            .table thead {
                display: none;
            }
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            .table tr {
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }
            .table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
                border-bottom: 1px solid #eee;
            }
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: 45%;
                padding-right: 15px;
                text-align: left;
                font-weight: 600;
                color: #6c757d;
                text-transform: uppercase;
                font-size: 0.75rem;
            }
            .action-buttons {
                display: flex;
                justify-content: flex-end;
                flex-wrap: wrap;
                gap: 5px;
            }
        }
        .btn-custom {
            background-color: #d4a017;
            color: white;
            border: none;
        }
        .btn-custom:hover {
            background-color: #b38b12;
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
                    <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Employees</a>
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

                <!-- Bulk Actions and Table -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body py-2">
                                <form method="POST" action="" id="bulkActionForm" class="row align-items-center">
                                    <div class="col-md-4 mb-2 mb-md-0">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllOrders">
                                            <label class="form-check-label fw-bold" for="selectAllOrders">
                                                Select All
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-5 mb-2 mb-md-0">
                                        <select name="status" id="bulkActionSelect" class="form-select form-select-sm" required>
                                            <option value="">Choose action...</option>
                                            <option value="confirmed">Mark as Confirmed</option>
                                            <option value="delivered">Mark as Delivered</option>
                                            <option value="cancelled">Mark as Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-custom w-100" id="bulkActionSubmit">
                                            <i class="fas fa-check-double me-1"></i> Apply
                                        </button>
                                    </div>

                                    <!-- Food Orders Table -->
                                    <div class="table-responsive mt-3">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th width="40"></th>
                                                    <th>Order</th>
                                                    <th>Item</th>
                                                    <th>Customer</th>
                                                    <th>Quantity</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Ordered At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                if ($food_orders && mysqli_num_rows($food_orders) > 0) {
                                                    mysqli_data_seek($food_orders, 0);
                                                }
                                                if ($food_orders && mysqli_num_rows($food_orders) > 0): 
                                                    while($order = mysqli_fetch_assoc($food_orders)): 
                                                        $status_class = strtolower($order['order_status']);
                                                ?>
                                                <tr class="status-<?php echo $status_class; ?>">
                                                    <td data-label="Select">
                                                        <?php if ($order['order_status'] !== 'delivered'): ?>
                                                            <input type="checkbox" name="order_id[]" value="<?php echo $order['id']; ?>" class="order-checkbox select-checkbox" id="order_<?php echo $order['id']; ?>">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Order">#<?php echo $order['id']; ?></td>
                                                    <td data-label="Item">
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($order['image_url'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($order['image_url']); ?>" alt="<?php echo htmlspecialchars($order['item_name']); ?>" class="order-image me-2">
                                                            <?php else: ?>
                                                                <img src="https://images.unsplash.com/photo-1504674900247-087703934569" alt="Food Placeholder" class="order-image me-2">
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-medium"><?php echo htmlspecialchars($order['item_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($order['hotel_name']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td data-label="Customer">
                                                        <div class="fw-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                    </td>
                                                    <td data-label="Quantity"><?php echo $order['quantity']; ?></td>
                                                    <td data-label="Total">PKR <?php echo number_format($order['total_price'], 2); ?></td>
                                                    <td data-label="Status">
                                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($order['order_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td data-label="Ordered At">
                                                        <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                                        <small class="d-block text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                                    </td>
                                                    <td data-label="Actions">
                                                        <div class="action-buttons">
                                                            <?php if ($order['order_status'] === 'pending'): ?>
                                                                <button type="button" class="btn btn-success btn-sm" 
                                                                        onclick="submitSingleOrder(<?php echo $order['id']; ?>, 'confirmed', 'confirm this order')">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-info btn-sm" 
                                                                        onclick="submitSingleOrder(<?php echo $order['id']; ?>, 'delivered', 'mark this order as delivered')">
                                                                    <i class="fas fa-truck"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                        onclick="submitSingleOrder(<?php echo $order['id']; ?>, 'cancelled', 'cancel this order')">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            <?php elseif ($order['order_status'] === 'confirmed'): ?>
                                                                <button type="button" class="btn btn-primary btn-sm" 
                                                                        onclick="submitSingleOrder(<?php echo $order['id']; ?>, 'delivered', 'mark this order as delivered')">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                        onclick="submitSingleOrder(<?php echo $order['id']; ?>, 'cancelled', 'cancel this order')">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted small">No actions</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                                <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="alert alert-info mb-0">
                                                            No food orders found. Apply different filters or wait for new orders.
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function submitSingleOrder(orderId, status, actionText) {
            if (confirm(`Are you sure you want to ${actionText}?`)) {
                const form = document.getElementById('bulkActionForm');
                const orderIdInput = document.createElement('input');
                orderIdInput.type = 'hidden';
                orderIdInput.name = 'order_id[]';
                orderIdInput.value = orderId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                
                const updateInput = document.createElement('input');
                updateInput.type = 'hidden';
                updateInput.name = 'update_status';
                updateInput.value = '1';
                
                // Clear existing order_id[] inputs to avoid duplicates
                const existingInputs = form.querySelectorAll('input[name="order_id[]"]');
                existingInputs.forEach(input => input.remove());
                
                form.appendChild(orderIdInput);
                form.appendChild(statusInput);
                form.appendChild(updateInput);
                form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllOrders');
            const orderCheckboxes = document.querySelectorAll('.order-checkbox');
            const bulkActionSelect = document.getElementById('bulkActionSelect');
            const bulkActionSubmit = document.getElementById('bulkActionSubmit');
            const bulkActionForm = document.getElementById('bulkActionForm');

            // Handle Select All checkbox
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    orderCheckboxes.forEach(checkbox => {
                        if (!checkbox.disabled) {
                            checkbox.checked = this.checked;
                        }
                    });
                    updateBulkActionButton();
                });
            }

            // Update Select All when individual checkboxes change
            orderCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked && selectAll.checked) {
                        selectAll.checked = false;
                    } else if (Array.from(orderCheckboxes).every(cb => cb.checked || cb.disabled)) {
                        selectAll.checked = true;
                    }
                    updateBulkActionButton();
                });
            });

            // Update bulk action button state
            function updateBulkActionButton() {
                if (bulkActionSubmit) {
                    const hasSelected = Array.from(orderCheckboxes).some(cb => cb.checked);
                    bulkActionSubmit.disabled = !hasSelected || !bulkActionSelect.value;
                }
            }

            // Handle bulk action form submission
            if (bulkActionForm) {
                bulkActionForm.addEventListener('submit', function(e) {
                    const selectedOrders = Array.from(orderCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    const status = bulkActionSelect.value;

                    if (selectedOrders.length === 0) {
                        alert('Please select at least one order to update.');
                        e.preventDefault();
                        return;
                    }
                    if (!status) {
                        alert('Please select an action to perform.');
                        e.preventDefault();
                        return;
                    }
                    if (!confirm(`Are you sure you want to update ${selectedOrders.length} selected order(s) to "${status}"?`)) {
                        e.preventDefault();
                    }
                });
            }

            // Update button state when bulk action changes
            if (bulkActionSelect) {
                bulkActionSelect.addEventListener('change', updateBulkActionButton);
            }

            // Initialize button state
            updateBulkActionButton();

            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    if (bootstrap.Alert.getInstance(alert)) {
                        bootstrap.Alert.getOrCreateInstance(alert).close();
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>