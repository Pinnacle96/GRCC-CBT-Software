<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';
require_once '../config/constants.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    $error = 'Access denied. Superadmin role required.';
    header('Location: ../login.php');
    exit();
}

// Get user data
$user_name = $_SESSION['user_name'] ?? 'Admin';

// Get database connection
$pdo = getDB();

// Get user details
$user_id = $_SESSION['user_id'];
$user = Functions::getUserById($pdo, $user_id);

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
        $name = strip_tags(trim($name));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }

        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email is already taken by another user.');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $email, $user_id]);

        // Handle password update if provided
        if (!empty($current_password)) {
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                throw new Exception('Current password is incorrect.');
            }

            // Validate new password
            if (empty($new_password)) {
                throw new Exception('New password is required.');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }

            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }

            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
        }

        // Commit transaction
        $pdo->commit();

        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$user_id, 'Updated profile', $_SERVER['REMOTE_ADDR']]);

        $message = 'Profile updated successfully.';

        // Refresh user data
        $user = Functions::getUserById($pdo, $user_id);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars(APP_NAME); ?></title>
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
                        'fade-in': 'fadeIn 0.3s ease-out',
                        'scale-up': 'scaleUp 0.2s ease-out'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0'
                            },
                            '100%': {
                                opacity: '1'
                            }
                        },
                        scaleUp: {
                            '0%': {
                                transform: 'scale(1)'
                            },
                            '100%': {
                                transform: 'scale(1.05)'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .gradient-primary { @apply bg-gradient-to-r from-blue-600 to-teal-500; }
            .gradient-secondary { @apply bg-gradient-to-r from-teal-500 to-amber-500; }
        }
    </style>
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">
    <!-- Include Responsive Navbar -->
    <?php include __DIR__ . '/../includes/superadmin_nav.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Flash Messages -->
        <?php if ($message): ?>
            <div class="mb-6 bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500 text-green-700 p-4 sm:p-6 rounded-r-lg animate-fade-in"
                role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-gradient-to-br from-red-50 to-red-100 border-l-4 border-red-500 text-red-700 p-4 sm:p-6 rounded-r-lg animate-fade-in"
                role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 mb-6 sm:mb-8">My Profile</h1>

        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow">
            <form method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm sm:text-base font-medium text-gray-700">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Full name" required>
                </div>

                <div>
                    <label for="email" class="block text-sm sm:text-base font-medium text-gray-700">Email
                        Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Email address" required>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Change Password</h3>
                    <div class="space-y-4">
                        <div>
                            <label for="current_password"
                                class="block text-sm sm:text-base font-medium text-gray-700">Current Password</label>
                            <input type="password" id="current_password" name="current_password"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                                aria-label="Current password">
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm sm:text-base font-medium text-gray-700">New
                                Password</label>
                            <input type="password" id="new_password" name="new_password"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                                aria-label="New password">
                        </div>
                        <div>
                            <label for="confirm_password"
                                class="block text-sm sm:text-base font-medium text-gray-700">Confirm New
                                Password</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                                aria-label="Confirm new password">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Save profile changes">Save Changes</button>
                </div>
            </form>
        </div>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>