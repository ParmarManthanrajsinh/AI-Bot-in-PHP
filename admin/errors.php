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

// Get error metrics
$metrics = [
    'total_errors' => 0,
    'unique_users_affected' => 0,
    'most_common_error' => 'None',
    'error_rate' => 0
];

// Total errors
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM error_logs WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['total_errors'] = $stmt->get_result()->fetch_assoc()['count'];

// Unique users affected
$stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM error_logs WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['unique_users_affected'] = $stmt->get_result()->fetch_assoc()['count'];

// Most common error type
if ($metrics['total_errors'] > 0) {
    $stmt = $conn->prepare("SELECT 
        error_type, 
        COUNT(*) as count 
        FROM error_logs 
        WHERE DATE(timestamp) BETWEEN ? AND ? 
        GROUP BY error_type 
        ORDER BY count DESC 
        LIMIT 1");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $metrics['most_common_error'] = $result->fetch_assoc()['error_type'];
    }
}

// Error rate (errors / total interactions)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$totalInteractions = $stmt->get_result()->fetch_assoc()['count'];

if ($totalInteractions > 0) {
    $metrics['error_rate'] = round(($metrics['total_errors'] / $totalInteractions) * 100, 2);
}

// Get daily error data for chart
$dailyErrors = [];
$stmt = $conn->prepare("SELECT 
    DATE(timestamp) as date, 
    COUNT(*) as error_count 
    FROM error_logs 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY DATE(timestamp) 
    ORDER BY date ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dailyErrors[] = $row;
}

// Get error types distribution
$errorTypes = [];
$stmt = $conn->prepare("SELECT 
    error_type, 
    COUNT(*) as count 
    FROM error_logs 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY error_type 
    ORDER BY count DESC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $errorTypes[] = $row;
}

// Get recent errors
$recentErrors = [];
$stmt = $conn->prepare("SELECT 
    e.error_id, 
    e.user_id, 
    e.session_id, 
    e.error_type, 
    e.error_message, 
    e.timestamp 
    FROM error_logs e 
    WHERE DATE(e.timestamp) BETWEEN ? AND ? 
    ORDER BY e.timestamp DESC 
    LIMIT 50");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentErrors[] = $row;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Analytics - AI Bot Dashboard</title>
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
        .error-message {
            max-width: 500px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .error-message:hover {
            white-space: normal;
            overflow: visible;
        }
        .error-type-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
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
                            <a class="nav-link active" href="errors.php">
                                <i class="bi bi-exclamation-triangle"></i> Errors
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom mt-4">
                    <h1 class="h2">Error Analytics</h1>
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
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Total Errors</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_errors']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Users Affected</div>
                            <div class="stat-value"><?php echo number_format($metrics['unique_users_affected']); ?></div>
                            <div class="stat-description">Unique users</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Error Rate</div>
                            <div class="stat-value"><?php echo $metrics['error_rate']; ?>%</div>
                            <div class="stat-description">Of total interactions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Most Common Error</div>
                            <div class="stat-value" style="font-size: 1.2rem;"><?php echo htmlspecialchars($metrics['most_common_error']); ?></div>
                            <div class="stat-description">By frequency</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Daily Error Trend</div>
                            <div class="card-body">
                                <canvas id="errorTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Error Type Distribution</div>
                            <div class="card-body">
                                <canvas id="errorTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Recent Errors</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Error Type</th>
                                        <th>Error Message</th>
                                        <th>User ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentErrors as $error): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($error['timestamp'])); ?></td>
                                            <td>
                                                <span class="badge bg-danger error-type-badge">
                                                    <?php echo htmlspecialchars($error['error_type']); ?>
                                                </span>
                                            </td>
                                            <td class="error-message" title="<?php echo htmlspecialchars($error['error_message']); ?>">
                                                <?php echo htmlspecialchars($error['error_message']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($error['user_id'], 0, 8) . '...'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recentErrors)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No errors found in the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Error Analysis & Recommendations</div>
                    <div class="card-body">
                        <?php if ($metrics['total_errors'] > 0): ?>
                            <h5>Key Findings</h5>
                            <ul>
                                <?php if ($metrics['error_rate'] > 5): ?>
                                    <li class="text-danger">High error rate (<?php echo $metrics['error_rate']; ?>%) indicates significant issues with the system.</li>
                                <?php elseif ($metrics['error_rate'] > 1): ?>
                                    <li class="text-warning">Moderate error rate (<?php echo $metrics['error_rate']; ?>%) suggests some issues that need attention.</li>
                                <?php else: ?>
                                    <li class="text-success">Low error rate (<?php echo $metrics['error_rate']; ?>%) indicates the system is generally stable.</li>
                                <?php endif; ?>
                                
                                <li>Most common error type: <strong><?php echo htmlspecialchars($metrics['most_common_error']); ?></strong></li>
                                
                                <?php if ($metrics['unique_users_affected'] > 10): ?>
                                    <li class="text-warning">A significant number of users (<?php echo $metrics['unique_users_affected']; ?>) are experiencing errors.</li>
                                <?php endif; ?>
                            </ul>
                            
                            <h5 class="mt-4">Recommendations</h5>
                            <ul>
                                <?php if (strpos(strtolower($metrics['most_common_error']), 'api') !== false): ?>
                                    <li>Implement better API error handling and retry mechanisms.</li>
                                    <li>Consider implementing a fallback response system when the API is unavailable.</li>
                                <?php endif; ?>
                                
                                <?php if (strpos(strtolower($metrics['most_common_error']), 'timeout') !== false): ?>
                                    <li>Review and optimize API timeout settings.</li>
                                    <li>Implement progressive loading or chunked responses for long queries.</li>
                                <?php endif; ?>
                                
                                <?php if ($metrics['error_rate'] > 2): ?>
                                    <li>Set up automated alerts for error spikes.</li>
                                    <li>Implement more comprehensive error logging with context information.</li>
                                <?php endif; ?>
                                
                                <li>Regularly review error logs to identify patterns and recurring issues.</li>
                                <li>Consider implementing user feedback collection after errors to understand impact.</li>
                            </ul>
                        <?php else: ?>
                            <p class="text-center">No errors recorded in the selected period. The system appears to be functioning correctly.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Error trend chart
        const errorData = <?php echo json_encode($dailyErrors); ?>;
        const dates = errorData.map(item => item.date);
        const errorCounts = errorData.map(item => item.error_count);

        const ctx = document.getElementById('errorTrendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Daily Errors',
                    data: errorCounts,
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    borderColor: 'rgba(220, 53, 69, 1)',
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

        // Error type distribution chart
        const errorTypes = <?php echo json_encode($errorTypes); ?>;
        const types = errorTypes.map(item => item.error_type);
        const counts = errorTypes.map(item => item.count);

        const typeCtx = document.getElementById('errorTypeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: types,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(25, 135, 84, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
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