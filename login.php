<?php
/**
 * Login Page
 * Handles user authentication
 */

// Include necessary files
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in, redirect to dashboard if true
if (Auth::isLoggedIn()) {
    Auth::redirectToDashboard();
}

// Initialize variables
$email = '';
$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get and sanitize input
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Attempt to authenticate user
            try {
                // Get database connection
                $database = new Database();
                $conn = $database->getConnection();
                
                // Prepare statement
                $stmt = $conn->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    
                    // Check if account is active
                    if ($user['status'] !== 'active') {
                        $error = 'Your account is not active. Please contact the administrator.';
                    } 
                    // Verify password
                    elseif (Security::verifyPassword($password, $user['password_hash'])) {
                        // Create user session
                        Auth::createUserSession($user['id'], $user['role'], $user['name'], $user['email']);
                        
                        // Log the login action
                        Functions::logAction($conn, $user['id'], 'User logged in');
                        
                        // Redirect to appropriate page
                        if (!empty($redirect) && filter_var($redirect, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
                            header("Location: " . $redirect);
                        } else {
                            Auth::redirectToDashboard();
                        }
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $error = 'An error occurred. Please try again later.';
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB', // Primary (Brand Blue)
                        },
                        teal: {
                            500: '#14B8A6', // Secondary (Teal)
                        },
                        amber: {
                            500: '#F59E0B', // Accent (Amber)
                        },
                        gray: {
                            50: '#F9FAFB',  // Background (Light)
                            500: '#6B7280', // Neutral (Gray)
                            700: '#374151', // Body text
                            900: '#111827', // Text (Dark)
                        },
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .gradient-primary {
                @apply bg-gradient-to-r from-blue-600 to-teal-500;
            }
            .gradient-secondary {
                @apply bg-gradient-to-r from-teal-500 to-amber-500;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Header/Navigation -->
    <header class="gradient-primary text-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="index.php" class="text-2xl font-bold"><?php echo APP_NAME; ?></a>
            </div>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="login.php" class="px-4 py-2 rounded-xl bg-white text-blue-600 font-bold hover:opacity-90 transition">Login</a></li>
                    <li><a href="register.php" class="px-4 py-2 rounded-xl border border-white text-white font-bold hover:bg-white hover:text-blue-600 transition">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Login to Your Account</h2>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout'])): ?>
                <div class="bg-amber-100 border-l-4 border-amber-500 text-amber-700 p-4 mb-6" role="alert">
                    <p>Your session has timed out. Please login again.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p>You have been successfully logged out.</p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-blue-600 hover:underline">Forgot password?</a>
                </div>
                
                <button type="submit" class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 transition">
                    Login
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-gray-700">Don't have an account? <a href="register.php" class="text-blue-600 hover:underline">Register here</a></p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="gradient-primary text-white py-4 px-4">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>