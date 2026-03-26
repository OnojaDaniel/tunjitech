<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get statistics for dashboard
$total_alerts = $pdo->query("SELECT COUNT(*) FROM security_alerts")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type LIKE 'client_%'")->fetchColumn();
$pending_approvals = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$critical_alerts = $pdo->query("SELECT COUNT(*) FROM security_alerts WHERE severity = 'critical'")->fetchColumn();

// Add sub-admin statistics (only for main admin)
if (isAdmin()) {
    $total_subadmins = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = '" . USER_TYPE_SUB_ADMIN . "'")->fetchColumn();
    $active_subadmins = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = '" . USER_TYPE_SUB_ADMIN . "' AND status = 'approved'")->fetchColumn();
}

// Recent alerts
$stmt = $pdo->prepare("SELECT * FROM security_alerts ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent clients
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_type LIKE 'client_%' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include './include/header.php'; ?>
<div class="dashboard-main-body">

    <!-- Page Title & Breadcrumb -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
        <h6 class="fw-semibold mb-0">Admin Dashboard</h6>
        <ul class="d-flex align-items-center gap-2">
            <li class="fw-medium">
                <a href="dashboard.php" class="d-flex align-items-center gap-1 hover-text-primary">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                    Dashboard
                </a>
            </li>
            <li>-</li>
            <li class="fw-medium">Admin</li>
        </ul>
    </div>

    <div class="row gy-4">
        <div class="col-xxl-8">
            <div class="row gy-4">

                <!-- sub-admin stats -->
                <?php if (isAdmin()): ?>
                    <div class="col-xl-3 col-md-6">
                        <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
                            <div class="card-body p-0 d-flex align-items-center gap-2">
                                <span class="w-48-px h-48-px bg-primary-600 text-white d-flex justify-content-center align-items-center rounded-circle">
                                    <iconify-icon icon="mdi:account-multiple-plus-outline" class="icon"></iconify-icon>
                                    </span>
                                <div>
                                    <span class="fw-medium text-secondary-light text-sm">Sub Admins</span>
                                    <h6 class="fw-semibold"><?php echo $total_subadmins; ?></h6>
                                    <small><?php echo $active_subadmins; ?> active</small>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Total Alerts -->
                <div class="col-xxl-4 col-sm-6">
                    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-1">
                        <div class="card-body p-0 d-flex align-items-center gap-2">
                            <span class="w-48-px h-48-px bg-primary-600 text-white d-flex justify-content-center align-items-center rounded-circle">
                                <iconify-icon icon="mdi:alert-circle" class="icon"></iconify-icon>
                            </span>
                            <div>
                                <span class="fw-medium text-secondary-light text-sm">Total Alerts</span>
                                <h6 class="fw-semibold"><?php echo $total_alerts; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Clients -->
                <div class="col-xxl-4 col-sm-6">
                    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-2">
                        <div class="card-body p-0 d-flex align-items-center gap-2">
                            <span class="w-48-px h-48-px bg-success-main text-white d-flex justify-content-center align-items-center rounded-circle">
                                <iconify-icon icon="mingcute:user-follow-fill" class="icon"></iconify-icon>
                            </span>
                            <div>
                                <span class="fw-medium text-secondary-light text-sm">Total Clients</span>
                                <h6 class="fw-semibold"><?php echo $total_clients; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="col-xxl-4 col-sm-6">
                    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-3">
                        <div class="card-body p-0 d-flex align-items-center gap-2">
                            <span class="w-48-px h-48-px bg-yellow text-white d-flex justify-content-center align-items-center rounded-circle">
                                <iconify-icon icon="mdi:account-clock" class="icon"></iconify-icon>
                            </span>
                            <div>
                                <span class="fw-medium text-secondary-light text-sm">Pending Approvals</span>
                                <h6 class="fw-semibold"><?php echo $pending_approvals; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Alerts -->
                <div class="col-xxl-4 col-sm-6">
                    <div class="card p-3 shadow-2 radius-8 border input-form-light h-100 bg-gradient-end-4">
                        <div class="card-body p-0 d-flex align-items-center gap-2">
                            <span class="w-48-px h-48-px bg-danger-main text-white d-flex justify-content-center align-items-center rounded-circle">
                                <iconify-icon icon="mdi:alert-decagram" class="icon"></iconify-icon>
                            </span>
                            <div>
                                <span class="fw-medium text-secondary-light text-sm">Critical Alerts</span>
                                <h6 class="fw-semibold"><?php echo $critical_alerts; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Recent Alerts -->
        <div class="col-xxl-6">
            <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0"><i class="fas fa-bell me-1"></i> Recent Security Alerts</h6>
                </div>
                <div class="card-body p-0 mt-2">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>Title</th>
                                <th>Severity</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_alerts as $alert): ?>
                                <tr>
                                    <td><?php echo $alert['title']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($alert['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Clients -->
        <div class="col-xxl-6">
            <div class="card p-3 shadow-2 radius-8 border input-form-light h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="fw-semibold mb-0"><i class="fas fa-users me-1"></i> Recent Client Registrations</h6>
                </div>
                <div class="card-body p-0 mt-2">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead>
                            <tr>
                                <th>Username</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_clients as $client): ?>
                                <tr>
                                    <td><?php echo $client['username']; ?></td>
                                    <td><?php echo str_replace('_', ' ', $client['user_type']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadge($client['status']); ?>">
                                            <?php echo ucfirst($client['status']); ?>
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
<?php include './include/footer.php'; ?>
