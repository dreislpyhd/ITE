<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_cleanup'])) {
    try {
        // First, let's see what users exist
        $stmt = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id");
        $users_before = $stmt->fetchAll();
        
        // Count total users
        $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
        $total_before = $stmt->fetch()['total'];
        
        // Delete all users except admin (username = 'admin' and role = 'admin')
        $stmt = $conn->prepare("DELETE FROM users WHERE username != 'admin' OR role != 'admin'");
        $result = $stmt->execute();
        
        if ($result) {
            $deleted_count = $stmt->rowCount();
            
            // Show remaining users
            $stmt = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id");
            $users_after = $stmt->fetchAll();
            
            $message = "Successfully deleted {$deleted_count} users! Only admin user remains.";
        } else {
            $error = "Error occurred during cleanup.";
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get current users for display
try {
    $stmt = $conn->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id");
    $current_users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $current_users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Cleanup - Brgy. 172 Urduja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=EB+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/night-mode.css" rel="stylesheet">
    <script src="../assets/js/night-mode.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                        'garamond': ['EB Garamond', 'serif'],
                    },
                    colors: {
                        'barangay-orange': '#ff8829',
                        'barangay-green': '#2E8B57',
                        'barangay-blue': '#1e40af',
                        'barangay-red': '#dc2626',
                    }
                }
            }
        }
    </script>
    <style>
    </style>
</head>
<body class="font-poppins bg-gray-50">
    <!-- Navigation -->
    <nav class="shadow-lg sticky top-0 z-50 backdrop-blur-md" style="background-color: #ff6700;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center space-x-3">
                        <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="h-14 w-14 rounded-full object-cover">
                        <div>
                            <h1 class="text-2xl font-bold text-white font-garamond">User Cleanup</h1>
                            <p class="text-sm text-orange-100">Caloocan City</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-white hover:text-orange-200 font-medium transition duration-300">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 font-garamond">User Cleanup</h1>
            <p class="text-gray-600">Remove all users except the main admin user</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Current Users -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 font-garamond mb-4">Current Users (<?php echo count($current_users); ?>)</h2>
            
            <?php if (empty($current_users)): ?>
                <p class="text-gray-500">No users found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($current_users as $user): ?>
                                <tr class="<?php echo $user['username'] === 'admin' ? 'bg-green-50' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                            echo $user['role'] === 'admin' ? 'bg-green-100 text-green-800' : 
                                                ($user['role'] === 'resident' ? 'bg-blue-100 text-blue-800' : 
                                                ($user['role'] === 'barangay_hall' ? 'bg-orange-100 text-orange-800' : 
                                                'bg-gray-100 text-gray-800')); 
                                        ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cleanup Action -->
        <?php if (count($current_users) > 1): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                <h2 class="text-xl font-bold text-red-800 font-garamond mb-4">⚠️ Warning: This action cannot be undone!</h2>
                <p class="text-red-700 mb-4">
                    This will permanently delete all users except the admin user. 
                    The admin user (highlighted in green above) will be preserved.
                </p>
                
                <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete all users except admin? This action cannot be undone!');">
                    <button type="submit" name="confirm_cleanup" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition duration-300">
                        Delete All Users Except Admin
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                <h2 class="text-xl font-bold text-green-800 font-garamond mb-4">✅ Cleanup Complete</h2>
                <p class="text-green-700">Only the admin user remains in the system.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
