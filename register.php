<?php

/**
 * Registration Page
 * Handles student registration and sends welcome email
 */

// Include necessary files
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/smtp.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Define log file
define('EMAIL_LOG_FILE', __DIR__ . '/logs/email.log');

// Ensure logs directory exists and is writable
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
if (!is_writable($log_dir)) {
    error_log('Log directory is not writable: ' . $log_dir);
    die('Error: Log directory is not writable.');
}

// Check for Composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    error_log('Autoloader not found at ' . __DIR__ . '/vendor/autoload.php');
    file_put_contents(EMAIL_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] [ERROR] Autoloader not found at ' . __DIR__ . '/vendor/autoload.php' . "\n", FILE_APPEND | LOCK_EX);
    die('Error: Composer autoloader not found. Please run "composer install".');
}
require_once __DIR__ . '/vendor/autoload.php';
file_put_contents(EMAIL_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] [INFO] Autoloader loaded from: ' . __DIR__ . '/vendor/autoload.php (PHP ' . PHP_VERSION . ")\n", FILE_APPEND | LOCK_EX);

// Ensure PHPMailer classes are loaded
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token early to persist across submissions
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = Security::generateCSRFToken();
}
$csrf_token = $_SESSION['csrf_token'];

// Check if user is already logged in, redirect to dashboard if true
if (Auth::isLoggedIn()) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_STUDENT) {
        header('Location: student/dashboard.php');
        exit();
    } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_ADMIN) {
        header('Location: admin/dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid role. Please contact an administrator.';
    }
}

// Initialize variables
$name = '';
$email = '';
$errors = [];
$success = '';

/**
 * Send welcome email using PHPMailer or fallback to mail()
 * @param string $name User's full name
 * @param string $email User's email address
 * @param string $app_name Application name from constants
 * @return bool Success or failure
 */
