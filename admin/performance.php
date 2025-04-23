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

// Get performance metrics
$metrics = [
    'total_api_calls' => 0,
    'success_rate' => 0,
    'avg_response_time' => 0,
    'min_response_time' => 0,
    'max_response_time' => 0,
    'p95_response_time' => 0
];

// Total API calls
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM api_performance WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['total_api_calls'] = $stmt->get_result()->fetch_assoc()['count'];

// Success rate
if ($metrics['total_api_calls'] > 0) {
    $stmt = $conn->prepare("SELECT 
        (SUM(is_success) / COUNT(*)) * 100 as success_rate 
        FROM api_performance 
        WHERE DATE(timestamp) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $metrics['success_rate'] = round($stmt->get_result()->fetch_assoc()['success_rate'], 2);
}

// Response time stats
if ($metrics['total_api_calls'] > 0) {
    $stmt = $conn->prepare("SELECT 
        AVG(response_time) as avg_time,
        MIN(response_time) as min_time,
        MAX(response_time) as max_time
        FROM api_performance 
        WHERE DATE(timestamp) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $metrics['avg_response_time'] = round($result['avg_time'], 2);
    $metrics['min_response_time'] = round($result['min_time'], 2);
    $metrics['max_response_time'] = round($result['max_time'], 2);
    
    // Calculate p95 (95th percentile) response time
    // This is an approximation since MySQL doesn't have a direct percentile function
    $stmt = $conn->prepare("SELECT response_time 
        FROM api_performance 
        WHERE DATE(timestamp) BETWEEN ? AND ? 
        ORDER BY response_time ASC");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responseTimes = [];
    while ($row = $result->fetch_assoc()) {
        $responseTimes[] = $row['response_time'];
    }
    
    if (count($responseTimes) > 0) {
        $index = ceil(count($responseTimes) * 0.95) - 1;
        $metrics['p95_response_time'] = round($responseTimes[$index], 2);
    }
}

// Get daily performance data for chart
$dailyPerformance = [];
$stmt = $conn->prepare("SELECT 
    DATE(timestamp) as date, 
    AVG(response_time) as avg_time,
    SUM(is_success) / COUNT(*) * 100 as success_rate
    FROM api_performance 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY DATE(timestamp) 
    ORDER BY date ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dailyPerformance[] = $row;
}

// Get HTTP error codes distribution
$errorCodes = [];
$stmt = $conn->prepare("SELECT 
    http_code, 
    COUNT(*) as count 
    FROM api_performance 
    WHERE is_success = 0 AND DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY http_code 
    ORDER BY count DESC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $errorCodes[] = $row;
}

// Get response time distribution
$timeDistribution = [];
$stmt = $conn->prepare("SELECT 
    CASE 
        WHEN response_time < 0.5 THEN '< 0.5s'
        WHEN response_time < 1 THEN '0.5s - 1s'
        WHEN response_time < 2 THEN '1s - 2s'
        WHEN response_time < 3 THEN '2s - 3s'
        WHEN response_time < 5 THEN '3s - 5s'
        ELSE '> 5s'
    END as time_range,
    COUNT(*) as count
    FROM api_performance 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY time_range 
    ORDER BY MIN(response_time) ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $timeDistribution[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics - AI Bot Dashboard</title>
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
        .performance-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .performance-indicator .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .performance-indicator .good {
            background-color: #198754;
        }
        .performance-indicator .warning {
            background-color: #ffc107;
        }
        .performance-indicator .bad {
            background-color: #dc3545;
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
                            <a class="nav-link active" href="performance.php">
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
                    <h1 class="h2">API Performance Analytics</h1>
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
                            <div class="stat-label">Total API Calls</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_api_calls']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Success Rate</div>
                            <div class="stat-value"><?php echo $metrics['success_rate']; ?>%</div>
                            <div class="stat-description">API calls succeeded</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">Avg Response Time</div>
                            <div class="stat-value"><?php echo $metrics['avg_response_time']; ?>s</div>
                            <div class="stat-description">Average API response</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="stat-label">P95 Response Time</div>
                            <div class="stat-value"><?php echo $metrics['p95_response_time']; ?>s</div>
                            <div class="stat-description">95th percentile</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">Daily Performance</div>
                            <div class="card-body">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">Response Time Distribution</div>
                            <div class="card-body">
                                <canvas id="timeDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Response Time Details</div>
                            <div class="card-body">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <th>Minimum Response Time</th>
                                            <td><?php echo $metrics['min_response_time']; ?>s</td>
                                        </tr>
                                        <tr>
                                            <th>Average Response Time</th>
                                            <td><?php echo $metrics['avg_response_time']; ?>s</td>
                                        </tr>
                                        <tr>
                                            <th>95th Percentile</th>
                                            <td><?php echo $metrics['p95_response_time']; ?>s</td>
                                        </tr>
                                        <tr>
                                            <th>Maximum Response Time</th>
                                            <td><?php echo $metrics['max_response_time']; ?>s</td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <h6 class="mt-4">Performance Assessment</h6>
                                <div class="performance-indicator">
                                    <div class="indicator <?php echo $metrics['avg_response_time'] < 1 ? 'good' : ($metrics['avg_response_time'] < 3 ? 'warning' : 'bad'); ?>"></div>
                                    <div>Average Response Time: <?php echo $metrics['avg_response_time'] < 1 ? 'Good' : ($metrics['avg_response_time'] < 3 ? 'Acceptable' : 'Needs Improvement'); ?></div>
                                </div>
                                <div class="performance-indicator">
                                    <div class="indicator <?php echo $metrics['success_rate'] > 98 ? 'good' : ($metrics['success_rate'] > 90 ? 'warning' : 'bad'); ?>"></div>
                                    <div>Success Rate: <?php echo $metrics['success_rate'] > 98 ? 'Good' : ($metrics['success_rate'] > 90 ? 'Acceptable' : 'Needs Improvement'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Error Code Distribution</div>
                            <div class="card-body">
                                <?php if (empty($errorCodes)): ?>
                                    <p class="text-center">No errors recorded in the selected period.</p>
                                <?php else: ?>
                                    <canvas id="errorCodesChart"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Performance Recommendations</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php if ($metrics['avg_response_time'] > 3): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                                    <strong>High Response Time:</strong> Consider optimizing API calls or implementing caching strategies.
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($metrics['success_rate'] < 95): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                                    <strong>Low Success Rate:</strong> Investigate error patterns and implement better error handling.
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($metrics['p95_response_time'] > 5): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                                    <strong>High P95 Response Time:</strong> Some requests are taking too long. Consider implementing timeouts or optimizing slow requests.
                                </li>
                            <?php endif; ?>
                            
                            <?php if (!empty($errorCodes)): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                                    <strong>Error Codes Detected:</strong> Address the most common error codes: 
                                    <?php 
                                    $topErrors = array_slice($errorCodes, 0, 3);
                                    $errorList = [];
                                    foreach ($topErrors as $error) {
                                        $errorList[] = "HTTP {$error['http_code']} ({$error['count']} occurrences)";
                                    }
                                    echo implode(", ", $errorList);
                                    ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($metrics['avg_response_time'] <= 3 && $metrics['success_rate'] >= 95 && empty($errorCodes)): ?>
                                <li class="list-group-item">
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Good Performance:</strong> API is performing well. Continue monitoring for any changes.
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily performance chart
        const performanceData = <?php echo json_encode($dailyPerformance); ?>;
        const dates = performanceData.map(item => item.date);
        const avgTimes = performanceData.map(item => parseFloat(item.avg_time));
        const successRates = performanceData.map(item => parseFloat(item.success_rate));

        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Avg Response Time (s)',
                        data: avgTimes,
                        backgroundColor: 'rgba(13, 110, 253, 0.2)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.1
                    },
                    {
                        label: 'Success Rate (%)',
                        data: successRates,
                        backgroundColor: 'rgba(25, 135, 84, 0.2)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 2,
                        yAxisID: 'y1',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Response Time (s)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Success Rate (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Response time distribution chart
        const timeDistribution = <?php echo json_encode($timeDistribution); ?>;
        const timeRanges = timeDistribution.map(item => item.time_range);
        const timeCounts = timeDistribution.map(item => item.count);

        const timeDistCtx = document.getElementById('timeDistributionChart').getContext('2d');
        new Chart(timeDistCtx, {
            type: 'pie',
            data: {
                labels: timeRanges,
                datasets: [{
                    data: timeCounts,
                    backgroundColor: [
                        'rgba(25, 135, 84, 0.7)',
                        'rgba(13, 202, 240, 0.7)',
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
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

        <?php if (!empty($errorCodes)): ?>
        // Error codes chart
        const errorCodes = <?php echo json_encode($errorCodes); ?>;
        const codes = errorCodes.map(item => 'HTTP ' + item.http_code);
        const counts = errorCodes.map(item => item.count);

        const errorCtx = document.getElementById('errorCodesChart').getContext('2d');
        new Chart(errorCtx, {
            type: 'bar',
            data: {
                labels: codes,
                datasets: [{
                    label: 'Error Count',
                    data: counts,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
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
        <?php endif; ?>
    </script>
</body>
</html>