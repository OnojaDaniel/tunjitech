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

// Check if alert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Alert ID not specified.";
    header("Location: dashboard.php");
    exit();
}

$alert_id = intval($_GET['id']);
$client_id = $_SESSION['user_id'];

// Verify that the alert belongs to this client
$stmt = $pdo->prepare("SELECT ca.id 
                       FROM client_alerts ca 
                       WHERE ca.alert_id = ? AND ca.client_id = ?");
$stmt->execute([$alert_id, $client_id]);
$client_alert = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client_alert) {
    $_SESSION['error_message'] = "Alert not found or you don't have permission to delete it.";
    header("Location: dashboard.php");
    exit();
}

// Handle form submission for confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['confirm_delete'])) {
        // Delete the client-alert association (not the actual alert)
        $stmt = $pdo->prepare("DELETE FROM client_alerts WHERE alert_id = ? AND client_id = ?");

        if ($stmt->execute([$alert_id, $client_id])) {
            $_SESSION['success_message'] = "Alert deleted successfully!";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error deleting alert. Please try again.";
            header("Location: delete_alert.php?id=" . $alert_id);
            exit();
        }
    } else {
        // User cancelled the deletion
        header("Location: dashboard.php");
        exit();
    }
}

// Get alert details for confirmation message
$stmt = $pdo->prepare("SELECT sa.* 
                       FROM security_alerts sa 
                       INNER JOIN client_alerts ca ON sa.id = ca.alert_id 
                       WHERE ca.alert_id = ? AND ca.client_id = ?");
$stmt->execute([$alert_id, $client_id]);
$alert = $stmt->fetch(PDO::FETCH_ASSOC);

// If alert not found (shouldn't happen due to previous check, but just in case)
if (!$alert) {
    $_SESSION['error_message'] = "Alert not found.";
    header("Location: dashboard.php");
    exit();
}
?>

<?php include  'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Delete Security Alert</h1>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Confirm Deletion
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error_message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-circle me-2"></i>Warning</h5>
                            <p>You are about to delete the following security alert. This action cannot be undone.</p>
                        </div>

                        <!-- Alert Details -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Alert Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Title:</strong> <?php echo htmlspecialchars($alert['title']); ?></p>
                                        <p><strong>Severity:</strong>
                                            <span class="badge bg-<?php echo getSeverityBadge($alert['severity']); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                        </p>
                                        <p><strong>Category:</strong> <?php echo htmlspecialchars($alert['categories']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Received:</strong> <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></p>
                                        <p><strong>Starts:</strong> <?php echo date('M j, Y g:i A', strtotime($alert['alert_begins'])); ?></p>
                                        <p><strong>Expires:</strong> <?php echo date('M j, Y g:i A', strtotime($alert['alert_expires'])); ?></p>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <p><strong>Summary:</strong></p>
                                    <div class="border rounded p-3 bg-light">
                                        <?php echo nl2br(htmlspecialchars($alert['summary'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Confirmation Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="confirmation" class="form-label">
                                    Type "DELETE" to confirm:
                                </label>
                                <input type="text" class="form-control" id="confirmation" name="confirmation"
                                       placeholder="Type DELETE here" required
                                       oninput="validateConfirmation(this)">
                                <div class="form-text">This is case-sensitive and must match exactly.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="confirm_delete" id="deleteButton"
                                        class="btn btn-danger me-md-2" disabled>
                                    <i class="fas fa-trash me-1"></i> Confirm Delete
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Deletion Information
                    </div>
                    <div class="card-body">
                        <h6>What happens when you delete an alert?</h6>
                        <ul class="small">
                            <li>The alert will be removed from your dashboard</li>
                            <li>You will no longer receive notifications for this alert</li>
                            <li>The alert is only removed from your view (not from the system)</li>
                            <li>Other users in your organization will still see the alert</li>
                            <li>Administrators can still access the alert</li>
                        </ul>

                        <div class="alert alert-info small">
                            <i class="fas fa-lightbulb me-1"></i>
                            <strong>Tip:</strong> If you think this alert was sent to you by mistake,
                            please contact your administrator instead of deleting it.
                        </div>

                        <h6>Need help?</h6>
                        <p class="small">
                            Contact your system administrator if you have questions about this alert
                            or if you believe it was sent in error.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validate confirmation text
        function validateConfirmation(input) {
            const deleteButton = document.getElementById('deleteButton');
            const confirmationText = input.value.trim();

            if (confirmationText === 'DELETE') {
                deleteButton.disabled = false;
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            } else {
                deleteButton.disabled = true;
                input.classList.remove('is-valid');
                if (confirmationText !== '') {
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            }
        }

        // Add confirmation before leaving page if form is filled
        let formChanged = false;
        document.getElementById('confirmation').addEventListener('input', function() {
            formChanged = this.value !== '';
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>

<?php include 'include/footer.php'; ?>