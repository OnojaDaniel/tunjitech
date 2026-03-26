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

// Handle alert deletion
if (isset($_GET['delete'])) {
    $alert_id = intval($_GET['delete']);

    // First delete associated client alerts
    $stmt = $pdo->prepare("DELETE FROM client_alerts WHERE alert_id = ?");
    $stmt->execute([$alert_id]);

    // Then delete the alert
    $stmt = $pdo->prepare("DELETE FROM security_alerts WHERE id = ?");
    if ($stmt->execute([$alert_id])) {
        $_SESSION['success_message'] = "Alert deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting alert.";
    }
    header("Location: all_alerts.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_alerts'])) {
    $selected_alerts = $_POST['selected_alerts'];
    $action = $_POST['bulk_action'];

    if (!empty($selected_alerts)) {
        $placeholders = implode(',', array_fill(0, count($selected_alerts), '?'));

        switch ($action) {
            case 'delete':
                // First delete associated client alerts
                $stmt = $pdo->prepare("DELETE FROM client_alerts WHERE alert_id IN ($placeholders)");
                $stmt->execute($selected_alerts);

                // Then delete the alerts
                $stmt = $pdo->prepare("DELETE FROM security_alerts WHERE id IN ($placeholders)");
                if ($stmt->execute($selected_alerts)) {
                    $_SESSION['success_message'] = "Selected alerts deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting alerts.";
                }
                break;

            case 'export':
                // Export functionality would go here
                $_SESSION['success_message'] = "Export feature coming soon!";
                break;
        }
    } else {
        $_SESSION['error_message'] = "No alerts selected for bulk action.";
    }
    header("Location: all_alerts.php");
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$severity = isset($_GET['severity']) ? sanitizeInput($_GET['severity']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query with filters
$query = "SELECT sa.*, u.username as created_by_name, 
          COUNT(ca.id) as recipient_count,
          SUM(ca.notified_via_email) as email_count,
          SUM(ca.notified_via_sms) as sms_count
          FROM security_alerts sa 
          LEFT JOIN users u ON sa.created_by = u.id 
          LEFT JOIN client_alerts ca ON sa.id = ca.alert_id 
          WHERE 1=1";

$params = [];

// Add search filters
if (!empty($search)) {
    $query .= " AND (sa.title LIKE ? OR sa.categories LIKE ? OR sa.affected_areas LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($severity)) {
    $query .= " AND sa.severity = ?";
    $params[] = $severity;
}

if (!empty($category)) {
    $query .= " AND sa.categories LIKE ?";
    $params[] = "%$category%";
}

// Add status filter (active/expired)
if (!empty($status)) {
    $current_time = date('Y-m-d H:i:s');
    if ($status === 'active') {
        $query .= " AND sa.alert_begins <= ? AND sa.alert_expires >= ?";
        $params[] = $current_time;
        $params[] = $current_time;
    } elseif ($status === 'expired') {
        $query .= " AND sa.alert_expires < ?";
        $params[] = $current_time;
    } elseif ($status === 'scheduled') {
        $query .= " AND sa.alert_begins > ?";
        $params[] = $current_time;
    }
}

// Complete query with grouping and ordering
$query .= " GROUP BY sa.id ORDER BY sa.created_at DESC";

// Get all alerts with filters
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter dropdown
$categories_stmt = $pdo->prepare("SELECT DISTINCT categories FROM security_alerts WHERE categories IS NOT NULL AND categories != ''");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Extract unique categories from comma-separated values - FIXED THIS SECTION
$unique_categories = array();
foreach ($categories as $cat) {
    $split_cats = explode(',', $cat);
    foreach ($split_cats as $single_cat) {
        $trimmed_cat = trim($single_cat);
        if (!empty($trimmed_cat)) {
            $unique_categories[$trimmed_cat] = $trimmed_cat;
        }
    }
}
sort($unique_categories);
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mt-4">All Security Alerts</h3>
            <a href="alerts.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Create New Alert
            </a>
        </div>

        <!-- Search and Filter Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter me-1"></i>
                Search & Filter Alerts
            </div>
            <div class="card-body">
                <form method="GET" action="all_alerts.php">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search by title, category, or affected areas...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="severity" class="form-label">Severity</label>
                                <select class="form-select" id="severity" name="severity">
                                    <option value="">All Severities</option>
                                    <option value="low" <?php echo $severity === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $severity === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $severity === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($unique_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"
                                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions Form -->
        <form method="POST" action="all_alerts.php" id="bulkActionForm">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-bell me-1"></i>
                            Security Alerts (<?php echo count($alerts); ?> found)
                        </div>
                        <div class="d-flex align-items-center">
                            <select name="bulk_action" class="form-select form-select-sm me-2" style="width: auto;">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="export">Export Selected</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirmBulkAction()">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($alerts)): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            No security alerts found matching your criteria.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                    </th>
                                    <th>Title</th>
                                    <th>Severity</th>
                                    <th>Categories</th>
                                    <th>Status</th>
                                    <th>Recipients</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($alerts as $alert):
                                    $current_time = time();
                                    $alert_begins = strtotime($alert['alert_begins']);
                                    $alert_expires = strtotime($alert['alert_expires']);

                                    if ($current_time < $alert_begins) {
                                        $status = 'Scheduled';
                                        $status_class = 'info';
                                    } elseif ($current_time >= $alert_begins && $current_time <= $alert_expires) {
                                        $status = 'Active';
                                        $status_class = 'success';
                                    } else {
                                        $status = 'Expired';
                                        $status_class = 'secondary';
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_alerts[]" value="<?php echo $alert['id']; ?>" class="alert-checkbox">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                            <?php if (!empty($alert['image_path'])): ?>
                                                <span class="badge bg-info ms-1" data-bs-toggle="tooltip" title="Has image">
                                                    <i class="fas fa-image"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                                <?php echo ucfirst($alert['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($alert['categories']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary" data-bs-toggle="tooltip"
                                                  title="Email: <?php echo $alert['email_count']; ?>, SMS: <?php echo $alert['sms_count']; ?>">
                                                <?php echo $alert['recipient_count']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($alert['alert_begins'])); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($alert['alert_expires'])); ?></td>
                                        <td><?php echo htmlspecialchars($alert['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_alert.php?id=<?php echo $alert['id']; ?>"
                                                   class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Alert">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_alert.php?id=<?php echo $alert['id']; ?>"
                                                   class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Edit Alert">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="all_alerts.php?delete=<?php echo $alert['id']; ?>"
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this alert? This will also delete all associated client notifications.')"
                                                   data-bs-toggle="tooltip" title="Delete Alert">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Export buttons -->
<!--                        <div class="mt-3">-->
<!--                            <a href="export_alerts.php?--><?php //echo http_build_query($_GET); ?><!--" class="btn btn-outline-success">-->
<!--                                <i class="fas fa-download me-1"></i> Export All to CSV-->
<!--                            </a>-->
<!--                            <a href="print_alerts.php?--><?php //echo http_build_query($_GET); ?><!--" target="_blank" class="btn btn-outline-secondary ms-2">-->
<!--                                <i class="fas fa-print me-1"></i> Print View-->
<!--                            </a>-->
<!--                        </div>-->
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Toggle select all checkboxes
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.alert-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }

        // Confirm bulk action
        function confirmBulkAction() {
            const form = document.getElementById('bulkActionForm');
            const action = form.bulk_action.value;
            const selectedCount = document.querySelectorAll('.alert-checkbox:checked').length;

            if (!action) {
                alert('Please select a bulk action.');
                return false;
            }

            if (selectedCount === 0) {
                alert('Please select at least one alert.');
                return false;
            }

            let message = '';
            switch (action) {
                case 'delete':
                    message = `Are you sure you want to delete ${selectedCount} alert(s)? This action cannot be undone.`;
                    break;
                case 'export':
                    message = `Export ${selectedCount} alert(s) to CSV?`;
                    break;
            }

            return confirm(message);
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize DataTables if available
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#dataTable').DataTable({
                    pageLength: 25,
                    order: [[6, 'desc']], // Sort by start date descending
                    responsive: true,
                    dom: '<"row"<"col-md-6"l><"col-md-6"f>><"row"<"col-md-12"t>><"row"<"col-md-6"i><"col-md-6"p>>'
                });
            }
        });
    </script>

<?php include 'include/footer.php'; ?>