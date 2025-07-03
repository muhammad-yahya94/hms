<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require login
requireLogin();

// Get user data
$user = getUserData();

// Debug getUserData() query
$debug_logs = [];
$sql_debug = "SELECT * FROM users WHERE id = ?";
$stmt_debug = mysqli_prepare($conn, $sql_debug);
mysqli_stmt_bind_param($stmt_debug, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_debug);
$result_debug = mysqli_stmt_get_result($stmt_debug);
$user_debug = mysqli_fetch_assoc($result_debug) ?: [];
$debug_logs[] = "Raw getUserData Query Result (Session ID: {$_SESSION['user_id']}): " . print_r([
    'id' => $user_debug['id'] ?? 'Not found',
    'username' => $user_debug['username'] ?? 'Not found',
    'email' => $user_debug['email'] ?? 'Not found',
    'phone' => $user_debug['phone'] ?? 'Not provided',
    'address' => $user_debug['address'] ?? 'Not provided',
    'profile_image' => $user_debug['profile_image'] ?? 'Not set',
    'created_at' => $user_debug['created_at'] ?? 'Not found'
], true);
mysqli_stmt_close($stmt_debug);

// Log user data from getUserData()
$debug_logs[] = "User Data (Initial): " . print_r([
    'username' => $user['username'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? 'Not provided',
    'address' => $user['address'] ?? 'Not provided',
    'profile_image' => $user['profile_image'] ?? 'Not set',
    'created_at' => $user['created_at']
], true);

// Fetch profile_image directly from database
$profile_image_db = '';
$sql_db = "SELECT profile_image FROM users WHERE id = ?";
$stmt_db = mysqli_prepare($conn, $sql_db);
mysqli_stmt_bind_param($stmt_db, "i", $user['id']);
mysqli_stmt_execute($stmt_db);
$result_db = mysqli_stmt_get_result($stmt_db);
$db_data = mysqli_fetch_assoc($result_db);
$profile_image_db = $db_data['profile_image'] ?? '';
$debug_logs[] = "Database profile_image for user ID {$user['id']}: " . ($profile_image_db ?: 'Not set');
error_log("Database profile_image for user ID {$user['id']}: " . ($profile_image_db ?: 'Not set'));
mysqli_stmt_close($stmt_db);

$error_profile = '';
$success_profile = '';
$error_password = '';
$success_password = '';

// Set image path using database value
$image_path = !empty($profile_image_db) ? $profile_image_db : '';
$server_path = $image_path ? __DIR__ . '/..' . $image_path : '';
$web_path_prefix = '/HMS'; // Adjust for project root
$web_image_path = $image_path ? $web_path_prefix . $image_path : '';
$debug_logs[] = "Image Path: " . ($image_path ?: 'No image set');
if ($image_path) {
    $debug_logs[] = "Server Image Path: $server_path";
    $debug_logs[] = "Image Exists: " . (file_exists($server_path) ? 'Yes' : 'No');
    $debug_logs[] = "Web URL: http://localhost{$web_image_path}";
    error_log("Profile Image Path: $image_path");
    error_log("Server Image Path: $server_path");
    error_log("Image Exists: " . (file_exists($server_path) ? 'Yes' : 'No'));
    error_log("Web URL: http://localhost{$web_image_path}");
    
    // Test HTTP accessibility
    $image_url = "http://localhost{$web_image_path}";
    $headers = @get_headers($image_url);
    $http_status = $headers ? $headers[0] : 'Failed to fetch headers';
    $debug_logs[] = "HTTP Status for $image_url: $http_status";
    error_log("HTTP Status for $image_url: $http_status");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Validation
        if (empty($username) || empty($email)) {
            $error_profile = "Username and email are required.";
            $debug_logs[] = "Profile Update Error: Username or email empty";
            error_log("Profile Update Error: Username or email empty");
        } else {
            // Check if username exists (excluding current user)
            $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt_username = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt_username, "si", $username, $user['id']);
            mysqli_stmt_execute($stmt_username);
            mysqli_stmt_store_result($stmt_username);
            
            if (mysqli_stmt_num_rows($stmt_username) > 0) {
                $error_profile = "Username already exists.";
                $debug_logs[] = "Profile Update Error: Username '$username' already exists";
                error_log("Profile Update Error: Username '$username' already exists");
                mysqli_stmt_close($stmt_username);
            } else {
                mysqli_stmt_close($stmt_username);
                // Check if email exists (excluding current user)
                $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt_email = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt_email, "si", $email, $user['id']);
                mysqli_stmt_execute($stmt_email);
                mysqli_stmt_store_result($stmt_email);
                
                if (mysqli_stmt_num_rows($stmt_email) > 0) {
                    $error_profile = "Email already exists.";
                    $debug_logs[] = "Profile Update Error: Email '$email' already exists";
                    error_log("Profile Update Error: Email '$email' already exists");
                    mysqli_stmt_close($stmt_email);
                } else {
                    mysqli_stmt_close($stmt_email);
                    // Update profile without password
                    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : '';
                    $address = !empty($_POST['address']) ? trim($_POST['address']) : '';
                    $profile_image = $profile_image_db; // Use existing database value
                    
                    // Handle profile image upload if provided
                    if (!empty($_FILES['profile_image']['name'])) {
                        $target_dir = __DIR__ . '/../Uploads/';
                        $web_path = '/Uploads/';
                        // Create directory if it doesn't exist
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                            $debug_logs[] = "Created upload directory: $target_dir";
                            error_log("Created upload directory: $target_dir");
                        }
                        // Check if directory is writable
                        if (!is_writable($target_dir)) {
                            $error_profile = "Upload directory is not writable.";
                            $debug_logs[] = "Profile Update Error: Upload directory '$target_dir' is not writable";
                            error_log("Upload directory '$target_dir' is not writable");
                        } else {
                            // Check for upload errors
                            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                                $error_profile = "File upload failed with error code: " . $_FILES['profile_image']['error'];
                                $debug_logs[] = "Profile Update Error: File upload error code: " . $_FILES['profile_image']['error'];
                                error_log("File upload error code: " . $_FILES['profile_image']['error']);
                            } else {
                                $imageFileType = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                                $unique_name = uniqid('profile_', true) . '.' . $imageFileType;
                                $target_file = $target_dir . $unique_name;
                                
                                // Validate file
                                if (!in_array($imageFileType, $allowed_types)) {
                                    $error_profile = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                                    $debug_logs[] = "Profile Update Error: Invalid file type '$imageFileType'";
                                    error_log("Invalid file type: $imageFileType");
                                } elseif ($_FILES['profile_image']['size'] > 500000) {
                                    $error_profile = "File size must be less than 500KB.";
                                    $debug_logs[] = "Profile Update Error: File size exceeds 500KB";
                                    error_log("File size exceeds 500KB: " . $_FILES['profile_image']['size']);
                                } elseif (!getimagesize($_FILES['profile_image']['tmp_name'])) {
                                    $error_profile = "File is not a valid image.";
                                    $debug_logs[] = "Profile Update Error: File is not a valid image";
                                    error_log("File is not a valid image");
                                } else {
                                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                                        $profile_image = $web_path . $unique_name; // Store web-accessible path
                                        $debug_logs[] = "Image uploaded successfully to: $target_file, stored as: $profile_image";
                                        error_log("Image uploaded successfully to: $target_file, stored as: $profile_image");
                                    } else {
                                        $error_profile = "Error uploading profile image.";
                                        $debug_logs[] = "Profile Update Error: Failed to move uploaded file to $target_file";
                                        error_log("Failed to move uploaded file to: $target_file");
                                    }
                                }
                            }
                        }
                    }
                    
                    if (empty($error_profile)) {
                        $sql = "UPDATE users SET username = ?, email = ?, phone = ?, address = ?, profile_image = ? WHERE id = ?";
                        $stmt_update = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt_update, "sssssi", $username, $email, $phone, $address, $profile_image, $user['id']);
                        
                        if (mysqli_stmt_execute($stmt_update)) {
                            $affected_rows = mysqli_stmt_affected_rows($stmt_update);
                            $debug_logs[] = "Profile update executed. Affected rows: $affected_rows";
                            error_log("Profile update executed. Affected rows: $affected_rows");
                            if ($affected_rows > 0) {
                                $success_profile = "Profile updated successfully.";
                                $debug_logs[] = "Profile updated successfully for user ID: " . $user['id'];
                                error_log("Profile updated successfully for user ID: " . $user['id']);
                                // Refresh user data
                                $user = getUserData();
                                $debug_logs[] = "User Data (After Update): " . print_r([
                                    'username' => $user['username'],
                                    'email' => $user['email'],
                                    'phone' => $user['phone'] ?? 'Not provided',
                                    'address' => $user['address'] ?? 'Not provided',
                                    'profile_image' => $user['profile_image'] ?? 'Not set',
                                    'created_at' => $user['created_at']
                                ], true);
                                // Refresh profile_image_db
                                $sql_db = "SELECT profile_image FROM users WHERE id = ?";
                                $stmt_db = mysqli_prepare($conn, $sql_db);
                                mysqli_stmt_bind_param($stmt_db, "i", $user['id']);
                                mysqli_stmt_execute($stmt_db);
                                $result_db = mysqli_stmt_get_result($stmt_db);
                                $db_data = mysqli_fetch_assoc($result_db);
                                $profile_image_db = $db_data['profile_image'] ?? '';
                                $debug_logs[] = "Database profile_image for user ID {$user['id']} (After Update): " . ($profile_image_db ?: 'Not set');
                                error_log("Database profile_image for user ID {$user['id']} (After Update): " . ($profile_image_db ?: 'Not set'));
                                mysqli_stmt_close($stmt_db);
                            } else {
                                $error_profile = "No changes made to profile. Database update had no effect.";
                                $debug_logs[] = "Profile Update Error: No rows affected";
                                error_log("Profile Update Error: No rows affected");
                            }
                        } else {
                            $error_profile = "Something went wrong. Please try again later: " . mysqli_error($conn);
                            $debug_logs[] = "Profile Update Error: Database error - " . mysqli_error($conn);
                            error_log("Profile Update Error: Database error - " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($stmt_update);
                    }
                }
            }
        }
    }

    // Handle password update
    if (isset($_POST['update_password'])) {
        $current_password = !empty($_POST['current_password']) ? trim($_POST['current_password']) : '';
        $new_password = !empty($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirm_password = !empty($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        
        // Validation
        if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt_password = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt_password, "i", $user['id']);
            mysqli_stmt_execute($stmt_password);
            $result = mysqli_stmt_get_result($stmt_password);
            $user_data = mysqli_fetch_assoc($result);
            
            if (!password_verify($current_password, $user_data['password'])) {
                $error_password = "Current password is incorrect.";
                $debug_logs[] = "Password Update Error: Current password incorrect";
                error_log("Password Update Error: Current password incorrect");
            } elseif (empty($new_password) || empty($confirm_password)) {
                $error_password = "New password and confirmation are required.";
                $debug_logs[] = "Password Update Error: New password or confirmation empty";
                error_log("Password Update Error: New password or confirmation empty");
            } elseif ($new_password !== $confirm_password) {
                $error_password = "New passwords do not match.";
                $debug_logs[] = "Password Update Error: New passwords do not match";
                error_log("Password Update Error: New passwords do not match");
            } elseif (strlen($new_password) < 6) {
                $error_password = "New password must be at least 6 characters long.";
                $debug_logs[] = "Password Update Error: New password too short";
                error_log("Password Update Error: New password too short");
            } else {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt_update_password = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt_update_password, "si", $hashed_password, $user['id']);
                
                if (mysqli_stmt_execute($stmt_update_password)) {
                    $success_password = "Password updated successfully.";
                    $debug_logs[] = "Password updated successfully for user ID: " . $user['id'];
                    error_log("Password updated successfully for user ID: " . $user['id']);
                } else {
                    $error_password = "Something went wrong. Please try again later: " . mysqli_error($conn);
                    $debug_logs[] = "Password Update Error: Database error - " . mysqli_error($conn);
                    error_log("Password Update Error: Database error - " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_update_password);
            }
            mysqli_stmt_close($stmt_password);
        } else {
            $error_password = "Please fill in all password fields to change your password.";
            $debug_logs[] = "Password Update Error: Password fields empty";
            error_log("Password Update Error: Password fields empty");
        }
    }
}

