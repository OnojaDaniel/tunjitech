<?php

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


// Check if user is logged in and is admin (only admins can create sub-admins)
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Define sub-admin permissions (everything except client management)
            $permissions = [
                PERMISSION_MANAGE_ALERTS,
                PERMISSION_VIEW_ANALYTICS
                // Note: PERMISSION_MANAGE_CLIENTS and PERMISSION_MANAGE_SUB_ADMINS are excluded
            ];

            // Insert new sub-admin
            $sql = "INSERT INTO users (username, email, password, user_type, first_name, last_name, phone, permissions, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')";

            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$username, $email, $hashed_password, USER_TYPE_SUB_ADMIN, $first_name, $last_name, $phone, json_encode($permissions)])) {
                $success = 'Sub-admin registered successfully!';

                // Send welcome email
                if (sendWelcomeMail($email, $username, $password, $first_name . ' ' . $last_name, true)) {
                    $success .= ' Welcome email sent.';
                }
                else {
                    $mailNotSent = ' Mail not sent';
                }

                // Clear form if needed
                if (!isset($_POST['add_another'])) {
                    $_POST = array();
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}


function sendWelcomeMail($email, $username, $password, $name, $isSubAdmin = false) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'inaleguonoja@gmail.com';
        $mail->Password   = 'vcyv zikr ljru httw'; // your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('inaleguonoja@gmail.com', 'Security Alert - TunjiTech');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);

        // Set subject and content based on role
        if ($isSubAdmin) {
            $mail->Subject = 'Welcome as Sub-Administrator - Security Alert System';
            $mail->Body = createSubAdminWelcomeEmail($name, $username, $password);
            $mail->AltBody = createSubAdminWelcomeEmailText($name, $username, $password);
        } else {
            $mail->Subject = 'Welcome to Security Alert System';
            $mail->Body = createClientWelcomeEmail($name, $username, $password);
            $mail->AltBody = createClientWelcomeEmailText($name, $username, $password);
        }

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Create HTML welcome email for sub-admins
 */
function createSubAdminWelcomeEmail($name, $username, $password) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background-color: #f9f9f9;
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
                border-radius: 10px 10px 0 0;
            }
            .content { 
                background-color: white; 
                padding: 30px; 
                border-radius: 0 0 10px 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .credentials { 
                background-color: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #667eea;
            }
            .permissions { 
                background-color: #e8f4fd; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
                border-left: 4px solid #2196F3;
            }
            .restrictions { 
                background-color: #fff3cd; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
                border-left: 4px solid #ffc107;
            }
            .footer { 
                background-color: #343a40; 
                color: white; 
                padding: 20px; 
                text-align: center; 
                border-radius: 0 0 10px 10px;
                margin-top: 20px;
            }
            .btn-primary { 
                display: inline-block; 
                padding: 12px 30px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                font-weight: bold;
                margin: 10px 0;
            }
            .badge { 
                display: inline-block; 
                padding: 5px 10px; 
                background-color: #667eea; 
                color: white; 
                border-radius: 15px; 
                font-size: 12px;
                margin-left: 10px;
            }
            ul { 
                padding-left: 20px; 
            }
            li { 
                margin-bottom: 8px; 
            }
            .icon { 
                margin-right: 8px; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔒 Security Alert System</h1>
                <h2>Welcome as Sub-Administrator!</h2>
            </div>
            <div class='content'>
                <h3>Dear {$name},</h3>
                
                <p>You have been registered as a <strong>Sub-Administrator</strong> for the Security Alert System. Your account has been created with the following credentials:</p>
                
                <div class='credentials'>
                    <h4>🔑 Your Login Credentials:</h4>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Password:</strong> {$password}</p>
                    <p><em>For security reasons, we recommend changing your password after first login.</em></p>
                </div>

                <div class='permissions'>
                    <h4>✅ Permitted Actions:</h4>
                    <ul>
                        <li>📝 Create and manage security alerts</li>
                        <li>✏️ Edit existing security alerts</li>
                        <li>🗑️ Delete security alerts</li>
                        <li>📧 Send email notifications to clients</li>
                        <li>📱 Send SMS notifications to clients</li>
                        <li>📊 View analytics and reports</li>
                        <li>💬 Chat with clients</li>
                        <li>🔔 Manage dashboard notifications</li>
                    </ul>
                </div>

                <div class='restrictions'>
                    <h4>🚫 Restricted Actions:</h4>
                    <ul>
                        <li>👥 Register new clients</li>
                        <li>✏️ Edit client information</li>
                        <li>🗑️ Delete client accounts</li>
                        <li>✅ Approve/reject client registrations</li>
                        <li>🛡️ Manage other sub-administrators</li>
                        <li>⚙️ System configuration changes</li>
                    </ul>
                </div>

                <p><strong>Important Notes:</strong></p>
                <ul>
                    <li>You can access the admin dashboard using the link below</li>
                    <li>All your activities are logged for security purposes</li>
                    <li>Contact the main administrator for any permission-related queries</li>
                    <li>Ensure you follow security best practices when handling alerts</li>
                </ul>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . BASE_URL . "/admin/login.php' class='btn-primary'>
                        🚀 Access Admin Dashboard
                    </a>
                </div>

                <p>If you have any questions or need assistance, please contact the main administrator.</p>
                
                <p>Best regards,<br>
                <strong>Security Alert System Team</strong><br>
                TunjiTech Consulting</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Security Alert System. All rights reserved.</p>
                <p>TunjiTech Consulting | Secure • Reliable • Professional</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Create plain text welcome email for sub-admins
 */
function createSubAdminWelcomeEmailText($name, $username, $password) {
    return "
SECURITY ALERT SYSTEM - SUB-ADMINISTRATOR ACCOUNT

Dear {$name},

You have been registered as a Sub-Administrator for the Security Alert System.

YOUR LOGIN CREDENTIALS:
Username: {$username}
Password: {$password}

IMPORTANT: For security reasons, we recommend changing your password after first login.

PERMITTED ACTIONS:
- Create and manage security alerts
- Edit existing security alerts
- Delete security alerts
- Send email notifications to clients
- Send SMS notifications to clients
- View analytics and reports
- Chat with clients
- Manage dashboard notifications

RESTRICTED ACTIONS:
- Register new clients
- Edit client information
- Delete client accounts
- Approve/reject client registrations
- Manage other sub-administrators
- System configuration changes

ACCESS YOUR ACCOUNT:
Login URL: " . BASE_URL . "/admin/login.php

IMPORTANT NOTES:
- All your activities are logged for security purposes
- Contact the main administrator for any permission-related queries
- Ensure you follow security best practices when handling alerts

If you have any questions or need assistance, please contact the main administrator.

Best regards,
Security Alert System Team
TunjiTech Consulting

© " . date('Y') . " Security Alert System. All rights reserved.
    ";
}

/**
 * Create HTML welcome email for clients (existing function)
 */
function createClientWelcomeEmail($name, $username, $password) {
    return "
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
                <h2>Welcome, {$name}!</h2>
                <p>Your account has been successfully created by the administrator.</p>
                
                <div class='credentials'>
                    <h3>Your Login Credentials:</h3>
                    <p><strong>Username:</strong> {$username}</p>
                    <p><strong>Password:</strong> {$password}</p>
                </div>
                
                <p>For security reasons, we recommend that you change your password after your first login.</p>
                
                <p><a href='" . BASE_URL . "/login.php' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Login to Your Account</a></p>
                
                <p>If you have any questions or need assistance, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Security Alert System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Create plain text welcome email for clients (existing function)
 */
function createClientWelcomeEmailText($name, $username, $password) {
    return "Welcome to Security Alert System\n\n" .
        "Dear {$name},\n\n" .
        "Your account has been successfully created by the administrator.\n\n" .
        "Your Login Credentials:\n" .
        "Username: {$username}\n" .
        "Password: {$password}\n\n" .
        "For security reasons, we recommend that you change your password after your first login.\n\n" .
        "Login URL: " . BASE_URL . "/login.php\n\n" .
        "If you have any questions or need assistance, please contact our support team.\n\n" .
        "© " . date('Y') . " Security Alert System. All rights reserved.";
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h3 class="mt-4">Register New Sub-Administrator</h3>
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-shield me-1"></i>
                        Sub-Administrator Registration Form
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? $_POST['first_name'] : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? $_POST['last_name'] : ''; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>">
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

                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="register_subadmin" class="btn btn-primary me-md-2">Register Sub-Admin</button>
                                <button type="submit" name="add_another" class="btn btn-outline-primary">Register & Add Another</button>
                                <a href="subadmins.php" class="btn btn-secondary ms-md-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Sub-Administrator Permissions
                    </div>
                    <div class="card-body">
                        <h6>Allowed Actions:</h6>
                        <ul class="small">
                            <li><i class="fas fa-check text-success me-1"></i> Create security alerts</li>
                            <li><i class="fas fa-check text-success me-1"></i> Edit security alerts</li>
                            <li><i class="fas fa-check text-success me-1"></i> Delete security alerts</li>
                            <li><i class="fas fa-check text-success me-1"></i> Send notifications (Email/SMS)</li>
                            <li><i class="fas fa-check text-success me-1"></i> View analytics and reports</li>
                            <li><i class="fas fa-check text-success me-1"></i> Chat with clients</li>
                        </ul>

                        <h6>Restricted Actions:</h6>
                        <ul class="small">
                            <li><i class="fas fa-times text-danger me-1"></i> Register new clients</li>
                            <li><i class="fas fa-times text-danger me-1"></i> Edit client information</li>
                            <li><i class="fas fa-times text-danger me-1"></i> Delete clients</li>
                            <li><i class="fas fa-times text-danger me-1"></i> Approve/reject clients</li>
                            <li><i class="fas fa-times text-danger me-1"></i> Manage other sub-admins</li>
                        </ul>

                        <div class="alert alert-info small">
                            <i class="fas fa-lightbulb me-1"></i>
                            <strong>Note:</strong> Only main administrators can create and manage sub-administrators.
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
        });
    </script>

<?php include 'include/footer.php'; ?>