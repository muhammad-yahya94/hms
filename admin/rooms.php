<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];

// Handle room deletion
if (isset($_POST['delete_room'])) {
    $room_id = (int)$_POST['room_id'];
    
    // Check if room belongs to a hotel owned by the admin and has any bookings
    $sql = "SELECT COUNT(*) as count 
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.id 
            JOIN hotels h ON r.hotel_id = h.id 
            WHERE r.id = ? AND h.vendor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $room_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking_count = mysqli_fetch_assoc($result)['count'];
    
    if ($booking_count > 0) {
        $error = "Cannot delete room with existing bookings.";
    } else {    
        // Delete the room if it belongs to the admin's hotel
        $sql = "DELETE r 
                FROM rooms r 
                JOIN hotels h ON r.hotel_id = h.id 
                WHERE r.id = ? AND h.vendor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $room_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $success = "Room deleted successfully.";
            } else {
                $error = "Room not found or you don't have permission to delete it.";
            }
        } else {
            $error = "Error deleting room: " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle room addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_room']) || isset($_POST['update_room']))) {
    $hotel_id = (int)$_POST['hotel_id'];
    $room_type = mysqli_real_escape_string($conn, $_POST['room_type']);
    $price = (float)$_POST['price_per_night'];
    $capacity = (int)$_POST['capacity'];
    $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = in_array($_POST['status'], ['available', 'booked', 'maintenance']) ? $_POST['status'] : 'available';
    $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;

    // Verify the hotel belongs to the admin
    $sql = "SELECT COUNT(*) as count FROM hotels WHERE id = ? AND vendor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $hotel_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hotel_count = mysqli_fetch_assoc($result)['count'];
    mysqli_stmt_close($stmt);

    if ($hotel_count == 0) {
        $error = "Invalid hotel selected or you don't have permission to add/update rooms for this hotel.";
    } else {
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/rooms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
            } elseif ($_FILES['room_image']['size'] > $max_file_size) {
                $error = "File size exceeds 5MB limit.";
            } else {
                $new_filename = uniqid('room_') . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['room_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/rooms/' . $new_filename;
                } else {
                    $error = "Error uploading image.";
                }
            }
        } elseif (isset($_POST['room_id'])) {
            $sql_check_image = "SELECT r.image_url 
                               FROM rooms r 
                               JOIN hotels h ON r.hotel_id = h.id 
                               WHERE r.id = ? AND h.vendor_id = ?";
            $stmt_check_image = mysqli_prepare($conn, $sql_check_image);
            mysqli_stmt_bind_param($stmt_check_image, "ii", $room_id, $user_id);
            mysqli_stmt_execute($stmt_check_image);
            $result_check_image = mysqli_stmt_get_result($stmt_check_image);
            $row_check_image = mysqli_fetch_assoc($result_check_image);
            if ($row_check_image && !empty($row_check_image['image_url'])) {
                $image_path = $row_check_image['image_url'];
            }
            mysqli_stmt_close($stmt_check_image);
        }

        if (empty($error)) {
            if (isset($_POST['add_room'])) {
                $sql = "INSERT INTO rooms (hotel_id, room_type, description, price_per_night, capacity, amenities, image_url, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "issdisss", $hotel_id, $room_type, $description, $price, $capacity, $amenities, $image_path, $status);
            } else {
                // Update room with vendor_id check
                $sql = "UPDATE rooms r 
                        JOIN hotels h ON r.hotel_id = h.id 
                        SET r.hotel_id = ?, r.room_type = ?, r.description = ?, r.price_per_night = ?, r.capacity = ?, r.amenities = ?, r.status = ?";
                $params = [$hotel_id, $room_type, $description, $price, $capacity, $amenities, $status];
                $types = "issdiss";
                
                if ($image_path !== null) {
                    $sql .= ", r.image_url = ?";
                    $params[] = $image_path;
                    $types .= "s";
                }
                
                $sql .= " WHERE r.id = ? AND h.vendor_id = ?";
                $params[] = $room_id;
                $params[] = $user_id;
                $types .= "ii";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }

            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0 || isset($_POST['add_room'])) {
                    $success = isset($_POST['add_room']) ? "Room added successfully!" : "Room updated successfully!";
                } else {
                    $error = "Room not found or you don't have permission to update it.";
                }
            } else {
                $error = "Error processing room: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
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

// Get all rooms with hotel information, filtered by vendor_id
$sql = "SELECT r.*, h.name as hotel_name 
        FROM rooms r 
        JOIN hotels h ON r.hotel_id = h.id 
        WHERE h.vendor_id = ?
        ORDER BY h.name ASC, r.room_type ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$rooms = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Jhang Hotels</title>
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
        .room-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .room-card:hover {
            transform: translateY(-5px);
        }
        .room-card img {
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
        .status-booked { background-color: #dc3545; color: white; }
        .status-maintenance { background-color: #ffc107; color: black; }
        .alert-dismissible {
            position: relative;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        #imagePreview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            display: none;
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
                    <a class="nav-link active" href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
                    <a class="nav-link" href="food.php"><i class="fas fa-utensils"></i> Food Menu</a>
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
                    <h2>Room Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                        <i class="fas fa-plus"></i> Add New Room
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

                <!-- Rooms List -->
                <div class="row">
                    <?php if (mysqli_num_rows($rooms) > 0): ?>
                        <?php while($room = mysqli_fetch_assoc($rooms)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="room-card">
                                    <?php if (!empty($room['image_url'])): ?>  
                                        <img src="../<?php echo htmlspecialchars($room['image_url']); ?>" alt="Room Image" class="room-image">
                                    <?php endif; ?>
                                    <div class="room-details">
                                        <h5><?php echo htmlspecialchars($room['hotel_name']); ?></h5>
                                        <p>Room no .   <?php echo $room['id']; ?></p>
                                        <p class="room-type"><?php echo ucfirst($room['room_type']); ?></p>
                                        <p class="price">PKR <?php echo number_format($room['price_per_night'], 2); ?> per hour</p>
                                        <p class="capacity">Capacity: <?php echo $room['capacity']; ?> guests</p>
                                        <p class="amenities"><?php echo htmlspecialchars($room['amenities']); ?></p>
                                        <p class="status">
                                            Status: <span class="status-badge status-<?php echo $room['status']; ?>">
                                                <?php echo ucfirst($room['status']); ?>
                                            </span>
                                        </p>
                                        <div class="room-actions">
                                            <button class="btn btn-sm btn-edit" data-bs-toggle="modal" data-bs-target="#editRoomModal"
                                                    data-room='<?php echo json_encode($room); ?>'>
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" action="" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this room?')">
                                                <input type="hidden" name="delete_room" value="true">
                                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
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
                                No rooms found. Click "Add New Room" to create one.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="addRoomForm">
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
                        <div class="mb-3">
                            <label for="room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="room_type" name="room_type" required>
                                <option value="">Select Type</option>
                                <option value="standard">Standard</option>
                                <option value="deluxe">Deluxe</option>
                                <option value="suite">Suite</option>
                                <option value="presidential_suite">Presidential Suite</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price_per_night" class="form-label">Price per Hour *</label>
                                <input type="number" class="form-control" id="price_per_night" name="price_per_night" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" min="1" name="capacity" id="capacity" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="amenities" class="form-label">Amenities</label>
                            <textarea class="form-control" id="amenities" name="amenities" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available">Available</option>
                                <option value="booked">Booked</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="room_image" class="form-label">Room Image </label>
                            <input type="file" class="form-control" id="room_image" name="room_image" accept="image/*">
                            <img id="imagePreview" src="#" alt="Image Preview">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_room" class="btn btn-custom">Add Room</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Room Modal -->
    <div class="modal fade" id="editRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="editRoomForm">
                        <input type="hidden" name="room_id" id="edit_room_id">
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
                            <label for="edit_room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="edit_room_type" name="room_type" required>
                                <option value="">Select Type</option>
                                <option value="standard">Standard</option>
                                <option value="deluxe">Deluxe</option>
                                <option value="suite">Suite</option>
                                <option value="presidential_suite">Presidential Suite</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_price_per_night" class="form-label">Price per Hour *</label>
                                <input type="number" class="form-control" id="edit_price_per_night" name="price_per_night" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_capacity" class="form-label">Capacity *</label>
                                <input type="number" min="1" name="capacity" id="edit_capacity" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_amenities" class="form-label">Amenities</label>
                            <textarea class="form-control" id="edit_amenities" name="amenities" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="booked">Booked</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_room_image" class="form-label">Room Image</label>
                            <input type="file" class="form-control" id="edit_room_image" name="room_image" accept="image/*">
                            <img id="editImagePreview" src="#" style="width:200px;margin-top:20px" alt="Image Preview">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_room" class="btn btn-custom">Update Room</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            });
        }, 5000);

        // Image preview for add room
        const roomImageInput = document.getElementById('room_image');
        const imagePreview = document.getElementById('imagePreview');
        if (roomImageInput && imagePreview) {
            roomImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type and size
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP');
                        e.target.value = '';
                        imagePreview.style.display = 'none';
                        return;
                    }
                    if (file.size > maxSize) {
                        alert('File size exceeds 5MB limit.');
                        e.target.value = '';
                        imagePreview.style.display = 'none';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.src = '#';
                    imagePreview.style.display = 'none';
                }
            });
        }

        // Image preview for edit room
        const editRoomImageInput = document.getElementById('edit_room_image');
        const editImagePreview = document.getElementById('editImagePreview');
        if (editRoomImageInput && editImagePreview) {
            editRoomImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type and size
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
        const editModal = document.getElementById('editRoomModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                if (!button || !button.dataset.room) return;
                const room = JSON.parse(button.dataset.room);
                
                // Populate form fields
                const fields = {
                    'edit_room_id': room.id || '',
                    'edit_hotel_id': room.hotel_id || '',
                    'edit_room_type': room.room_type || '',
                    'edit_description': room.description || '',
                    'edit_price_per_night': room.price_per_night || '',
                    'edit_capacity': room.capacity || '',
                    'edit_amenities': room.amenities || '',
                    'edit_status': room.status || ''
                };
                for (const [id, value] of Object.entries(fields)) {
                    const element = document.getElementById(id);
                    if (element) element.value = value;
                }

                // Set image preview
                if (editImagePreview) {
                    if (room.image_url && room.image_url.trim() !== '') {
                        editImagePreview.src = '../' + room.image_url;
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
                if (editRoomImageInput) {
                    editRoomImageInput.value = ''; // Clear file input
                }
                // Reset form fields
                const form = document.getElementById('editRoomForm');
                if (form) form.reset();
            });
        }

        // Form validation for add room
        const addRoomForm = document.getElementById('addRoomForm');
        if (addRoomForm) {
            addRoomForm.addEventListener('submit', function(e) {
                const price = document.getElementById('price_per_night');
                const capacity = document.getElementById('capacity');
                if (price && price.value <= 0) {
                    e.preventDefault();
                    alert('Price per hour must be greater than 0.');
                }
                if (capacity && capacity.value < 1) {
                    e.preventDefault();
                    alert('Capacity must be at least 1.');
                }
            });
        }

        // Form validation for edit room
        const editRoomForm = document.getElementById('editRoomForm');
        if (editRoomForm) {
            editRoomForm.addEventListener('submit', function(e) {
                const price = document.getElementById('edit_price_per_night');
                const capacity = document.getElementById('edit_capacity');
                if (price && price.value <= 0) {
                    e.preventDefault();
                    alert('Price per hour must be greater than 0.');
                }
                if (capacity && capacity.value < 1) {
                    e.preventDefault();
                    alert('Capacity must be at least 1.');
                }
            });
        }
    });
    </script>
</body>
</html>