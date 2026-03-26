<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// Handle client approval
if (isset($_GET['approve'])) {
    $client_id = intval($_GET['approve']);
    $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
    if ($stmt->execute([$client_id])) {
        $_SESSION['success_message'] = "Client approved successfully!";
    } else {
        $_SESSION['error_message'] = "Error approving client.";
    }
    header("Location: clients.php");
    exit();
}

// Handle client rejection
if (isset($_GET['reject'])) {
    $client_id = intval($_GET['reject']);
    $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$client_id])) {
        $_SESSION['success_message'] = "Client rejected successfully!";
    } else {
        $_SESSION['error_message'] = "Error rejecting client.";
    }
    header("Location: clients.php");
    exit();
}

// Handle client deletion
if (isset($_GET['delete'])) {
    $client_id = intval($_GET['delete']);
    
    // Check if client has any alerts before deletion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM client_alerts WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $alert_count = $stmt->fetchColumn();
    
    if ($alert_count > 0) {
        // Show confirmation page for force deletion
        $_SESSION['force_delete_client_id'] = $client_id;
        $_SESSION['force_delete_alert_count'] = $alert_count;
        header("Location: confirm_force_delete.php");
        exit();
    } else {
        // No alerts, proceed with normal deletion
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type LIKE 'client_%'");
        if ($stmt->execute([$client_id])) {
            $_SESSION['success_message'] = "Client deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting client.";
        }
        header("Location: clients.php");
        exit();
    }
}

// Handle force deletion confirmation
if (isset($_POST['force_delete'])) {
    $client_id = intval($_POST['client_id']);
    $delete_alerts = isset($_POST['delete_alerts']) ? true : false;
    
    try {
        $pdo->beginTransaction();
        
        if ($delete_alerts) {
            // Delete client alerts first
            $stmt = $pdo->prepare("DELETE FROM client_alerts WHERE client_id = ?");
            $stmt->execute([$client_id]);
        }
        
        // Delete the client
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type LIKE 'client_%'");
        $stmt->execute([$client_id]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Client and associated data deleted successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting client: " . $e->getMessage();
    }
    
    // Clear session variables
    unset($_SESSION['force_delete_client_id']);
    unset($_SESSION['force_delete_alert_count']);
    
    header("Location: clients.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_clients'])) {
    $selected_clients = $_POST['selected_clients'];
    $action = $_POST['bulk_action'];
    
    if (!empty($selected_clients)) {
        $placeholders = implode(',', array_fill(0, count($selected_clients), '?'));
        
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id IN ($placeholders)");
                if ($stmt->execute($selected_clients)) {
                    $_SESSION['success_message'] = "Selected clients approved successfully!";
                } else {
                    $_SESSION['error_message'] = "Error approving clients.";
                }
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id IN ($placeholders)");
                if ($stmt->execute($selected_clients)) {
                    $_SESSION['success_message'] = "Selected clients rejected successfully!";
                } else {
                    $_SESSION['error_message'] = "Error rejecting clients.";
                }
                break;
                
            case 'delete':
                // Check if any selected clients have alerts
                $clients_with_alerts = [];
                foreach ($selected_clients as $client_id) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM client_alerts WHERE client_id = ?");
                    $stmt->execute([$client_id]);
                    $alert_count = $stmt->fetchColumn();
                    
                    if ($alert_count > 0) {
                        $clients_with_alerts[] = $client_id;
                    }
                }
                
                if (!empty($clients_with_alerts)) {
                    // Store clients with alerts in session for bulk force deletion
                    $_SESSION['bulk_force_delete_clients'] = $clients_with_alerts;
                    $_SESSION['bulk_force_delete_count'] = count($clients_with_alerts);
                    $_SESSION['bulk_remaining_clients'] = array_diff($selected_clients, $clients_with_alerts);
                    header("Location: confirm_bulk_force_delete.php");
                    exit();
                } else {
                    // No clients have alerts, proceed with normal deletion
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND user_type LIKE 'client_%'");
                    if ($stmt->execute($selected_clients)) {
                        $_SESSION['success_message'] = "Selected clients deleted successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error deleting clients.";
                    }
                }
                break;
        }
    } else {
        $_SESSION['error_message'] = "No clients selected for bulk action.";
    }
    header("Location: clients.php");
    exit();
}

// Handle bulk force deletion
if (isset($_POST['bulk_force_delete'])) {
    $selected_clients = $_POST['selected_clients'];
    $delete_alerts = isset($_POST['delete_alerts']) ? true : false;
    
    try {
        $pdo->beginTransaction();
        
        if ($delete_alerts) {
            // Delete client alerts first for all selected clients
            $placeholders = implode(',', array_fill(0, count($selected_clients), '?'));
            $stmt = $pdo->prepare("DELETE FROM client_alerts WHERE client_id IN ($placeholders)");
            $stmt->execute($selected_clients);
        }
        
        // Delete the clients
        $placeholders = implode(',', array_fill(0, count($selected_clients), '?'));
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND user_type LIKE 'client_%'");
        $stmt->execute($selected_clients);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = count($selected_clients) . " clients and associated data deleted successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting clients: " . $e->getMessage();
    }
    
    // Clear session variables
    unset($_SESSION['bulk_force_delete_clients']);
    unset($_SESSION['bulk_force_delete_count']);
    unset($_SESSION['bulk_remaining_clients']);
    
    header("Location: clients.php");
    exit();
}

