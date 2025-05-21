<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';

// Handle hotel deletion
if (isset($_POST['delete_hotel'])) {
    $hotel_id = (int)$_POST['hotel_id'];
    
    // Check if hotel has any bookings
    $sql = "SELECT COUNT(*) as count FROM bookings WHERE hotel_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $hotel_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking_count = mysqli_fetch_assoc($result)['count'];
    
    if ($booking_count > 0) {
        $error = "Cannot delete hotel with existing bookings.";
    } else {
        $sql = "DELETE FROM hotels WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $hotel_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Hotel deleted successfully.";
        } else {
            $error = "Error deleting hotel.";
        }
    }
}

// Handle hotel addition/editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_hotel'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website']);
    // $image_url = trim($_POST['image_url']); // Removed URL handling
    
    // Handle image upload
    $image_path = null; // Initialize image_path as null
    if (isset($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/hotels/';
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES['hotel_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid('hotel_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['hotel_image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/hotels/' . $new_filename; // Path to save in database
            } else {
                $error = "Error uploading image.";
            }
        } else {
            $error = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF, WEBP";
        }
    } else if (isset($_POST['hotel_id'])) {
        // If editing and no new file uploaded, retain existing image path
        $hotel_id = (int)$_POST['hotel_id'];
        $sql = "SELECT image_url FROM hotels WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $hotel_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        if ($row && !empty($row['image_url'])) {
            $image_path = $row['image_url'];
        }
    }

    
    if (empty($name) || empty($address) || empty($city)) {
        $error = "Required fields cannot be empty.";
    } else if (!empty($error)) {
        // Error already set during file upload
    } else {
        if (isset($_POST['hotel_id'])) {
            // Update existing hotel
            $hotel_id = (int)$_POST['hotel_id'];
            $sql = "UPDATE hotels SET name = ?, description = ?, address = ?, city = ?, 
                    phone = ?, email = ?, website = ?, image_url = ? WHERE id = ?"; // Updated SQL
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssi", $name, $description, $address, $city, 
                                 $phone, $email, $website, $image_path, $hotel_id); // Updated parameters
        } else {
            // Add new hotel
            $sql = "INSERT INTO hotels (name, description, address, city, phone, email, website, image_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; // Updated SQL
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssss", $name, $description, $address, $city, 
                                 $phone, $email, $website, $image_path); // Updated parameters
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $success = isset($_POST['hotel_id']) ? "Hotel updated successfully." : "Hotel added successfully.";
        } else {
            $error = "Error saving hotel.";
        }
    }
}

// Get all hotels
$sql = "SELECT * FROM hotels ORDER BY name ASC";
$hotels = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Management - Jhang Hotels</title>
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
        .hotel-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .hotel-card:hover {
            transform: translateY(-5px);
        }
        .hotel-card img {
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
                    <h2>Hotel Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#hotelModal">
                        <i class="fas fa-plus"></i> Add New Hotel
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Hotels List -->
                <div class="row">
                    <?php while($hotel = mysqli_fetch_assoc($hotels)): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="hotel-card">
                                <img src="<?php echo htmlspecialchars($hotel['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($hotel['name']); ?>"
                                     onerror="this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945';">
                                <h4><?php echo htmlspecialchars($hotel['name']); ?></h4>
                                <p class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($hotel['city']); ?>
                                </p>
                                <p><?php echo htmlspecialchars(substr($hotel['description'], 0, 100)) . '...'; ?></p>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-edit" 
                                            onclick="editHotel(<?php echo htmlspecialchars(json_encode($hotel)); ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this hotel?');">
                                        <input type="hidden" name="hotel_id" value="<?php echo $hotel['id']; ?>">
                                        <button type="submit" name="delete_hotel" class="btn btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Hotel Modal -->
    <div class="modal fade" id="hotelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Hotel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="hotelForm" enctype="multipart/form-data">
                        <input type="hidden" name="hotel_id" id="hotel_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Hotel Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="hotel_image" class="form-label">Hotel Image</label>
                                <input type="file" class="form-control" id="hotel_image" name="hotel_image" accept="image/*">
                                <div id="current_image_preview" class="mt-2" style="display: none;">
                                    <img src="" alt="Current Hotel Image" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address *</label>
                            <input type="text" class="form-control" id="address" name="address" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City *</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="save_hotel" class="btn btn-custom">Save Hotel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editHotel(hotel) {
            document.getElementById('hotel_id').value = hotel.id;
            document.getElementById('name').value = hotel.name;
            document.getElementById('description').value = hotel.description;
            document.getElementById('address').value = hotel.address;
            document.getElementById('city').value = hotel.city;
            document.getElementById('phone').value = hotel.phone;
            document.getElementById('email').value = hotel.email;
            document.getElementById('website').value = hotel.website;
            // document.getElementById('image_url').value = hotel.image_url; // Removed URL line
            
            // Handle existing image display for editing
            const currentImagePreview = document.getElementById('current_image_preview');
            const currentImage = currentImagePreview.querySelector('img');
            if (hotel.image_url) {
                currentImage.src = '../' + hotel.image_url; // Assuming image_url is relative path
                currentImagePreview.style.display = 'block';
            } else {
                currentImage.src = '';
                currentImagePreview.style.display = 'none';
            }
            
            // Clear the file input when opening for edit
            document.getElementById('hotel_image').value = '';

            new bootstrap.Modal(document.getElementById('hotelModal')).show();
        }
    </script>
</body>
</html> 