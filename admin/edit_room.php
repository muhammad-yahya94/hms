<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';
$edit_room = null;

// Fetch room data if editing
if (isset($_GET['id'])) {
    $room_id = (int)$_GET['id'];
    $sql = "SELECT * FROM rooms WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_room = mysqli_fetch_assoc($result);

    if (!$edit_room) {
        $error = "Room not found.";
    }
}

// Handle form submission (Add or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } else if ($room_id) { // Keep existing image if editing and no new image uploaded
         $sql_check_image = "SELECT image_url FROM rooms WHERE id = ?";
         $stmt_check_image = mysqli_prepare($conn, $sql_check_image);
         mysqli_stmt_bind_param($stmt_check_image, "i", $room_id);
         mysqli_stmt_execute($stmt_check_image);
         $result_check_image = mysqli_stmt_get_result($stmt_check_image);
         $row_check_image = mysqli_fetch_assoc($result_check_image);
         if ($row_check_image && !empty($row_check_image['image_url'])) {
             $image_path = $row_check_image['image_url'];
         }
    }

    if (empty($error)) {
        if ($room_id) { // Update existing room
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
        } else { // Add new room
            $sql = "INSERT INTO rooms (hotel_id, room_type, description, price_per_night, capacity, amenities, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issdiss", $hotel_id, $room_type, $description, $price, $capacity, $amenities, $image_path);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success = $room_id ? "Room updated successfully!" : "Room added successfully!";
            // Redirect after successful add/update
             header("Location: rooms.php?success=" . urlencode($success));
             exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all hotels for dropdown
$sql_hotels = "SELECT id, name FROM hotels ORDER BY name ASC";
$hotels_result = mysqli_query($conn, $sql_hotels);

// Get distinct room types for dropdown
$sql_room_types = "SELECT DISTINCT room_type FROM rooms ORDER BY room_type";
$room_types_result = mysqli_query($conn, $sql_room_types);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_room ? 'Edit Room' : 'Add New Room'; ?> - Jhang Hotels</title>
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
        .form-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                    <h2><?php echo $edit_room ? 'Edit Room' : 'Add New Room'; ?></h2>
                    <a href="rooms.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Rooms</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php if ($edit_room): ?>
                            <input type="hidden" name="room_id" value="<?php echo $edit_room['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel *</label>
                            <select class="form-select" id="hotel_id" name="hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php
                                    mysqli_data_seek($hotels_result, 0); // Reset pointer
                                    while($hotel = mysqli_fetch_assoc($hotels_result)): ?>
                                    <option value="<?php echo $hotel['id']; ?>"
                                            <?php echo ($edit_room && $edit_room['hotel_id'] == $hotel['id']) ? 'selected' : ''; ?>>
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
                                        mysqli_data_seek($room_types_result, 0); // Reset pointer
                                        while($type_row = mysqli_fetch_assoc($room_types_result)) {
                                            $selected = ($edit_room && $edit_room['room_type'] == $type_row['room_type']) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($type_row['room_type']) . "' $selected>" . htmlspecialchars(ucfirst($type_row['room_type'])) . "</option>";
                                        }
                                        ?>
                                </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_room ? htmlspecialchars($edit_room['description']) : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price_per_night" class="form-label">Price per Night *</label>
                                <input type="number" class="form-control" id="price_per_night" name="price_per_night" step="0.01" required value="<?php echo $edit_room ? $edit_room['price_per_night'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" min="1" name="capacity" id="capacity" class="form-control shadow-none" required value="<?php echo $edit_room ? $edit_room['capacity'] : ''; ?>">
                            </div>
                        </div>

                         <div class="mb-3">
                            <label for="amenities" class="form-label">Amenities</label>
                            <textarea class="form-control" id="amenities" name="amenities" rows="2"><?php echo $edit_room ? htmlspecialchars($edit_room['amenities']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="room_image" class="form-label">Room Image</label>
                            <input type="file" class="form-control" id="room_image" name="room_image" accept="image/*">
                            <?php if ($edit_room && !empty($edit_room['image_url'])): ?>
                                <div class="mt-2">
                                    <p class="form-label">Current Image:</p>
                                    <img src="../<?php echo htmlspecialchars($edit_room['image_url']); ?>" alt="Current Room Image" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-center">
                            <button type="submit" name="save_room" class="btn btn-custom">
                                <i class="fas fa-save"></i> <?php echo $edit_room ? 'Update Room' : 'Add Room'; ?>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 