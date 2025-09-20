<?php
/**
 * Security Functions
 * Handles CSRF protection, input sanitization, and other security measures
 */

class Security {
    /**
     * Generate a CSRF token and store it in the session
     * @return string The generated CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify if the submitted CSRF token matches the one in the session
     * @param string $token The token to verify
     * @return bool True if the token is valid, false otherwise
     */
    public static function verifyCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }
    
    /**
     * Sanitize input data to prevent XSS attacks
     * @param string $data The data to sanitize
     * @return string The sanitized data
     */
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    /**
     * Sanitize an array of input data
     * @param array $dataArray The array to sanitize
     * @return array The sanitized array
     */
    public static function sanitizeArray($dataArray) {
        $sanitizedArray = [];
        foreach ($dataArray as $key => $value) {
            if (is_array($value)) {
                $sanitizedArray[$key] = self::sanitizeArray($value);
            } else {
                $sanitizedArray[$key] = self::sanitizeInput($value);
            }
        }
        return $sanitizedArray;
    }
    
    /**
     * Validate email format
     * @param string $email The email to validate
     * @return bool True if the email is valid, false otherwise
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash a password using password_hash
     * @param string $password The password to hash
     * @return string The hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify a password against a hash
     * @param string $password The password to verify
     * @param string $hash The hash to verify against
     * @return bool True if the password is valid, false otherwise
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate a secure random token
     * @param int $length The length of the token
     * @return string The generated token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}