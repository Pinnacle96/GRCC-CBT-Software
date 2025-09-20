<?php
/**
 * Common Helper Functions
 * Contains utility functions used throughout the application
 */

require_once __DIR__ . '/security.php';

class Functions {
    /**
     * Format a date/time string
     * @param string $datetime The date/time to format
     * @param string $format The format to use (default: 'Y-m-d H:i:s')
     * @return string The formatted date/time
     */
    public static function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
        $date = new DateTime($datetime);
        return $date->format($format);
    }
    
    /**
     * Calculate time remaining in seconds
     * @param string $endTime The end time
     * @return int The time remaining in seconds
     */
    public static function calculateTimeRemaining($endTime) {
        $end = new DateTime($endTime);
        $now = new DateTime();
        $interval = $now->diff($end);
        
        // Convert to seconds
        $seconds = $interval->days * 24 * 60 * 60;
        $seconds += $interval->h * 60 * 60;
        $seconds += $interval->i * 60;
        $seconds += $interval->s;
        
        return ($now > $end) ? 0 : $seconds;
    }
    
    /**
     * Helper function to get grade point
     * @param string $grade The letter grade
     * @return float The corresponding grade point
     */
    public static function getGradePoint($grade) {
        $grade_points = [
            'A' => 4.0,
            'B' => 3.0,
            'C' => 2.0,
            'D' => 1.0,
            'F' => 0.0
        ];
        return $grade_points[$grade] ?? 0;
    }
    
    /**
     * Format seconds into a human-readable time string (HH:MM:SS)
     * @param int $seconds The number of seconds
     * @return string The formatted time string
     */
    public static function formatTimeString($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    
    /**
     * Generate a random string
     * @param int $length The length of the string
     * @return string The generated string
     */
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Calculate Grade from numeric score
     * @param float $score
     * @return array grade + gpa
     */
    public static function calculateGrade($score) {
        if ($score >= 70) {
            return ['grade' => 'A', 'gpa' => 4.0];
        } elseif ($score >= 60) {
            return ['grade' => 'B', 'gpa' => 3.0];
        } elseif ($score >= 50) {
            return ['grade' => 'C', 'gpa' => 2.0];
        } elseif ($score >= 45) {
            return ['grade' => 'D', 'gpa' => 1.0];
        } else {
            return ['grade' => 'F', 'gpa' => 0.0];
        }
    }
    
    /**
     * Calculate CGPA from results
     * @param array $results Array with 'credit_units' and 'gpa'
     * @return float CGPA
     */
    public static function calculateCGPA($results) {
        $totalCreditUnits = 0;
        $totalGradePoints = 0;
        
        foreach ($results as $result) {
            $totalCreditUnits += $result['credit_units'];
            $totalGradePoints += ($result['gpa'] * $result['credit_units']);
        }
        
        return ($totalCreditUnits == 0) ? 0 : round($totalGradePoints / $totalCreditUnits, 2);
    }

    /**
     * Get user details by ID
     * @param PDO $conn
     * @param int $userId
     * @return array|false
     */
    public static function getUserById($conn, $userId) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log an action
     * @param PDO $conn
     * @param int $userId
     * @param string $action
     * @return bool
     */
    public static function logAction($conn, $userId, $action) {
        try {
            $stmt = $conn->prepare("INSERT INTO logs (user_id, action, timestamp) VALUES (:user_id, :action, NOW())");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Log action error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Redirect with a flash message
     */
    public static function redirectWithMessage($url, $message, $type = 'info') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
        header("Location: $url");
        exit;
    }
    
    /**
     * Display a flash message
     */
    public static function displayFlashMessage() {
        if (!isset($_SESSION['flash_message'])) {
            return '';
        }
        
        $message = Security::sanitizeInput($_SESSION['flash_message']);
        $type = $_SESSION['flash_type'] ?? 'info';
        
        // Tailwind classes
        $classes = [
            'success' => 'bg-green-100 border-green-500 text-green-700',
            'error'   => 'bg-red-100 border-red-500 text-red-700',
            'warning' => 'bg-yellow-100 border-yellow-500 text-yellow-700',
            'info'    => 'bg-blue-100 border-blue-500 text-blue-700'
        ];
        
        $class = $classes[$type] ?? $classes['info'];
        
        // clear session
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        return "<div class=\"border-l-4 p-4 mb-4 {$class}\" role=\"alert\">\n"
             . "    <p>{$message}</p>\n"
             . "</div>";
    }
}
