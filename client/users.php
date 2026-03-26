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


// Load Composer's autoloader (if using Composer)
require_once ROOT_PATH . '/vendor/autoload.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Get client information
$client_id = $_SESSION['user_id'];
$client = getUserById($client_id);

// Check if client is an organization (can manage users)
$canManageUsers = ($client['user_type'] == 'client_company');
if (!$canManageUsers) {
    $_SESSION['error_message'] = "Individual accounts cannot manage users.";
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission for adding new user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!empty($phone) && !validatePhoneNumber($phone)) {
        $error = 'Please enter a valid phone number (e.g., +2348078200765, 08078200765, or 8078200765).';
    } else {
        // Check if username or email already exists in client_users
        $stmt = $pdo->prepare("SELECT id FROM client_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Format phone number if provided
            $formatted_phone = !empty($phone) ? formatPhoneForDatabase($phone) : null;

            // Insert new user
            $sql = "INSERT INTO client_users (client_id, username, email, password, first_name, last_name, phone, role) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$client_id, $username, $email, $hashed_password, $first_name, $last_name, $formatted_phone, $role])) {
                // Send welcome email to new user
                if (sendClientUserWelcomeEmail($email, $username, $password, $first_name . ' ' . $last_name)) {
                    $success = 'User added successfully! Welcome email sent.';
                } else {
                    $success = 'User added successfully! Welcome email could not be sent.';
                }

                // Clear form
                $_POST = array();
            } else {
                $error = 'Error adding user. Please try again.';
            }
        }
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);

    // Check if user belongs to this client
    $stmt = $pdo->prepare("DELETE FROM client_users WHERE id = ? AND client_id = ?");
    if ($stmt->execute([$user_id, $client_id])) {
        $_SESSION['success_message'] = "User deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting user.";
    }
    header("Location: users.php");
    exit();
}

// Handle user status toggle
if (isset($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);

    // Get current status
    $stmt = $pdo->prepare("SELECT status FROM client_users WHERE id = ? AND client_id = ?");
    $stmt->execute([$user_id, $client_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $new_status = ($user['status'] == 'active') ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE client_users SET status = ? WHERE id = ? AND client_id = ?");

        if ($stmt->execute([$new_status, $user_id, $client_id])) {
            $_SESSION['success_message'] = "User status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating user status.";
        }
    }
    header("Location: users.php");
    exit();
}

// Handle edit user
if (isset($_GET['edit'])) {
    $user_id = intval($_GET['edit']);

    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM client_users WHERE id = ? AND client_id = ?");
    $stmt->execute([$user_id, $client_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$edit_user) {
        $_SESSION['error_message'] = "User not found.";
        header("Location: users.php");
        exit();
    }
}

// Handle update user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);

    // Validate inputs
    if (empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!empty($phone) && !validatePhoneNumber($phone)) {
        $error = 'Please enter a valid phone number (e.g., +2348078200765, 08078200765, or 8078200765).';
    } else {
        // Format phone number if provided
        $formatted_phone = !empty($phone) ? formatPhoneForDatabase($phone) : null;

        // Update user
        $sql = "UPDATE client_users SET first_name = ?, last_name = ?, phone = ?, role = ?, updated_at = NOW() 
                WHERE id = ? AND client_id = ?";

        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$first_name, $last_name, $formatted_phone, $role, $user_id, $client_id])) {
            $_SESSION['success_message'] = "User updated successfully!";
            header("Location: users.php");
            exit();
        } else {
            $error = 'Error updating user. Please try again.';
        }
    }
}

