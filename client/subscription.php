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

$client_id = $_SESSION['user_id'];

// Get client subscription details
$client = getUserById($client_id);
$subscription_details = getSubscriptionDetails($client_id);

// Handle receipt download
if (isset($_GET['download_receipt']) && !empty($_GET['reference'])) {
    $reference = sanitizeInput($_GET['reference']);
    downloadReceipt($reference, $client);
    exit();
}

// Handle invoice request
if (isset($_POST['send_invoice'])) {
    $reference = sanitizeInput($_POST['reference']);
    $result = sendInvoiceEmail($reference, $client);

    if ($result) {
        $_SESSION['success_message'] = "Invoice sent to your email successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to send invoice. Please try again.";
    }
    header("Location: subscription.php");
    exit();
}

// Handle subscription renewal
if (isset($_POST['renew_subscription'])) {
    $plan = sanitizeInput($_POST['plan']);
    $result = initializeSubscriptionRenewal($client, $plan);

    if ($result['status']) {
        header('Location: ' . $result['data']['authorization_url']);
        exit();
    } else {
        $_SESSION['error_message'] = 'Failed to initialize renewal: ' . $result['message'];
        header("Location: subscription.php");
        exit();
    }
}

/**
 * Get subscription details
 */
function getSubscriptionDetails($client_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            DATEDIFF(u.subscription_expiry, CURDATE()) as days_remaining,
            CASE 
                WHEN u.subscription_expiry IS NULL THEN 'inactive'
                WHEN u.subscription_expiry < CURDATE() THEN 'expired'
                WHEN DATEDIFF(u.subscription_expiry, CURDATE()) <= 7 THEN 'expiring_soon'
                ELSE 'active'
            END as status,
            (SELECT COUNT(*) FROM payments WHERE user_id = u.id) as total_payments,
            (SELECT MAX(paid_at) FROM payments WHERE user_id = u.id) as last_payment_date
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$client_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get payment history
    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? 
        ORDER BY paid_at DESC
    ");
    $stmt->execute([$client_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'info' => $subscription,
        'payments' => $payments
    ];
}

/**
 * Download receipt
 */
function downloadReceipt($reference, $client) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE reference = ? AND user_id = ?
    ");
    $stmt->execute([$reference, $client['id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        $_SESSION['error_message'] = "Receipt not found.";
        header("Location: subscription.php");
        exit();
    }

    // Generate PDF receipt
    include 'include/tcpdf/tcpdf.php';

    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Security Alert System');
    $pdf->SetAuthor('Tunjitech Consulting Ltd');
    $pdf->SetTitle('Payment Receipt - ' . $reference);
    $pdf->SetSubject('Payment Receipt');

    // Add a page
    $pdf->AddPage();

    // Set content
    $html = '
        <h1 style="text-align:center;color:#007bff;">Payment Receipt</h1>
        <table border="0" cellpadding="5">
            <tr>
                <td width="30%"><strong>Receipt Number:</strong></td>
                <td>' . $reference . '</td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td>' . date('F j, Y', strtotime($payment['paid_at'])) . '</td>
            </tr>
            <tr>
                <td><strong>Customer:</strong></td>
                <td>' . $client['first_name'] . ' ' . $client['last_name'] . '</td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td>' . $client['email'] . '</td>
            </tr>
            <tr>
                <td><strong>Plan:</strong></td>
                <td>' . ucfirst($payment['plan']) . ' Plan</td>
            </tr>
            <tr>
                <td><strong>Amount:</strong></td>
                <td>₦' . number_format($payment['amount'] / 100, 2) . '</td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td style="color:green;">' . ucfirst($payment['status']) . '</td>
            </tr>
        </table>
        <br>
        <p style="font-style:italic;">Thank you for your subscription to our Security Alert System.</p>
        <p style="font-size:small;color:#666;">This is a computer-generated receipt. No signature is required.</p>
    ';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Output PDF
    $pdf->Output('receipt_' . $reference . '.pdf', 'D');
}

/**
 * Send invoice email
 */
function sendInvoiceEmail($reference, $client) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE reference = ? AND user_id = ?
    ");
    $stmt->execute([$reference, $client['id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        return false;
    }

    // Create email content
    $subject = "Invoice for Your Security Alert System Subscription";

    $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8f9fa; padding: 20px; }
                .footer { background-color: #343a40; color: white; padding: 10px; text-align: center; }
                .invoice-details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Security Alert System</h1>
                    <p>Invoice</p>
                </div>
                <div class='content'>
                    <h2>Hello " . $client['first_name'] . ",</h2>
                    <p>Thank you for your subscription to our Security Alert System. Here's your invoice details:</p>
                    
                    <div class='invoice-details'>
                        <h3>Invoice #: " . $reference . "</h3>
                        <p><strong>Date:</strong> " . date('F j, Y', strtotime($payment['paid_at'])) . "</p>
                        <p><strong>Plan:</strong> " . ucfirst($payment['plan']) . " Plan</p>
                        <p><strong>Amount:</strong> ₦" . number_format($payment['amount'] / 100, 2) . "</p>
                        <p><strong>Status:</strong> <span style='color:green;'>Paid</span></p>
                    </div>
                    
                    <p>You can download a receipt from your subscription page anytime.</p>
                    <p>If you have any questions about this invoice, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Tunjitech Consulting Ltd. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
    ";

    // Send email using PHPMailer
    return sendEmailPHPMailer($client['email'], $subject, $message, $client['first_name'] . ' ' . $client['last_name']);
}

/**
 * Initialize subscription renewal
 */
function initializeSubscriptionRenewal($client, $plan) {
    global $payment_plans;

    if (!isset($payment_plans[$plan])) {
        return ['status' => false, 'message' => 'Invalid plan selected'];
    }

    $selected_plan = $payment_plans[$plan];

    $url = PAYSTACK_BASE_URL . '/transaction/initialize';

    $fields = [
        'email' => $client['email'],
        'amount' => $selected_plan['amount'],
        'reference' => 'RENEW_' . uniqid(),
        'callback_url' => BASE_URL . '/subscription.php',
        'metadata' => json_encode([
            'custom_fields' => [
                [
                    'display_name' => 'Full Name',
                    'variable_name' => 'full_name',
                    'value' => $client['first_name'] . ' ' . $client['last_name']
                ],
                [
                    'display_name' => 'Plan',
                    'variable_name' => 'plan',
                    'value' => $selected_plan['name']
                ],
                [
                    'display_name' => 'Type',
                    'variable_name' => 'type',
                    'value' => 'renewal'
                ]
            ]
        ])
    ];

    $fields_string = http_build_query($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

// Payment plans (same as in register.php)
$payment_plans = [
    'monthly' => [
        'name' => 'Monthly Security Alert Plan',
        'amount' => 5000000,
        'duration' => '1 month'
    ],
    '3months' => [
        'name' => '3 Months Security Alert Plan',
        'amount' => 14000000,
        'duration' => '3 months'
    ],
    '6months' => [
        'name' => '6 Months Security Alert Plan',
        'amount' => 25000000,
        'duration' => '6 months'
    ],
    '12months' => [
        'name' => '12 Months Security Alert Plan',
        'amount' => 40000000,
        'duration' => '12 months'
    ]
];
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h4 class="mt-4">Subscription Management</h4>


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

        <div class="row">
            <!-- Subscription Overview -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0 text-white" ><i class="fas fa-credit-card me-2"></i>Subscription Overview</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($subscription_details['info']['subscription_expiry']): ?>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Current Plan:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo ucfirst($subscription_details['info']['payment_plan'] ?? 'No active plan'); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-6">
                                    <?php
                                    $status = $subscription_details['info']['status'];
                                    $badge_class = [
                                            'active' => 'success',
                                            'expiring_soon' => 'warning',
                                            'expired' => 'danger',
                                            'inactive' => 'secondary'
                                        ][$status] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Expiry Date:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo date('F j, Y', strtotime($subscription_details['info']['subscription_expiry'])); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <strong>Days Remaining:</strong>
                                </div>
                                <div class="col-6">
                                    <?php
                                    $days_remaining = $subscription_details['info']['days_remaining'];
                                    if ($days_remaining > 0) {
                                        echo $days_remaining . ' days';
                                    } elseif ($days_remaining == 0) {
                                        echo 'Expires today';
                                    } else {
                                        echo 'Expired ' . abs($days_remaining) . ' days ago';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Total Payments:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo $subscription_details['info']['total_payments']; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No active subscription found. Please subscribe to continue using our services.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Renew Subscription -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0 text-white" ><i class="fas fa-sync-alt me-2"></i>Renew Subscription</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Select Plan:</label>
                                <select class="form-select" name="plan" required>
                                    <option value="">Choose a plan...</option>
                                    <?php foreach ($payment_plans as $key => $plan): ?>
                                        <option value="<?php echo $key; ?>">
                                            <?php echo $plan['name']; ?> - ₦<?php echo number_format($plan['amount'] / 100, 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="renew_subscription" class="btn btn-primary w-100">
                                <i class="fas fa-credit-card me-2"></i>Renew Now
                            </button>
                        </form>

                        <?php if ($subscription_details['info']['status'] == 'expiring_soon'): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-clock me-2"></i>
                                Your subscription will expire in <?php echo $subscription_details['info']['days_remaining']; ?> days.
                            </div>
                        <?php elseif ($subscription_details['info']['status'] == 'expired'): ?>
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Your subscription has expired. Please renew to continue receiving alerts.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Payment History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($subscription_details['payments'])): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No payment history found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Date</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($subscription_details['payments'] as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['reference']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['paid_at'])); ?></td>
                                    <td><?php echo ucfirst($payment['plan']); ?> Plan</td>
                                    <td>₦<?php echo number_format($payment['amount'] / 100, 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $payment['status'] == 'success' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="subscription.php?download_receipt=<?php echo $payment['reference']; ?>"
                                               class="btn btn-sm btn-outline-primary" title="Download Receipt">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="reference" value="<?php echo $payment['reference']; ?>">
                                                <button type="submit" name="send_invoice" class="btn btn-sm btn-outline-info" title="Send Invoice">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription Plans -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-crown me-2"></i>Available Plans</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($payment_plans as $key => $plan): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-header text-center">
                                    <h6><?php echo $plan['name']; ?></h6>
                                </div>
                                <div class="card-body text-center">
                                    <h5 class="text-primary">₦<?php echo number_format($plan['amount'] / 100, 2); ?></h5>
                                    <p class="text-muted"><?php echo $plan['duration']; ?></p>
                                    <ul class="list-unstyled">
                                        <li>✓ 24/7 Monitoring</li>
                                        <li>✓ Email & SMS Alerts</li>
                                        <li>✓ Dashboard Access</li>
                                        <li>✓ Priority Support</li>
                                    </ul>
                                </div>
                                <div class="card-footer text-center">
                                    <form method="POST" action="">
                                        <input type="hidden" name="plan" value="<?php echo $key; ?>">
                                        <button type="submit" name="renew_subscription" class="btn btn-primary btn-sm">
                                            Select Plan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<?php include 'include/footer.php'; ?>