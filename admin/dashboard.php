<?php
require_once '../config/database.php';
require_once '../includes/session.php';

// Require admin role
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get analytics data
$stats = array();

// Total Hotels
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM hotels");
$stats['hotels'] = mysqli_fetch_assoc($result)['total'];

// Total Rooms
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM rooms");
$stats['rooms'] = mysqli_fetch_assoc($result)['total'];

// Total Users
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$stats['users'] = mysqli_fetch_assoc($result)['total'];

// Total Bookings
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings");
$stats['bookings'] = mysqli_fetch_assoc($result)['total'];

// Pending Bookings
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'pending'");
$stats['pending_bookings'] = mysqli_fetch_assoc($result)['total'];

// Confirmed Bookings
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'confirmed'");
$stats['confirmed_bookings'] = mysqli_fetch_assoc($result)['total'];

// Cancelled Bookings
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE booking_status = 'cancelled'");
$stats['cancelled_bookings'] = mysqli_fetch_assoc($result)['total'];

// Recent Bookings
$recent_bookings = mysqli_query($conn, "
    SELECT b.*, r.room_type, h.name as hotel_name, u.username 
    FROM bookings b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hotels h ON b.hotel_id = h.id 
    JOIN users u ON b.user_id = u.id 
    ORDER BY b.check_in_date DESC 
    LIMIT 5
");

// Revenue Analytics
$revenue = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(check_in_date, '%Y-%m') as month,
        SUM(total_price) as total_revenue
    FROM bookings 
    GROUP BY DATE_FORMAT(check_in_date, '%Y-%m')
    ORDER BY month DESC 
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jhang Hotels</title>
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <h4 class="mb-4">Admin Panel</h4>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link" href="hotels.php">
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
                <h2 class="mb-4">Dashboard Overview</h2>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-hotel"></i>
                            <h3><?php echo $stats['hotels']; ?></h3>
                            <p>Total Hotels</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-bed"></i>
                            <h3><?php echo $stats['rooms']; ?></h3>
                            <p>Total Rooms</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-users"></i>
                            <h3><?php echo $stats['users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-calendar-check"></i>
                            <h3><?php echo $stats['bookings']; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </div>
                </div>

                <!-- Booking Status Cards -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-clock"></i>
                            <h3><?php echo $stats['pending_bookings']; ?></h3>
                            <p>Pending Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-check-circle"></i>
                            <h3><?php echo $stats['confirmed_bookings']; ?></h3>
                            <p>Confirmed Bookings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-times-circle"></i>
                            <h3><?php echo $stats['cancelled_bookings']; ?></h3>
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
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['hotel_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['check_in_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($booking['check_out_date'])); ?></td>
                                    <td>PKR <?php echo number_format($booking['total_price'], 2); ?></td>
                                    <td>
                                        <a href="reservations.php?id=<?php echo $booking['id']; ?>" class="btn btn-custom btn-sm">View</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="revenue-chart">
                    <h4 class="mb-4">Revenue Overview</h4>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php 
            $chartData = array();
            while($row = mysqli_fetch_assoc($revenue)) {
                $chartData[] = array(
                    'month' => date('M Y', strtotime($row['month'] . '-01')),
                    'revenue' => $row['total_revenue']
                );
            }
            echo json_encode(array_reverse($chartData));
        ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => item.month),
                datasets: [{
                    label: 'Monthly Revenue',
                    data: revenueData.map(item => item.revenue),
                    borderColor: '#d4a017',
                    backgroundColor: 'rgba(212, 160, 23, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'PKR ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 