<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';

// Handle room deletion
if (isset($_POST['delete_room'])) {
    $room_id = (int)$_POST['room_id'];
    
    // Check if room has any bookings
    $sql = "SELECT COUNT(*) as count FROM bookings WHERE room_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking_count = mysqli_fetch_assoc($result)['count'];
    
    if ($booking_count > 0) {
        $error = "Cannot delete room with existing bookings.";
    } else {
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $room_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Room deleted successfully.";
        } else {
            $error = "Error deleting room.";
        }
    }
}

// Handle room addition/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_room']) || isset($_POST['update_room'])) {
        $hotel_id = (int)$_POST['hotel_id'];
        $room_type = mysqli_real_escape_string($conn, $_POST['room_type']);
        $price = (float)$_POST['price_per_night'];
        $capacity = (int)$_POST['capacity'];
        $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;

        // Handle image upload
        $image_path = null;
        if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/rooms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['room_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid('room_') . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['room_image']['tmp_name'], $upload_path)) {
                    $image_path = 'uploads/rooms/' . $new_filename;
                } else {
                    $error = "Error uploading image.";
                }
            } else {
                $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
            }
        } else if (isset($_POST['room_id'])) {
            $room_id_check = (int)$_POST['room_id'];
            $sql_check_image = "SELECT image_url FROM rooms WHERE id = ?";
            $stmt_check_image = mysqli_prepare($conn, $sql_check_image);
            mysqli_stmt_bind_param($stmt_check_image, "i", $room_id_check);
            mysqli_stmt_execute($stmt_check_image);
            $result_check_image = mysqli_stmt_get_result($stmt_check_image);
            $row_check_image = mysqli_fetch_assoc($result_check_image);
            if ($row_check_image && !empty($row_check_image['image_url'])) {
                $image_path = $row_check_image['image_url'];
            }
        }

        if (empty($error)) {
            if (isset($_POST['add_room'])) {
                $sql = "INSERT INTO rooms (hotel_id, room_type, description, price_per_night, capacity, amenities, image_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "issdiss", $hotel_id, $room_type, $description, $price, $capacity, $amenities, $image_path);
            } else {
                $sql = "UPDATE rooms SET hotel_id = ?, room_type = ?, description = ?, price_per_night = ?, capacity = ?, amenities = ?";
                $params = [$hotel_id, $room_type, $description, $price, $capacity, $amenities];
                $types = "issdiss";
                
                if ($image_path !== null) {
                    $sql .= ", image_url = ?";
                    $params[] = $image_path;
                    $types .= "s";
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $room_id;
                $types .= "i";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }

            if (mysqli_stmt_execute($stmt)) {
                $success = isset($_POST['add_room']) ? "Room added successfully!" : "Room updated successfully!";
            } else {
                $error = "Error updating room: " . mysqli_error($conn);
            }
        }
    }
}

// Get all hotels for dropdown
$sql = "SELECT id, name FROM hotels ORDER BY name ASC";
$hotels = mysqli_query($conn, $sql);

// Get all rooms with hotel information
$sql = "SELECT r.*, h.name as hotel_name 
        FROM rooms r 
        JOIN hotels h ON r.hotel_id = h.id 
        ORDER BY h.name ASC, r.room_type ASC";
$rooms = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Jhang Hotels</title>
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
        .room-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        .hotel-badge {
            background-color: #d4a017;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            display: inline-block;
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
                    <a class="nav-link active" href="rooms.php">
                        <i class="fas fa-bed"></i> Rooms
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a class="nav-link" href="reservations.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Room Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                        <i class="fas fa-plus"></i> Add New Room
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Rooms List -->
                <div class="row">
                    <?php while($room = mysqli_fetch_assoc($rooms)): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="room-card">
                                <?php if (!empty($room['image_url'])): ?>
                                    <img src="../<?php echo htmlspecialchars($room['image_url']); ?>" alt="Room Image" class="room-image">
                                <?php endif; ?>
                                <div class="room-details">
                                    <h5><?php echo htmlspecialchars($room['hotel_name']); ?></h5>
                                    <p class="room-type"><?php echo ucfirst($room['room_type']); ?></p>
                                    <p class="price">PKR <?php echo number_format($room['price_per_night'], 2); ?> per hour</p>
                                    <p class="capacity">Capacity: <?php echo $room['capacity']; ?> guests</p>
                                    <p class="amenities"><?php echo htmlspecialchars($room['amenities']); ?></p>
                                    <div class="room-actions">
                                        <a href="edit_room.php?id=<?php echo $room['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" action="" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this room?')">
                                            <input type="hidden" name="delete_room" value="true">
                                            <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
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

    <!-- Add Room Modal -->
    <div class="modal fade" id="addRoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel *</label>
                            <select class="form-select" id="hotel_id" name="hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php
                                mysqli_data_seek($hotels, 0); // Reset hotels cursor
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
                                <?php
                                $sql_room_types = "SELECT DISTINCT room_type FROM rooms ORDER BY room_type";
                                $room_types_result = mysqli_query($conn, $sql_room_types);
                                while($type_row = mysqli_fetch_assoc($room_types_result)): ?>
                                    <option value="<?php echo htmlspecialchars($type_row['room_type']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($type_row['room_type'])); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price_per_night" class="form-label">Price per Hour *</label>
                                <input type="number" class="form-control" id="price_per_night" name="price_per_night" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" min="1" name="capacity" id="capacity" class="form-control shadow-none" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="amenities" class="form-label">Amenities</label>
                            <textarea class="form-control" id="amenities" name="amenities" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="room_image" class="form-label">Room Image</label>
                            <input type="file" class="form-control" id="room_image" name="room_image" accept="image/*">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>