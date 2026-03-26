<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Define Paystack configuration
define('PAYSTACK_PUBLIC_KEY', 'pk_live_1b95cbc488beb68436880e62b90676b5a4f01414');
define('PAYSTACK_SECRET_KEY', 'sk_live_135613bd451bf66c77793bba195003d31ce1b8be');
define('PAYSTACK_BASE_URL', 'https://api.paystack.co');

// Payment plans
$payment_plans = [
    'monthly' => [
        'name' => 'Monthly Security Alert Plan',
        'amount' => 5000000, // 50,000 Naira in kobo
        'url' => 'https://paystack.shop/pay/pua5gk6aq6',
        'duration' => '1 month'
    ],
    '3months' => [
        'name' => '3 Months Security Alert Plan',
        'amount' => 14000000, // 140,000 Naira in kobo
        'url' => 'https://paystack.shop/pay/ydft5p2jl6',
        'duration' => '3 months'
    ],
    '6months' => [
        'name' => '6 Months Security Alert Plan',
        'amount' => 25000000, // 250,000 Naira in kobo
        'url' => 'https://paystack.shop/pay/18afrj7he5',
        'duration' => '6 months'
    ],
    '12months' => [
        'name' => '12 Months Security Alert Plan',
        'amount' => 40000000, // 400,000 Naira in kobo
        'url' => 'https://paystack.shop/pay/8g5i0yy-a7',
        'duration' => '12 months'
    ]
];

$error = '';
$success = '';
$show_payment_options = false;
$user_data = [];

// Handle initial registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['payment_plan'])) {
    $user_type = sanitizeInput($_POST['user_type']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);

    $company_name = '';
    $company_size = '';

    if ($user_type == 'client_company') {
        $company_name = sanitizeInput($_POST['company_name']);
        $company_size = sanitizeInput($_POST['company_size']);
    }

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Store user data in session for payment processing
            $_SESSION['registration_data'] = [
                'user_type' => $user_type,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'company_name' => $company_name,
                'company_size' => $company_size
            ];

            $show_payment_options = true;
            $user_data = $_SESSION['registration_data'];
        }
    }
}

// Handle payment plan selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_plan'])) {
    $payment_plan = sanitizeInput($_POST['payment_plan']);

    if (!isset($_SESSION['registration_data'])) {
        $error = 'Registration data not found. Please start over.';
    } elseif (!isset($payment_plans[$payment_plan])) {
        $error = 'Invalid payment plan selected.';
    } else {
        // Initialize Paystack payment
        $user_data = $_SESSION['registration_data'];
        $plan = $payment_plans[$payment_plan];

        // Create Paystack transaction
        $result = initializePaystackPayment($user_data, $plan);

        if ($result['status']) {
            // Redirect to Paystack payment page
            header('Location: ' . $result['data']['authorization_url']);
            exit();
        } else {
            $error = 'Failed to initialize payment: ' . $result['message'];
        }
    }
}

// Handle Paystack callback
if (isset($_GET['reference'])) {
    $transaction_reference = sanitizeInput($_GET['reference']);
    $result = verifyPaystackPayment($transaction_reference);

    if ($result['status']) {
        // Payment successful - complete registration
        if (isset($_SESSION['registration_data'])) {
            $user_data = $_SESSION['registration_data'];

            // Hash password
            $hashed_password = password_hash($user_data['password'], PASSWORD_DEFAULT);

            // Insert user into database with approved status
            $sql = "INSERT INTO users (username, email, password, user_type, first_name, last_name, phone, company_name, company_size, status, payment_plan, payment_reference) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?)";

            $stmt = $pdo->prepare($sql);

            // Determine payment plan from amount
            $amount = $result['data']['amount'];
            $payment_plan = getPlanFromAmount($amount);

            if ($stmt->execute([
                $user_data['username'],
                $user_data['email'],
                $hashed_password,
                $user_data['user_type'],
                $user_data['first_name'],
                $user_data['last_name'],
                $user_data['phone'],
                $user_data['company_name'],
                $user_data['company_size'],
                $payment_plan,
                $transaction_reference
            ])) {
                $user_id = $pdo->lastInsertId();

                // Set session variables and redirect to dashboard
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user_data['username'];
                $_SESSION['user_type'] = $user_data['user_type'];
                $_SESSION['email'] = $user_data['email'];

                // Clear registration data
                unset($_SESSION['registration_data']);

                // Redirect to dashboard
                header("Location: client/dashboard.php");
                exit();
            } else {
                $error = 'Registration failed after payment. Please contact support.';
            }
        } else {
            $error = 'Registration data not found. Please contact support.';
        }
    } else {
        $error = 'Payment verification failed: ' . $result['message'];
    }
}

