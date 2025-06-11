<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Set timezone to Pakistan Time
date_default_timezone_set('Asia/Karachi');

// Check and add vendor_id column to hotels table if missing
$check_hotel_vendor = "SHOW COLUMNS FROM hotels LIKE 'vendor_id'";
$result = mysqli_query($conn, $check_hotel_vendor);
if (mysqli_num_rows($result) == 0) {
    mysqli_query($conn, "ALTER TABLE hotels ADD vendor_id INT NOT NULL DEFAULT 0");
    error_log("Added vendor_id column to hotels table");
}

// Check and add vendor_id column to users table if missing
$check_user_vendor = "SHOW COLUMNS FROM users LIKE 'vendor_id'";
$result = mysqli_query($conn, $check_user_vendor);
if (mysqli_num_rows($result) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD vendor_id INT NOT NULL DEFAULT 0");
    error_log("Added vendor_id column to users table");
}

// Initialize stats array
$stats = array();
$user_id = $_SESSION['user_id'];

// Total Hotels for current admin
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM hotels WHERE vendor_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['hotels'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['hotels'] = 0;
    error_log("Error fetching total hotels: " . mysqli_error($conn));
}

// Total Rooms for current admin's hotels
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM rooms r JOIN hotels h ON r.hotel_id = h.id WHERE h.vendor_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['rooms'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['rooms'] = 0;
    error_log("Error fetching total rooms: " . mysqli_error($conn));
}

// Total Users (excluding admins) for current admin
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin' AND vendor_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['users'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['users'] = 0;
    error_log("Error fetching total users: " . mysqli_error($conn));
}

// Total Bookings for current admin's hotels
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM bookings b JOIN hotels h ON b.hotel_id = h.id WHERE h.vendor_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['bookings'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['bookings'] = 0;
    error_log("Error fetching total bookings: " . mysqli_error($conn));
}

// Pending Bookings for current admin's hotels
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM bookings b JOIN hotels h ON b.hotel_id = h.id WHERE h.vendor_id = ? AND b.booking_status = 'pending'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['pending_bookings'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['pending_bookings'] = 0;
    error_log("Error fetching pending bookings: " . mysqli_error($conn));
}

// Confirmed Bookings for current admin's hotels
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM bookings b JOIN hotels h ON b.hotel_id = h.id WHERE h.vendor_id = ? AND b.booking_status = 'confirmed'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['confirmed_bookings'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['confirmed_bookings'] = 0;
    error_log("Error fetching confirmed bookings: " . mysqli_error($conn));
}

// Cancelled Bookings for current admin's hotels
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM bookings b JOIN hotels h ON b.hotel_id = h.id WHERE h.vendor_id = ? AND b.booking_status = 'cancelled'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $stats['cancelled_bookings'] = mysqli_fetch_assoc($result)['total'] ?? 0;
} else {
    $stats['cancelled_bookings'] = 0;
    error_log("Error fetching cancelled bookings: " . mysqli_error($conn));
}

