<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../core/auth.php';
require_once '../core/functions.php';
require_once '../core/db.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    header('Location: ../login.php');
    exit();
}

$pdo = getDB();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_admin':
                try {
                    // Generate a random password
                    $password = bin2hex(random_bytes(8));
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'],
                        $password_hash,
                        'admin',
                        'active'
                    ]);

                    // Log the action
                    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)');
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'Created new admin account: ' . $_POST['email'],
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $message = 'Admin created successfully. Temporary password: ' . $password;
                } catch (PDOException $e) {
                    $error = 'Error creating admin: ' . $e->getMessage();
                }
                break;

            case 'update_status':
                try {
                    $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ? AND role = "admin"');
                    $stmt->execute([$_POST['status'], $_POST['admin_id']]);

                    // Log the action
                    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)');
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'Updated admin status: ID ' . $_POST['admin_id'] . ' to ' . $_POST['status'],
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $message = 'Admin status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Error updating admin status: ' . $e->getMessage();
                }
                break;

            case 'reset_password':
                try {
                    $new_password = bin2hex(random_bytes(8));
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND role = "admin"');
                    $stmt->execute([$password_hash, $_POST['admin_id']]);

                    // Log the action
                    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)');
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'Reset password for admin ID: ' . $_POST['admin_id'],
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $message = 'Password reset successfully. New password: ' . $new_password;
                } catch (PDOException $e) {
                    $error = 'Error resetting password: ' . $e->getMessage();
                }
                break;

            case 'delete_admin':
                try {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "admin"');
                    $stmt->execute([$_POST['admin_id']]);

                    // Log the action
                    $stmt = $pdo->prepare('INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)');
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'Deleted admin account ID: ' . $_POST['admin_id'],
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $message = 'Admin deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Error deleting admin: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all admins with their activity counts
$stmt = $pdo->query("SELECT u.*, 
                            (SELECT COUNT(*) FROM logs WHERE user_id = u.id) as activity_count,
                            (SELECT MAX(timestamp) FROM logs WHERE user_id = u.id) as last_activity
                     FROM users u 
                     WHERE u.role = 'admin' 
                     ORDER BY u.created_at DESC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            600: '#2563EB',
                            700: '#1E40AF'
                        },
                        teal: {
                            500: '#14B8A6'
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

        <!-- Add Admin Form -->
        <div
            class="bg-gradient-to-br from-blue-50 to-teal-50 rounded-xl shadow-lg p-4 sm:p-6 mb-8 hover:shadow-xl transition-shadow">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-4">Add New Admin</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_admin">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Name</label>
                        <input type="text" name="name" id="name" required
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                            aria-label="Admin name">
                    </div>
                    <div>
                        <label for="email"
                            class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="email" required
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                            aria-label="Admin email">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Add admin">Add Admin</button>
                </div>
            </form>
        </div>

        <!-- Admins List -->
        <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 hover:shadow-xl transition-shadow">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 mb-4">Existing Admins</h2>
            <!-- Desktop Table View -->
            <div class="hidden sm:block overflow-x-auto">
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
                                Status</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Activities</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Last Active</th>
                            <th
                                class="px-4 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-gray-700 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($admins as $admin): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($admin['name']); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo htmlspecialchars($admin['email']); ?></td>
                                <td class="px-4 sm:px-6 py-4">
                                    <span
                                        class="px-2 inline-flex text-xs sm:text-sm leading-5 font-semibold rounded-full <?php echo $admin['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($admin['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700"><?php echo $admin['activity_count']; ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-700">
                                    <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 space-x-2">
                                    <button
                                        onclick="showStatusModal(<?php echo $admin['id']; ?>, '<?php echo $admin['status']; ?>')"
                                        class="text-blue-600 hover:text-blue-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        aria-label="Update status for admin <?php echo htmlspecialchars($admin['name']); ?>">Update
                                        Status</button>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($admin['name']); ?>?');">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit"
                                            class="text-yellow-600 hover:text-yellow-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                            aria-label="Reset password for admin <?php echo htmlspecialchars($admin['name']); ?>">Reset
                                            Password</button>
                                    </form>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete admin <?php echo htmlspecialchars($admin['name']); ?>?');">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit"
                                            class="text-red-600 hover:text-red-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-red-500"
                                            aria-label="Delete admin <?php echo htmlspecialchars($admin['name']); ?>">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile Card View -->
            <div class="sm:hidden space-y-4">
                <?php foreach ($admins as $admin): ?>
                    <div class="bg-white p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['name']); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Email:</span>
                            <?php echo htmlspecialchars($admin['email']); ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Status:</span>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $admin['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo ucfirst($admin['status']); ?>
                            </span>
                        </p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Activities:</span>
                            <?php echo $admin['activity_count']; ?></p>
                        <p class="text-sm text-gray-700 mt-1"><span class="font-medium">Last Active:</span>
                            <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                        </p>
                        <div class="mt-2 space-x-2">
                            <button
                                onclick="showStatusModal(<?php echo $admin['id']; ?>, '<?php echo $admin['status']; ?>')"
                                class="text-blue-600 hover:text-blue-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Update status for admin <?php echo htmlspecialchars($admin['name']); ?>">Update
                                Status</button>
                            <form method="POST" class="inline"
                                onsubmit="return confirm('Are you sure you want to reset the password for <?php echo htmlspecialchars($admin['name']); ?>?');">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <button type="submit"
                                    class="text-yellow-600 hover:text-yellow-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                    aria-label="Reset password for admin <?php echo htmlspecialchars($admin['name']); ?>">Reset
                                    Password</button>
                            </form>
                            <form method="POST" class="inline"
                                onsubmit="return confirm('Are you sure you want to delete admin <?php echo htmlspecialchars($admin['name']); ?>?');">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <button type="submit"
                                    class="text-red-600 hover:text-red-900 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-red-500"
                                    aria-label="Delete admin <?php echo htmlspecialchars($admin['name']); ?>">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Update Status Modal -->
    <div id="statusModal"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
        <div class="relative p-4 sm:p-5 border w-full sm:w-96 shadow-lg rounded-xl bg-gradient-to-br from-blue-50 to-teal-50 animate-fade-in"
            role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <h3 id="modal-title" class="text-lg sm:text-xl font-medium text-gray-900 mb-4">Update Admin Status</h3>
            <form method="POST" id="statusForm" class="space-y-4">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="admin_id" id="status_admin_id">
                <div>
                    <label for="status_select"
                        class="block text-sm sm:text-base font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status_select" required
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-sm sm:text-base py-2 sm:py-3"
                        aria-label="Admin status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()"
                        class="px-4 sm:px-6 py-2 bg-gray-200 text-gray-700 rounded-xl font-bold hover:bg-gray-300 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-gray-500"
                        aria-label="Cancel">Cancel</button>
                    <button type="submit"
                        class="px-4 sm:px-6 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 hover:scale-105 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Update admin status">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="gradient-primary text-white py-4 px-4 sm:px-6 lg:px-8">
        <div class="container mx-auto text-center">
            <p>© <?php echo date('Y'); ?> GRCC CBT System. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        function showStatusModal(adminId, currentStatus) {
            document.getElementById('status_admin_id').value = adminId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        // Close modal on Escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !document.getElementById('statusModal').classList.contains('hidden')) {
                closeStatusModal();
            }
        });
    </script>
</body>

</html>