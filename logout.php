<?php
/**
 * Logout Page
 * Handles user logout by destroying the session
 */

// Include necessary files
require_once __DIR__ . '/core/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/core/db.php';
    require_once __DIR__ . '/core/functions.php';
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        Functions::logAction($conn, $_SESSION['user_id'], 'User logged out');
    } catch (PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
    }
}

// Destroy the session
Auth::logout();

// Redirect to login page with success message
header('Location: login.php?logout=1');
exit;