<?php
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
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Superadmin</h1>
            <div class="space-x-4">
                <a href="dashboard.php" class="hover:text-gray-200">Dashboard</a>
                <a href="system_logs.php" class="hover:text-gray-200">System Logs</a>
                <a href="config.php" class="hover:text-gray-200">Configuration</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Add Admin Form -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Add New Admin</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_admin">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Add Admin</button>
                </div>
            </form>
        </div>

        <!-- Admins List -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Existing Admins</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activities</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($admins as $admin): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($admin['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $admin['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($admin['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $admin['activity_count']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap space-x-2">
                                <button onclick="showStatusModal(<?php echo $admin['id']; ?>, '<?php echo $admin['status']; ?>')" 
                                        class="text-blue-600 hover:text-blue-900">Update Status</button>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to reset the password?');">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">Reset Password</button>
                                </form>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                    <input type="hidden" name="action" value="delete_admin">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Update Status Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Update Admin Status</h3>
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="admin_id" id="status_admin_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status_select" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showStatusModal(adminId, currentStatus) {
            document.getElementById('status_admin_id').value = adminId;
            document.getElementById('status_select').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
    </script>
</body>
</html>