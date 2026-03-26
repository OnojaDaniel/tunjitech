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
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Check if client ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Client ID not specified.";
    header("Location: clients.php");
    exit();
}

$client_id = intval($_GET['id']);

// Get client details with alert statistics
$stmt = $pdo->prepare("SELECT u.*, 
                      (SELECT COUNT(*) FROM client_alerts WHERE client_id = u.id) as total_alerts,
                      (SELECT COUNT(*) FROM client_alerts WHERE client_id = u.id AND is_read = 1) as read_alerts,
                      (SELECT MAX(created_at) FROM client_alerts WHERE client_id = u.id) as last_alert_date
                      FROM users u 
                      WHERE u.id = ? AND u.user_type LIKE 'client_%'");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if client exists
if (!$client) {
    $_SESSION['error_message'] = "Client not found.";
    header("Location: clients.php");
    exit();
}

// Get client's recent alerts
$stmt = $pdo->prepare("SELECT sa.*, ca.is_read, ca.created_at as received_at 
                      FROM security_alerts sa 
                      INNER JOIN client_alerts ca ON sa.id = ca.alert_id 
                      WHERE ca.client_id = ? 
                      ORDER BY ca.created_at DESC 
                      LIMIT 5");
$stmt->execute([$client_id]);
$recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Client Details</h1>

        <div class="row">
            <div class="col-lg-8">
                <!-- Client Information Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Personal Information</h6>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($client['username']); ?></p>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"><?php echo htmlspecialchars($client['email']); ?></a></p>
                                <p><strong>Phone:</strong>
                                    <?php if (!empty($client['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>"><?php echo htmlspecialchars($client['phone']); ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                                <p><strong>Type:</strong> <span class="badge bg-info"><?php echo str_replace('_', ' ', $client['user_type']); ?></span></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Account Information</h6>
                                <p><strong>Status:</strong> <span class="badge bg-<?php echo getStatusBadge($client['status']); ?>"><?php echo ucfirst($client['status']); ?></span></p>
                                <p><strong>Registered:</strong> <?php echo date('M j, Y g:i A', strtotime($client['created_at'])); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($client['updated_at'])); ?></p>

                                <?php if ($client['user_type'] == 'client_company'): ?>
                                    <p><strong>Company:</strong> <?php echo htmlspecialchars($client['company_name']); ?></p>
                                    <p><strong>Company Size:</strong> <?php echo htmlspecialchars($client['company_size']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="clients.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Clients
                            </a>
                            <div>
                                <a href="edit_client.php?id=<?php echo $client['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <a href="clients.php?delete=<?php echo $client['id']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.')">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Security Alerts</h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_alerts) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recent_alerts as $alert): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($alert['title']); ?></h6>
                                                <small class="text-muted">
                                                    Severity: <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>"><?php echo ucfirst($alert['severity']); ?></span>
                                                    | Received: <?php echo date('M j, Y g:i A', strtotime($alert['received_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $alert['is_read'] ? 'success' : 'warning'; ?>">
                                            <?php echo $alert['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No alerts received yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Alert Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Alert Statistics</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-primary"><?php echo $client['total_alerts']; ?></h3>
                                    <small class="text-muted">Total Alerts</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-success"><?php echo $client['read_alerts']; ?></h3>
                                    <small class="text-muted">Read Alerts</small>
                                </div>
                            </div>
                        </div>
                        <?php if ($client['last_alert_date']): ?>
                            <p class="mb-0">
                                <small class="text-muted">
                                    Last alert: <?php echo date('M j, Y g:i A', strtotime($client['last_alert_date'])); ?>
                                </small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-envelope me-1"></i> Send Email
                            </a>
                            <?php if (!empty($client['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" class="btn btn-outline-info">
                                    <i class="fas fa-phone me-1"></i> Call Client
                                </a>
                            <?php endif; ?>
                            <a href="../client/view_alert.php?id=<?php echo $alert['id']; ?>" target="_blank" class="btn btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i> View as Client
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'include/footer.php'; ?>