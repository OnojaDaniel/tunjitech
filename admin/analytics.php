<?php
// Start session if not already started
//if (session_status() == PHP_SESSION_NONE) {
//    session_start();
//}

// Define root path and include config
define('ROOT_PATH', dirname(dirname(__FILE__)));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdminOrSubAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Get all alerts for admin
$all_alerts = getAllAlerts();
$total_alerts = count($all_alerts);

// Get client statistics
$client_stats = getClientStatistics();

// Get alert statistics for different time periods
$daily_stats = getAdminAlertStats('day');
$weekly_stats = getAdminAlertStats('week');
$monthly_stats = getAdminAlertStats('month');
$yearly_stats = getAdminAlertStats('year');

// Get alerts by category
$category_stats = getAdminAlertStatsByCategory();

// Get monthly trend data
$monthly_trend = getAdminMonthlyTrend();

// Get client registration trends
$client_registration_trend = getClientRegistrationTrend();

// Get alert status statistics
$alert_status_stats = getAlertStatusStats();

/**
 * Get all alerts for admin
 */
function getAllAlerts() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM security_alerts ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get client statistics
 */
function getClientStatistics() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_clients,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_clients,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_clients,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_clients,
            SUM(CASE WHEN user_type = 'client_individual' THEN 1 ELSE 0 END) as individual_clients,
            SUM(CASE WHEN user_type = 'client_company' THEN 1 ELSE 0 END) as company_clients
        FROM users 
        WHERE user_type LIKE 'client_%'
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get alert statistics for admin
 */
function getAdminAlertStats($period = 'month') {
    global $pdo;

    $dateCondition = "";
    switch ($period) {
        case 'day':
            $dateCondition = " WHERE DATE(sa.created_at) = CURDATE()";
            break;
        case 'week':
            $dateCondition = " WHERE YEARWEEK(sa.created_at) = YEARWEEK(CURDATE())";
            break;
        case 'month':
            $dateCondition = " WHERE YEAR(sa.created_at) = YEAR(CURDATE()) AND MONTH(sa.created_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $dateCondition = " WHERE YEAR(sa.created_at) = YEAR(CURDATE())";
            break;
    }

    $stmt = $pdo->prepare("
        SELECT 
            severity,
            COUNT(*) as count
        FROM security_alerts sa" . $dateCondition . "
        GROUP BY severity
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get alert statistics by category for admin
 */
function getAdminAlertStatsByCategory() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            categories,
            COUNT(*) as count
        FROM security_alerts
        GROUP BY categories
        ORDER BY count DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly trend data for admin
 */
function getAdminMonthlyTrend() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
        FROM security_alerts
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get client registration trend
 */
function getClientRegistrationTrend() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as registrations,
            SUM(CASE WHEN user_type = 'client_individual' THEN 1 ELSE 0 END) as individual,
            SUM(CASE WHEN user_type = 'client_company' THEN 1 ELSE 0 END) as company
        FROM users 
        WHERE user_type LIKE 'client_%'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get alert status statistics
 */
function getAlertStatusStats() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT sa.id) as total_alerts,
            COUNT(DISTINCT ca.alert_id) as distributed_alerts,
            COUNT(DISTINCT u.id) as total_clients,
            AVG((SELECT COUNT(*) FROM client_alerts WHERE alert_id = sa.id)) as avg_distribution
        FROM security_alerts sa
        LEFT JOIN client_alerts ca ON sa.id = ca.alert_id
        LEFT JOIN users u ON u.user_type LIKE 'client_%' AND u.status = 'approved'
    ");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Prepare data for charts
function prepareChartData($stats) {
    $data = [
        'labels' => [],
        'data' => [],
        'colors' => []
    ];

    $colorMap = [
        'critical' => '#dc3545',
        'high' => '#fd7e14',
        'medium' => '#ffc107',
        'low' => '#28a745'
    ];

    // Initialize with all severity levels
    $allSeverities = ['critical', 'high', 'medium', 'low'];
    $severityCounts = [];

    foreach ($allSeverities as $severity) {
        $severityCounts[$severity] = 0;
    }

    // Update with actual data
    foreach ($stats as $stat) {
        if (isset($severityCounts[$stat['severity']])) {
            $severityCounts[$stat['severity']] = $stat['count'];
        }
    }

    foreach ($allSeverities as $severity) {
        $data['labels'][] = ucfirst($severity);
        $data['data'][] = $severityCounts[$severity];
        $data['colors'][] = $colorMap[$severity];
    }

    return $data;
}

// Prepare category data
function prepareCategoryData($stats) {
    $data = [
        'labels' => [],
        'data' => [],
        'colors' => []
    ];

    // Generate distinct colors for categories
    $colors = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40',
        '#FF6384', '#C9CBCF', '#4BC0C0', '#FFCD56', '#C9CBCF', '#FF6384'
    ];

    foreach ($stats as $index => $stat) {
        // Limit category name length for better display
        $category = $stat['categories'];
        if (strlen($category) > 20) {
            $category = substr($category, 0, 17) . '...';
        }
        $data['labels'][] = $category;
        $data['data'][] = $stat['count'];
        $data['colors'][] = $colors[$index % count($colors)];
    }

    return $data;
}