// Recompute image path after potential update
$image_path = !empty($profile_image_db) ? $profile_image_db : '';
$server_path = $image_path ? __DIR__ . '/..' . $image_path : '';
$web_image_path = $image_path ? $web_path_prefix . $image_path : '';
$debug_logs[] = "Image Path (Final): " . ($image_path ?: 'No image set');
if ($image_path) {
    $debug_logs[] = "Server Image Path (Final): $server_path";
    $debug_logs[] = "Image Exists (Final): " . (file_exists($server_path) ? 'Yes' : 'No');
    $debug_logs[] = "Web URL (Final): http://localhost{$web_image_path}";
    error_log("Final Image Path: $image_path");
    error_log("Final Server Image Path: $server_path");
    error_log("Final Image Exists: " . (file_exists($server_path) ? 'Yes' : 'No'));
    error_log("Final Web URL: http://localhost{$web_image_path}");
    
    // Test HTTP accessibility
    $image_url = "http://localhost{$web_image_path}";
    $headers = @get_headers($image_url);
    $http_status = $headers ? $headers[0] : 'Failed to fetch headers';
    $debug_logs[] = "HTTP Status for $image_url (Final): $http_status";
    error_log("HTTP Status for $image_url (Final): $http_status");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Jhang Hotels</title>
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
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .profile-card:hover {
            transform: translateY(-5px);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid #d4a017;
            position: relative;
            background: #f0e4d7; /* Light skin tone for cartoon face */
            overflow: hidden;
        }
        .cartoon-face {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .cartoon-face .eyes {
            position: absolute;
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 20px;
        }
        .cartoon-face .eye {
            width: 20px;
            height: 20px;
            background: #000;
            border-radius: 50%;
            position: relative;
        }
        .cartoon-face .eye::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
        }
        .cartoon-face .mouth {
            position: absolute;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 20px;
            background: #ff6b6b;
            border-radius: 0 0 20px 20px;
        }
        .cartoon-face .hair {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 40px;
            background: #4a3728;
            border-radius: 20px 20px 0 0;
        }
        .profile-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .profile-info p {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .profile-info i {
            width: 30px;
            color: #d4a017;
            margin-right: 10px;
        }
        .btn-custom {
            background-color: #d4a017;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-custom:hover {
            background-color: #b38b12;
            transform: translateY(-2px);
        }
        .form-control {
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 5px;
        }
        .form-control:focus {
            border-color: #d4a017;
            box-shadow: 0 0 0 0.2rem rgba(212, 160, 23, 0.25);
        }
        .debug-logs {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            max-width: 100%;
            overflow-x: auto;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
    <script>
        // Debug image loading
        window.onload = function() {
            const img = document.querySelector('.profile-avatar img');
            if (img) {
                img.addEventListener('error', function() {
                    console.error('Image failed to load: ' + img.src);
                    // Ensure cartoon avatar is displayed
                    this.parentElement.innerHTML = '<div class="profile-avatar"><div class="cartoon-face"><div class="hair"></div><div class="eyes"><div class="eye"></div><div class="eye"></div></div><div class="mouth"></div></div></div>';
                });
                img.addEventListener('load', function() {
                    console.log('Image loaded successfully: ' + img.src);
                });
            }
        };
    </script>
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
                    <a class="nav-link" href="food_orders.php">
                        <i class="fas fa-shopping-cart"></i> My Food Orders
                    </a>
                    <a class="nav-link active" href="profile.php">
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
                <h2 class="mb-4">My Profile</h2>
                
                <?php if ($error_profile): ?>
                    <div class="alert alert-danger"><?php echo $error_profile; ?></div>
                <?php endif; ?>
                <?php if ($success_profile): ?>
                    <div class="alert alert-success"><?php echo $success_profile; ?></div>
                <?php endif; ?>
                
                <?php if ($error_password): ?>
                    <div class="alert alert-danger"><?php echo $error_password; ?></div>
                <?php endif; ?>
                <?php if ($success_password): ?>
                    <div class="alert alert-success"><?php echo $success_password; ?></div>
                <?php endif; ?>
                
                <div class="profile-card">
                    <div class="profile-header">
                        <?php
                        $cache_buster = time(); // Add timestamp to prevent caching
                        if ($image_path && file_exists($server_path)):
                            $image_src = htmlspecialchars($web_image_path . '?v=' . $cache_buster);
                        ?>
                            <img src="<?php echo $image_src; ?>" 
                                 alt="Profile Picture" 
                                 class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar">
                                <div class="cartoon-face">
                                    <div class="hair"></div>
                                    <div class="eyes">
                                        <div class="eye"></div>
                                        <div class="eye"></div>
                                    </div>
                                    <div class="mouth"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p class="text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    
                    <div class="profile-info">
                        <p>
                            <i class="fas fa-envelope"></i>
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <p>
                            <i class="fas fa-phone"></i>
                            <strong>Phone:</strong> <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not provided'; ?>
                        </p>
                        <p>
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Address:</strong> <?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not provided'; ?>
                        </p>
                    </div>
                    
                    <!-- Profile Update Form -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="update_profile" class="btn btn-custom">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                    
                    <!-- Password Update Form -->
                    <form method="POST" action="" class="mt-5">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" name="update_password" class="btn btn-custom">
                                <i class="fas fa-lock"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Debug Logs -->
                <!-- <div class="debug-logs">
                    <h4>Debug Logs</h4>
                    <pre><?php echo htmlspecialchars(implode("\n", $debug_logs)); ?></pre>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>