<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';
require_once '../config/constants.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    $error = 'Access denied. Superadmin role required.';
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    try {
        $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
        $name = strip_tags(trim($name));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW);
        $role = trim($role);
        $status = filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW);
        $status = trim($status);

        // Validate inputs
        if (empty($name) || empty($email) || empty($role) || empty($status)) {
            throw new Exception('All fields are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }
        if (!in_array($role, ['admin', 'student'])) {
            throw new Exception('Invalid role.');
        }
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Invalid status.');
        }

        // Check for duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email is already in use.');
        }

        $stmt = $pdo->prepare("INSERT INTO users (name, email, role, status, created_at) VALUES (:name, :email, :role, :status, NOW())");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':role' => $role,
            ':status' => $status,
        ]);

        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], 'Added user: ' . $email, $_SERVER['REMOTE_ADDR']]);

        $message = 'User added successfully.';
    } catch (Exception $e) {
        $error = 'Error adding user: ' . $e->getMessage();
    }
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
        $name = strip_tags(trim($name));
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = filter_input(INPUT_POST, 'role', FILTER_UNSAFE_RAW);
        $role = trim($role);
        $status = filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW);
        $status = trim($status);

        // Validate inputs
        if (!$id || empty($name) || empty($email) || empty($role) || empty($status)) {
            throw new Exception('All fields are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }
        if (!in_array($role, ['admin', 'student'])) {
            throw new Exception('Invalid role.');
        }
        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Invalid status.');
        }

        // Check for duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Email is already in use.');
        }

        $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, role = :role, status = :status WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':role' => $role,
            ':status' => $status,
            ':id' => $id
        ]);

        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], 'Updated user: ' . $email, $_SERVER['REMOTE_ADDR']]);

        $message = 'User updated successfully.';
    } catch (Exception $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}

// Handle Delete User
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new Exception('Invalid user ID.');
        }

        // Prevent deleting the current superadmin
        if ($id === $_SESSION['user_id']) {
            throw new Exception('Cannot delete your own account.');
        }

        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new Exception('User not found.');
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        // Log the action
        $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SESSION['user_id'], 'Deleted user: ' . $user['email'], $_SERVER['REMOTE_ADDR']]);

        $message = 'User deleted successfully.';
    } catch (Exception $e) {
        $error = 'Error deleting user: ' . $e->getMessage();
    }
}

// Fetch all users
$users = [];
try {
    $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo htmlspecialchars(APP_NAME); ?></title>
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
    <?php include '../includes/superadmin_nav.php'; ?>

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

        <?php if ($action === 'list'): ?>
            <!-- User List with Actions -->
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Manage Users</h2>
                <a href="users.php?action=add"
                    class="inline-flex px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                    aria-label="Add new user">+ Add User</a>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden sm:block bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Name</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Email</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Role</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Status</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($u['name']); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($u['email']); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo ucfirst(htmlspecialchars($u['role'])); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo ucfirst(htmlspecialchars($u['status'])); ?></td>
                                <td class="px-4 sm:px-6 py-4 space-x-2">
                                    <a href="users.php?action=edit&id=<?php echo $u['id']; ?>"
                                        class="text-blue-600 hover:text-blue-800 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        aria-label="Edit user <?php echo htmlspecialchars($u['name']); ?>">Edit</a>
                                    <a href="users.php?action=delete&id=<?php echo $u['id']; ?>"
                                        class="text-red-600 hover:text-red-800 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-red-500"
                                        onclick="return confirm('Delete user <?php echo htmlspecialchars($u['name']); ?>?')"
                                        aria-label="Delete user <?php echo htmlspecialchars($u['name']); ?>">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="sm:hidden space-y-4">
                <?php foreach ($users as $u): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($u['name']); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Email:</span>
                            <?php echo htmlspecialchars($u['email']); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Role:</span>
                            <?php echo ucfirst(htmlspecialchars($u['role'])); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Status:</span>
                            <?php echo ucfirst(htmlspecialchars($u['status'])); ?></p>
                        <div class="mt-2 space-x-2">
                            <a href="users.php?action=edit&id=<?php echo $u['id']; ?>"
                                class="text-blue-600 hover:text-blue-800 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Edit user <?php echo htmlspecialchars($u['name']); ?>">Edit</a>
                            <a href="users.php?action=delete&id=<?php echo $u['id']; ?>"
                                class="text-red-600 hover:text-red-800 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-red-500"
                                onclick="return confirm('Delete user <?php echo htmlspecialchars($u['name']); ?>?')"
                                aria-label="Delete user <?php echo htmlspecialchars($u['name']); ?>">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <?php
            $user = ['id' => '', 'name' => '', 'email' => '', 'role' => 'student', 'status' => 'active'];
            if ($action === 'edit' && isset($_GET['id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;
                } catch (Exception $e) {
                    $error = 'Error fetching user: ' . $e->getMessage();
                }
            }
            ?>
            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-6">
                <?php echo $action === 'add' ? 'Add New User' : 'Edit User'; ?></h2>
            <form method="POST"
                class="bg-gradient-to-br from-blue-50 to-teal-50 p-4 sm:p-6 rounded-xl shadow-lg max-w-lg sm:max-w-xl space-y-6">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <?php endif; ?>
                <div>
                    <label for="name" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="User name" required>
                </div>
                <div>
                    <label for="email" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="User email" required>
                </div>
                <div>
                    <label for="role" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" id="role"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="User role">
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student
                        </option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="User status">
                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive
                        </option>
                    </select>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="submit"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="<?php echo $action === 'add' ? 'Add user' : 'Save user changes'; ?>">Save</button>
                    <a href="users.php"
                        class="px-4 sm:px-6 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-gray-500"
                        aria-label="Cancel">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>

</html>