// Prepare monthly trend data
function prepareMonthlyTrendData($stats) {
    $data = [
        'labels' => [],
        'datasets' => [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => []
        ]
    ];

    // Reverse to show chronological order
    $stats = array_reverse($stats);

    foreach ($stats as $stat) {
        $month = date('M Y', strtotime($stat['month'] . '-01'));
        $data['labels'][] = $month;
        $data['datasets']['critical'][] = $stat['critical'];
        $data['datasets']['high'][] = $stat['high'];
        $data['datasets']['medium'][] = $stat['medium'];
        $data['datasets']['low'][] = $stat['low'];
    }

    return $data;
}

// Prepare client registration trend data
function prepareClientTrendData($stats) {
    $data = [
        'labels' => [],
        'datasets' => [
            'individual' => [],
            'company' => [],
            'total' => []
        ]
    ];

    // Reverse to show chronological order
    $stats = array_reverse($stats);

    foreach ($stats as $stat) {
        $month = date('M Y', strtotime($stat['month'] . '-01'));
        $data['labels'][] = $month;
        $data['datasets']['individual'][] = $stat['individual'];
        $data['datasets']['company'][] = $stat['company'];
        $data['datasets']['total'][] = $stat['registrations'];
    }

    return $data;
}

// Prepare the chart data
$daily_chart = prepareChartData($daily_stats);
$weekly_chart = prepareChartData($weekly_stats);
$monthly_chart = prepareChartData($monthly_stats);
$yearly_chart = prepareChartData($yearly_stats);
$category_chart = prepareCategoryData($category_stats);
$trend_chart = prepareMonthlyTrendData($monthly_trend);
$client_trend_chart = prepareClientTrendData($client_registration_trend);

