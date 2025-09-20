<?php
/**
 * Registration Page
 * Handles student registration
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
$name = '';
$email = '';
$error = '';
$success = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get and sanitize input
        $name = Security::sanitizeInput($_POST['name'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Get database connection
                $database = new Database();
                $conn = $database->getConnection();
                
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = 'Email address is already registered.';
                } else {
                    // Hash password
                    $password_hash = Security::hashPassword($password);
                    
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (:name, :email, :password_hash, :role, :status)");
                    $role = ROLE_STUDENT;
                    $status = 'pending'; // New students need admin approval
                    
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password_hash', $password_hash);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':status', $status);
                    
                    if ($stmt->execute()) {
                        $user_id = $conn->lastInsertId();
                        
                        // Log the registration action
                        Functions::logAction($conn, $user_id, 'User registered');
                        
                        $success = 'Registration successful! Your account is pending approval by an administrator.';
                        
                        // Clear form fields after successful registration
                        $name = '';
                        $email = '';
                    } else {
                        $error = 'An error occurred during registration. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'An error occurred. Please try again later.';
                error_log("Registration error: " . $e->getMessage());
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
    <title>Register - <?php echo APP_NAME; ?></title>
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
                    <li><a href="login.php" class="px-4 py-2 rounded-xl border border-white text-white font-bold hover:bg-white hover:text-blue-600 transition">Login</a></li>
                    <li><a href="register.php" class="px-4 py-2 rounded-xl bg-white text-blue-600 font-bold hover:opacity-90 transition">Register</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-md p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center">Create an Account</h2>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="password" class="block text-gray-700 font-medium mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long.</p>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" required class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="terms" class="ml-2 block text-sm text-gray-700">I agree to the <a href="#" class="text-blue-600 hover:underline">Terms of Service</a> and <a href="#" class="text-blue-600 hover:underline">Privacy Policy</a></label>
                    </div>
                </div>
                
                <button type="submit" class="w-full gradient-primary text-white py-2 px-4 rounded-xl font-bold hover:opacity-90 transition">
                    Register
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-gray-700">Already have an account? <a href="login.php" class="text-blue-600 hover:underline">Login here</a></p>
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