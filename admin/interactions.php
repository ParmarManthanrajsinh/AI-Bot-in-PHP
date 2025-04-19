<?php
include "auth_check.php"; // Check admin authentication
include "../db_connect.php"; // Database connection

// Set default date range (last 7 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));

// Process date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// Get interaction metrics
$metrics = [
    'total_messages' => 0,
    'user_messages' => 0,
    'assistant_messages' => 0,
    'avg_response_time' => 0,
    'avg_token_count' => 0
];

// Total messages
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['total_messages'] = $stmt->get_result()->fetch_assoc()['count'];

// User messages
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE role = 'user' AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['user_messages'] = $stmt->get_result()->fetch_assoc()['count'];

// Assistant messages
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE role = 'assistant' AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['assistant_messages'] = $stmt->get_result()->fetch_assoc()['count'];

// Average response time
$stmt = $conn->prepare("SELECT AVG(response_time) as avg_time FROM chat_messages WHERE role = 'assistant' AND response_time IS NOT NULL AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$avgTime = $stmt->get_result()->fetch_assoc()['avg_time'];
$metrics['avg_response_time'] = $avgTime ? round($avgTime, 2) : 0;

// Average token count
$stmt = $conn->prepare("SELECT AVG(token_count) as avg_tokens FROM chat_messages WHERE token_count IS NOT NULL AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$avgTokens = $stmt->get_result()->fetch_assoc()['avg_tokens'];
$metrics['avg_token_count'] = $avgTokens ? round($avgTokens) : 0;

// Get hourly activity for heatmap
$hourlyActivity = [];
$stmt = $conn->prepare("SELECT 
    HOUR(timestamp) as hour, 
    COUNT(*) as message_count 
    FROM chat_messages 
    WHERE DATE(timestamp) BETWEEN ? AND ? 
    GROUP BY HOUR(timestamp) 
    ORDER BY hour ASC");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Initialize all hours with zero
for ($i = 0; $i < 24; $i++) {
    $hourlyActivity[$i] = 0;
}

// Fill in actual data
while ($row = $result->fetch_assoc()) {
    $hourlyActivity[(int)$row['hour']] = (int)$row['message_count'];
}

// Get most common words in user messages
$commonWords = [];
$stmt = $conn->prepare("SELECT 
    content 
    FROM chat_messages 
    WHERE role = 'user' AND DATE(timestamp) BETWEEN ? AND ? 
    LIMIT 1000"); // Limit to prevent excessive processing
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$allWords = [];
$stopWords = ['a', 'an', 'the', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 
              'in', 'on', 'at', 'to', 'for', 'with', 'by', 'about', 'against', 'between', 'into', 'through', 
              'during', 'before', 'after', 'above', 'below', 'from', 'up', 'down', 'of', 'off', 'over', 'under', 
              'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'any', 
              'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 
              'same', 'so', 'than', 'too', 'very', 'can', 'will', 'just', 'should', 'now'];

while ($row = $result->fetch_assoc()) {
    $content = strtolower($row['content']);
    $content = preg_replace('/[^\w\s]/', '', $content); // Remove punctuation
    $words = preg_split('/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
    
    foreach ($words as $word) {
        if (strlen($word) > 2 && !in_array($word, $stopWords)) {
            if (isset($allWords[$word])) {
                $allWords[$word]++;
            } else {
                $allWords[$word] = 1;
            }
        }
    }
}

arsort($allWords);
$commonWords = array_slice($allWords, 0, 20, true);

// Get recent conversations
$conversations = [];
$stmt = $conn->prepare("SELECT 
    c.id, 
    c.role, 
    c.content, 
    c.timestamp, 
    c.user_id, 
    c.session_id, 
    c.response_time 
    FROM chat_messages c 
    WHERE DATE(c.timestamp) BETWEEN ? AND ? 
    ORDER BY c.timestamp DESC 
    LIMIT 100");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}

// Group conversations by session
$groupedConversations = [];
foreach ($conversations as $message) {
    $sessionId = $message['session_id'] ?: 'unknown';
    if (!isset($groupedConversations[$sessionId])) {
        $groupedConversations[$sessionId] = [];
    }
    $groupedConversations[$sessionId][] = $message;
}

// Sort each conversation by timestamp
foreach ($groupedConversations as $sessionId => $messages) {
    usort($groupedConversations[$sessionId], function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interaction Analytics - AI Bot Dashboard</title>
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
        .heatmap-container {
            display: grid;
            grid-template-columns: repeat(24, 1fr);
            gap: 2px;
            margin: 20px 0;
        }
        .heatmap-cell {
            aspect-ratio: 1;
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: white;
            position: relative;
        }
        .heatmap-cell:hover::after {
            content: attr(data-count) " messages";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 10;
        }
        .heatmap-legend {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .conversation-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 80%;
        }
        .message.user {
            background-color: #f1f1f1;
            margin-right: auto;
        }
        .message.assistant {
            background-color: #d1e7ff;
            margin-left: auto;
        }
        .message-meta {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .session-header {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 0.9rem;
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
                            <a class="nav-link active" href="interactions.php">
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
                    <h1 class="h2">Interaction Analytics</h1>
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
                            <div class="stat-label">Total Messages</div>
                            <div class="stat-value"><?php echo number_format($metrics['total_messages']); ?></div>
                            <div class="stat-description">In selected period</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Avg Response Time</div>
                            <div class="stat-value"><?php echo $metrics['avg_response_time']; ?> s</div>
                            <div class="stat-description">For assistant messages</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="stat-label">Avg Token Count</div>
                            <div class="stat-value"><?php echo number_format($metrics['avg_token_count']); ?></div>
                            <div class="stat-description">Per message</div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Message Distribution</div>
                            <div class="card-body">
                                <canvas id="messageDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">Common Words in User Messages</div>
                            <div class="card-body">
                                <canvas id="wordFrequencyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Hourly Activity Heatmap</div>
                    <div class="card-body">
                        <div class="heatmap-container" id="hourlyHeatmap">
                            <?php 
                            $maxCount = max($hourlyActivity);
                            for ($hour = 0; $hour < 24; $hour++) {
                                $count = $hourlyActivity[$hour];
                                $intensity = $maxCount > 0 ? ($count / $maxCount) : 0;
                                $r = 13;
                                $g = 110;
                                $b = 253;
                                $alpha = 0.2 + ($intensity * 0.8);
                                $bgcolor = "rgba($r, $g, $b, $alpha)";
                                echo "<div class='heatmap-cell' style='background-color: $bgcolor;' data-count='$count'>$hour</div>";
                            }
                            ?>
                        </div>
                        <div class="heatmap-legend">
                            <span>12 AM</span>
                            <span>6 AM</span>
                            <span>12 PM</span>
                            <span>6 PM</span>
                            <span>11 PM</span>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Recent Conversations</div>
                    <div class="card-body conversation-container">
                        <?php if (empty($groupedConversations)): ?>
                            <p class="text-center">No conversations found in the selected period.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($groupedConversations, 0, 5) as $sessionId => $messages): ?>
                                <div class="session-header">
                                    <strong>Session:</strong> <?php echo htmlspecialchars(substr($sessionId, 0, 8) . '...'); ?>
                                    <span class="float-end"><?php echo date('Y-m-d H:i', strtotime($messages[0]['timestamp'])); ?></span>
                                </div>
                                <div class="conversation">
                                    <?php foreach (array_slice($messages, 0, 6) as $message): ?>
                                        <div class="message <?php echo $message['role']; ?>">
                                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                            <div class="message-meta">
                                                <?php echo date('H:i:s', strtotime($message['timestamp'])); ?>
                                                <?php if ($message['role'] == 'assistant' && $message['response_time']): ?>
                                                    <span class="ms-2"><?php echo round($message['response_time'], 2); ?>s</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($messages) > 6): ?>
                                        <p class="text-center text-muted"><small>... <?php echo count($messages) - 6; ?> more messages ...</small></p>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Message distribution chart
        const messageDistributionCtx = document.getElementById('messageDistributionChart').getContext('2d');
        new Chart(messageDistributionCtx, {
            type: 'pie',
            data: {
                labels: ['User Messages', 'Assistant Messages'],
                datasets: [{
                    data: [<?php echo $metrics['user_messages']; ?>, <?php echo $metrics['assistant_messages']; ?>],
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(13, 110, 253, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 193, 7, 1)',
                        'rgba(13, 110, 253, 1)'
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

        // Word frequency chart
        const wordFrequencyCtx = document.getElementById('wordFrequencyChart').getContext('2d');
        new Chart(wordFrequencyCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("', '", array_keys(array_slice($commonWords, 0, 10))) . "'"; ?>],
                datasets: [{
                    label: 'Frequency',
                    data: [<?php echo implode(", ", array_slice($commonWords, 0, 10)); ?>],
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
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