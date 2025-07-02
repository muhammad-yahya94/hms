<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Ensure the user is an admin
requireAdmin();

$error = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];

// Handle hotel addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_hotel'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    
    // Validate inputs
    if (empty($name) || empty($address) || empty($city)) {
        $error = "Required fields (Name, Address, City) cannot be empty.";
    } elseif ($phone && !preg_match('/^[0-9+\-\(\) ]{10,20}$/', $phone)) {
        $error = "Invalid phone number format.";
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error = "Invalid website URL.";
    } else {
        // Handle image upload
        $image_path = null;
        $image_uploaded = false;
        if (isset($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../Uploads/hotels/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error = "Failed to create upload directory.";
                }
            }
            if (!$error) {
                $file_extension = strtolower(pathinfo($_FILES['hotel_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
                } elseif ($_FILES['hotel_image']['size'] > 5 * 1024 * 1024) {
                    $error = "Image file size exceeds 5MB.";
                } else {
                    $new_filename = uniqid('hotel_') . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['hotel_image']['tmp_name'], $upload_path)) {
                        $image_path = 'Uploads/hotels/' . $new_filename;
                        $image_uploaded = true;
                    } else {
                        $error = "Error uploading image.";
                    }
                }
            }
        }
        
        if (!$error) {
            // Insert hotel into database
            $sql = "INSERT INTO hotels (name, description, address, city, phone, email, website, image_url, vendor_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $error = "Database error: Unable to prepare insert query.";
            } else {
                mysqli_stmt_bind_param($stmt, "ssssssssi", $name, $description, $address, $city, 
                                      $phone, $email, $website, $image_path, $user_id);
                if (!mysqli_stmt_execute($stmt)) {
                    $error = "Error adding hotel: " . mysqli_error($conn);
                    if ($image_uploaded && $image_path && file_exists('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                } elseif (mysqli_stmt_affected_rows($stmt) !== 1) {
                    $error = "Error: Hotel not inserted.";
                    if ($image_uploaded && $image_path && file_exists('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                } else {
                    $success = "Hotel added successfully.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Handle hotel update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_hotel'])) {
    $hotel_id = (int)$_POST['hotel_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    
    // Validate inputs
    if (empty($name) || empty($address) || empty($city)) {
        $error = "Required fields (Name, Address, City) cannot be empty.";
    } elseif ($phone && !preg_match('/^[0-9+\-\(\) ]{10,20}$/', $phone)) {
        $error = "Invalid phone number format.";
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
        $error = "Invalid website URL.";
    } else {
        // Handle image upload if a new image is provided
        $image_path = $_POST['existing_image'];
        $image_uploaded = false;
        
        if (isset($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../Uploads/hotels/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error = "Failed to create upload directory.";
                }
            }
            if (!$error) {
                $file_extension = strtolower(pathinfo($_FILES['hotel_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
                } elseif ($_FILES['hotel_image']['size'] > 5 * 1024 * 1024) {
                    $error = "Image file size exceeds 5MB.";
                } else {
                    $new_filename = uniqid('hotel_') . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['hotel_image']['tmp_name'], $upload_path)) {
                        // Delete old image if it exists
                        if ($image_path && file_exists('../' . $image_path)) {
                            unlink('../' . $image_path);
                        }
                        $image_path = 'Uploads/hotels/' . $new_filename;
                        $image_uploaded = true;
                    } else {
                        $error = "Error uploading image.";
                    }
                }
            }
        }
        
        if (!$error) {
            // Update hotel in database
            $sql = "UPDATE hotels SET name=?, description=?, address=?, city=?, phone=?, email=?, website=?, image_url=? 
                    WHERE id=? AND vendor_id=?";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $error = "Database error: Unable to prepare update query.";
            } else {
                mysqli_stmt_bind_param($stmt, "ssssssssii", $name, $description, $address, $city, 
                                      $phone, $email, $website, $image_path, $hotel_id, $user_id);
                if (!mysqli_stmt_execute($stmt)) {
                    $error = "Error updating hotel: " . mysqli_error($conn);
                    if ($image_uploaded && $image_path && file_exists('../' . $image_path)) {
                        unlink('../' . $image_path);
                    }
                } else {
                    $success = "Hotel updated successfully.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Handle hotel deletion
if (isset($_GET['delete'])) {
    $hotel_id = (int)$_GET['delete'];
    
    // First get the image path to delete the file
    $sql = "SELECT image_url FROM hotels WHERE id=? AND vendor_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $image_path);
    mysqli_stmt_fetch($stmt);   
    mysqli_stmt_close($stmt);
    
    // Delete the hotel
    $sql = "DELETE FROM hotels WHERE id=? AND vendor_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
    if (mysqli_stmt_execute($stmt)) {
        // Delete the associated image file if it exists
        if ($image_path && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        $success = "Hotel deleted successfully.";
    } else {
        $error = "Error deleting hotel: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Fetch hotels for the current admin (vendor)
$sql = "SELECT * FROM hotels WHERE vendor_id = ? ORDER BY name ASC";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    $error = "Database error: Unable to prepare hotel retrieval query.";
} else {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $hotels = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}

// Fetch hotel details for editing
$edit_hotel = null;
if (isset($_GET['edit'])) {
    $hotel_id = (int)$_GET['edit'];
    $sql = "SELECT * FROM hotels WHERE id=? AND vendor_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_hotel = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Management - HMS</title>
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
        .hotel-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .hotel-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }
        .hotel-image-container {
            position: relative;
            width: 100%;
            padding-top: 60%; /* 5:3 aspect ratio */
            overflow: hidden;
        }
        .hotel-card img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .hotel-card:hover img {
            transform: scale(1.05);
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
        .btn-danger-custom {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger-custom:hover {
            background-color: #bb2d3b;
            color: white;
        }
        .alert {
            margin-bottom: 20px;
        }
        .hotel-card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .hotel-card h4 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .hotel-location {
            display: flex;
            align-items: center;
            color: #7f8c8d;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        .hotel-location i {
            margin-right: 8px;
            color: #d4a017;
        }
        .hotel-details {
            margin: 15px 0;
            flex: 1;
        }
        .hotel-details p {
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #555;
            line-height: 1.5;
        }
        .hotel-details p:last-child {
            margin-bottom: 0;
        }
        .hotel-details-label {
            font-weight: 600;
            color: #2c3e50;
            display: inline-block;
            min-width: 80px;
        }
        .hotel-description {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 10px 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .action-buttons {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-sm i {
            margin-right: 5px;
        }
        .modal-title {
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
                    <a class="nav-link active" href="hotels.php">
                        <i class="fas fa-hotel"></i> Hotels
                    </a>
                    <a class="nav-link" href="rooms.php">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a class="nav-link" href="food.php"><i class="fas fa-utensils"></i> Food Menu</a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Employee
                    </a>
                    <a class="nav-link" href="reservations.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Hotel Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#hotelModal">
                        <i class="fas fa-plus"></i> Add New Hotel
                    </button>
                </div>

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


                <!-- Hotels List -->
                <div class="row">
                    <?php if (mysqli_num_rows($hotels) > 0): ?>
                        <?php while($hotel = mysqli_fetch_assoc($hotels)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="hotel-card">
                                    <div class="hotel-image-container">
                                        <img src="../<?php echo htmlspecialchars($hotel['image_url'] ?? 'images/placeholder.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($hotel['name']); ?>"
                                             onerror="this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945';">
                                    </div>
                                    <div class="hotel-card-body">
                                        <h4><?php echo htmlspecialchars($hotel['name']); ?></h4>
                                        <div class="hotel-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($hotel['city']); ?></span>
                                        </div>
                                        
                                        <div class="hotel-details">
                                            <?php if ($hotel['address']): ?>
                                                <p><span class="hotel-details-label">Address:</span> <?php echo htmlspecialchars($hotel['address']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($hotel['phone']): ?>
                                                <p><span class="hotel-details-label">Phone:</span> <?php echo htmlspecialchars($hotel['phone']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($hotel['email']): ?>
                                                <p><span class="hotel-details-label">Email:</span> <a href="mailto:<?php echo htmlspecialchars($hotel['email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($hotel['email']); ?></a></p>
                                            <?php endif; ?>
                                            <?php if ($hotel['website']): ?>
                                                <p><span class="hotel-details-label">Website:</span> <a href="<?php echo (strpos($hotel['website'], 'http') === 0 ? '' : 'http://') . htmlspecialchars($hotel['website']); ?>" target="_blank" class="text-decoration-none">Visit Website</a></p>
                                            <?php endif; ?>
                                            <?php if (!empty($hotel['description'])): ?>
                                                <div class="hotel-description" title="<?php echo htmlspecialchars($hotel['description']); ?>">
                                                    <?php echo htmlspecialchars(substr($hotel['description'], 0, 150)); ?><?php echo strlen($hotel['description']) > 150 ? '...' : ''; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="action-buttons">
                                            <a href="?edit=<?php echo $hotel['id']; ?>" class="btn btn-sm btn-custom">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-hotel"></i> Hotel
                                            </span>
                                            <!-- <a href="?delete=<?php echo $hotel['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this hotel?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                No hotels found. Click "Add New Hotel" to create one.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Hotel Modal (Add/Edit) -->
    <div class="modal fade" id="hotelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo isset($edit_hotel) ? 'Edit Hotel' : 'Add Hotel'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="hotelForm" enctype="multipart/form-data">
                        <?php if (isset($edit_hotel)): ?>
                            <input type="hidden" name="hotel_id" value="<?php echo $edit_hotel['id']; ?>">
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_hotel['image_url'] ?? ''); ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Hotel Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hotel_image" class="form-label">Hotel Image</label>
                                <input type="file" class="form-control" id="hotel_image" name="hotel_image" accept="image/*">
                                <?php if (isset($edit_hotel) && $edit_hotel['image_url']): ?>
                                    <small class="text-muted">Current image: <?php echo basename($edit_hotel['image_url']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['description']) : ''; 
                            ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address *</label>
                            <input type="text" class="form-control" id="address" name="address" 
                                   value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['address']) : ''; ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['city']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['phone']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" 
                                   value="<?php echo isset($edit_hotel) ? htmlspecialchars($edit_hotel['website']) : ''; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="<?php echo isset($edit_hotel) ? 'update_hotel' : 'save_hotel'; ?>" class="btn btn-custom">
                                <?php echo isset($edit_hotel) ? 'Update Hotel' : 'Save Hotel'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Automatically show modal if editing
        <?php if (isset($edit_hotel)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var hotelModal = new bootstrap.Modal(document.getElementById('hotelModal'));
                hotelModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>