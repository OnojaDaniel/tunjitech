<?php
// Start session if not already started
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

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

// Get client details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type LIKE 'client_%'");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if client exists
if (!$client) {
    $_SESSION['error_message'] = "Client not found.";
    header("Location: clients.php");
    exit();
}

// Initialize variables to avoid undefined errors
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data with proper null checks
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $company_size = sanitizeInput($_POST['company_size'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if email already exists (excluding current client)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $client_id]);

        if ($stmt->rowCount() > 0) {
            $error = 'Email already exists for another user.';
        } else {
            // Update client
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                    company_name = ?, company_size = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$first_name, $last_name, $email, $phone, $company_name, $company_size, $status, $client_id])) {
                $_SESSION['success_message'] = "Client updated successfully!";
                header("Location: clients.php");
                exit();
            } else {
                $error = 'Error updating client. Please try again.';
            }
        }
    }
}
?>

<?php include 'include/header.php'; ?>

<div class="container-fluid">
    <h3 class="mt-4">Edit Client</h3>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-edit me-1"></i>
                    Edit Client Information
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                        </div>

                        <?php if ($client['user_type'] == 'client_company'): ?>
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name"
                                       value="<?php echo htmlspecialchars($client['company_name'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="company_size" class="form-label">Company Size</label>
                                <select class="form-select" id="company_size" name="company_size">
                                    <option value="">Select Size</option>
                                    <option value="1-10" <?php echo (isset($client['company_size']) && $client['company_size'] == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                    <option value="11-50" <?php echo (isset($client['company_size']) && $client['company_size'] == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                    <option value="51-200" <?php echo (isset($client['company_size']) && $client['company_size'] == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                    <option value="201-500" <?php echo (isset($client['company_size']) && $client['company_size'] == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                    <option value="501+" <?php echo (isset($client['company_size']) && $client['company_size'] == '501+') ? 'selected' : ''; ?>>501+ employees</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo ($client['status'] == 'pending') ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo ($client['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($client['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary me-md-2">Update Client</button>
                            <a href="clients.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    Client Information
                </div>
                <div class="card-body">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($client['username'] ?? ''); ?></p>
                    <p><strong>Type:</strong> <?php echo str_replace('_', ' ', $client['user_type'] ?? ''); ?></p>
                    <p><strong>Registered:</strong> 
                        <?php echo isset($client['created_at']) ? date('M j, Y g:i A', strtotime($client['created_at'])) : 'N/A'; ?>
                    </p>
                    <p><strong>Last Updated:</strong> 
                        <?php echo isset($client['updated_at']) ? date('M j, Y g:i A', strtotime($client['updated_at'])) : 'N/A'; ?>
                    </p>

                    <hr>
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-1"></i>
                        <strong>Note:</strong> Username cannot be changed for security reasons.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>