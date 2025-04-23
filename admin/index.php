<?php
include "auth_check.php";
include "../db_connect.php";

// Set default date range (last 7 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));

// Process date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Get summary metrics
$metrics = [
    'total_users' => 0,
    'new_users' => 0,
    'total_sessions' => 0,
    'total_interactions' => 0,
    'avg_response_time' => 0,
    'error_count' => 0
];

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$metrics['total_users'] = $stmt->get_result()->fetch_assoc()['count'];

// New users in date range
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(first_seen) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['new_users'] = $stmt->get_result()->fetch_assoc()['count'];

// Total sessions in date range
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions WHERE DATE(start_time) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['total_sessions'] = $stmt->get_result()->fetch_assoc()['count'];

// Total interactions in date range
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['total_interactions'] = $stmt->get_result()->fetch_assoc()['count'];

// Average response time in date range
$stmt = $conn->prepare("SELECT AVG(response_time) as avg_time FROM chat_messages WHERE DATE(timestamp) BETWEEN ? AND ? AND role = 'assistant' AND response_time IS NOT NULL");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$avgTime = $stmt->get_result()->fetch_assoc()['avg_time'];
$metrics['avg_response_time'] = $avgTime ? round($avgTime, 2) : 0;

// Error count in date range
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM error_logs WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['error_count'] = $stmt->get_result()->fetch_assoc()['count'];

// Get daily metrics for chart
$dailyData = [];
$stmt = $conn->prepare("SELECT 
    DATE(timestamp) as date, 
    COUNT(*) as message_count 
    FROM chat_messages 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY DATE(timestamp) 
    ORDER BY date ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dailyData[] = $row;
}

// Get top user agents
$userAgents = [];
$stmt = $conn->prepare("SELECT 
    user_agent, 
    COUNT(*) as count 
    FROM users 
    GROUP BY user_agent 
    ORDER BY count DESC 
    LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $userAgents[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AI Bot Analytics</title>
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
        .stat-card {
            text-align: center;
            padding: 15px;
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }
        .stat-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        .navbar {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
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
                    <h1 class="h2">Dashboard Overview</h1>
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
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_users']); ?></div>
                            <div class="stat-description">All time</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">New Users</div>
                            <div class="stat-value"><?php echo number_format($metrics['new_users']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Total Sessions</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_sessions']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Total Interactions</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_interactions']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Avg Response Time</div>
                            <div class="stat-value"><?php echo $metrics['avg_response_time']; ?> s</div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Error Count</div>
                            <div class="stat-value"><?php echo number_format($metrics['error_count']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Daily Interactions</div>
                            <div class="card-body">
                                <canvas id="interactionsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Top User Agents</div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($userAgents as $agent): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars(substr($agent['user_agent'], 0, 40) . (strlen($agent['user_agent']) > 40 ? '...' : '')); ?>
                                            <span class="badge bg-primary rounded-pill"><?php echo $agent['count']; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (empty($userAgents)): ?>
                                        <li class="list-group-item">No data available</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily interactions chart
        const dailyData = <?php echo json_encode($dailyData); ?>;
        const dates = dailyData.map(item => item.date);
        const counts = dailyData.map(item => item.message_count);

        const ctx = document.getElementById('interactionsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Daily Interactions',
                    data: counts,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.1
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
    </script>
</body>
</html>