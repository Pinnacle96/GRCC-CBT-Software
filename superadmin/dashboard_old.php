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

// Get summary statistics
$stats = [
    'total_admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'total_courses' => $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'total_exams' => $pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn()
];

// Get recent system logs
$stmt = $pdo->query('SELECT l.*, u.name as user_name, u.role 
                    FROM logs l 
                    LEFT JOIN users u ON l.user_id = u.id 
                    ORDER BY l.timestamp DESC LIMIT 10');
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin activity summary
$stmt = $pdo->query("SELECT u.name, u.email, 
                            (SELECT COUNT(*) FROM logs WHERE user_id = u.id) as activity_count,
                            MAX(l.timestamp) as last_activity
                    FROM users u
                    LEFT JOIN logs l ON u.id = l.user_id
                    WHERE u.role = 'admin'
                    GROUP BY u.id
                    ORDER BY activity_count DESC
                    LIMIT 5");
$admin_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard - GRCC CBT System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-gradient-to-r from-blue-600 to-teal-500 p-4 text-white">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-2xl font-bold">GRCC CBT Superadmin</h1>
            <div class="space-x-4">
                <a href="manage_admins.php" class="hover:text-gray-200">Manage Admins</a>
                <a href="system_logs.php" class="hover:text-gray-200">System Logs</a>
                <a href="config.php" class="hover:text-gray-200">Configuration</a>
                <a href="../logout.php" class="hover:text-gray-200">Logout</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Admins</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_admins']; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Students</h3>
                <p class="text-3xl font-bold text-teal-500"><?php echo $stats['total_students']; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Courses</h3>
                <p class="text-3xl font-bold text-amber-500"><?php echo $stats['total_courses']; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-700">Total Exams</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats['total_exams']; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Recent System Logs -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Recent System Logs</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recent_logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?>
                                    <span class="text-xs text-gray-500">(<?php echo $log['role'] ?? 'system'; ?>)</span>
                                </td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                    <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="system_logs.php" class="text-blue-600 hover:text-blue-800">View All Logs →</a>
                </div>
            </div>

            <!-- Admin Activity -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-2xl font-semibold text-gray-700 mb-4">Admin Activity Summary</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activities</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($admin_activity as $admin): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($admin['name']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?php echo $admin['activity_count']; ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                    <?php echo $admin['last_activity'] ? date('Y-m-d H:i', strtotime($admin['last_activity'])) : 'Never'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 text-right">
                    <a href="manage_admins.php" class="text-blue-600 hover:text-blue-800">Manage Admins →</a>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 mt-8 py-4">
        <div class="container mx-auto text-center text-gray-600">
            &copy; <?php echo date('Y'); ?> GRCC CBT System. All rights reserved.
        </div>
    </footer>
</body>
</html>