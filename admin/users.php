<?php
include "auth_check.php"; // Check admin authentication
include "../db_connect.php"; // Database connection

// Set default date range (last 30 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

// Process date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total user count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$totalUsers = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get users with pagination
$users = [];
$stmt = $conn->prepare("SELECT 
    user_id, 
    first_seen, 
    last_seen, 
    user_agent, 
    ip_address, 
    session_count 
    FROM users 
    ORDER BY last_seen DESC 
    LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Get user activity by date
$userActivity = [];
$stmt = $conn->prepare("SELECT 
    DATE(first_seen) as date, 
    COUNT(*) as new_users 
    FROM users 
    WHERE DATE(first_seen) BETWEEN ? AND ? 
    GROUP BY DATE(first_seen) 
    ORDER BY date ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $userActivity[] = $row;
}

// Get device type distribution
$deviceTypes = [];
$stmt = $conn->prepare("SELECT 
    s.device_type, 
    COUNT(*) as count 
    FROM sessions s 
    JOIN users u ON s.user_id = u.user_id 
    WHERE DATE(s.start_time) BETWEEN ? AND ? 
    GROUP BY s.device_type 
    ORDER BY count DESC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $deviceTypes[] = $row;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Analytics - AI Bot Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #343a40;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            font-weight: 500;
            color: #fff;
            padding: .75rem 1rem;
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, .1);
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, .1);
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        main {
            padding-top: 30px;
        }
        .card {
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            font-weight: 600;
        }
        .navbar {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .user-id-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">AI Bot Analytics Dashboard</a>
            <div class="d-flex text-white">
                <span class="me-2">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="logout.php" class="text-white text-decoration-none"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="interactions.php">
                                <i class="bi bi-chat-dots"></i> Interactions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="performance.php">
                                <i class="bi bi-graph-up"></i> Performance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="errors.php">
                                <i class="bi bi-exclamation-triangle"></i> Errors
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom mt-4">
                    <h1 class="h2">User Analytics</h1>
                    <form class="d-flex" method="GET">
                        <div class="input-group me-2">
                            <span class="input-group-text">From</span>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="input-group me-2">
                            <span class="input-group-text">To</span>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">New Users Over Time</div>
                            <div class="card-body">
                                <canvas id="userActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Device Type Distribution</div>
                            <div class="card-body">
                                <canvas id="deviceTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">User List</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>First Seen</th>
                                        <th>Last Seen</th>
                                        <th>Sessions</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="user-id-cell" title="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                <?php echo htmlspecialchars($user['user_id']); ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['first_seen'])); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['last_seen'])); ?></td>
                                            <td><?php echo $user['session_count']; ?></td>
                                            <td><?php echo htmlspecialchars($user['ip_address']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="User pagination">
                                <ul class="pagination">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User activity chart
        const userActivity = <?php echo json_encode($userActivity); ?>;
        const dates = userActivity.map(item => item.date);
        const newUsers = userActivity.map(item => item.new_users);

        const ctx = document.getElementById('userActivityChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [{
                    label: 'New Users',
                    data: newUsers,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Device type chart
        const deviceTypes = <?php echo json_encode($deviceTypes); ?>;
        const deviceLabels = deviceTypes.map(item => item.device_type || 'unknown');
        const deviceCounts = deviceTypes.map(item => item.count);

        const deviceCtx = document.getElementById('deviceTypeChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceCounts,
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(25, 135, 84, 0.7)',
                        'rgba(255, 193, 7, 0.7)'
                    ],
                    borderColor: [
                        'rgba(13, 110, 253, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(25, 135, 84, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>