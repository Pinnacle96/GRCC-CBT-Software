<?php
require_once __DIR__ . '/../config/constants.php';
/**
 * Authentication Functions
 * Handles user authentication, session management, and role-based access control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/security.php';

class Auth {
    /**
     * Check if a user is logged in
     * @return bool True if the user is logged in, false otherwise
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get the current user's ID
     * @return int|null The user ID if logged in, null otherwise
     */
    public static function getUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get the current user's role
     * @return string|null The user role if logged in, null otherwise
     */
    public static function getUserRole() {
        return self::isLoggedIn() ? $_SESSION['user_role'] : null;
    }
    
    /**
     * Check if the current user has a specific role
     * @param string|array $roles The role(s) to check
     * @return bool True if the user has the role, false otherwise
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (is_array($roles)) {
            return in_array($_SESSION['user_role'], $roles);
        }
        
        return $_SESSION['user_role'] === $roles;
    }
    
    /**
     * Check if the current user is a superadmin
     * @return bool True if the user is a superadmin, false otherwise
     */
    public static function isSuperAdmin() {
        return self::hasRole('superadmin');
    }
    
    /**
     * Check if the current user is an admin
     * @return bool True if the user is an admin, false otherwise
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Create a new user session
     * @param int $userId The user ID
     * @param string $userRole The user role
     * @param string $userName The user name
     * @param string $userEmail The user email
     */
    public static function createUserSession($userId, $userRole, $userName, $userEmail) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $userRole;
        $_SESSION['user_name'] = $userName;
        $_SESSION['user_email'] = $userEmail;
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Destroy the current user session
     */
    public static function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
    }
    
    /**
     * Check if the session has timed out
     * @param int $timeout The timeout in seconds (default: 30 minutes)
     * @return bool True if the session has timed out, false otherwise
     */
    public static function checkSessionTimeout($timeout = 1800) {
        if (!self::isLoggedIn()) {
            return true;
        }
        
        $lastActivity = isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 0;
        $currentTime = time();
        
        if ($currentTime - $lastActivity > $timeout) {
            self::logout();
            return true;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $currentTime;
        return false;
    }
    
    /**
     * Redirect to login page if not logged in
     * @param string $redirectUrl The URL to redirect to after login
     */
    public static function requireLogin($redirectUrl = '') {
        if (!self::isLoggedIn()) {
            $redirect = empty($redirectUrl) ? '' : '?redirect=' . urlencode($redirectUrl);
            header('Location: /grcc_cbt/login.php' . $redirect);
            exit;
        }
        
        // Check for session timeout
        if (self::checkSessionTimeout()) {
            $redirect = empty($redirectUrl) ? '' : '?redirect=' . urlencode($redirectUrl);
            header('Location: /grcc_cbt/login.php?timeout=1' . $redirect);
            exit;
        }
    }
    
    /**
     * Redirect to appropriate dashboard based on user role
     */
    public static function redirectToDashboard() {
        if (!self::isLoggedIn()) {
            header('Location: /grcc_cbt/login.php');
            exit;
        }
        
        switch ($_SESSION['user_role']) {
            case 'student':
                header('Location: /grcc_cbt/student/dashboard.php');
                break;
            case 'admin':
                header('Location: /grcc_cbt/admin/dashboard.php');
                break;
            case 'superadmin':
                header('Location: /grcc_cbt/superadmin/dashboard.php');
                break;
            default:
                header('Location: /grcc_cbt/index.php');
                break;
        }
        exit;
    }
    
    /**
     * Check if the current user has permission to access a specific area
     * @param string|array $allowedRoles The role(s) allowed to access the area
     */
    public static function requireRole($allowedRoles) {
        self::requireLogin();
        
        if (!self::hasRole($allowedRoles)) {
            header('Location: /grcc_cbt/index.php?error=unauthorized');
            exit;
        }
    }
}