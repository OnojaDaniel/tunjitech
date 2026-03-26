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

// Handle sub-admin deletion
if (isset($_GET['delete'])) {
    $subadmin_id = intval($_GET['delete']);

    // Prevent admin from deleting themselves
    if ($subadmin_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type = ?");
        if ($stmt->execute([$subadmin_id, USER_TYPE_SUB_ADMIN])) {
            $_SESSION['success_message'] = "Sub-admin deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting sub-admin.";
        }
    }
    header("Location: subadmins.php");
    exit();
}

// Get all sub-admins
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_type = ? ORDER BY created_at DESC");
$stmt->execute([USER_TYPE_SUB_ADMIN]);
$subadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <h3 class="mt-4">Sub-Administrator Management</h3>
            <a href="register_subadmin.php" class="btn btn-primary">
                <i class="fas fa-user-shield me-1"></i> Register New Sub-Admin
            </a>
        </div>

        <!-- Sub-Admins List -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-user-shield me-1"></i>
                All Sub-Administrators
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subadmins as $subadmin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subadmin['username']); ?></td>
                                <td><?php echo htmlspecialchars($subadmin['first_name'] . ' ' . $subadmin['last_name']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($subadmin['email']); ?>">
                                        <?php echo htmlspecialchars($subadmin['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($subadmin['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($subadmin['phone']); ?>">
                                            <?php echo htmlspecialchars($subadmin['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadge($subadmin['status']); ?>">
                                        <?php echo ucfirst($subadmin['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($subadmin['created_at'])); ?></td>
                                <td>
                                    <?php echo $subadmin['last_login'] ? date('M j, Y g:i A', strtotime($subadmin['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_subadmin.php?id=<?php echo $subadmin['id']; ?>"
                                           class="btn btn-info btn-sm" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_subadmin.php?id=<?php echo $subadmin['id']; ?>"
                                           class="btn btn-warning btn-sm" data-bs-toggle="tooltip" title="Edit Sub-Admin">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="subadmins.php?delete=<?php echo $subadmin['id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this sub-administrator? This action cannot be undone.')"
                                           data-bs-toggle="tooltip" title="Delete Sub-Admin">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

<?php include 'include/footer.php'; ?>