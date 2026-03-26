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

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

// Handle test SMS submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_test_sms'])) {
    $phone_number = sanitizeInput($_POST['phone_number']);
    $message = sanitizeInput($_POST['message']);

    if (empty($phone_number) || empty($message)) {
        $error = 'Please provide both phone number and message.';
    } else {
        // Test SMS sending
        $result = sendTestSMS($phone_number, $message);

        if ($result['success']) {
            $success = 'Test SMS sent successfully!';
        } else {
            $error = 'Error sending test SMS: ' . $result['error'];
        }
    }
}
?>

<?php include 'include/header.php'; ?>

    <div class="container-fluid">
        <h1 class="mt-4">Test SMS Functionality</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sms me-1"></i>
                        Send Test SMS
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number"
                                       value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                                       placeholder="e.g., +1234567890" required>
                                <div class="form-text">Include country code (e.g., +1 for US/Canada)</div>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="4"
                                          placeholder="Enter your test message here..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                <div class="form-text"><span id="charCount">0</span>/160 characters</div>
                            </div>

                            <button type="submit" name="send_test_sms" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Send Test SMS
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        SMS Configuration Guide
                    </div>
                    <div class="card-body">
                        <h6>Supported SMS Gateways:</h6>
                        <ul class="small">
                            <li><strong>Twilio</strong> (Recommended)</li>
                            <li><strong>Nexmo/Vonage</strong></li>
                            <li><strong>Plivo</strong></li>
                            <li><strong>Custom API</strong></li>
                        </ul>

                        <h6>Configuration Steps:</h6>
                        <ol class="small">
                            <li>Sign up for an SMS gateway service</li>
                            <li>Add your credentials to <code>includes/config.php</code></li>
                            <li>Verify your phone number (for testing)</li>
                            <li>Test the configuration using this page</li>
                        </ol>

                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Important:</strong> SMS functionality requires proper API credentials and may incur costs.
                        </div>

                        <h6>Current Configuration:</h6>
                        <div class="bg-light p-3 rounded small">
                            <?php
                            $sms_configured = false;
                            if (defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID &&
                                defined('TWILIO_AUTH_TOKEN') && TWILIO_AUTH_TOKEN) {
                                echo '<span class="badge bg-success">Twilio Configured</span>';
                                $sms_configured = true;
                            } else {
                                echo '<span class="badge bg-danger">Twilio Not Configured</span>';
                            }
                            echo '<br>';

                            if (defined('SMS_API_KEY') && SMS_API_KEY) {
                                echo '<span class="badge bg-success">Custom SMS API Configured</span>';
                                $sms_configured = true;
                            } else {
                                echo '<span class="badge bg-secondary">Custom SMS API Not Configured</span>';
                            }
                            ?>
                        </div>

                        <?php if (!$sms_configured): ?>
                            <div class="alert alert-info mt-3 small">
                                <i class="fas fa-info-circle me-1"></i>
                                No SMS gateway configured. Please add your credentials to <code>includes/config.php</code>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Character count for SMS message
        document.getElementById('message').addEventListener('input', function() {
            const charCount = this.value.length;
            document.getElementById('charCount').textContent = charCount;

            // Warn when approaching SMS limit
            const counter = document.getElementById('charCount');
            if (charCount > 150) {
                counter.className = 'text-warning';
            } else {
                counter.className = '';
            }
        });
    </script>

<?php include 'include/footer.php'; ?>