<?php
// Start session if not already started
//if (session_status() == PHP_SESSION_NONE) {
//    session_start();
//}

// Define root path and include config
define('ROOT_PATH', dirname(__FILE__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';

// Load Composer's autoloader (if using Composer)
require_once ROOT_PATH . '/vendor/autoload.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in users table or client_users table
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'approved'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $isClientUser = false;
        if (!$user) {
            // Check if it's a client user
            $stmt = $pdo->prepare("SELECT id, username, client_id FROM client_users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $isClientUser = true;
        }

        if ($user) {
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            // Insert new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");

            if ($stmt->execute([$email, $token, $expires])) {
                // Send password reset email
                if (sendPasswordResetEmail($email, $token, $user['username'], $isClientUser)) {
                    $success = 'Password reset instructions have been sent to your email address.';
                } else {
                    $error = 'Failed to send password reset email. Please try again later.';
                }
            } else {
                $error = 'Error generating reset token. Please try again.';
            }
        } else {
            // For security reasons, don't reveal if email exists or not
            $success = 'If your email address exists in our system, password reset instructions have been sent.';
        }
    }
}

/**
 * Send password reset email using PHPMailer
 */
function sendPasswordResetEmail($email, $token, $username, $isClientUser = false) {
    $mail = new PHPMailer(true);

      try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'mail.tunjitechconsulting.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sias@tunjitechconsulting.com';
        $mail->Password   = 'Tunjitech2024@';

        if (SMTP_SECURE == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 465;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT ? SMTP_PORT : 587;
        }

        // Recipients
        $mail->setFrom('sias@tunjitechconsulting.com', 'TunjiTech - Security Alert');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Security Alert System';

        $resetLink = BASE_URL . "/reset_password.php?token=" . $token . "&email=" . urlencode($email);

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
                    .button { display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Security Alert System</h1>
                    </div>
                    <div class='content'>
                        <h2>Password Reset Request</h2>
                        <p>Hello " . htmlspecialchars($username) . ",</p>
                        <p>We received a request to reset your password for the Security Alert System account.</p>
                        <p>Click the button below to reset your password:</p>
                        <p style='text-align: center;'>
                            <a href='" . $resetLink . "' class='button' style='color: white'>Reset Password</a>
                        </p>
                        <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
                        <p><strong>Note:</strong> This link will expire in 1 hour for security reasons.</p>
                    </div>
                    <div class='footer'>
                        <p> Tunjitech Consulting &copy; " . date('Y') . " Security Alert System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "Password Reset Request\n\n" .
            "Hello " . $username . ",\n\n" .
            "We received a request to reset your password for the Security Alert System account.\n\n" .
            "Reset your password here: " . $resetLink . "\n\n" .
            "If you didn't request a password reset, please ignore this email. Your password will remain unchanged.\n\n" .
            "Note: This link will expire in 1 hour for security reasons.\n\n" .
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

    <!DOCTYPE html>
    <html lang="en" data-theme="light">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tunjitech Consulting - Security Alert!</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png" sizes="16x16">
    <!-- remix icon font css  -->
    <link rel="stylesheet" href="assets/css/remixicon.css">
    <!-- BootStrap css -->
    <link rel="stylesheet" href="assets/css/lib/bootstrap.min.css">
    <!-- Apex Chart css -->
    <link rel="stylesheet" href="assets/css/lib/apexcharts.css">
    <!-- Data Table css -->
    <link rel="stylesheet" href="assets/css/lib/dataTables.min.css">
    <!-- Text Editor css -->
    <link rel="stylesheet" href="assets/css/lib/editor-katex.min.css">
    <link rel="stylesheet" href="assets/css/lib/editor.atom-one-dark.min.css">
    <link rel="stylesheet" href="assets/css/lib/editor.quill.snow.css">
    <!-- Date picker css -->
    <link rel="stylesheet" href="assets/css/lib/flatpickr.min.css">
    <!-- Calendar css -->
    <link rel="stylesheet" href="assets/css/lib/full-calendar.css">
    <!-- Vector Map css -->
    <link rel="stylesheet" href="assets/css/lib/jquery-jvectormap-2.0.5.css">
    <!-- Popup css -->
    <link rel="stylesheet" href="assets/css/lib/magnific-popup.css">
    <!-- Slick Slider css -->
    <link rel="stylesheet" href="assets/css/lib/slick.css">
    <!-- prism css -->
    <link rel="stylesheet" href="assets/css/lib/prism.css">
    <!-- file upload css -->
    <link rel="stylesheet" href="assets/css/lib/file-upload.css">

    <link rel="stylesheet" href="assets/css/lib/audioplayer.css">
    <!-- main css -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!--    fontawesome-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

    <div class="card mt-80">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="fas fa-key fa-xs "></i>  Forgot Password
                        </h5>
                    </div>
                    <div class="card-body">
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

                        <p class="text-muted">Enter your email address and we'll send you instructions to reset your password.</p>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required placeholder="Enter your email address">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Send Reset Instructions</button>
                                <a href="login.php" class="btn btn-secondary">Back to Login</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> For security reasons, password reset links expire after 1 hour.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<footer class="d-footer">
    <div class="row align-items-center justify-content-between">
        <div class="col-auto">
            <p class="mb-0">Tunjitech Consulting &copy; 2025 Security Alert System. All rights reserved.</p>
        </div>
    </div>
</footer></main>


<!-- jQuery library js -->
<script src="assets/js/lib/jquery-3.7.1.min.js"></script>
<!-- Bootstrap js -->
<script src="assets/js/lib/bootstrap.bundle.min.js"></script>
<!-- Apex Chart js -->
<script src="assets/js/lib/apexcharts.min.js"></script>
<!-- Data Table js -->
<script src="assets/js/lib/dataTables.min.js"></script>
<!-- Iconify Font js -->
<script src="assets/js/lib/iconify-icon.min.js"></script>
<!-- jQuery UI js -->
<script src="assets/js/lib/jquery-ui.min.js"></script>
<!-- Vector Map js -->
<script src="assets/js/lib/jquery-jvectormap-2.0.5.min.js"></script>
<script src="assets/js/lib/jquery-jvectormap-world-mill-en.js"></script>
<!-- Popup js -->
<script src="assets/js/lib/magnifc-popup.min.js"></script>
<!-- Slick Slider js -->
<script src="assets/js/lib/slick.min.js"></script>
<!-- prism js -->
<script src="assets/js/lib/prism.js"></script>
<!-- file upload js -->
<script src="assets/js/lib/file-upload.js"></script>
<!-- audioplayer -->
<script src="assets/js/lib/audioplayer.js"></script>

<!-- main js -->
<script src="../assets/js/app.js"></script>
<script src="../assets/js/homeTwoChart.js"></script>
</body>
