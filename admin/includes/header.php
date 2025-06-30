<?php
// Use absolute path to ensure correct inclusion
$project_root = dirname(__DIR__, 2); // Go up two levels from admin/includes/ to reach HMS directory
require_once $project_root . '/config/database.php';
require_once $project_root . '/includes/session.php';
require_once __DIR__ . '/auth.php';

// Verify database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in header.php: " . (isset($conn) ? $conn->connect_error : 'No connection object'), 3, $project_root . '/error.log');
    die("Database connection error. Please try again later.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Jhang Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
            color: white;
            padding: 0;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 12px 20px;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #d4a017;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .unread-chat-count {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 10px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Return to Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="chat.php">
                            <i class="fas fa-comments"></i> Chats
                            <?php
                            // Get unread message count for the admin's hotel
                            $unread_count = 0;
                            $vendor_id = $_SESSION['user_id'];
                            $stmt = $conn->prepare("
                                SELECT COUNT(m.id) as unread_count
                                FROM messages m
                                JOIN conversations c ON m.conversation_id = c.id
                                JOIN hotels h ON c.hotel_id = h.id
                                WHERE h.vendor_id = ? AND m.sender_type = 'user' AND m.is_read = FALSE
                            ");
                            if ($stmt === false) {
                                error_log("Prepare failed for unread count: " . $conn->error, 3, $project_root . '/error.log');
                            } else {
                                $stmt->bind_param("i", $vendor_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($row = $result->fetch_assoc()) {
                                    $unread_count = $row['unread_count'];
                                }
                                $stmt->close();
                            }
                            if ($unread_count > 0): ?>
                                <span class="unread-chat-count"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-md-10">
                <!-- Page content will be inserted here -->
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Enable Bootstrap tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Toggle sidebar on mobile
            document.querySelector('.navbar-toggler').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('show');
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var fade = new bootstrap.Alert(alert);
                fade.close();
            });
        }, 5000);
    </script>
</body>
</html>