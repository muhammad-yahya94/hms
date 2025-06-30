<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
requireAdmin();

$error = '';
$success = '';
$admin_id = $_SESSION['user_id'];

// Check the structure of the hotel_employee table
$check_table = "SHOW COLUMNS FROM hotel_employee";
$table_columns = [];
if ($result = mysqli_query($conn, $check_table)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $table_columns[] = $row['Field'];
    }
} else {
    error_log("Error checking hotel_employee table structure: " . mysqli_error($conn));
    $error = "Error checking table structure. Please contact the administrator.";
}

// Build the select columns array for queries
$select_columns = [
    "u.id as user_id",
    "u.first_name",
    "u.last_name",
    "u.email",
    "u.phone",
    "u.address",
    "he.id as employee_id",
    "he.employee_id as employee_code",
    "he.designation",
    "he.department",
    "he.salary",
    "he.joining_date",
    "he.shift_timing",
    "he.status as employee_status",
    "h.name as hotel_name"
];
if (!in_array('employee_id', $table_columns)) {
    $select_columns[array_search("he.employee_id as employee_code", $select_columns)] = "'N/A' as employee_code";
}
if (!in_array('designation', $table_columns)) {
    $select_columns[array_search("he.designation", $select_columns)] = "'N/A' as designation";
}
if (!in_array('department', $table_columns)) {
    $select_columns[array_search("he.department", $select_columns)] = "'N/A' as department";
}
if (!in_array('salary', $table_columns)) {
    $select_columns[array_search("he.salary", $select_columns)] = "0 as salary";
}
if (!in_array('joining_date', $table_columns)) {
    $select_columns[array_search("he.joining_date", $select_columns)] = "'N/A' as joining_date";
}
if (!in_array('shift_timing', $table_columns)) {
    $select_columns[array_search("he.shift_timing", $select_columns)] = "'N/A' as shift_timing";
}
if (!in_array('status', $table_columns)) {
    $select_columns[array_search("he.status as employee_status", $select_columns)] = "'inactive' as employee_status";
}

// Fetch admin's vendor_id
$admin_query = "SELECT vendor_id FROM users WHERE id = ?";
$admin_stmt = mysqli_prepare($conn, $admin_query);
mysqli_stmt_bind_param($admin_stmt, "i", $admin_id);
mysqli_stmt_execute($admin_stmt);
$admin_result = mysqli_stmt_get_result($admin_stmt);
$admin = mysqli_fetch_assoc($admin_result);
$admin_vendor_id = $admin['vendor_id'];

// Fetch employees for hotels the admin can manage
$employees_query = "SELECT " . implode(", ", $select_columns) . " 
    FROM users u 
    INNER JOIN hotel_employee he ON u.id = he.user_id
    INNER JOIN hotels h ON he.hotel_id = h.id
    WHERE h.id IN (SELECT id FROM hotels WHERE vendor_id = ? OR ? = 1) 
    ORDER BY he.created_at DESC";
$employees_stmt = mysqli_prepare($conn, $employees_query);
mysqli_stmt_bind_param($employees_stmt, "ii", $admin_vendor_id, $admin_id);
mysqli_stmt_execute($employees_stmt);
$employees = mysqli_stmt_get_result($employees_stmt);