// Get all users for this client
$stmt = $pdo->prepare("SELECT * FROM client_users WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$client_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Validate phone number format
 */
function validatePhoneNumber($phone) {
    // Remove any non-digit characters except +
    $phone = preg_replace('/[^\d\+]/', '', $phone);

    // Check for valid Nigerian phone formats
    if (preg_match('/^\+234[7-9][0-9]{9}$/', $phone)) {
        return true; // +234XXXXXXXXXX format
    } elseif (preg_match('/^234[7-9][0-9]{9}$/', $phone)) {
        return true; // 234XXXXXXXXXX format
    } elseif (preg_match('/^0[7-9][0-9]{9}$/', $phone)) {
        return true; // 0XXXXXXXXXX format
    } elseif (preg_match('/^[7-9][0-9]{9}$/', $phone)) {
        return true; // XXXXXXXXXX format (without 0)
    }

    return false;
}

/**
 * Format phone number for database storage
 */
function formatPhoneForDatabase($phone) {
    // Remove any non-digit characters except +
    $phone = preg_replace('/[^\d\+]/', '', $phone);

    // Convert to +234 format for storage
    if (strpos($phone, '+234') === 0) {
        return $phone; // Already in +234 format
    } elseif (strpos($phone, '234') === 0) {
        return '+' . $phone; // Convert 234 to +234
    } elseif ($phone[0] === '0' && strlen($phone) === 11) {
        return '+234' . substr($phone, 1); // Convert 0XXXXXXXXXX to +234XXXXXXXXXX
    } elseif (strlen($phone) === 10 && ($phone[0] === '7' || $phone[0] === '8' || $phone[0] === '9')) {
        return '+234' . $phone; // Convert XXXXXXXXXX to +234XXXXXXXXXX
    }

    return $phone; // Return as-is if doesn't match patterns
}

/**
 * Format phone for display
 */
function formatPhoneForDisplay($phone) {
    if (empty($phone)) return 'N/A';

    // If already in +234 format, format nicely
    if (strpos($phone, '+234') === 0 && strlen($phone) === 14) {
        $number = substr($phone, 4); // Remove +234
        return '+234 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6, 4);
    }

    return $phone;
}

/**
 * Send welcome email to client user
 */
function sendClientUserWelcomeEmail($email, $username, $password, $name) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'inaleguonoja@gmail.com';
        $mail->Password   = 'vcyv zikr ljru httw';

        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 587;
        }

        // Recipients
        $mail->setFrom('inaleguonoja@gmail.com', 'Security Alert System - Tunjitech');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Our Security Alert System';

        // Email body
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                    .content { background-color: #f8f9fa; padding: 20px; }
                    .footer { background-color: #343a40; color: white; padding: 10px; text-align: center; }
                    .credentials { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Security Alert System</h1>
                    </div>
                    <div class='content'>
                        <h2>Welcome, $name!</h2>
                        <p>Your account has been created by your organization administrator for accessing the Security Alert System.</p>
                        
                        <div class='credentials'>
                            <h3>Your Login Credentials:</h3>
                            <p><strong>Username:</strong> $username</p>
                            <p><strong>Password:</strong> $password</p>
                        </div>
                        
                        <p><strong>Important:</strong> For security reasons, we recommend that you change your password after your first login.</p>
                        
                        <p><a href='" . BASE_URL . "/login.php' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Login to Your Account</a></p>
                        
                        <p>You will receive security alerts via email and SMS (if phone number is provided) whenever there are important security updates.</p>
                        
                        <p>If you have any questions or need assistance, please contact your organization administrator or our support team.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Tunjitech Security Alert System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        // Plain text version
        $mail->AltBody = "Welcome to Security Alert System\n\n" .
            "Dear $name,\n\n" .
            "Your account has been created by your organization administrator.\n\n" .
            "Your Login Credentials:\n" .
            "Username: $username\n" .
            "Password: $password\n\n" .
            "Important: For security reasons, please change your password after first login.\n\n" .
            "Login URL: " . BASE_URL . "/login.php\n\n" .
            "You will receive security alerts via email and SMS.\n\n" .
            "For assistance, contact your administrator or support team.\n\n" .
            "© " . date('Y') . " Tunjitech Security Alert System";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Welcome Email Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<?php include 'include/header.php'; ?>


    <div class="container-fluid">
        <h1 class="mt-4">User Management</h1>

        <!-- Display messages -->
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

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5">
                <!-- Add/Edit User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-<?php echo isset($edit_user) ? 'edit' : 'user-plus'; ?> me-1"></i>
                        <?php echo isset($edit_user) ? 'Edit User' : 'Add New User'; ?>
                    </div>
                    <div class="card-body">
                        <?php if (isset($edit_user)): ?>
                            <form method="POST" action="">
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                   value="<?php echo htmlspecialchars($edit_user['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   value="<?php echo htmlspecialchars($edit_user['last_name']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number (for SMS alerts)</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo formatPhoneForDisplay($edit_user['phone']); ?>"
                                           placeholder="e.g., +2348078200765, 08078200765, or 8078200765">
                                    <div class="form-text">
                                        Optional. Used for SMS security alerts. Nigerian numbers only.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user" <?php echo ($edit_user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo ($edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <div class="form-text">
                                        Admins can manage users and access all features. Users have limited access.
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username"
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number (for SMS alerts)</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                           placeholder="e.g., +2348078200765, 08078200765, or 8078200765">
                                    <div class="form-text">
                                        Optional. Used for SMS security alerts. Nigerian numbers only.
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <div class="form-text">
                                        Admins can manage users and access all features. Users have limited access.
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <!-- Users List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users me-1"></i>
                        Organization Users (<?php echo count($client_users); ?>)
                    </div>
                    <div class="card-body">
                        <?php if (count($client_users) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($client_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo formatPhoneForDisplay($user['phone']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'warning' : 'info'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="users.php?edit=<?php echo $user['id']; ?>"
                                                       class="btn btn-sm btn-info"
                                                       title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="users.php?toggle_status=<?php echo $user['id']; ?>"
                                                       class="btn btn-sm btn-<?php echo $user['status'] == 'active' ? 'warning' : 'success'; ?>"
                                                       title="<?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas fa-<?php echo $user['status'] == 'active' ? 'times' : 'check'; ?>"></i>
                                                    </a>
                                                    <a href="users.php?delete=<?php echo $user['id']; ?>"
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')"
                                                       title="Delete User">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                No users added yet. Use the form to add users to your organization.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Management Guidelines -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        User Management Guidelines
                    </div>
                    <div class="card-body">
                        <h6>User Roles:</h6>
                        <ul class="small">
                            <li><strong>Admin:</strong> Can manage users, view all alerts, and access all features</li>
                            <li><strong>User:</strong> Can view alerts but cannot manage users or access admin features</li>
                        </ul>

                        <h6>Phone Numbers:</h6>
                        <ul class="small">
                            <li>Phone numbers are optional but recommended for SMS alert delivery</li>
                            <li>Enter phone numbers in any Nigerian format</li>
                            <li>Users with phone numbers will receive SMS security alerts</li>
                            <li>Numbers are automatically formatted to +234XXXXXXXXXX for storage</li>
                        </ul>

                        <h6>Accepted Phone Formats:</h6>
                        <ul class="small">
                            <li><code>+2348078200765</code> (International format)</li>
                            <li><code>08078200765</code> (Local format with leading 0)</li>
                            <li><code>8078200765</code> (Without country code)</li>
                            <li><code>2348078200765</code> (Without +)</li>
                        </ul>

                        <h6>Best Practices:</h6>
                        <ul class="small">
                            <li>Assign admin role only to trusted team members</li>
                            <li>Collect phone numbers for critical SMS alerts</li>
                            <li>Regularly review and update user access permissions</li>
                            <li>Deactivate users who no longer need access to the system</li>
                            <li>Use strong, unique passwords for each user account</li>
                        </ul>

                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Note:</strong> All users will receive the same security alerts and will be subject to the same terms and conditions as the main account.
                        </div>
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

            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    confirmPassword.focus();
                }

                if (password.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    password.focus();
                }
            }

            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput && phoneInput.value.trim() !== '') {
                let phone = phoneInput.value.trim();

                // Remove any non-digit characters except +
                phone = phone.replace(/[^\d\+]/g, '');

                // Format validation
                const validFormats = [
                    /^\+234[7-9][0-9]{9}$/,  // +234XXXXXXXXXX
                    /^234[7-9][0-9]{9}$/,    // 234XXXXXXXXXX
                    /^0[7-9][0-9]{9}$/,      // 0XXXXXXXXXX
                    /^[7-9][0-9]{9}$/        // XXXXXXXXXX
                ];

                let isValid = false;
                for (const format of validFormats) {
                    if (format.test(phone)) {
                        isValid = true;
                        break;
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('Please enter a valid Nigerian phone number.\n\nAccepted formats:\n• +2348078200765\n• 08078200765\n• 8078200765\n• 2348078200765');
                    phoneInput.focus();
                }
            }
        });

        // Phone number real-time formatting
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let phone = this.value.trim();

                // Remove any non-digit characters except +
                phone = phone.replace(/[^\d\+]/g, '');

                // Auto-format as user types
                if (phone.length > 0) {
                    if (phone.startsWith('0') && phone.length <= 11) {
                        // Keep as is for 0XXXXXXXXXX format
                    } else if (phone.startsWith('+234')) {
                        // Keep as is for +234 format
                    } else if (phone.startsWith('234')) {
                        // Keep as is for 234 format
                    } else if (/^[7-9]/.test(phone) && phone.length <= 10) {
                        // Keep as is for XXXXXXXXXX format
                    }
                }

                // Update input value
                this.value = phone;
            });

            // Show format hint on focus
            phoneInput.addEventListener('focus', function() {
                if (!this.value) {
                    this.placeholder = 'e.g., +2348078200765';
                }
            });
        }
    </script>

<?php include 'include/footer.php'; ?>