// Calculate totals safely
$total_alerts = is_array($all_alerts) ? count($all_alerts) : 0;
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Admin Analytics Dashboard</h1>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-purple text-white mb-4">
                    <div class="card-body">
                        <h5>Total Alerts</h5>
                        <h3><?php echo $total_alerts; ?></h3>
                        <small>All time</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-primary text-white mb-4">
                    <div class="card-body">
                        <h5>Total Clients</h5>
                        <h3><?php echo $client_stats['total_clients'] ?? 0; ?></h3>
                        <small><?php echo $client_stats['approved_clients'] ?? 0; ?> approved</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card bg-gradient-success text-white mb-4">
                    <div class="card-body">
                        <h5>Alert Distribution</h5>
                        <h3><?php echo round($alert_status_stats['avg_distribution'] ?? 0, 1); ?></h3>
                        <small>Avg. per client</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card  bg-gradient-danger text-white mb-4">
                    <div class="card-body">
                        <h5>Pending Approvals</h5>
                        <h3><?php echo $client_stats['pending_clients'] ?? 0; ?></h3>
                        <small>Requires attention</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Statistics -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-users me-1"></i>
                        Client Statistics
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h4 class="text-primary"><?php echo $client_stats['individual_clients'] ?? 0; ?></h4>
                                    <small class="text-muted">Individual</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h4 class="text-success"><?php echo $client_stats['company_clients'] ?? 0; ?></h4>
                                    <small class="text-muted">Company</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-3 bg-light">
                                    <h4 class="text-danger"><?php echo $client_stats['rejected_clients'] ?? 0; ?></h4>
                                    <small class="text-muted">Rejected</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-chart-line me-1"></i>
                        Client Registration Trend
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="clientTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <!-- Severity Distribution Chart -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-1"></i>
                        Alert Distribution by Severity
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="severityChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Distribution of all alerts by severity level
                    </div>
                </div>
            </div>

            <!-- Category Distribution Chart -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Alert Distribution by Category
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Distribution of alerts by category
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend Charts -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-1"></i>
                        Monthly Alert Trends
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        12-month trend of security alerts
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Period Charts -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-day me-1"></i>
                        Daily Alerts
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="dailyChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts created today
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-week me-1"></i>
                        Weekly Alerts
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts created this week
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Monthly Alerts
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts created this month
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-calendar me-1"></i>
                        Yearly Alerts
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="yearlyChart"></canvas>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts created this year
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Category Statistics
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($category_stats as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['categories']); ?></td>
                                        <td><?php echo $category['count']; ?></td>
                                        <td><?php echo round(($category['count'] / max(1, $total_alerts)) * 100, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table me-1"></i>
                        Severity Statistics
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                    <th>Color</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $severity_counts = [
                                    'critical' => 0,
                                    'high' => 0,
                                    'medium' => 0,
                                    'low' => 0
                                ];

                                foreach ($yearly_stats as $stat) {
                                    if (isset($severity_counts[$stat['severity']])) {
                                        $severity_counts[$stat['severity']] = $stat['count'];
                                    }
                                }

                                $total_severity = array_sum($severity_counts);

                                foreach ($severity_counts as $severity => $count):
                                    ?>
                                    <tr>
                                        <td>
                                        <span class="badge bg-<?php echo getSeverityBadge($severity); ?>">
                                            <?php echo ucfirst($severity); ?>
                                        </span>
                                        </td>
                                        <td><?php echo $count; ?></td>
                                        <td><?php echo $total_severity > 0 ? round(($count / $total_severity) * 100, 1) : 0; ?>%</td>
                                        <td>
                                        <span class="color-swatch" style="background-color:
                                        <?php
                                        $colors = [
                                            'critical' => '#dc3545',
                                            'high' => '#fd7e14',
                                            'medium' => '#ffc107',
                                            'low' => '#28a745'
                                        ];
                                        echo $colors[$severity] ?? '#6c757d';
                                        ?>;">
                                        </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Overview -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-tachometer-alt me-1"></i>
                        System Overview
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-primary"><?php echo $alert_status_stats['total_alerts'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Alerts Created</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success"><?php echo $alert_status_stats['distributed_alerts'] ?? 0; ?></h4>
                                    <small class="text-muted">Alerts Distributed</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info"><?php echo $alert_status_stats['total_clients'] ?? 0; ?></h4>
                                    <small class="text-muted">Active Clients</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-warning"><?php echo round($alert_status_stats['avg_distribution'] ?? 0, 1); ?></h4>
                                    <small class="text-muted">Avg. Alerts per Client</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
        // Mobile detection and chart configuration
        const isMobile = window.innerWidth <= 768;
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: isMobile ? 'bottom' : 'right',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: isMobile ? 10 : 12
                        }
                    }
                }
            }
        };

        // Severity Distribution Pie Chart
        const severityCtx = document.getElementById('severityChart');
        const severityChart = new Chart(severityCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($yearly_chart['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($yearly_chart['data']); ?>,
                    backgroundColor: <?php echo json_encode($yearly_chart['colors']); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Alert Distribution by Severity',
                        font: {
                            size: isMobile ? 14 : 16
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Category Distribution Doughnut Chart
        const categoryCtx = document.getElementById('categoryChart');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($category_chart['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($category_chart['data']); ?>,
                    backgroundColor: <?php echo json_encode($category_chart['colors']); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Alert Distribution by Category',
                        font: {
                            size: isMobile ? 14 : 16
                        }
                    }
                }
            }
        });

        // Monthly Trend Line Chart
        const trendCtx = document.getElementById('trendChart');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_chart['labels']); ?>,
                datasets: [
                    {
                        label: 'Critical',
                        data: <?php echo json_encode($trend_chart['datasets']['critical']); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'High',
                        data: <?php echo json_encode($trend_chart['datasets']['high']); ?>,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Medium',
                        data: <?php echo json_encode($trend_chart['datasets']['medium']); ?>,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Low',
                        data: <?php echo json_encode($trend_chart['datasets']['low']); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.1,
                        fill: true
                    }
                ]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Monthly Alert Trends',
                        font: {
                            size: isMobile ? 14 : 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Alerts',
                            font: {
                                size: isMobile ? 12 : 14
                            }
                        },
                        ticks: {
                            font: {
                                size: isMobile ? 10 : 12
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month',
                            font: {
                                size: isMobile ? 12 : 14
                            }
                        },
                        ticks: {
                            font: {
                                size: isMobile ? 10 : 12
                            }
                        }
                    }
                }
            }
        });

        // Client Registration Trend Chart
        const clientTrendCtx = document.getElementById('clientTrendChart');
        const clientTrendChart = new Chart(clientTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($client_trend_chart['labels']); ?>,
                datasets: [
                    {
                        label: 'Individual Clients',
                        data: <?php echo json_encode($client_trend_chart['datasets']['individual']); ?>,
                        borderColor: '#36A2EB',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Company Clients',
                        data: <?php echo json_encode($client_trend_chart['datasets']['company']); ?>,
                        borderColor: '#FF6384',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Total Registrations',
                        data: <?php echo json_encode($client_trend_chart['datasets']['total']); ?>,
                        borderColor: '#4BC0C0',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        fill: true
                    }
                ]
            },
            options: {
                ...chartOptions,
                plugins: {
                    ...chartOptions.plugins,
                    title: {
                        display: true,
                        text: 'Client Registration Trends',
                        font: {
                            size: isMobile ? 14 : 16
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Registrations',
                            font: {
                                size: isMobile ? 12 : 14
                            }
                        },
                        ticks: {
                            font: {
                                size: isMobile ? 10 : 12
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month',
                            font: {
                                size: isMobile ? 12 : 14
                            }
                        },
                        ticks: {
                            font: {
                                size: isMobile ? 10 : 12
                            }
                        }
                    }
                }
            }
        });

        // Time Period Bar Charts
        function createBarChart(canvasId, labels, data, colors, title) {
            const ctx = document.getElementById(canvasId);
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    ...chartOptions,
                    plugins: {
                        ...chartOptions.plugins,
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: title,
                            font: {
                                size: isMobile ? 14 : 16
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: isMobile ? 10 : 12
                                }
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: isMobile ? 10 : 12
                                }
                            }
                        }
                    }
                }
            });
        }

        // Create all bar charts
        createBarChart('dailyChart',
            <?php echo json_encode($daily_chart['labels']); ?>,
            <?php echo json_encode($daily_chart['data']); ?>,
            <?php echo json_encode($daily_chart['colors']); ?>,
            'Today\'s Alerts by Severity'
        );

        createBarChart('weeklyChart',
            <?php echo json_encode($weekly_chart['labels']); ?>,
            <?php echo json_encode($weekly_chart['data']); ?>,
            <?php echo json_encode($weekly_chart['colors']); ?>,
            'This Week\'s Alerts by Severity'
        );

        createBarChart('monthlyChart',
            <?php echo json_encode($monthly_chart['labels']); ?>,
            <?php echo json_encode($monthly_chart['data']); ?>,
            <?php echo json_encode($monthly_chart['colors']); ?>,
            'This Month\'s Alerts by Severity'
        );

        createBarChart('yearlyChart',
            <?php echo json_encode($yearly_chart['labels']); ?>,
            <?php echo json_encode($yearly_chart['data']); ?>,
            <?php echo json_encode($yearly_chart['colors']); ?>,
            'This Year\'s Alerts by Severity'
        );

        // Handle window resize for better mobile responsiveness
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                location.reload();
            }, 250);
        });

        // Add some custom CSS for better visualization
        const style = document.createElement('style');
        style.innerHTML = `
    .color-swatch {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 3px;
        margin-right: 5px;
    }
    .chart-container {
        position: relative;
        height: 300px;
        min-height: 250px;
    }
    @media (max-width: 768px) {
        .chart-container {
            height: 250px;
            min-height: 200px;
        }
        .card-body {
            padding: 1rem;
        }
        .table-responsive {
            font-size: 0.875rem;
        }
    }
    @media (max-width: 576px) {
        .chart-container {
            height: 200px;
            min-height: 150px;
        }
        .card-header {
            padding: 0.75rem 1rem;
        }
        .card-footer {
            padding: 0.5rem 1rem;
        }
    }
`;
        document.head.appendChild(style);
    </script>

<?php include 'include/footer.php'; ?>