// Handle employee addition (user + employee data)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $hotel_id = (int)$_POST['hotel_id'];
    $employee_code = trim($_POST['employee_code']);
    $designation = trim($_POST['designation']);
    $department = trim($_POST['department']);
    $salary = (float)$_POST['salary'];
    $joining_date = trim($_POST['joining_date']);
    $shift_timing = trim($_POST['shift_timing']);
    $status = trim($_POST['status']);

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name) || empty($hotel_id) || 
        empty($employee_code) || empty($designation) || empty($department) || $salary <= 0 || empty($joining_date) || empty($status)) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $employee_code)) {
        $error = "Employee ID must contain only letters and numbers.";
    } else {
        // Check for duplicate username, email, or employee_id
        $check_user_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_user_stmt = mysqli_prepare($conn, $check_user_sql);
        mysqli_stmt_bind_param($check_user_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_user_stmt);
        mysqli_stmt_store_result($check_user_stmt);

        $check_employee_sql = "SELECT id FROM hotel_employee WHERE employee_id = ?";
        $check_employee_stmt = mysqli_prepare($conn, $check_employee_sql);
        mysqli_stmt_bind_param($check_employee_stmt, "s", $employee_code);
        mysqli_stmt_execute($check_employee_stmt);
        mysqli_stmt_store_result($check_employee_stmt);

        if (mysqli_stmt_num_rows($check_user_stmt) > 0) {
            $error = "Username or email already exists.";
        } elseif (mysqli_stmt_num_rows($check_employee_stmt) > 0) {
            $error = "Employee ID already exists.";
        } else {
            // Begin transaction
            mysqli_begin_transaction($conn);
            try {
                // Insert into users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_sql = "INSERT INTO users (username, email, password, role, first_name, last_name, phone, address, vendor_id) 
                             VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?)";
                $user_stmt = mysqli_prepare($conn, $user_sql);
                mysqli_stmt_bind_param($user_stmt, "sssssssi", $username, $email, $hashed_password, $first_name, $last_name, $phone, $address, $admin_vendor_id);
                mysqli_stmt_execute($user_stmt);
                $user_id = mysqli_insert_id($conn);

                // Insert into hotel_employee table
                $employee_sql = "INSERT INTO hotel_employee (user_id, hotel_id, employee_id, designation, department, salary, joining_date, shift_timing, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $employee_stmt = mysqli_prepare($conn, $employee_sql);
                mysqli_stmt_bind_param($employee_stmt, "iisssdsss", $user_id, $hotel_id, $employee_code, $designation, $department, $salary, $joining_date, $shift_timing, $status);
                mysqli_stmt_execute($employee_stmt);

                // Commit transaction
                mysqli_commit($conn);
                $success = "Employee added successfully.";
                // Redirect to refresh the page
                header("Location: users.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Error adding employee: " . mysqli_error($conn);
            }
        }
    }
}

// Handle employee update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $employee_id = (int)$_POST['employee_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $designation = trim($_POST['designation']);
    $department = trim($_POST['department']);
    $salary = (float)$_POST['salary'];
    $joining_date = trim($_POST['joining_date']);
    $shift_timing = trim($_POST['shift_timing']);
    $status = trim($_POST['status']);

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($designation) || empty($department) || $salary <= 0 || empty($joining_date) || empty($status)) {
        $error = "All required fields must be filled.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        try {
            // Update users table
            $user_sql = "UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? 
                         WHERE id = (SELECT user_id FROM hotel_employee WHERE id = ?)";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "ssssi", $first_name, $last_name, $phone, $address, $employee_id);
            mysqli_stmt_execute($user_stmt);

            // Update hotel_employee table
            $employee_sql = "UPDATE hotel_employee SET designation = ?, department = ?, salary = ?, joining_date = ?, shift_timing = ?, status = ? 
                             WHERE id = ? AND EXISTS (SELECT 1 FROM hotels h WHERE h.id = hotel_employee.hotel_id AND h.id IN (SELECT id FROM hotels WHERE vendor_id = ? OR ? = 1))";
            $employee_stmt = mysqli_prepare($conn, $employee_sql);
            mysqli_stmt_bind_param($employee_stmt, "ssdsssii", $designation, $department, $salary, $joining_date, $shift_timing, $status, $employee_id, $admin_vendor_id, $admin_id);
            mysqli_stmt_execute($employee_stmt);

            mysqli_commit($conn);
            $success = "Employee updated successfully.";
            // Redirect to refresh the page
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error updating employee: " . mysqli_error($conn);
        }
    }
}

