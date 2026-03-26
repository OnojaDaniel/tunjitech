<?php
require_once 'includes/config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] == USER_TYPE_ADMIN || $_SESSION['user_type'] == USER_TYPE_SUB_ADMIN) {
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: client/dashboard.php");
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // session check to avoid unnecessary loading
    require_once 'includes/functions.php';
    require_once 'includes/auth.php'; // Include auth.php for loginClientUser function

    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is approved
            if ($user['status'] !== 'approved') {
                $error = 'Your account is pending approval. Please contact administrator.';
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                // Set permissions for sub-admins
                if ($user['user_type'] == USER_TYPE_SUB_ADMIN && !empty($user['permissions'])) {
                    $_SESSION['permissions'] = json_decode($user['permissions'], true);
                }

                // Update last login (with error handling in case column doesn't exist)
                try {
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                } catch (PDOException $e) {
                    // Log the error but don't prevent login
                    error_log("Last login update failed: " . $e->getMessage());
                    // You can optionally create the column here or just continue
                }

                // Redirect based on user type
                if ($_SESSION['user_type'] == USER_TYPE_ADMIN || $_SESSION['user_type'] == USER_TYPE_SUB_ADMIN) {
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    header("Location: client/dashboard.php");
                    exit();
                }
            }
        } else {
            // Check if it's a client user login
            if (loginClientUser($username, $password)) {
                // Check if main client account is approved
                $mainClient = getUserById($_SESSION['user_id']);
                if ($mainClient['status'] !== 'approved') {
                    session_destroy();
                    $error = 'The main account is pending approval. Please contact administrator.';
                } else {
                    header("Location: client/dashboard.php");
                    exit();
                }
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>

<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tunjitech Consulting - Security Alert System</title>
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
            <div style="text-align: center;" >
                <a href="index.php" class="mb-40 max-w-290-px">
                    <img src="assets/images/tunjitech-logo.png" alt=" tunjitech consulting" width="200 x 200">
                </a>
                <h4></h4>
                <p class="mb-0">Security Alert System</p>
                <p class="mb-32 text-secondary-light text-lg">Welcome back! please enter your detail</p>
                <!--                login error display-->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>
            <form action="" method="POST">

                <div class="icon-field mb-16">
                        <span class="icon top-50 translate-middle-y">
                            <iconify-icon icon="mage:email"></iconify-icon>
                        </span>
                    <input type="text" class="form-control h-56-px bg-neutral-50 radius-12" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"  placeholder="Email or Username" required>
                </div>

                <div class="position-relative mb-20">
                    <div class="icon-field">
                            <span class="icon top-50 translate-middle-y">
                                <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                            </span>
                        <input type="password" class="form-control h-56-px bg-neutral-50 radius-12"  placeholder="Password" id="password" name="password" required>
                    </div>
                    <span class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light" data-toggle="#your-password"></span>
                </div>

                <div class="">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="form-check style-check d-flex align-items-center">
                            <input class="form-check-input border border-neutral-300" type="checkbox" value=""  id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me </label>
                        </div>
                        <a href="forgot_password.php" class="text-primary-600 fw-medium">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"> Sign In</button>

                
                <div class="mt-32 text-center text-sm">
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary-600 fw-semibold">Sign Up</a></p>
                    <p><a href="forgot_password.php">Forgot your password?</a></p>
                </div>

            </form>
        </div>
    </div>
</section>

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
<script src="assets/js/app.js"></script>

<script>
    // ================== Password Show Hide Js Start ==========
    function initializePasswordToggle(toggleSelector) {
        $(toggleSelector).on('click', function() {
            $(this).toggleClass("ri-eye-off-line");
            var input = $($(this).attr("data-toggle"));
            if (input.attr("type") === "password") {
                input.attr("type", "text");
            } else {
                input.attr("type", "password");
            }
        });
    }
    // Call the function
    initializePasswordToggle('.toggle-password');
    // ========================= Password Show Hide Js End ===========================
</script>

</body>


</html>