<?php
// Start session if not already started
//if (session_status() == PHP_SESSION_NONE) {
//    session_start();
//}

// Define root path and include config
define('ROOT_PATH', dirname(__FILE__));
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';

$error = '';
$success = '';
$valid_token = false;
$email = '';

// Check if token and email are provided
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = sanitizeInput($_GET['token']);
    $email = sanitizeInput($_GET['email']);

    // Validate token
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$email, $token]);
    $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset_request) {
        $valid_token = true;
    } else {
        $error = 'Invalid or expired password reset link. Please request a new password reset.';
    }
} else {
    $error = 'Invalid password reset link.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email exists in users table or client_users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'approved'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $isClientUser = false;
        if (!$user) {
            // Check if it's a client user
            $stmt = $pdo->prepare("SELECT id FROM client_users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $isClientUser = true;
        }

        if ($user) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password
            if ($isClientUser) {
                $stmt = $pdo->prepare("UPDATE client_users SET password = ? WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            }

            if ($stmt->execute([$hashed_password, $email])) {
                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND token = ?");
                $stmt->execute([$email, $token]);

                $success = 'Password has been reset successfully. You can now <a href="login.php">login</a> with your new password.';
                $valid_token = false; // Prevent further use
            } else {
                $error = 'Error resetting password. Please try again.';
            }
        } else {
            $error = 'User account not found.';
        }
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
                        <h5 class="card-title mb-0">
                            <i class="fas fa-key me-2"></i>Reset Password
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

                        <?php if ($valid_token): ?>
                            <p class="text-muted">Please enter your new password below.</p>

                            <form method="POST" action="">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Reset Password</button>
                                    <a href="login.php" class="btn btn-secondary">Back to Login</a>
                                </div>
                            </form>
                        <?php elseif (empty($success)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error ?: 'Invalid password reset request.'; ?>
                            </div>
                            <div class="d-grid">
                                <a href="forgot_password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Minimum 6 characters</li>
                                <li>Use a combination of letters, numbers, and symbols</li>
                                <li>Avoid using easily guessable information</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                document.getElementById('confirm_password').focus();
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                document.getElementById('new_password').focus();
            }
        });
    </script>


<footer class="d-footer">
    <div class="row align-items-center justify-content-between">
        <div class="col-auto">
            <p class="mb-0">© 2024 WowDash. All Rights Reserved.</p>
        </div>
        <div class="col-auto">
            <p class="mb-0">Made by <span class="text-primary-600">wowtheme7</span></p>
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