function sendWelcomeEmail($name, $email, $app_name)
{
    global $smtp_config;
    $log_message = '[' . date('Y-m-d H:i:s') . '] [EMAIL] ';
    file_put_contents(EMAIL_LOG_FILE, $log_message . "Attempting to send email to $email using PHPMailer (Host: {$smtp_config['host']}, Port: {$smtp_config['port']})\n", FILE_APPEND | LOCK_EX);

    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $error = 'PHPMailer class not found';
            file_put_contents(EMAIL_LOG_FILE, $log_message . "Failed to send email to $email: $error\n", FILE_APPEND | LOCK_EX);
            // Fallback to mail()
            return sendEmailFallback($name, $email, $app_name);
        }
        $mail = new PHPMailer(true);
        // Enable SMTP debugging
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) use ($email, $log_message) {
            file_put_contents(EMAIL_LOG_FILE, $log_message . "[SMTP Debug] [$email] $str\n", FILE_APPEND | LOCK_EX);
        };
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_config['username'];
        $mail->Password = $smtp_config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtp_config['port'];

        // Sender and recipient
        $mail->setFrom($smtp_config['username'], $app_name);
        $mail->addAddress($email, $name);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ' . $app_name . '!';
        $mail->Body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 8px; }
                    .header { background: linear-gradient(to right, #2563EB, #14B8A6); color: #ffffff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #2563EB; color: #ffffff; text-decoration: none; border-radius: 5px; }
                    .footer { text-align: center; font-size: 12px; color: #777; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>Welcome to ' . htmlspecialchars($app_name) . '!</h1>
                    </div>
                    <div class="content">
                        <p>Dear ' . htmlspecialchars($name) . ',</p>
                        <p>Thank you for registering with ' . htmlspecialchars($app_name) . '! Your account has been created and is currently pending approval by an administrator.</p>
                        <p>Once your account is approved, you will be able to log in and access all the features of our platform.</p>
                        <p><strong>Next Steps:</strong></p>
                        <ul>
                            <li>Wait for an email confirmation once your account is approved.</li>
                            <li>Log in using your email and password at <a href="' . htmlspecialchars(BASE_URL) . '/login.php" class="button">Log In</a></li>
                            <li>Contact our support team at <a href="mailto:support@grccglobal.org">support@grccglobal.org</a> if you have any questions.</li>
                        </ul>
                        <p>We’re excited to have you on board!</p>
                        <p>Best regards,<br>The ' . htmlspecialchars($app_name) . ' Team</p>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($app_name) . '. All rights reserved.</p>
                        <p><a href="' . htmlspecialchars(BASE_URL) . '/privacy.php">Privacy Policy</a> | <a href="' . htmlspecialchars(BASE_URL) . '/terms.php">Terms of Service</a></p>
                    </div>
                </div>
            </body>
            </html>
        ';
        $mail->AltBody = "Dear " . htmlspecialchars($name) . ",\n\nThank you for registering with " . htmlspecialchars($app_name) . "! Your account is pending approval by an administrator.\n\nNext Steps:\n- Wait for approval confirmation.\n- Log in at " . htmlspecialchars(BASE_URL) . "/login.php\n- Contact support@grccglobal.org for assistance.\n\nBest regards,\nThe " . htmlspecialchars($app_name) . " Team";

        $mail->send();
        file_put_contents(EMAIL_LOG_FILE, $log_message . "Successfully sent email to $email\n", FILE_APPEND | LOCK_EX);
        return true;
    } catch (Exception $e) {
        $error = $mail->ErrorInfo;
        file_put_contents(EMAIL_LOG_FILE, $log_message . "Failed to send email to $email: $error\n", FILE_APPEND | LOCK_EX);
        // Fallback to mail()
        return sendEmailFallback($name, $email, $app_name);
    }
}

/**
 * Fallback to PHP's mail() function if PHPMailer fails
 * @param string $name User's full name
 * @param string $email User's email address
 * @param string $app_name Application name from constants
 * @return bool Success or failure
 */
function sendEmailFallback($name, $email, $app_name)
{
    global $smtp_config;
    $log_message = '[' . date('Y-m-d H:i:s') . '] [EMAIL] ';
    file_put_contents(EMAIL_LOG_FILE, $log_message . "Attempting to send email to $email using mail()\n", FILE_APPEND | LOCK_EX);

    $subject = 'Welcome to ' . $app_name . '!';
    $message = "Dear " . htmlspecialchars($name) . ",\n\nThank you for registering with " . htmlspecialchars($app_name) . "! Your account is pending approval by an administrator.\n\nNext Steps:\n- Wait for approval confirmation.\n- Log in at " . htmlspecialchars(BASE_URL) . "/login.php\n- Contact support@grccglobal.org for assistance.\n\nBest regards,\nThe " . htmlspecialchars($app_name) . " Team";
    $headers = "From: " . $smtp_config['username'] . "\r\n" .
        "Reply-To: support@grccglobal.org\r\n" .
        "X-Mailer: PHP/" . PHP_VERSION;

    if (mail($email, $subject, $message, $headers)) {
        file_put_contents(EMAIL_LOG_FILE, $log_message . "Successfully sent email to $email via mail()\n", FILE_APPEND | LOCK_EX);
        return true;
    } else {
        $error = 'mail() function failed';
        file_put_contents(EMAIL_LOG_FILE, $log_message . "Failed to send email to $email: $error\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get and sanitize input
        $name = Security::sanitizeInput(isset($_POST['name']) ? $_POST['name'] : '');
        $email = Security::sanitizeInput(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Validate input
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $errors[] = 'Please fill in all fields.';
        }
        if (!Security::validateEmail($email)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        if (!isset($_POST['terms'])) {
            $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
        }

        if (empty($errors)) {
            try {
                // Get database connection
                $database = new Database();
                $conn = $database->getConnection();

                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $errors[] = 'Email address is already registered.';
                } else {
                    // Hash password
                    $password_hash = Security::hashPassword($password);

                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (:name, :email, :password_hash, :role, :status)");
                    $role = ROLE_STUDENT;
                    $status = 'pending';

                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password_hash', $password_hash);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':status', $status);

                    if ($stmt->execute()) {
                        $user_id = $conn->lastInsertId();

                        // Log the registration action
                        Functions::logAction($conn, $user_id, 'User registered');

                        // Send welcome email
                        if (sendWelcomeEmail($name, $email, APP_NAME)) {
                            $success = 'Registration successful! A welcome email has been sent to your email address. Your account is pending approval by an administrator.';
                        } else {
                            $success = 'Registration successful! Your account is pending approval by an administrator. (Note: Failed to send welcome email, please contact support at support@grccglobal.org.)';
                        }

                        // Clear form fields
                        $name = '';
                        $email = '';
                    } else {
                        $errors[] = 'An error occurred during registration. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'An error occurred. Please try again later.';
                file_put_contents(EMAIL_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] [ERROR] [Registration] Failed for ' . $email . ': ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB'
                        },
                        teal: {
                            500: '#14B8A6'
                        },
                        amber: {
                            500: '#F59E0B'
                        },
                        gray: {
                            50: '#F9FAFB',
                            500: '#6B7280',
                            700: '#374151',
                            900: '#111827'
                        }
                    },
                    animation: {
                        'slide-down': 'slideDown 0.3s ease-out'
                    },
                    keyframes: {
                        slideDown: {
                            '0%': {
                                transform: 'translateY(-10px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Header/Navigation -->
    <header class="bg-gradient-to-r from-blue-600 to-teal-500 text-white shadow-md sticky top-0 z-10">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-5 max-w-7xl">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <a href="index.php"
                        class="text-xl sm:text-2xl md:text-3xl font-bold"><?php echo htmlspecialchars(APP_NAME); ?></a>
                </div>
                <nav class="hidden sm:flex items-center">
                    <ul class="flex space-x-4 sm:space-x-6 md:space-x-8">
                        <li><a href="login.php"
                                class="px-4 py-2.5 rounded-xl border border-white text-white font-semibold text-base hover:bg-white hover:text-blue-600 active:scale-95 transition">Login</a>
                        </li>
                        <li><a href="register.php"
                                class="px-4 py-2.5 rounded-xl bg-white text-blue-600 font-semibold text-base hover:bg-opacity-90 active:scale-95 transition">Register</a>
                        </li>
                    </ul>
                </nav>
                <button id="mobile-menu-button"
                    class="sm:hidden p-2 rounded-md hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white transition"
                    aria-label="Toggle navigation menu" aria-controls="mobile-menu" aria-expanded="false">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            <nav id="mobile-menu" class="hidden sm:hidden mt-2 px-4 pb-2 transition-all duration-300 ease-out"
                aria-labelledby="mobile-menu-button">
                <ul class="flex flex-col space-y-2">
                    <li><a href="login.php"
                            class="block px-4 py-2.5 rounded-xl border border-white text-white font-semibold text-base hover:bg-white hover:text-blue-600 active:scale-95 transition min-h-[44px]">Login</a>
                    </li>
                    <li><a href="register.php"
                            class="block px-4 py-2.5 rounded-xl bg-white text-blue-600 font-semibold text-base hover:bg-opacity-90 active:scale-95 transition min-h-[44px]">Register</a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-4 sm:p-6">
        <div
            class="bg-white rounded-xl shadow-md p-5 sm:p-6 md:p-8 w-full max-w-full sm:max-w-lg min-w-[280px] max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl sm:text-2xl md:text-3xl font-bold mb-4 sm:mb-6 text-center">Create an Account</h2>

            <?php if (!empty($errors)): ?>
                <div class="relative bg-red-100 border-l-4 border-red-500 text-red-700 p-4 sm:p-5 mb-4 sm:mb-6 rounded-r-lg animate-slide-down"
                    role="alert" id="error-message">
                    <?php echo htmlspecialchars(implode('<br>', $errors)); ?>
                    <button type="button" class="absolute top-2 right-2 text-red-700 hover:text-red-900"
                        onclick="this.parentElement.remove()" aria-label="Dismiss error message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="relative bg-green-100 border-l-4 border-green-500 text-green-700 p-4 sm:p-5 mb-4 sm:mb-6 rounded-r-lg animate-slide-down"
                    role="alert" id="success-message">
                    <p class="text-sm sm:text-base"><?php echo htmlspecialchars($success); ?></p>
                    <button type="button" class="absolute top-2 right-2 text-green-700 hover:text-green-900"
                        onclick="this.parentElement.remove()" aria-label="Dismiss success message">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="mb-4 sm:mb-5">
                        <label for="name" class="block text-gray-700 font-medium text-sm sm:text-base mb-1 sm:mb-2">Full
                            Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                            class="w-full px-4 sm:px-5 py-2.5 sm:py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                            aria-required="true" aria-describedby="error-message">
                    </div>

                    <div class="mb-4 sm:mb-5">
                        <label for="email" class="block text-gray-700 font-medium text-sm sm:text-base mb-1 sm:mb-2">Email
                            Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required
                            class="w-full px-4 sm:px-5 py-2.5 sm:py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                            aria-required="true" aria-describedby="error-message">
                    </div>

                    <div class="mb-4 sm:mb-5">
                        <label for="password"
                            class="block text-gray-700 font-medium text-sm sm:text-base mb-1 sm:mb-2">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full px-4 sm:px-5 py-2.5 sm:py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                            aria-required="true" aria-describedby="password-hint error-message">
                        <p id="password-hint" class="text-xs sm:text-sm text-gray-500 mt-1">Password must be at least 8
                            characters long.</p>
                    </div>

                    <div class="mb-4 sm:mb-5">
                        <label for="confirm_password"
                            class="block text-gray-700 font-medium text-sm sm:text-base mb-1 sm:mb-2">Confirm
                            Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full px-4 sm:px-5 py-2.5 sm:py-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                            aria-required="true" aria-describedby="error-message">
                    </div>

                    <div class="mb-5 sm:mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="terms" name="terms" required
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                aria-required="true" aria-describedby="terms-description error-message">
                            <label for="terms" class="ml-2 block text-sm sm:text-base text-gray-700">
                                I agree to the <a href="terms.php" class="text-blue-600 hover:underline">Terms of
                                    Service</a> and
                                <a href="privacy.php" class="text-blue-600 hover:underline">Privacy Policy</a>
                            </label>
                        </div>
                        <p id="terms-description" class="text-xs sm:text-sm text-gray-500 mt-1">You must agree to our terms
                            and policies to register.</p>
                    </div>

                    <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-teal-500 text-white py-3 sm:py-3.5 px-4 rounded-xl font-semibold text-base sm:text-lg hover:opacity-90 active:scale-95 transition">
                        Register
                    </button>
                </form>
            <?php endif; ?>

            <div class="mt-4 sm:mt-6 text-center">
                <p class="text-sm sm:text-base text-gray-700">Already have an account? <a href="login.php"
                        class="text-blue-600 hover:underline">Login here</a></p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-600 to-teal-500 text-white py-4 sm:py-5 px-4 sm:px-6">
        <div class="container mx-auto text-center max-w-7xl">
            <p class="text-sm sm:text-base">© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All
                Rights Reserved.</p>
            <p class="text-xs sm:text-sm mt-1">
                <a href="privacy.php" class="text-white hover:underline">Privacy Policy</a> |
                <a href="terms.php" class="text-white hover:underline">Terms of Service</a>
            </p>
        </div>
    </footer>

    <!-- Mobile Menu Script -->
    <script>
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        // Toggle mobile menu
        mobileMenuButton.addEventListener('click', () => {
            const isOpen = !mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('animate-slide-down', !isOpen);
            mobileMenuButton.setAttribute('aria-expanded', !isOpen);
            mobileMenuButton.classList.toggle('open', !isOpen);
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mobileMenu.contains(e.target) && !mobileMenuButton.contains(e.target)) {
                mobileMenu.classList.add('hidden');
                mobileMenu.classList.remove('animate-slide-down');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
                mobileMenuButton.classList.remove('open');
            }
        });

        // Close mobile menu on resize to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 640) {
                mobileMenu.classList.add('hidden');
                mobileMenu.classList.remove('animate-slide-down');
                mobileMenuButton.setAttribute('aria-expanded', 'false');
                mobileMenuButton.classList.remove('open');
            }
        });

        // Keyboard navigation for mobile menu button
        mobileMenuButton.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                mobileMenuButton.click();
            }
        });
    </script>

    <!-- Inline CSS for mobile menu icon toggle -->
    <style type="text/css">
        #mobile-menu-button.open svg path {
            d: path('M6 18L18 6M6 6l12 12');
        }
    </style>
</body>

</html>