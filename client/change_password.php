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

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        // Get user information
        $user_id = $_SESSION['user_id'];

        // Check if it's a client user or main user
        if (isClientUser() && isset($_SESSION['client_user_id'])) {
            // Client user password change
            $stmt = $pdo->prepare("SELECT password FROM client_users WHERE id = ?");
            $stmt->execute([$_SESSION['client_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE client_users SET password = ? WHERE id = ?");

                if ($stmt->execute([$hashed_password, $_SESSION['client_user_id']])) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Error changing password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        } else {
            // Main user (admin or client) password change
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Error changing password. Please try again.';
                }
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}

// Determine redirect URL based on user type
$redirect_url = 'dashboard.php';
if (isAdmin()) {
    $redirect_url = 'admin/dashboard.php';
} elseif (isClient() || isClientUser()) {
    $redirect_url = 'client/dashboard.php';
}
?>

<?php include  'include/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0 text-white">
                            <i class="fas fa-lock fa-sm"></i> Change Password
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

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

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
                                <div class="progress" style="height: 5px;">
                                    <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <small id="password-strength-text" class="text-muted">Password strength</small>
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
                                <button type="submit" class="btn btn-primary">Change Password</button>
                                <a href="<?php echo $redirect_url; ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
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
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match. Please check and try again.');
                document.getElementById('confirm_password').focus();
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                document.getElementById('new_password').focus();
            }
        });

        // Add this to your change_password.php script section
        function checkPasswordStrength(password) {
            let strength = 0;
            let messages = [];

            // Length check
            if (password.length >= 8) strength++;
            else messages.push('at least 8 characters');

            // uppercase check
            if (/[A-Z]/.test(password)) strength++;
            else messages.push('one uppercase letter');

            // lowercase check
            if (/[a-z]/.test(password)) strength++;
            else messages.push('one lowercase letter');

            // number check
            if (/[0-9]/.test(password)) strength++;
            else messages.push('one number');

            // special character check
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else messages.push('one special character');

            return {
                strength: strength,
                messages: messages,
                score: (strength / 5) * 100
            };
        }

        // Add password strength meter
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');

            if (strengthBar && strengthText) {
                strengthBar.style.width = strength.score + '%';

                if (strength.score < 40) {
                    strengthBar.className = 'progress-bar bg-danger';
                    strengthText.textContent = 'Weak';
                } else if (strength.score < 70) {
                    strengthBar.className = 'progress-bar bg-warning';
                    strengthText.textContent = 'Medium';
                } else if (strength.score < 100) {
                    strengthBar.className = 'progress-bar bg-info';
                    strengthText.textContent = 'Strong';
                } else {
                    strengthBar.className = 'progress-bar bg-success';
                    strengthText.textContent = 'Very Strong';
                }
            }
        });
    </script>

<?php include 'include/footer.php'; ?>