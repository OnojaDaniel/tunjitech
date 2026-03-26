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

// Load Composer's autoloader (if using Composer)
require_once ROOT_PATH . '/vendor/autoload.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

// Define payment plans
$payment_plans = [
    'monthly' => [
        'name' => 'Monthly Security Alert Plan',
        'duration' => '1 month',
        'price' => '₦50,000'
    ],
    '3months' => [
        'name' => '3 Months Security Alert Plan',
        'duration' => '3 months',
        'price' => '₦140,000'
    ],
    '6months' => [
        'name' => '6 Months Security Alert Plan',
        'duration' => '6 months',
        'price' => '₦250,000'
    ],
    '12months' => [
        'name' => '12 Months Security Alert Plan',
        'duration' => '12 months',
        'price' => '₦400,000'
    ]
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $user_type = sanitizeInput($_POST['user_type']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $status = sanitizeInput($_POST['status']);
    $payment_plan = sanitizeInput($_POST['payment_plan']);
    $subscription_expiry = calculateExpiryDate($payment_plan);

    // Company-specific fields
    $company_name = '';
    $company_size = '';

    if ($user_type == 'client_company') {
        $company_name = sanitizeInput($_POST['company_name']);
        $company_size = sanitizeInput($_POST['company_size']);
    }

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (empty($payment_plan)) {
        $error = 'Please select a subscription plan.';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // FIXED: Use last_payment_date instead of payment_date
            $sql = "INSERT INTO users (username, email, password, user_type, first_name, last_name, phone, company_name, company_size, status, payment_plan, subscription_expiry, last_payment_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $pdo->prepare($sql);

            // After successful registration
         
                    if ($stmt->execute([$username, $email, $hashed_password, $user_type, $first_name, $last_name, $phone, $company_name, $company_size, $status, $payment_plan, $subscription_expiry])) {
                        $client_id = $pdo->lastInsertId();
                    
                        // Check if payments table exists before trying to insert
                        try {
                            $reference = 'ADMIN_' . uniqid();
                            $amount = getPlanAmount($payment_plan);
                            
                            // Check if payments table exists
                            $table_exists = $pdo->query("SHOW TABLES LIKE 'payments'")->rowCount() > 0;
                            
                            if ($table_exists) {
                               $stmt = $pdo->prepare("INSERT INTO payments (client_id, reference, plan_type, amount, status, payment_date) 
                                VALUES (?, ?, ?, ?, 'completed', NOW()) ");
                                $stmt->execute([$client_id, $reference, $payment_plan, $amount]);
                            }
                        } catch (Exception $e) {
                            // Log error but don't stop registration
                            error_log("Payment recording failed: " . $e->getMessage());
                        }
                    
                        $success = 'Client registered successfully with ' . $payment_plans[$payment_plan]['name'] . '!';

                // Send welcome email if account is approved
                if ($status == 'approved') {
                    if (sendWelcomeEmail($email, $username, $password, $first_name . ' ' . $last_name, $payment_plan, $subscription_expiry)) {
                        $success .= ' Welcome email sent.';
                    } else {
                        $success .= ' Could not send welcome email.';
                    }
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

/**
 * Calculate subscription expiry date based on plan
 */
function calculateExpiryDate($plan) {
    $today = new DateTime();

    switch ($plan) {
        case 'monthly':
            $today->modify('+1 month');
            break;
        case '3months':
            $today->modify('+3 months');
            break;
        case '6months':
            $today->modify('+6 months');
            break;
        case '12months':
            $today->modify('+12 months');
            break;
        default:
            $today->modify('+1 month');
    }

    return $today->format('Y-m-d');
}

/**
 * Get plan amount in kobo
 */
function getPlanAmount($plan) {
    $amounts = [
        'monthly' => 5000000,    // 50,000 Naira in kobo
        '3months' => 14000000,   // 140,000 Naira in kobo
        '6months' => 25000000,   // 250,000 Naira in kobo
        '12months' => 40000000   // 400,000 Naira in kobo
    ];

    return $amounts[$plan] ?? 5000000;
}

/**
 * Send welcome email using PHPMailer with subscription details
 */
function sendWelcomeEmail($email, $username, $password, $name, $plan, $expiry_date) {
    global $payment_plans;

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'mail.tunjitechconsulting.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'Info@tunjitechconsulting.com';
        $mail->Password   = 'Tunjitech2024@';

        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 587;
        }
        // Recipients
        $mail->setFrom('sias@tunjitechconsulting.com', 'Security Alert - TunjiTech');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Tunjitech Security Alert System - Subscription Activated';

        $plan_name = $payment_plans[$plan]['name'] ?? 'Security Alert Plan';
        $plan_price = $payment_plans[$plan]['price'] ?? '₦50,000';

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
                    .subscription { background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Security Alert System</h1>
                    </div>
                    <div class='content'>
                        <h2>Welcome, $name!</h2>
                        <p>Your account has been successfully created by the administrator.</p>
                        
                        <div class='credentials'>
                            <h3>Your Login Credentials:</h3>
                            <p><strong>Username:</strong> $username</p>
                            <p><strong>Password:</strong> $password</p>
                        </div>
                        
                        <div class='subscription'>
                            <h3>Subscription Details:</h3>
                            <p><strong>Plan:</strong> $plan_name</p>
                            <p><strong>Price:</strong> $plan_price</p>
                            <p><strong>Expiry Date:</strong> " . date('F j, Y', strtotime($expiry_date)) . "</p>
                            <p><strong>Status:</strong> <span style='color: green;'>Active</span></p>
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

        // Plain text version
        $mail->AltBody = "Welcome to Security Alert System\n\n" .
            "Dear $name,\n\n" .
            "Your account has been successfully created by the administrator.\n\n" .
            "Your Login Credentials:\n" .
            "Username: $username\n" .
            "Password: $password\n\n" .
            "Subscription Details:\n" .
            "Plan: $plan_name\n" .
            "Price: $plan_price\n" .
            "Expiry Date: " . date('F j, Y', strtotime($expiry_date)) . "\n" .
            "Status: Active\n\n" .
            "For security reasons, we recommend that you change your password after your first login.\n\n" .
            "Login URL: " . BASE_URL . "/login.php\n\n" .
            "If you have any questions or need assistance, please contact our support team.\n\n" .
            "© " . date('Y') . " Security Alert System. All rights reserved.";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!-- Navigation -->
<?php include  'include/header.php'; ?>

<div id="layoutSidenav">
    <div id="layoutSidenav_content">
        <main>
            <div class="container-fluid px-4">
                <h1 class="mt-4">Register New Client</h1>
                <ol class="breadcrumb mb-4">
                    <li class="btn btn-lilac-600 radius-8 px-20 py-11"><a href="clients.php"> View Clients</a></li>
                </ol>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-plus me-1"></i>
                                Client Registration Form
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
                                                <label for="user_type" class="form-label">Client Type <span class="text-danger">*</span></label>
                                                <select class="form-select" id="user_type" name="user_type" required>
                                                    <option value="client_individual" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'client_individual') ? 'selected' : ''; ?>>Individual</option>
                                                    <option value="client_company" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'client_company') ? 'selected' : ''; ?>>Company/Organization</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending Approval</option>
                                                    <option value="approved" <?php echo (isset($_POST['status']) && $_POST['status'] == 'approved') ? 'selected' : 'selected'; ?>>Approved</option>
                                                    <option value="rejected" <?php echo (isset($_POST['status']) && $_POST['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

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

                                    <div id="company-fields" style="display: none;">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo isset($_POST['company_name']) ? $_POST['company_name'] : ''; ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label for="company_size" class="form-label">Company Size</label>
                                            <select class="form-select" id="company_size" name="company_size">
                                                <option value="">Select Size</option>
                                                <option value="1-10" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                                <option value="11-50" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                                <option value="51-200" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                                <option value="201-500" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                                <option value="501+" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '501+') ? 'selected' : ''; ?>>501+ employees</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Subscription Plan Selection -->
                                    <div class="mb-3">
                                        <label for="payment_plan" class="form-label">Subscription Plan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="payment_plan" name="payment_plan" required>
                                            <option value="">Select Subscription Plan</option>
                                            <?php foreach ($payment_plans as $key => $plan): ?>
                                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['payment_plan']) && $_POST['payment_plan'] == $key) ? 'selected' : ''; ?>>
                                                    <?php echo $plan['name']; ?> - <?php echo $plan['price']; ?> (<?php echo $plan['duration']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">The subscription will be automatically activated upon registration.</div>
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
                                        <button type="submit" name="register_client" class="btn btn-primary me-md-2">Register Client</button>
                                        <button type="submit" name="add_another" class="btn btn-outline-primary">Register & Add Another</button>
                                        <a href="clients.php" class="btn btn-secondary ms-md-2">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-1"></i>
                                Registration Guidelines
                            </div>
                            <div class="card-body">
                                <h6>Client Types:</h6>
                                <ul class="small">
                                    <li><strong>Individual:</strong> For single users who want to receive security alerts</li>
                                    <li><strong>Company/Organization:</strong> For businesses or organizations that need multiple user accounts</li>
                                </ul>

                                <h6>Subscription Plans:</h6>
                                <ul class="small">
                                    <?php foreach ($payment_plans as $key => $plan): ?>
                                        <li><strong><?php echo $plan['name']; ?>:</strong> <?php echo $plan['price']; ?> (<?php echo $plan['duration']; ?>)</li>
                                    <?php endforeach; ?>
                                </ul>

                                <h6>Status Options:</h6>
                                <ul class="small">
                                    <li><strong>Pending Approval:</strong> Client cannot login until approved</li>
                                    <li><strong>Approved:</strong> Client can immediately access the system</li>
                                    <li><strong>Rejected:</strong> Client account is blocked from accessing the system</li>
                                </ul>

                                <h6>Password Requirements:</h6>
                                <ul class="small">
                                    <li>Minimum 6 characters</li>
                                    <li>Should be unique and secure</li>
                                </ul>

                                <div class="alert alert-info small">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <strong>Note:</strong> The subscription will be automatically activated and the client will receive a welcome email with their login credentials and subscription details.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <?php include 'include/footer.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Show/hide company fields based on user type
    document.getElementById('user_type').addEventListener('change', function() {
        const companyFields = document.getElementById('company-fields');
        const companyName = document.getElementById('company_name');

        if (this.value === 'client_company') {
            companyFields.style.display = 'block';
            companyName.setAttribute('required', 'required');
        } else {
            companyFields.style.display = 'none';
            companyName.removeAttribute('required');
        }
    });

    // Trigger change event on page load
    document.getElementById('user_type').dispatchEvent(new Event('change'));

    // Password confirmation validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const paymentPlan = document.getElementById('payment_plan');

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

        if (!paymentPlan.value) {
            e.preventDefault();
            alert('Please select a subscription plan.');
            paymentPlan.focus();
        }
    });
</script>
</body>
</html>