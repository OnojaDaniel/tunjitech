<?php
// Check if config.php is already included
if (!isset($pdo)) {
    require_once dirname(__DIR__) . '/includes/config.php';
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == USER_TYPE_ADMIN;
}

// Check if user is sub-admin
function isSubAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == USER_TYPE_SUB_ADMIN;
}

// Check if user is client
function isClient() {
    return isset($_SESSION['user_type']) && ($_SESSION['user_type'] == USER_TYPE_CLIENT_INDIVIDUAL || $_SESSION['user_type'] == USER_TYPE_CLIENT_COMPANY);
}

// Check if user is admin or sub-admin
function isAdminOrSubAdmin() {
    return isAdmin() || isSubAdmin();
}

// Check if user is client user (sub-user of client company)
//function isClientUser() {
//    return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'client_user';
//}

// Check specific permission
function hasPermission($permission) {
    if (isAdmin()) {
        return true; // Admins have all permissions
    }

    if (isSubAdmin() && isset($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']);
    }

    return false;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: ../client/dashboard.php");
        exit();
    }
}

// Redirect if not admin or sub-admin
function requireAdminOrSubAdmin() {
    requireLogin();
    if (!isAdminOrSubAdmin()) {
        header("Location: ../client/dashboard.php");
        exit();
    }
}

// Redirect if not sub-admin
function requireSubAdmin() {
    requireLogin();
    if (!isSubAdmin()) {
        header("Location: ../admin/dashboard.php");
        exit();
    }
}

// Redirect if not client
function requireClient() {
    requireLogin();
    if (!isClient() && !isClientUser()) {
        header("Location: ../admin/dashboard.php");
        exit();
    }
}

// Redirect if not client (main client account only, not client users)
function requireMainClient() {
    requireLogin();
    if (!isClient()) {
        header("Location: ../admin/dashboard.php");
        exit();
    }
}

// Check if user is approved
function isUserApproved($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user && $user['status'] == 'approved';
}

// Check if user can manage client users
//function canManageClientUsers() {
//    if (isClient() && $_SESSION['user_type'] == USER_TYPE_CLIENT_COMPANY) {
//        return true;
//    }
//
//    if (isClientUser() && isset($_SESSION['client_user_role']) && $_SESSION['client_user_role'] == 'admin') {
//        return true;
//    }
//
//    return false;
//}

// Redirect based on user type
function redirectBasedOnUserType() {
    if (isAdminOrSubAdmin()) {
        header("Location: admin/dashboard.php");
        exit();
    } else {
        header("Location: client/dashboard.php");
        exit();
    }
}

// Check if user can access client management features
function canManageClients() {
    return isAdmin(); // Only main admins can manage clients
}

// Check if user can manage sub-admins
function canManageSubAdmins() {
    return isAdmin(); // Only main admins can manage sub-admins
}

// Check if user can manage alerts
function canManageAlerts() {
    return isAdminOrSubAdmin(); // Both admins and sub-admins can manage alerts
}

// Check if user can view analytics
function canViewAnalytics() {
    return isAdminOrSubAdmin(); // Both admins and sub-admins can view analytics
}

// Add this function to check client user login
function loginClientUser($username, $password) {
    global $pdo;

    error_log("Attempting client user login for: " . $username);

    $stmt = $pdo->prepare("SELECT cu.*, u.company_name, u.status as client_status 
                          FROM client_users cu 
                          JOIN users u ON cu.client_id = u.id 
                          WHERE cu.username = ? OR cu.email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        error_log("Client user found: " . print_r($user, true));

        // Check if client user is active
        if ($user['status'] !== 'active') {
            error_log("Client user account is not active");
            return false;
        }

        // Check if main client account is approved
        if ($user['client_status'] !== 'approved') {
            error_log("Main client account is not approved");
            return false;
        }

        if (password_verify($password, $user['password'])) {
            error_log("Password verification successful");

            // Set session variables
            $_SESSION['user_id'] = $user['client_id']; // Main client ID
            $_SESSION['client_user_id'] = $user['id']; // Client user ID
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = 'client_user';
            $_SESSION['email'] = $user['email'];
            $_SESSION['client_user_role'] = $user['role'];
            $_SESSION['company_name'] = $user['company_name'];

            error_log("Session variables set successfully");
            return true;
        } else {
            error_log("Password verification failed");
        }
    } else {
        error_log("No client user found with username/email: " . $username);
    }

    return false;
}

// Function to get user display name
function getUserDisplayName() {
    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
        return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    } elseif (isset($_SESSION['username'])) {
        return $_SESSION['username'];
    } else {
        return 'User';
    }
}

// Function to get user role display name
function getUserRoleDisplay() {
    if (isAdmin()) {
        return 'Administrator';
    } elseif (isSubAdmin()) {
        return 'Sub-Administrator';
    } elseif (isClientUser()) {
        return 'Client User (' . ($_SESSION['client_user_role'] ?? 'user') . ')';
    } elseif (isClient()) {
        if ($_SESSION['user_type'] == USER_TYPE_CLIENT_COMPANY) {
            return 'Company Client';
        } else {
            return 'Individual Client';
        }
    } else {
        return 'Unknown Role';
    }
}

// Function to check if user can perform action based on permissions
function canPerformAction($action) {
    switch ($action) {
        case 'create_alert':
        case 'edit_alert':
        case 'delete_alert':
        case 'send_notifications':
            return canManageAlerts();

        case 'view_analytics':
        case 'export_reports':
            return canViewAnalytics();

        case 'register_client':
        case 'edit_client':
        case 'delete_client':
        case 'approve_client':
            return canManageClients();

        case 'register_subadmin':
        case 'edit_subadmin':
        case 'delete_subadmin':
            return canManageSubAdmins();

        case 'chat_with_clients':
            return isAdminOrSubAdmin();

        default:
            return false;
    }
}

// Function to log user activity (for audit trail)
function logUserActivity($action, $details = '') {
    global $pdo;

    if (!isLoggedIn()) return;

    $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, user_type, action, details, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['user_type'],
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
}

// Function to get user permissions array
function getUserPermissions() {
    if (isAdmin()) {
        return [
            PERMISSION_MANAGE_CLIENTS,
            PERMISSION_MANAGE_ALERTS,
            PERMISSION_MANAGE_SUB_ADMINS,
            PERMISSION_VIEW_ANALYTICS
        ];
    } elseif (isSubAdmin() && isset($_SESSION['permissions'])) {
        return $_SESSION['permissions'];
    } else {
        return [];
    }
}
?>