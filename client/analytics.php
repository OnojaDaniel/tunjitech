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

// Temporary function definitions if they don't exist
if (!function_exists('isClientUser')) {
    function isClientUser() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'client_user';
    }
}

if (!function_exists('canAccessClientArea')) {
    function canAccessClientArea() {
        // Check if isClient function exists
        if (function_exists('isClient')) {
            return isClient() || isClientUser();
        }

        // Fallback if isClient function doesn't exist
        return isset($_SESSION['user_type']) &&
            ($_SESSION['user_type'] == 'client_individual' ||
                $_SESSION['user_type'] == 'client_company' ||
                $_SESSION['user_type'] == 'client_user');
    }
}

// Check if user is logged in and can access client area
if (!isLoggedIn() || !canAccessClientArea()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is approved (for main clients) or if main client is approved (for client users)
if (isClient()) {
    // Main client account
    if (!isUserApproved($_SESSION['user_id'])) {
        session_destroy();
        header("Location: ../login.php?error=not_approved");
        exit();
    }
} elseif (isClientUser()) {
    // Client user - check if main client account is approved
    // Use the main client ID stored in session during login
    $mainClientId = $_SESSION['user_id']; // This should be the main client ID for client users
    $mainClient = getUserById($mainClientId);
    if (!$mainClient || $mainClient['status'] !== 'approved') {
        session_destroy();
        header("Location: ../login.php?error=not_approved");
        exit();
    }
}

// Get client alerts - handle both main clients and client users
$client_id = $_SESSION['user_id']; // This will be the main client ID for both cases
$client_alerts = getClientAlerts($client_id);

// Get alert statistics for different time periods
$daily_stats = getAlertStats($client_id, 'day');
$weekly_stats = getAlertStats($client_id, 'week');
$monthly_stats = getAlertStats($client_id, 'month');
$yearly_stats = getAlertStats($client_id, 'year');

// Get alerts by category
$category_stats = getAlertStatsByCategory($client_id);

// Get monthly trend data
$monthly_trend = getMonthlyTrend($client_id);

// Get read vs unread statistics
$read_stats = getReadUnreadStats($client_id);


/**
 * Get alert statistics by category
 */
function getAlertStatsByCategory($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            categories,
            COUNT(*) as count
        FROM security_alerts sa
        INNER JOIN client_alerts ca ON sa.id = ca.alert_id
        WHERE ca.client_id = ?
        GROUP BY categories
        ORDER BY count DESC
    ");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get monthly trend data
 */
function getMonthlyTrend($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(sa.created_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
        FROM security_alerts sa
        INNER JOIN client_alerts ca ON sa.id = ca.alert_id
        WHERE ca.client_id = ?
        GROUP BY DATE_FORMAT(sa.created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get read vs unread statistics
 */
function getReadUnreadStats($client_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN ca.is_read = 1 THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN ca.is_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM client_alerts ca
        WHERE ca.client_id = ?
    ");
    $stmt->execute([$client_id]);
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
        $severityCounts[$stat['severity']] = $stat['count'];
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

// Prepare the chart data
$daily_chart = prepareChartData($daily_stats);
$weekly_chart = prepareChartData($weekly_stats);
$monthly_chart = prepareChartData($monthly_stats);
$yearly_chart = prepareChartData($yearly_stats);
$category_chart = prepareCategoryData($category_stats);
$trend_chart = prepareMonthlyTrendData($monthly_trend);

// Calculate total alerts count safely
$total_alerts = is_array($client_alerts) ? count($client_alerts) : 0;
?>

<?php include  'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Security Alerts Analytics</h1>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-gradient-purple text-white h-100">
                    <div class="card-body">
                        <h5>Total Alerts</h5>
                        <h3><?php echo $total_alerts; ?></h3>
                        <small>All time</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-gradient-primary text-white h-100">
                    <div class="card-body">
                        <h5>Read Alerts</h5>
                        <h3><?php echo $read_stats['read_count'] ?? 0; ?></h3>
                        <small><?php echo round((($read_stats['read_count'] ?? 0) / max(1, $total_alerts)) * 100); ?>% read rate</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-gradient-success text-white h-100">
                    <div class="card-body">
                        <h5>Unread Alerts</h5>
                        <h3><?php echo $read_stats['unread_count'] ?? 0; ?></h3>
                        <small>Requires attention</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card bg-gradient-danger text-white h-100">
                    <div class="card-body">
                        <h5>Categories</h5>
                        <h3><?php echo count($category_stats); ?></h3>
                        <small>Different alert types</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <!-- Severity Distribution Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-1"></i>
                        Alert Distribution by Severity
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container">
                            <canvas id="severityChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Distribution of alerts by severity level
                    </div>
                </div>
            </div>

            <!-- Category Distribution Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Alert Distribution by Category
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Distribution of alerts by category
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line me-1"></i>
                        Monthly Alert Trends
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        12-month trend of security alerts
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Period Charts -->
        <div class="row">
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-calendar-day me-1"></i>
                        Daily Alerts
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container-sm">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts received today
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-calendar-week me-1"></i>
                        Weekly Alerts
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container-sm">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts received this week
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Monthly Alerts
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container-sm">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts received this month
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-calendar me-1"></i>
                        Yearly Alerts
                    </div>
                    <div class="card-body position-relative">
                        <div class="chart-container-sm">
                            <canvas id="yearlyChart"></canvas>
                        </div>
                    </div>
                    <div class="card-footer small text-muted">
                        Alerts received this year
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
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

            <div class="col-lg-6 mb-4">
                <div class="card h-100">
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
    </div>

    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
        // Mobile detection and chart configuration
        const isMobile = window.innerWidth <= 768;

        // Common chart options for mobile
        const mobileOptions = {
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
        const severityCtx = document.getElementById('severityChart').getContext('2d');
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
                ...mobileOptions,
                plugins: {
                    ...mobileOptions.plugins,
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
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
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
                ...mobileOptions,
                plugins: {
                    ...mobileOptions.plugins,
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
        const trendCtx = document.getElementById('trendChart').getContext('2d');
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
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Alert Trends',
                        font: {
                            size: isMobile ? 14 : 16
                        }
                    },
                    legend: {
                        position: isMobile ? 'bottom' : 'top',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: isMobile ? 10 : 12
                            }
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

        // Time Period Bar Charts
        function createBarChart(canvasId, labels, data, colors, title) {
            const ctx = document.getElementById(canvasId).getContext('2d');
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
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: title,
                            font: {
                                size: isMobile ? 12 : 14
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
            'Today\'s Alerts'
        );

        createBarChart('weeklyChart',
            <?php echo json_encode($weekly_chart['labels']); ?>,
            <?php echo json_encode($weekly_chart['data']); ?>,
            <?php echo json_encode($weekly_chart['colors']); ?>,
            'This Week\'s Alerts'
        );

        createBarChart('monthlyChart',
            <?php echo json_encode($monthly_chart['labels']); ?>,
            <?php echo json_encode($monthly_chart['data']); ?>,
            <?php echo json_encode($monthly_chart['colors']); ?>,
            'This Month\'s Alerts'
        );

        createBarChart('yearlyChart',
            <?php echo json_encode($yearly_chart['labels']); ?>,
            <?php echo json_encode($yearly_chart['data']); ?>,
            <?php echo json_encode($yearly_chart['colors']); ?>,
            'This Year\'s Alerts'
        );

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                severityChart.resize();
                categoryChart.resize();
                trendChart.resize();

                // Re-initialize bar charts
                const barCharts = ['dailyChart', 'weeklyChart', 'monthlyChart', 'yearlyChart'];
                barCharts.forEach(chartId => {
                    const chart = Chart.getChart(chartId);
                    if (chart) {
                        chart.destroy();
                    }
                });

                createBarChart('dailyChart',
                    <?php echo json_encode($daily_chart['labels']); ?>,
                    <?php echo json_encode($daily_chart['data']); ?>,
                    <?php echo json_encode($daily_chart['colors']); ?>,
                    'Today\'s Alerts'
                );

                createBarChart('weeklyChart',
                    <?php echo json_encode($weekly_chart['labels']); ?>,
                    <?php echo json_encode($weekly_chart['data']); ?>,
                    <?php echo json_encode($weekly_chart['colors']); ?>,
                    'This Week\'s Alerts'
                );

                createBarChart('monthlyChart',
                    <?php echo json_encode($monthly_chart['labels']); ?>,
                    <?php echo json_encode($monthly_chart['data']); ?>,
                    <?php echo json_encode($monthly_chart['colors']); ?>,
                    'This Month\'s Alerts'
                );

                createBarChart('yearlyChart',
                    <?php echo json_encode($yearly_chart['labels']); ?>,
                    <?php echo json_encode($yearly_chart['data']); ?>,
                    <?php echo json_encode($yearly_chart['colors']); ?>,
                    'This Year\'s Alerts'
                );
            }, 250);
        });

        // Add some custom CSS for better mobile responsiveness
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
        height: 250px;
        width: 100%;
    }
    .chart-container-sm {
        position: relative;
        height: 200px;
        width: 100%;
    }
    @media (max-width: 768px) {
        .chart-container {
            height: 300px;
        }
        .chart-container-sm {
            height: 250px;
        }
        .card-header {
            padding: 0.75rem;
        }
        .card-body {
            padding: 0.75rem;
        }
        .card-footer {
            padding: 0.75rem;
        }
        h3 {
            font-size: 1.5rem;
        }
        h5 {
            font-size: 1.1rem;
        }
    }
    @media (max-width: 576px) {
        .chart-container {
            height: 250px;
        }
        .chart-container-sm {
            height: 200px;
        }
        .col-md-6, .col-lg-3 {
            margin-bottom: 1rem;
        }
    }
`;
        document.head.appendChild(style);
    </script>

<?php include  'include/footer.php'; ?>