/**
 * Initialize Paystack payment
 */
function initializePaystackPayment($user_data, $plan) {
    $url = PAYSTACK_BASE_URL . '/transaction/initialize';

    $fields = [
        'email' => $user_data['email'],
        'amount' => $plan['amount'],
        'reference' => 'TUNJI_' . uniqid(),
        'callback_url' => BASE_URL . '/register.php',
        'metadata' => json_encode([
            'custom_fields' => [
                [
                    'display_name' => 'Full Name',
                    'variable_name' => 'full_name',
                    'value' => $user_data['first_name'] . ' ' . $user_data['last_name']
                ],
                [
                    'display_name' => 'Username',
                    'variable_name' => 'username',
                    'value' => $user_data['username']
                ],
                [
                    'display_name' => 'Plan',
                    'variable_name' => 'plan',
                    'value' => $plan['name']
                ]
            ]
        ])
    ];

    $fields_string = http_build_query($fields);

    // Open connection
    $ch = curl_init();

    // Set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ));

    // So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute post
    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Verify Paystack payment
 */
function verifyPaystackPayment($reference) {
    $url = PAYSTACK_BASE_URL . '/transaction/verify/' . $reference;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Get plan name from amount
 */
function getPlanFromAmount($amount) {
    $plans = [
        5000000 => 'monthly',
        14000000 => '3months',
        25000000 => '6months',
        40000000 => '12months'
    ];

    return $plans[$amount] ?? 'unknown';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Security Alert System</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png" sizes="16x16">
    <link rel="stylesheet" href="assets/css/remixicon.css">
    <link rel="stylesheet" href="assets/css/lib/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .payment-plan {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .payment-plan:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,123,255,0.1);
        }
        .payment-plan.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        .plan-price {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .plan-duration {
            color: #6c757d;
            font-size: 14px;
        }
        .plan-features {
            margin-top: 15px;
        }
        .plan-features li {
            margin-bottom: 8px;
            color: #495057;
        }
    </style>
    <!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1493058521734218');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1493058521734218&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
</head>
<body>

<section class="auth bg-base d-flex flex-wrap">
    <div class="auth-left d-lg-block d-none">
        <div class="d-flex align-items-center flex-column h-100 justify-content-center">
            <img src="assets/images/auth/auth-img.png" alt="">
        </div>
    </div>
    <div class="auth-right py-32 px-24 d-flex flex-column justify-content-center">
        <div class="max-w-464-px mx-auto w-100">
            <div style="margin-left:50px;">
                <a href="index.php" class="mb-40 max-w-290-px">
                    <img src="assets/images/tunjitech-logo.png" alt="Tunjitech Consulting Logo" width="200 x 200">
                </a>

                <?php if (!$show_payment_options): ?>
                    <h4 class="mb-12">Create Your Account</h4>
                    <p class="mb-32 text-secondary-light text-lg">Fill in your details to sign up</p>
                <?php else: ?>
                    <h4 class="mb-12">Choose Your Plan</h4>
                    <p class="mb-32 text-secondary-light text-lg">Select a subscription plan to continue</p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!$show_payment_options): ?>
                <!-- Registration Form -->
                <form method="POST" action="">
                    <div class="mb-16">
                        <select class="form-select h-56-px bg-neutral-50 radius-12" name="user_type" id="user_type" required>
                            <option value="client_individual" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'client_individual') ? 'selected' : ''; ?>>Individual</option>
                            <option value="client_company" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'client_company') ? 'selected' : ''; ?>>Company/Organization</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-16">
                            <input type="text" class="form-control h-56-px bg-neutral-50 radius-12" name="first_name" placeholder="First Name" value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-16">
                            <input type="text" class="form-control h-56-px bg-neutral-50 radius-12" name="last_name" placeholder="Last Name" value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <div class="mb-16">
                        <input type="text" class="form-control h-56-px bg-neutral-50 radius-12" name="username" placeholder="Username" value="<?php echo $_POST['username'] ?? ''; ?>" required>
                    </div>
                    <div class="mb-16">
                        <input type="email" class="form-control h-56-px bg-neutral-50 radius-12" name="email" placeholder="Email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    <div class="mb-16">
                        <input type="tel" class="form-control h-56-px bg-neutral-50 radius-12" name="phone" placeholder="Phone Number" value="<?php echo $_POST['phone'] ?? ''; ?>">
                    </div>

                    <div id="company-fields" style="display: none;">
                        <div class="mb-16">
                            <input type="text" class="form-control h-56-px bg-neutral-50 radius-12" name="company_name" id="company_name" placeholder="Company Name" value="<?php echo $_POST['company_name'] ?? ''; ?>">
                        </div>
                        <div class="mb-16">
                            <select class="form-select h-56-px bg-neutral-50 radius-12" name="company_size" id="company_size">
                                <option value="">Select Company Size</option>
                                <option value="1-10" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '1-10') ? 'selected' : ''; ?>>1-10 employees</option>
                                <option value="11-50" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '11-50') ? 'selected' : ''; ?>>11-50 employees</option>
                                <option value="51-200" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '51-200') ? 'selected' : ''; ?>>51-200 employees</option>
                                <option value="201-500" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '201-500') ? 'selected' : ''; ?>>201-500 employees</option>
                                <option value="501+" <?php echo (isset($_POST['company_size']) && $_POST['company_size'] == '501+') ? 'selected' : ''; ?>>501+ employees</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-16">
                        <input type="password" class="form-control h-56-px bg-neutral-50 radius-12" name="password" id="password" placeholder="Password" required>
                    </div>
                    <div class="mb-16">
                        <input type="password" class="form-control h-56-px bg-neutral-50 radius-12" name="confirm_password" placeholder="Confirm Password" required>
                    </div>

                    <div class="form-check style-check mb-16">
                        <input class="form-check-input border border-neutral-300" type="checkbox" id="terms" required>
                        <label class="form-check-label text-sm" for="terms">
                            By creating an account you agree to our
                            <a href="#" class="text-primary-600 fw-semibold">Terms & Conditions</a> and
                            <a href="#" class="text-primary-600 fw-semibold">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 radius-12 py-16">Continue to Payment</button>

                    <div class="mt-32 text-center text-sm">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary-600 fw-semibold">Sign In</a></p>
                    </div>
                </form>
            <?php else: ?>
                <!-- Payment Plan Selection -->
                <form method="POST" action="">
                    <input type="hidden" name="payment_plan" id="selected_plan" value="" required>

                    <div class="payment-plans">
                        <?php foreach ($payment_plans as $key => $plan): ?>
                            <div class="payment-plan" onclick="selectPlan('<?php echo $key; ?>')" id="plan-<?php echo $key; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5><?php echo $plan['name']; ?></h5>
                                    <div class="text-end">
                                        <div class="plan-price">₦<?php echo number_format($plan['amount'] / 100, 2); ?></div>
                                        <div class="plan-duration"><?php echo $plan['duration']; ?></div>
                                    </div>
                                </div>
                                <ul class="plan-features">
                                    <li>24/7 Security Monitoring</li>
                                    <li>Real-time Alert Notifications</li>
                                    <li>Email & SMS Alerts</li>
                                    <li>Dashboard Access</li>
                                    <li>Priority Support</li>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-24">
                        <button type="submit" class="btn btn-primary w-100 radius-12 py-16" id="pay-button" disabled>
                            Pay Now with Paystack
                        </button>
                    </div>

                    <div class="mt-16 text-center">
                        <p class="text-muted small">Secure payment processed by Paystack</p>
                    </div>

                    <div class="mt-32 text-center text-sm">
                        <p class="mb-0">Need help? <a href="contact.php" class="text-primary-600 fw-semibold">Contact Support</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="assets/js/lib/jquery-3.7.1.min.js"></script>
