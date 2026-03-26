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

// Check if user is logged in and is client
if (!isLoggedIn() || !isClient()) {
    header("Location: ../login.php");
    exit();
}

// Check if user is approved
if (!isUserApproved($_SESSION['user_id'])) {
    session_destroy();
    header("Location: ../login.php?error=not_approved");
    exit();
}

// Get client alerts
try {
    $client_alerts = getClientAlerts($_SESSION['user_id']);
} catch (Exception $e) {
    error_log("Error loading client alerts: " . $e->getMessage());
    $client_alerts = [];
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">

        <!-- Alerts List -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-bell me-1"></i>
                All Security Alerts
            </div>
            <div class="card-body">
                <?php if (empty($client_alerts)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        No security alerts received yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Title</th>
                                <th>Severity</th>
                                <th>Categories</th>
                                <th>Received</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($client_alerts as $alert): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($alert['title']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['categories']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $alert['is_read'] ? 'success' : 'warning'; ?>">
                                            <?php echo $alert['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="delete_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this alert?')">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php include 'include/footer.php'; ?>