<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];

// Handle food item deletion
if (isset($_POST['delete_food'])) {
    $item_id = (int)$_POST['item_id'];
    
    // Check if item belongs to a hotel owned by the admin and has any orders
    $sql = "SELECT COUNT(*) as count 
            FROM food_orders fo 
            JOIN hotel_menu hm ON fo.menu_item_id = hm.id 
            JOIN hotels h ON hm.hotel_id = h.id 
            WHERE hm.id = ? AND h.vendor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $item_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order_count = mysqli_fetch_assoc($result)['count'];
    
    if ($order_count > 0) {
        $error = "Cannot delete food item with existing orders.";
    } else {    
        // Delete the item if it belongs to the admin's hotel
        $sql = "DELETE hm 
                FROM hotel_menu hm 
                JOIN hotels h ON hm.hotel_id = h.id 
                WHERE hm.id = ? AND h.vendor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $item_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $success = "Food item deleted successfully.";
            } else {
                $error = "Food item not found or you don't have permission to delete it.";
            }
        } else {
            $error = "Error deleting food item: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle food item addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_food']) || isset($_POST['update_food']))) {
    if (isset($_POST['add_food'])) {
        // Handle multiple food items
        $items = $_POST['items'];
        $hotel_id = (int)$_POST['hotel_id'];
        
        // Verify the hotel belongs to the admin
        $sql = "SELECT COUNT(*) as count FROM hotels WHERE id = ? AND vendor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hotel_count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);

        if ($hotel_count == 0) {
            $error = "Invalid hotel selected or you don't have permission to add items.";
        } else {
            $sql = "INSERT INTO hotel_menu (hotel_id, item_name, price, is_available, image_url) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($items as $index => $item) {
                $item_name = mysqli_real_escape_string($conn, trim($item['item_name']));
                $price = (float)$item['price'];
                $is_available = isset($item['is_available']) ? 1 : 0;
                
                // Handle image upload
                $image_path = null;
                if (isset($_FILES['items']['name'][$index]['image']) && $_FILES['items']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/food/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_extension = strtolower(pathinfo($_FILES['items']['name'][$index]['image'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $max_file_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error = "Invalid file type for item '$item_name'. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
                        break;
                    } elseif ($_FILES['items']['size'][$index]['image'] > $max_file_size) {
                        $error = "File size exceeds 5MB limit for item '$item_name'.";
                        break;
                    } else {
                        $new_filename = uniqid('food_') . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['items']['tmp_name'][$index]['image'], $upload_path)) {
                            $image_path = 'Uploads/food/' . $new_filename;
                        } else {
                            $error = "Error uploading image for item '$item_name'.";
                            break;
                        }
                    }
                }
                
                if (empty($item_name) || $price <= 0) {
                    $error = "Item name and valid price are required for item " . ($index + 1) . ".";
                    break;
                }
                
                mysqli_stmt_bind_param($stmt, "isdis", $hotel_id, $item_name, $price, $is_available, $image_path);
                if (!mysqli_stmt_execute($stmt)) {
                    $error = "Error adding item '$item_name': " . mysqli_error($conn);
                    break;
                }
            }
            
            if (empty($error)) {
                $success = "Food items added successfully!";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Handle single item update
        $item_id = (int)$_POST['item_id'];
        $hotel_id = (int)$_POST['hotel_id'];
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $price = (float)$_POST['price'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        // Verify the hotel belongs to the admin
        $sql = "SELECT COUNT(*) as count FROM hotels WHERE id = ? AND vendor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $hotel_count = mysqli_fetch_assoc($result)['count'];
        mysqli_stmt_close($stmt);

        if ($hotel_count == 0) {
            $error = "Invalid hotel selected or you don't have permission to update items.";
        } else {
            // Handle image upload
            $image_path = null;
            if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../Uploads/food/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_extension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
                } elseif ($_FILES['item_image']['size'] > $max_file_size) {
                    $error = "File size exceeds 5MB limit.";
                } else {
                    $new_filename = uniqid('food_') . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                        $image_path = 'Uploads/food/' . $new_filename;
                    } else {
                        $error = "Error uploading image.";
                    }
                }
            } else {
                $sql_check_image = "SELECT hm.image_url 
                                   FROM hotel_menu hm 
                                   JOIN hotels h ON hm.hotel_id = h.id 
                                   WHERE hm.id = ? AND h.vendor_id = ?";
                $stmt_check_image = mysqli_prepare($conn, $sql_check_image);
                mysqli_stmt_bind_param($stmt_check_image, "ii", $item_id, $user_id);
                mysqli_stmt_execute($stmt_check_image);
                $result_check_image = mysqli_stmt_get_result($stmt_check_image);
                $row_check_image = mysqli_fetch_assoc($result_check_image);
                if ($row_check_image && !empty($row_check_image['image_url'])) {
                    $image_path = $row_check_image['image_url'];
                }
                mysqli_stmt_close($stmt_check_image);
            }

            if (empty($error)) {
                $sql = "UPDATE hotel_menu hm 
                        JOIN hotels h ON hm.hotel_id = h.id 
                        SET hm.hotel_id = ?, hm.item_name = ?, hm.price = ?, hm.is_available = ?";
                $params = [$hotel_id, $item_name, $price, $is_available];
                $types = "isdi";
                
                if ($image_path !== null) {
                    $sql .= ", hm.image_url = ?";
                    $params[] = $image_path;
                    $types .= "s";
                }
                
                $sql .= " WHERE hm.id = ? AND h.vendor_id = ?";
                $params[] = $item_id;
                $params[] = $user_id;
                $types .= "ii";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                
                if (mysqli_stmt_execute($stmt)) {
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        $success = "Food item updated successfully!";
                    } else {
                        $error = "Food item not found or you don't have permission to update it.";
                    }
                } else {
                    $error = "Error updating food item: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Get hotels for dropdown, filtered by vendor_id
$sql = "SELECT id, name FROM hotels WHERE vendor_id = ? ORDER BY name ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$hotels = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get all food items with hotel information, filtered by vendor_id
$sql = "SELECT hm.*, h.name as hotel_name 
        FROM hotel_menu hm 
        JOIN hotels h ON hm.hotel_id = h.id 
        WHERE h.vendor_id = ?
        ORDER BY h.name ASC, hm.item_name ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$food_items = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Management - Jhang Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .sidebar {
            background-color: #1a1a1a;
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .link:hover {
            color: rgba(255, 255, 255, 1);
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar .active {
            background-color: #d4a017;
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
        .food-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .food-card:hover {
            transform: translateY(-5px);
        }
        .food-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
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
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .status-available { background-color: #28a745; color: white; }
        .status-unavailable { background-color: #dc3545; color: white; }
        .alert-dismissible {
            position: relative;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        #imagePreview, .item-image-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            display: none;
        }
        #editImagePreview {
            width: 200px;
            margin-top: 20px;
            display: none;
        }
        .food-item-group {
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            position: relative;
        }
        .remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .remove-item:hover {
            color: #c82333;
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
                    <a class="nav-link active" href="food.php"><i class="fas fa-utensils"></i> Food Menu</a>
                    <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a>
                    <a class="nav-link" href="food_orders.php"><i class="fas fa-shopping-cart"></i> Food Orders</a>
                    <a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Food Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addFoodModal">
                        <i class="fas fa-plus"></i> Add New Food Item
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Food Items List -->
                <div class="row">
                    <?php if (mysqli_num_rows($food_items) > 0): ?>
                        <?php while($item = mysqli_fetch_assoc($food_items)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="food-card">
                                    <?php if (!empty($item['image_url'])): ?>  
                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="Food Image" class="food-image">
                                    <?php endif; ?>
                                    <div class="food-details">
                                        <h5><?php echo htmlspecialchars($item['hotel_name']); ?></h5>
                                        <p class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></p>
                                        <p class="price">PKR <?php echo number_format($item['price'], 2); ?></p>
                                        <p class="status">
                                            Status: <span class="status-badge status-<?php echo $item['is_available'] ? 'available' : 'unavailable'; ?>">
                                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                            </span>
                                        </p>
                                        <div class="food-actions">
                                            <button class="btn btn-sm btn-edit" data-bs-toggle="modal" data-bs-target="#editFoodModal"
                                                    data-item='<?php echo json_encode($item); ?>'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" action="" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this food item?')">
                                                <input type="hidden" name="delete_food" value="true">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No food items found. Click "Add New Food Item" to create one.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Food Item Modal -->
    <div class="modal fade" id="addFoodModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Food Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="addFoodForm">
                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel *</label>
                            <select class="form-select" id="hotel_id" name="hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php
                                mysqli_data_seek($hotels, 0);
                                while($hotel = mysqli_fetch_assoc($hotels)): ?>
                                    <option value="<?php echo $hotel['id']; ?>">
                                        <?php echo htmlspecialchars($hotel['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div id="food-items-container">
                            <div class="food-item-group">
                                <i class="fas fa-times remove-item" onclick="removeFoodItem(this)"></i>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="item_name_0" class="form-label">Item Name *</label>
                                        <input type="text" class="form-control" id="item_name_0" name="items[0][item_name]" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="price_0" class="form-label">Price (PKR) *</label>
                                        <input type="number" class="form-control" id="price_0" name="items[0][price]" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="image_0" class="form-label">Item Image</label>
                                    <input type="file" class="form-control item-image-input" id="image_0" name="items[0][image]" accept="image/*">
                                    <img class="item-image-preview" id="image_preview_0" src="#" alt="Image Preview">
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_available_0" name="items[0][is_available]" checked>
                                    <label class="form-check-label" for="is_available_0">Available</label>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary mb-3" onclick="addFoodItem()">Add Another Item</button>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_food" class="btn btn-custom">Add Items</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Food Item Modal -->
    <div class="modal fade" id="editFoodModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Food Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="editFoodForm">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        <div class="mb-3">
                            <label for="edit_hotel_id" class="form-label">Hotel *</label>
                            <select class="form-select" id="edit_hotel_id" name="hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php
                                mysqli_data_seek($hotels, 0);
                                while($hotel = mysqli_fetch_assoc($hotels)): ?>
                                    <option value="<?php echo $hotel['id']; ?>">
                                        <?php echo htmlspecialchars($hotel['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_item_name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Price (PKR) *</label>
                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_item_image" class="form-label">Item Image</label>
                            <input type="file" class="form-control" id="edit_item_image" name="item_image" accept="image/*">
                            <img id="editImagePreview" src="#" style="width:200px;margin-top:20px" alt="Image Preview">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_available" name="is_available">
                            <label class="form-check-label" for="edit_is_available">Available</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_food" class="btn btn-custom">Update Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let itemCount = 1;

    function addFoodItem() {
        const container = document.getElementById('food-items-container');
        const newItem = document.createElement('div');
        newItem.className = 'food-item-group';
        newItem.innerHTML = `
            <i class="fas fa-times remove-item" onclick="removeFoodItem(this)"></i>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="item_name_${itemCount}" class="form-label">Item Name *</label>
                    <input type="text" class="form-control" id="item_name_${itemCount}" name="items[${itemCount}][item_name]" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="price_${itemCount}" class="form-label">Price (PKR) *</label>
                    <input type="number" class="form-control" id="price_${itemCount}" name="items[${itemCount}][price]" step="0.01" min="0" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="image_${itemCount}" class="form-label">Item Image</label>
                <input type="file" class="form-control item-image-input" id="image_${itemCount}" name="items[${itemCount}][image]" accept="image/*">
                <img class="item-image-preview" id="image_preview_${itemCount}" src="#" alt="Image Preview">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="is_available_${itemCount}" name="items[${itemCount}][is_available]" checked>
                <label class="form-check-label" for="is_available_${itemCount}">Available</label>
            </div>
        `;
        container.appendChild(newItem);
        attachImagePreviewListener(itemCount);
        itemCount++;
    }

    function removeFoodItem(element) {
        if (document.querySelectorAll('.food-item-group').length > 1) {
            element.parentElement.remove();
        }
    }

    function attachImagePreviewListener(index) {
        const input = document.getElementById(`image_${index}`);
        const preview = document.getElementById(`image_preview_${index}`);
        if (input && preview) {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP');
                        e.target.value = '';
                        preview.style.display = 'none';
                        return;
                    }
                    if (file.size > maxSize) {
                        alert('File size exceeds 5MB limit.');
                        e.target.value = '';
                        preview.style.display = 'none';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.src = '#';
                    preview.style.display = 'none';
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 5000);

        // Initial image preview listener
        attachImagePreviewListener(0);

        // Image preview for edit food item
        const editItemImageInput = document.getElementById('edit_item_image');
        const editImagePreview = document.getElementById('editImagePreview');
        if (editItemImageInput && editImagePreview) {
            editItemImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP');
                        e.target.value = '';
                        editImagePreview.style.display = 'none';
                        return;
                    }
                    if (file.size > maxSize) {
                        alert('File size exceeds 5MB limit.');
                        e.target.value = '';
                        editImagePreview.style.display = 'none';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editImagePreview.src = e.target.result;
                        editImagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    editImagePreview.src = '#';
                    editImagePreview.style.display = 'none';
                }
            });
        }

        // Populate edit modal and handle image preview
        const editModal = document.getElementById('editFoodModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button || !button.dataset.item) return;
                const item = JSON.parse(button.dataset.item);
                
                // Populate form fields
                document.getElementById('edit_item_id').value = item.id || '';
                document.getElementById('edit_hotel_id').value = item.hotel_id || '';
                document.getElementById('edit_item_name').value = item.item_name || '';
                document.getElementById('edit_price').value = item.price || '';
                document.getElementById('edit_is_available').checked = item.is_available == 1;

                // Set image preview
                if (editImagePreview) {
                    if (item.image_url && item.image_url.trim() !== '') {
                        editImagePreview.src = '../' + item.image_url;
                        editImagePreview.style.display = 'block';
                    } else {
                        editImagePreview.src = '#';
                        editImagePreview.style.display = 'none';
                    }
                }
            });

            // Reset edit modal on close
            editModal.addEventListener('hidden.bs.modal', function() {
                if (editImagePreview) {
                    editImagePreview.src = '#';
                    editImagePreview.style.display = 'none';
                }
                if (editItemImageInput) {
                    editItemImageInput.value = '';
                }
                const form = document.getElementById('editFoodForm');
                if (form) form.reset();
            });
        }

        // Form validation for add food
        const addFoodForm = document.getElementById('addFoodForm');
        if (addFoodForm) {
            addFoodForm.addEventListener('submit', function(e) {
                const items = document.querySelectorAll('.food-item-group');
                items.forEach((item, index) => {
                    const price = document.getElementById(`price_${index}`);
                    if (price && price.value <= 0) {
                        e.preventDefault();
                        alert(`Price for item ${index + 1} must be greater than 0.`);
                    }
                });
            });
        }

        // Form validation for edit food
        const editFoodForm = document.getElementById('editFoodForm');
        if (editFoodForm) {
            editFoodForm.addEventListener('submit', function(e) {
                const price = document.getElementById('edit_price');
                if (price && price.value <= 0) {
                    e.preventDefault();
                    alert('Price must be greater than 0.');
                }
            });
        }
    });
    </script>
</body>
</html>