// Recent Bookings for current admin's hotels
$stmt = mysqli_prepare($conn, "
    SELECT b.*, r.room_type, h.name as hotel_name, u.first_name, u.last_name, b.booking_status 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id
    JOIN hotels h ON b.hotel_id = h.id 
    JOIN users u ON b.user_id = u.id 
    WHERE h.vendor_id = ?
    ORDER BY b.created_at DESC 
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_bookings = mysqli_stmt_get_result($stmt) ?: [];

// // Revenue Analytics (last 6 months) for current admin's hotels
// $stmt = mysqli_prepare($conn, "
//     SELECT 
//         DATE_FORMAT(b.check_in_date, '%Y-%m') as month,
//         SUM(b.total_price) as total_revenue
//     FROM bookings b 
//     JOIN rooms r ON b.room_id = r.id 
//     JOIN hotels h ON b.hotel_id = h.id 
//     WHERE h.vendor_id = ? AND b.check_in_date <= NOW() AND b.booking_status = 'confirmed'
//     GROUP BY DATE_FORMAT(b.check_in_date, '%Y-%m')
//     ORDER BY month DESC 
//     LIMIT 6
// ");
// mysqli_stmt_bind_param($stmt, "i", $user_id);
// mysqli_stmt_execute($stmt);
// $revenue_query = mysqli_stmt_get_result($stmt) ?: [];

// // Prepare revenue data for the chart
// $chartData = [];
// $months = [];
// $currentDate = new DateTime('2025-05-26 18:15:00'); // Current date and time: 06:15 PM PKT, May 26, 2025
// for ($i = 0; $i < 6; $i++) {
//     $monthKey = $currentDate->format('Y-m');
//     $months[$i] = $currentDate->format('M Y');
//     $chartData[$i] = 0;
//     $currentDate->modify('-1 month');
// }

// if (mysqli_num_rows($revenue_query) > 0) {
//     while ($row = mysqli_fetch_assoc($revenue_query)) {
//         $monthKey = $row['month'];
//         $index = array_search($monthKey, array_column($revenueData, 'month'));
//         if ($index !== false) {
//             $chartData[$index] = (float)($row['total_revenue'] ?? 0);
//         }
//     }
// }

// $revenueData = [];
// for ($i = 0; $i < 6; $i++) {
//     $revenueData[] = [
//         'month' => $months[$i],
//         'revenue' => $chartData[$i]
//     ];
// }
// $revenueData = array_reverse($revenueData); // Oldest to newest for chart

// // Generate revenue chart data
// $labels = array_column($revenueData, 'month');
// $revenues = array_column($revenueData, 'revenue');
// ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jhang Hotels</title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card i {
            font-size: 2rem;
            color: #d4a017;
        }
        .stat-card h3 {
            font-size: 1.8rem;
            margin: 10px 0;
        }
        .stat-card p {
            color: #666;
            margin: 0;
        }
        .recent-bookings {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .revenue-chart {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            display: inline-block;
        }
        .status-pending { background-color: #ffc107; color: black; }
        .status-confirmed { background-color: #28a745; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h4 class="mb-4">Admin Panel</h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="nav-link" href="hotels.php"><i class="fas fa-hotel"></i> Hotels</a>
                    <a class="nav-link" href="rooms.php"><i class="fas fa-bed"></i> Rooms</a>
                    <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a>
                    <a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a>
                    <a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
                    <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Dashboard Overview (Admin: <?php echo htmlspecialchars($_SESSION['username']); ?>)</h2>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-hotel"></i>
                            <h3><?php echo htmlspecialchars($stats['hotels']); ?></h3>
                            <p>Total Hotels</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-bed"></i>
                            <h3><?php echo htmlspecialchars($stats['rooms']); ?></h3>
                            <p>Total Rooms</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <h3><?php echo htmlspecialchars($stats['users']); ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-check"></i>
                            <h3><?php echo htmlspecialchars($stats['bookings']); ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                </div>

                <!-- Booking Status Cards -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo htmlspecialchars($stats['pending_bookings']); ?></h3>
                            <p>Pending Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo htmlspecialchars($stats['confirmed_bookings']); ?></h3>
                            <p>Confirmed Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-times-circle"></i>
                            <h3><?php echo htmlspecialchars($stats['cancelled_bookings']); ?></h3>
                            <p>Cancelled Bookings</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="recent-bookings">
                    <h4 class="mb-4">Recent Bookings</h4>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Hotel</th>
                                    <th>Room</th>
                                    <th>Guest</th>
                                    <th>Guests</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($recent_bookings) > 0): ?>
                                    <?php while ($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['hotel_name']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($booking['room_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['adults']) . ' Adults, ' . htmlspecialchars($booking['children']) . ' Children'; ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($booking['check_in_date'])); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($booking['check_out_date'])); ?></td>
                                            <td>PKR <?php echo number_format($booking['total_price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo htmlspecialchars($booking['booking_status']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($booking['booking_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="reservations.php?user_id=<?php echo htmlspecialchars($booking['user_id']); ?>" class="btn btn-custom btn-sm">View</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No recent bookings found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <!-- <div class="revenue-chart">
                    <h4 class="mb-4">Revenue Overview (Last 6 Months)</h4>
                    {
                        "type": "line",
                        "data": {
                            "labels": <?php echo json_encode($labels); ?>,
                            "datasets": [{
                                "label": "Monthly Revenue (PKR)",
                                "data": <?php echo json_encode($revenues); ?>,
                                "borderColor": "#d4a017",
                                "backgroundColor": "rgba(212, 160, 23, 0.2)",
                                "tension": 0.4,
                                "fill": true,
                                "pointBackgroundColor": "#d4a017",
                                "pointBorderColor": "#fff",
                                "pointHoverBackgroundColor": "#fff",
                                "pointHoverBorderColor": "#d4a017"
                            }]
                        },
                        "options": {
                            "responsive": true,
                            "plugins": {
                                "legend": {
                                    "position": "top",
                                    "labels": {
                                        "font": {
                                            "family": "Poppins",
                                            "size": 14
                                        }
                                    }
                                },
                                "tooltip": {
                                    "callbacks": {
                                        "label": function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += 'PKR ' + context.parsed.y.toLocaleString();
                                            return label;
                                        }
                                    }
                                }
                            },
                            "scales": {
                                "y": {
                                    "beginAtZero": true,
                                    "ticks": {
                                        "callback": function(value) {
                                            return 'PKR ' + value.toLocaleString();
                                        },
                                        "font": {
                                            "family": "Poppins",
                                            "size": 12
                                        }
                                    },
                                    "grid": {
                                        "color": "rgba(0, 0, 0, 0.05)"
                                    }
                                },
                                "x": {
                                    "ticks": {
                                        "font": {
                                            "family": "Poppins",
                                            "size": 12
                                        }
                                    },
                                    "grid": {
                                        "display": false
                                    }
                                }
                            }
                        }
                    }
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>