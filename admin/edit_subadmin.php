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

// Get sub-admin details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = ?");
$stmt->execute([$subadmin_id, USER_TYPE_SUB_ADMIN]);
$subadmin = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if sub-admin exists
if (!$subadmin) {
    $_SESSION['error_message'] = "Sub-admin not found.";
    header("Location: subadmins.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $status = sanitizeInput($_POST['status']);

    // Password fields (optional update)
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists (excluding current sub-admin)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $subadmin_id]);

        if ($stmt->rowCount() > 0) {
            $error = 'Email already exists for another user.';
        } else {
            // Prepare update query
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                        password = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?";
                $params = [$first_name, $last_name, $email, $phone, $hashed_password, $status, $subadmin_id];
            } else {
                // Update without changing password
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                        status = ?, updated_at = NOW() 
                        WHERE id = ?";
                $params = [$first_name, $last_name, $email, $phone, $status, $subadmin_id];
            }

            $stmt = $pdo->prepare($sql);

            if ($stmt->execute($params)) {
                $_SESSION['success_message'] = "Sub-admin updated successfully!";
                header("Location: subadmins.php");
                exit();
            } else {
                $error = 'Error updating sub-admin. Please try again.';
            }
        }
    }
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h3 class="mt-4">Edit Sub-Administrator</h3>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit me-1"></i>
                        Edit Sub-Administrator Information
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
                                               value="<?php echo htmlspecialchars($subadmin['first_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                               value="<?php echo htmlspecialchars($subadmin['last_name']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($subadmin['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($subadmin['phone']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?php echo ($subadmin['status'] == 'pending') ? 'selected' : ''; ?>>Pending Approval</option>
                                    <option value="approved" <?php echo ($subadmin['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($subadmin['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label class="form-label">Password Update (Optional)</label>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Leave password fields blank to keep current password.
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="password" name="password">
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" class="btn btn-primary me-md-2">Update Sub-Admin</button>
                                <a href="view_subadmin.php?id=<?php echo $subadmin['id']; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                                <a href="subadmins.php" class="btn btn-outline-secondary">Back to List</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Sub-Admin Information
                    </div>
                    <div class="card-body">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($subadmin['username']); ?></p>
                        <p><strong>Role:</strong> <span class="badge bg-info">Sub-Administrator</span></p>
                        <p><strong>Registered:</strong> <?php echo date('M j, Y g:i A', strtotime($subadmin['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($subadmin['updated_at'])); ?></p>
                        <p><strong>Last Login:</strong> <?php echo $subadmin['last_login'] ? date('M j, Y g:i A', strtotime($subadmin['last_login'])) : 'Never'; ?></p>

                        <hr>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Note:</strong> Username cannot be changed for security reasons.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shield-alt me-1"></i>
                        Permissions Information
                    </div>
                    <div class="card-body">
                        <h6>Current Permissions:</h6>
                        <ul class="small">
                            <li><i class="fas fa-check text-success me-1"></i> Manage security alerts</li>
                            <li><i class="fas fa-check text-success me-1"></i> Send notifications</li>
                            <li><i class="fas fa-check text-success me-1"></i> View analytics</li>
                            <li><i class="fas fa-check text-success me-1"></i> Chat with clients</li>
                        </ul>

                        <h6>Restrictions:</h6>
                        <ul class="small">
                            <li><i class="fas fa-times text-danger me-1"></i> Client management</li>
                            <li><i class="fas fa-times text-danger me-1"></i> Sub-admin management</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            // Only validate if password field is not empty
            if (password.value && password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                confirmPassword.focus();
            }

            if (password.value && password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                password.focus();
            }
        });

        // Show/hide password fields based on user interaction
        document.getElementById('password').addEventListener('input', function() {
            const confirmField = document.getElementById('confirm_password');
            if (this.value) {
                confirmField.setAttribute('required', 'required');
            } else {
                confirmField.removeAttribute('required');
            }
        });
    </script>

<?php include 'include/footer.php'; ?>