// Handle employee deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_employee'])) {
    $employee_id = (int)$_POST['employee_id'];

    // Check for dependencies
    $check_sql = "SELECT COUNT(*) as count FROM bookings b 
                  JOIN hotel_employee he ON b.user_id = he.user_id 
                  WHERE he.id = ? 
                  UNION 
                  SELECT COUNT(*) as count FROM food_orders fo 
                  JOIN hotel_employee he ON fo.user_id = he.user_id 
                  WHERE he.id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $employee_id, $employee_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $counts = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $total_count = array_sum(array_column($counts, 'count'));

    if ($total_count > 0) {
        $error = "Cannot delete employee with existing bookings or food orders.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        try {
            // Delete from hotel_employee
            $sql = "DELETE FROM hotel_employee WHERE id = ? AND EXISTS (SELECT 1 FROM hotels h WHERE h.id = hotel_employee.hotel_id AND h.id IN (SELECT id FROM hotels WHERE vendor_id = ? OR ? = 1))";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iii", $employee_id, $admin_vendor_id, $admin_id);
            mysqli_stmt_execute($stmt);

            // Optionally delete user if no other employee records exist
            $user_sql = "DELETE FROM users WHERE id = (SELECT user_id FROM hotel_employee WHERE id = ?) AND NOT EXISTS (SELECT 1 FROM hotel_employee he2 WHERE he2.user_id = users.id AND he2.id != ?)";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "ii", $employee_id, $employee_id);
            mysqli_stmt_execute($user_stmt);

            mysqli_commit($conn);
            $success = "Employee deleted successfully.";
            // Redirect to refresh the page
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error deleting employee: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Jhang Hotels</title>
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
        .employee-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .employee-card:hover {
            transform: translateY(-5px);
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
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .icon-clr {
            color: #d4a017;
        }
        .btn-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .invalid-feedback {
            font-size: 0.875rem;
        }
        .modal-dialog {
            max-width: 600px;
        }
        .form-control:focus {
            border-color: #d4a017;
            box-shadow: 0 0 0 0.2rem rgba(212, 160, 23, 0.25);
        }
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        .status-active { background-color: #28a745; color: white; }
        .status-on_leave { background-color: #ffc107; color: black; }
        .status-inactive { background-color: #6c757d; color: white; }
        .status-terminated { background-color: #dc3545; color: white; }
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
                    <a class="nav-link active" href="users.php"><i class="fas fa-users"></i> Employees</a>
                    <a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a>
                    <a class="nav-link" href="food_orders.php"><i class="fas fa-shopping-cart"></i> Food Orders</a>
                    <a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Employee Management</h2>
                    <button type="button" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus"></i> Add New Employee
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

                <!-- Employee List -->
                <div class="row">
                    <?php if (mysqli_num_rows($employees) == 0): ?>
                        <div class="col-12">
                            <div class="alert alert-info">No employees found for your hotels.</div>
                        </div>
                    <?php else: ?>
                        <?php while ($employee = mysqli_fetch_assoc($employees)): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="employee-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h5>
                                        <span class="badge status-badge status-<?php echo htmlspecialchars($employee['employee_status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($employee['employee_status'])); ?>
                                        </span>
                                    </div>
                                    <ul class="list-unstyled mb-3">
                                        <li class="mb-2"><i class="fas fa-id-card icon-clr me-2"></i> ID: <?php echo htmlspecialchars($employee['employee_code']); ?></li>
                                        <li class="mb-2"><i class="fas fa-hotel icon-clr me-2"></i> Hotel: <?php echo htmlspecialchars($employee['hotel_name']); ?></li>
                                        <li class="mb-2"><i class="fas fa-briefcase icon-clr me-2"></i> Designation: <?php echo htmlspecialchars($employee['designation']); ?></li>
                                        <li class="mb-2"><i class="fas fa-building icon-clr me-2"></i> Department: <?php echo htmlspecialchars($employee['department']); ?></li>
                                        <li class="mb-2"><i class="fas fa-money-bill icon-clr me-2"></i> Salary: PKR <?php echo number_format($employee['salary'], 2); ?></li>
                                        <li class="mb-2"><i class="far fa-calendar icon-clr me-2"></i> Joined: <?php echo date('M d, Y', strtotime($employee['joining_date'])); ?></li>
                                        <li class="mb-2"><i class="far fa-clock icon-clr me-2"></i> Shift: <?php echo htmlspecialchars($employee['shift_timing'] ?: 'N/A'); ?></li>
                                        <li><i class="fas fa-envelope icon-clr me-2"></i> <?php echo htmlspecialchars($employee['email']); ?></li>
                                        <li><i class="fas fa-phone icon-clr me-2"></i> <?php echo htmlspecialchars($employee['phone'] ?: 'N/A'); ?></li>
                                        <li><i class="fas fa-map-marker-alt icon-clr me-2"></i> <?php echo htmlspecialchars($employee['address'] ?: 'N/A'); ?></li>
                                    </ul>
                                    <div class="d-flex justify-content-between">
                                        <button class="btn btn-sm btn-outline-primary edit-employee" 
                                                data-bs-toggle="modal" data-bs-target="#editEmployeeModal"
                                                data-id="<?php echo $employee['employee_id']; ?>"
                                                data-first-name="<?php echo htmlspecialchars($employee['first_name']); ?>"
                                                data-last-name="<?php echo htmlspecialchars($employee['last_name']); ?>"
                                                data-phone="<?php echo htmlspecialchars($employee['phone']); ?>"
                                                data-address="<?php echo htmlspecialchars($employee['address']); ?>"
                                                data-designation="<?php echo htmlspecialchars($employee['designation']); ?>"
                                                data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                                data-salary="<?php echo $employee['salary']; ?>"
                                                data-joining-date="<?php echo $employee['joining_date']; ?>"
                                                data-shift-timing="<?php echo htmlspecialchars($employee['shift_timing']); ?>"
                                                data-status="<?php echo htmlspecialchars($employee['employee_status']); ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this employee and their user account?');">
                                            <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                            <button type="submit" name="delete_employee" class="btn btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <h6 class="mb-3">User Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required pattern="[A-Za-z0-9]+" title="Only letters and numbers are allowed">
                                <div class="invalid-feedback">Please enter a valid username (letters and numbers only).</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="invalid-feedback">Password must be at least 6 characters.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                                <div class="invalid-feedback">Please enter a first name.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                                <div class="invalid-feedback">Please enter a last name.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" pattern="[0-9]{10,15}" title="Phone number must be 10-15 digits">
                                <div class="invalid-feedback">Please enter a valid phone number (10-15 digits).</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address">
                            </div>
                        </div>
                        <h6 class="mt-4 mb-3">Employee Information</h6>
                        <div class="mb-3">
                            <label for="hotel_id" class="form-label">Hotel *</label>
                            <select class="form-select" id="hotel_id" name="hotel_id" required>
                                <option value="">Select Hotel</option>
                                <?php
                                $hotels_query = "SELECT id, name FROM hotels WHERE vendor_id = ? OR ? = 1";
                                $hotels_stmt = mysqli_prepare($conn, $hotels_query);
                                mysqli_stmt_bind_param($hotels_stmt, "ii", $admin_vendor_id, $admin_id);
                                mysqli_stmt_execute($hotels_stmt);
                                $hotels_result = mysqli_stmt_get_result($hotels_stmt);
                                while ($hotel = mysqli_fetch_assoc($hotels_result)):
                                ?>
                                    <option value="<?php echo $hotel['id']; ?>">
                                        <?php echo htmlspecialchars($hotel['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">Please select a hotel.</div>
                        </div>
                        <div class="mb-3">
                            <label for="employee_code" class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" id="employee_code" name="employee_code" required pattern="[A-Za-z0-9]+" title="Only letters and numbers are allowed">
                            <div class="invalid-feedback">Please enter a valid employee ID.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="designation" class="form-label">Designation *</label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                                <div class="invalid-feedback">Please enter a designation.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department *</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                                <div class="invalid-feedback">Please enter a department.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary (PKR) *</label>
                                <input type="number" class="form-control" id="salary" name="salary" min="0" step="0.01" required>
                                <div class="invalid-feedback">Please enter a valid salary.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="joining_date" class="form-label">Joining Date *</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date" required>
                                <div class="invalid-feedback">Please select a joining date.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="shift_timing" class="form-label">Shift Timing</label>
                            <input type="text" class="form-control" id="shift_timing" name="shift_timing" placeholder="e.g., 9:00 AM - 5:00 PM">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_employee" class="btn btn-custom">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        <h6 class="mb-3">User Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                <div class="invalid-feedback">Please enter a first name.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                <div class="invalid-feedback">Please enter a last name.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" pattern="[0-9]{10,15}" title="Phone number must be 10-15 digits">
                                <div class="invalid-feedback">Please enter a valid phone number.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="edit_address" name="address">
                            </div>
                        </div>
                        <h6 class="mt-4 mb-3">Employee Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_designation" class="form-label">Designation *</label>
                                <input type="text" class="form-control" id="edit_designation" name="designation" required>
                                <div class="invalid-feedback">Please enter a designation.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_department" class="form-label">Department *</label>
                                <input type="text" class="form-control" id="edit_department" name="department" required>
                                <div class="invalid-feedback">Please enter a department.</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_salary" class="form-label">Salary (PKR) *</label>
                                <input type="number" class="form-control" id="edit_salary" name="salary" min="0" step="0.01" required>
                                <div class="invalid-feedback">Please enter a valid salary.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_joining_date" class="form-label">Joining Date *</label>
                                <input type="date" class="form-control" id="edit_joining_date" name="joining_date" required>
                                <div class="invalid-feedback">Please select a joining date.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_shift_timing" class="form-label">Shift Timing</label>
                            <input type="text" class="form-control" id="edit_shift_timing" name="shift_timing" placeholder="e.g., 9:00 AM - 5:00 PM">
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_employee" class="btn btn-custom">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validation
        (function () {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Populate Edit Employee Modal
        document.querySelectorAll('.edit-employee').forEach(button => {
            button.addEventListener('click', function () {
                const modal = document.getElementById('editEmployeeModal');
                const employeeId = this.getAttribute('data-id');
                const firstName = this.getAttribute('data-first-name');
                const lastName = this.getAttribute('data-last-name');
                const phone = this.getAttribute('data-phone');
                const address = this.getAttribute('data-address');
                const designation = this.getAttribute('data-designation');
                const department = this.getAttribute('data-department');
                const salary = this.getAttribute('data-salary');
                const joiningDate = this.getAttribute('data-joining-date');
                const shiftTiming = this.getAttribute('data-shift-timing');
                const status = this.getAttribute('data-status');

                modal.querySelector('#edit_employee_id').value = employeeId;
                modal.querySelector('#edit_first_name').value = firstName;
                modal.querySelector('#edit_last_name').value = lastName;
                modal.querySelector('#edit_phone').value = phone || '';
                modal.querySelector('#edit_address').value = address || '';
                modal.querySelector('#edit_designation').value = designation;
                modal.querySelector('#edit_department').value = department;
                modal.querySelector('#edit_salary').value = salary;
                modal.querySelector('#edit_joining_date').value = joiningDate;
                modal.querySelector('#edit_shift_timing').value = shiftTiming || '';
                modal.querySelector('#edit_status').value = status;
            });
        });

        // Auto-generate employee ID and username
        document.addEventListener('DOMContentLoaded', function () {
            const employeeCodeInput = document.querySelector('#employee_code');
            const usernameInput = document.querySelector('#username');
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('#joining_date').value = today;
            document.querySelector('#edit_joining_date').value = today;

            employeeCodeInput.addEventListener('focus', function () {
                if (!this.value) {
                    const prefix = 'EMP';
                    const random = Math.floor(1000 + Math.random() * 9000);
                    this.value = `${prefix}${random}`;
                }
            });

            usernameInput.addEventListener('focus', function () {
                if (!this.value) {
                    const random = Math.floor(1000 + Math.random() * 9000);
                    this.value = `employee${random}`;
                }
            });
        });
    </script>
</body>
</html>