<?php

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

// Check if sub-admin ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Sub-admin ID not specified.";
    header("Location: subadmins.php");
    exit();
}

$subadmin_id = intval($_GET['id']);

// Get sub-admin details with activity statistics
$stmt = $pdo->prepare("SELECT u.*, 
                      (SELECT COUNT(*) FROM security_alerts WHERE created_by = u.id) as alerts_created,
                      (SELECT MAX(created_at) FROM security_alerts WHERE created_by = u.id) as last_alert_created
                      FROM users u 
                      WHERE u.id = ? AND u.user_type = ?");
$stmt->execute([$subadmin_id, USER_TYPE_SUB_ADMIN]);
$subadmin = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if sub-admin exists
if (!$subadmin) {
    $_SESSION['error_message'] = "Sub-admin not found.";
    header("Location: subadmins.php");
    exit();
}

// Get sub-admin's recent alerts
$stmt = $pdo->prepare("SELECT * FROM security_alerts 
                      WHERE created_by = ? 
                      ORDER BY created_at DESC 
                      LIMIT 5");
$stmt->execute([$subadmin_id]);
$recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get permissions
$permissions = !empty($subadmin['permissions']) ? json_decode($subadmin['permissions'], true) : [];
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h3 class="mt-4">Sub-Administrator Details</h3>

        <div class="row">
            <div class="col-lg-8">
                <!-- Sub-Admin Information Card -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0 text-white">
                            <i class="fas fa-user-shield me-2"></i>
                            <?php echo htmlspecialchars($subadmin['first_name'] . ' ' . $subadmin['last_name']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Personal Information</h6>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($subadmin['username']); ?></p>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($subadmin['email']); ?>"><?php echo htmlspecialchars($subadmin['email']); ?></a></p>
                                <p><strong>Phone:</strong>
                                    <?php if (!empty($subadmin['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($subadmin['phone']); ?>"><?php echo htmlspecialchars($subadmin['phone']); ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>Account Information</h6>
                                <p><strong>Status:</strong> <span class="badge bg-<?php echo getStatusBadge($subadmin['status']); ?>"><?php echo ucfirst($subadmin['status']); ?></span></p>
                                <p><strong>Registered:</strong> <?php echo date('M j, Y g:i A', strtotime($subadmin['created_at'])); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($subadmin['updated_at'])); ?></p>
                                <p><strong>Last Login:</strong> <?php echo $subadmin['last_login'] ? date('M j, Y g:i A', strtotime($subadmin['last_login'])) : 'Never'; ?></p>
                            </div>
                        </div>

                        <!-- Permissions Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6>Permissions</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0 text-white">Allowed Actions</h6>
                                            </div>
                                            <div class="card-body">
                                                <ul class="list-unstyled mb-0">
                                                    <li><i class="fas fa-check text-success me-2"></i> Create security alerts</li>
                                                    <li><i class="fas fa-check text-success me-2"></i> Edit security alerts</li>
                                                    <li><i class="fas fa-check text-success me-2"></i> Delete security alerts</li>
                                                    <li><i class="fas fa-check text-success me-2"></i> Send notifications</li>
                                                    <li><i class="fas fa-check text-success me-2"></i> View analytics</li>
                                                    <li><i class="fas fa-check text-success me-2"></i> Chat with clients</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-danger text-white">
                                                <h6 class="mb-0 text-white">Restricted Actions</h6>
                                            </div>
                                            <div class="card-body">
                                                <ul class="list-unstyled mb-0">
                                                    <li><i class="fas fa-times text-danger me-2"></i> Register clients</li>
                                                    <li><i class="fas fa-times text-danger me-2"></i> Edit client information</li>
                                                    <li><i class="fas fa-times text-danger me-2"></i> Delete clients</li>
                                                    <li><i class="fas fa-times text-danger me-2"></i> Approve/reject clients</li>
                                                    <li><i class="fas fa-times text-danger me-2"></i> Manage sub-admins</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="subadmins.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Sub-Admins
                            </a>
                            <div>
                                <a href="edit_subadmin.php?id=<?php echo $subadmin['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <a href="subadmins.php?delete=<?php echo $subadmin['id']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this sub-administrator? This action cannot be undone.')">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts Created -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Recent Alerts Created</h6>
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
                                                    | Created: <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                                                </small>
                                            </div>
                                            <a href="view_alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No alerts created yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Activity Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Activity Statistics</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="border rounded p-3 bg-light">
                                    <h3 class="text-primary"><?php echo $subadmin['alerts_created']; ?></h3>
                                    <small class="text-muted">Total Alerts Created</small>
                                </div>
                            </div>
                        </div>
                        <?php if ($subadmin['last_alert_created']): ?>
                            <p class="mb-0">
                                <small class="text-muted">
                                    Last alert: <?php echo date('M j, Y g:i A', strtotime($subadmin['last_alert_created'])); ?>
                                </small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                        <span class="badge bg-<?php echo getStatusBadge($subadmin['status']); ?> p-2 mb-2">
                            <i class="fas fa-<?php echo $subadmin['status'] == 'approved' ? 'check-circle' : 'clock'; ?> me-1"></i>
                            <?php echo ucfirst($subadmin['status']); ?>
                        </span>
                            <?php if ($subadmin['status'] == 'pending'): ?>
                                <p class="mb-0 small text-muted">
                                    This account is pending activation. The user cannot login until approved.
                                </p>
                            <?php elseif ($subadmin['status'] == 'approved'): ?>
                                <p class="mb-0 small text-muted">
                                    This account is active and can access the system.
                                </p>
                            <?php else: ?>
                                <p class="mb-0 small text-muted">
                                    This account has been rejected and cannot access the system.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit_subadmin.php?id=<?php echo $subadmin['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-1"></i> Edit Sub-Admin
                            </a>
                            <?php if (!empty($subadmin['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($subadmin['phone']); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-phone me-1"></i> Call Sub-Admin
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'include/footer.php'; ?>