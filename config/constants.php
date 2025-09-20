<?php
/**
 * Global Constants
 * Defines constants used throughout the application
 */

// Application information
define('APP_NAME', 'GRCC CBT System');
define('APP_VERSION', '1.0.0');
define('APP_URL', '/grcc_cbt');

// File paths
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Storage directories
define('CERTIFICATES_DIR', STORAGE_PATH . '/certificates');
define('TRANSCRIPTS_DIR', STORAGE_PATH . '/transcripts');
define('LOGS_DIR', STORAGE_PATH . '/logs');

// User roles
define('ROLE_STUDENT', 'student');
define('ROLE_ADMIN', 'admin');
define('ROLE_SUPERADMIN', 'superadmin');

// Exam status
define('EXAM_STATUS_PENDING', 'pending');
define('EXAM_STATUS_ACTIVE', 'active');
define('EXAM_STATUS_COMPLETED', 'completed');
define('EXAM_STATUS_CLOSED', 'closed');

// Session timeout (30 minutes in seconds)
define('SESSION_TIMEOUT', 1800);

// Pagination
define('ITEMS_PER_PAGE', 10);