// Get all clients with additional information
$stmt = $pdo->prepare("SELECT u.*, 
                      (SELECT COUNT(*) FROM client_alerts WHERE client_id = u.id) as alert_count,
                      (SELECT MAX(created_at) FROM client_alerts WHERE client_id = u.id) as last_alert_date
                      FROM users u 
                      WHERE u.user_type LIKE 'client_%' 
                      ORDER BY u.created_at DESC");
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- Top of table -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4">Client Management</h1>
        <a href="register_client.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Register New Client
        </a>
    </div>

    <!-- Bulk Actions Form -->
    <form method="POST" action="clients.php" id="bulkActionForm">
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-users me-1"></i>
                        All Clients
                    </div>
                    <div class="d-flex align-items-center">
                        <select name="bulk_action" class="form-select form-select-sm me-2" style="width: auto;">
                            <option value="">Bulk Actions</option>
                            <option value="approve">Approve Selected</option>
                            <option value="reject">Reject Selected</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirmBulkAction()">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Company</th>
                            <th>Alerts</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_clients[]" value="<?php echo $client['id']; ?>" class="client-checkbox">
                                </td>
                                <td><?php echo htmlspecialchars($client['username']); ?></td>
                                <td><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($client['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>">
                                            <?php echo htmlspecialchars($client['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo str_replace('_', ' ', $client['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $client['company_name'] ? htmlspecialchars($client['company_name']) : 'N/A'; ?></td>
                                <td class="text-center">
                                    <?php if ($client['alert_count'] > 0): ?>
                                        <span class="badge bg-warning" data-bs-toggle="tooltip"
                                              title="Last alert: <?php echo $client['last_alert_date'] ? date('M j, Y', strtotime($client['last_alert_date'])) : 'Never'; ?>">
                                            <?php echo $client['alert_count']; ?> alert(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0 alerts</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadge($client['status']); ?>">
                                        <?php echo ucfirst($client['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($client['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_client.php?id=<?php echo $client['id']; ?>"
                                           class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Client">
                                            <iconify-icon icon="heroicons:eye" class="icon"></iconify-icon>
                                        </a>
                                        <a href="edit_client.php?id=<?php echo $client['id']; ?>"
                                           class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Edit Client">
                                            <iconify-icon icon="heroicons:pencil" class="icon"></iconify-icon>
                                        </a>
                                        <?php if ($client['status'] == 'pending'): ?>
                                            <a href="clients.php?approve=<?php echo $client['id']; ?>"
                                               class="btn btn-success btn-sm" data-bs-toggle="tooltip" title="Approve Client">
                                                <iconify-icon icon="mdi:check-circle" class="icon"></iconify-icon>
                                            </a>
                                            <a href="clients.php?reject=<?php echo $client['id']; ?>"
                                               class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Reject Client">
                                                <iconify-icon icon="mdi:delete" class="icon"></iconify-icon>
                                            </a>
                                        <?php else: ?>
                                            <a href="clients.php?delete=<?php echo $client['id']; ?>"
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirmDelete(<?php echo $client['id']; ?>, <?php echo $client['alert_count']; ?>)"
                                               data-bs-toggle="tooltip" title="Delete Client">
                                                <iconify-icon icon="mdi:delete" class="icon"></iconify-icon>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Toggle select all checkboxes
    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.client-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
    }

    // Confirm delete with alert count warning
    function confirmDelete(clientId, alertCount) {
        if (alertCount > 0) {
            return confirm(`This client has ${alertCount} associated security alert(s).\n\nYou will be redirected to a confirmation page to proceed with deletion.`);
        } else {
            return confirm('Are you sure you want to delete this client? This action cannot be undone.');
        }
    }

    // Confirm bulk action
    function confirmBulkAction() {
        const form = document.getElementById('bulkActionForm');
        const action = form.bulk_action.value;
        const selectedCount = document.querySelectorAll('.client-checkbox:checked').length;

        if (!action) {
            alert('Please select a bulk action.');
            return false;
        }

        if (selectedCount === 0) {
            alert('Please select at least one client.');
            return false;
        }

        let message = '';
        switch (action) {
            case 'approve':
                message = `Are you sure you want to approve ${selectedCount} client(s)?`;
                break;
            case 'reject':
                message = `Are you sure you want to reject ${selectedCount} client(s)?`;
                break;
            case 'delete':
                message = `Are you sure you want to delete ${selectedCount} client(s)? This action cannot be undone.`;
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
    });
</script>

<?php include 'include/footer.php'; ?>