<script src="assets/js/lib/bootstrap.bundle.min.js"></script>
<script src="assets/js/lib/iconify-icon.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
    // Show company fields when user selects "Company"
    $('#user_type').on('change', function() {
        if ($(this).val() === 'client_company') {
            $('#company-fields').slideDown();
            $('#company_name').attr('required', true);
        } else {
            $('#company-fields').slideUp();
            $('#company_name').removeAttr('required');
        }
    }).trigger('change');

    // Payment plan selection
    function selectPlan(planId) {
        // Remove selected class from all plans
        $('.payment-plan').removeClass('selected');

        // Add selected class to clicked plan
        $('#plan-' + planId).addClass('selected');

        // Set the selected plan value
        $('#selected_plan').val(planId);

        // Enable pay button
        $('#pay-button').prop('disabled', false);
    }

    // Handle Paystack callback if reference exists
    <?php if (isset($_GET['reference'])): ?>
    // Show loading state
    $(document).ready(function() {
        $('body').append(`
                <div class="modal fade" id="paymentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body text-center py-4">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <h5>Verifying Payment...</h5>
                                <p class="text-muted">Please wait while we verify your payment.</p>
                            </div>
                        </div>
                    </div>
                </div>
            `);

        $('#paymentModal').modal('show');
    });
    <?php endif; ?>
</script>
</body>
</html>