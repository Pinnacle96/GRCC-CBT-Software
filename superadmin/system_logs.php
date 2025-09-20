<?php
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/functions.php';

// Ensure only superadmin users can access this page
if (!Auth::isSuperAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Pagination settings
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtering
$filter_user = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT);
$filter_action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
$filter_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);

// Build query
$where_clauses = [];
$params = [];

if ($filter_user) {
    $where_clauses[] = 'l.user_id = ?';
    $params[] = $filter_user;
}

if ($filter_action) {
    $where_clauses[] = 'l.action LIKE ?';
    $params[] = "%$filter_action%";
}

if ($filter_date) {
    $where_clauses[] = 'DATE(l.timestamp) = ?';
    $params[] = $filter_date;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM logs l $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get logs with user details
$sql = "SELECT l.*, u.name as user_name, u.email, u.role 
        FROM logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        $where_sql 
        ORDER BY l.timestamp DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique users for filter
$stmt = $pdo->query("SELECT DISTINCT u.id, u.name, u.role FROM users u JOIN logs l ON u.id = l.user_id ORDER BY u.name");
$users = $stmt->fetchAll();

// Get unique actions for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - GRCC CBT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/superadmin_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">System Logs</h1>
        
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                    <select name="user" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                    <select name="action" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action == $action ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>" 
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 gradient-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Logs Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($log['user_id']): ?>
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['user_name']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($log['email']); ?></div>
                            <?php else: ?>
                            <span class="text-sm text-gray-500">System</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?php echo ucfirst($log['role'] ?? 'System'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($log['ip_address']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>&date=<?php echo $filter_date; ?>" 
                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium 
                          <?php echo $page === $i ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Add gradient utility class
    document.querySelector('style').textContent += `
        .gradient-primary {
            background: linear-gradient(to right, #2563EB, #14B8A6);
        }
    `;
    </script>
</body>
</html>