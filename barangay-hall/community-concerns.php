<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is barangay_staff or barangay_hall
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'barangay_staff' && $_SESSION['role'] !== 'barangay_hall')) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
require_once '../includes/notification_helper.php';
$db = new Database();
$conn = $db->getConnection();
$notificationHelper = new NotificationHelper();

// Include notification badge helper
require_once 'includes/notification_badge.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $concern_id = $_POST['concern_id'];
                $new_status = $_POST['status'];
                $admin_response = trim($_POST['admin_response'] ?? '');
                
                try {
                    // Verify the current session user exists in the database
                    $admin_id = $_SESSION['user_id'];
                    $admin_check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('barangay_staff', 'barangay_hall', 'admin')");
                    $admin_check_stmt->execute([$admin_id]);
                    $admin_exists = $admin_check_stmt->fetch();
                    
                    if (!$admin_exists) {
                        // Log the issue but don't fail - set admin_id to NULL instead
                        error_log("Warning: Session user ID {$admin_id} not found in database or not barangay staff. Setting admin_id to NULL.");
                        $admin_id = null;
                    }
                    
                    $stmt = $conn->prepare("UPDATE community_concerns SET status = ?, admin_response = ?, admin_id = ?, updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$new_status, $admin_response, $admin_id, $concern_id])) {
                        header('Location: community-concerns.php?message=Concern status updated successfully');
                        exit();
                    } else {
                        $error = 'Failed to update concern status';
                    }
                } catch (Exception $e) {
                    $error = 'Error updating concern: ' . $e->getMessage();
                    error_log("Community concern update error: " . $e->getMessage());
                    error_log("Session user ID: " . ($_SESSION['user_id'] ?? 'null'));
                    error_log("Session role: " . ($_SESSION['role'] ?? 'null'));
                }
                break;
        }
    }
}

// Get messages from URL parameters
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get all community concerns with user information
try {
    $stmt = $conn->prepare("
        SELECT cc.*, u.full_name, u.email 
        FROM community_concerns cc 
        LEFT JOIN users u ON cc.user_id = u.id 
        ORDER BY cc.created_at DESC
    ");
    $stmt->execute();
    $concerns = $stmt->fetchAll();
} catch (Exception $e) {
    $concerns = [];
}

// Mark community concerns as viewed
$user_id = $_SESSION['user_id'];
$notificationHelper->markAsViewed($user_id, 'community_concerns');

// Mark concerns as viewed when staff visits this page
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_viewed_concerns = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error updating last_viewed_concerns: " . $e->getMessage());
    }
}

// Get notification counts for sidebar
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Concerns - Barangay Hall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                        'eb-garamond': ['EB Garamond', 'serif'],
                    },
                    colors: {
                        'barangay-blue': '#1e40af',
                        'barangay-orange': '#ff8829',
                        'barangay-green': '#2E8B57',
                    }
                }
            }
        }
    </script>
</head>
<body class="font-poppins bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-barangay-orange shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center space-x-4">
                        <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="h-14 w-14 rounded-full">
                        <div>
                            <h1 class="text-xl font-bold text-white font-eb-garamond">Barangay Hall Dashboard</h1>
                            <p class="text-sm text-orange-100">Brgy. 172 Urduja Zone 15 District 1 Caloocan City</p>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="h-14 w-14 rounded-full">
                            <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="h-16 w-16 rounded-full">
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                            <span class="text-barangay-orange font-bold text-lg">
                                <?php 
                                $fullName = $_SESSION['full_name'] ?? 'Staff';
                                $nameParts = explode(' ', $fullName);
                                $initials = '';
                                if (count($nameParts) >= 2) {
                                    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($fullName, 0, 2));
                                }
                                echo $initials;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Mobile Menu Button -->
        <button id="sidebarToggle" class="lg:hidden fixed top-20 left-4 z-40 bg-barangay-orange text-white p-1.5 rounded-full shadow-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>

        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white shadow-lg min-h-screen transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out fixed lg:relative z-40">
            <div class="p-4">
                <nav class="space-y-2">
                    <a href="index.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5v14M16 5v14"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="barangay-staff.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Residents Account
                        <?php if ($notification_counts['residents'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="services.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Certificates
                    </a>
                    <a href="applications.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Applications
                        <?php if ($notification_counts['applications'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="community-concerns.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                        </svg>
                        Community Concerns
                    </a>
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Reports
                    </a>
                </nav>
                
                <!-- Logout Button at Bottom -->
                <div class="mt-auto pt-4 border-t border-gray-200">
                    <button onclick="showLogoutModal()" class="w-full flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Community Concerns</h1>
                <p class="text-gray-600 mt-2">Manage and respond to community concerns reported by residents</p>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Concerns List -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 font-eb-garamond">All Community Concerns</h2>
                </div>
                
                <?php if (empty($concerns)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 font-eb-garamond">No concerns reported</h3>
                        <p class="mt-1 text-sm text-gray-500">No community concerns have been reported yet.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($concerns as $concern): ?>
                            <div class="p-6 hover:bg-gray-50">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h3 class="text-lg font-medium text-gray-900 font-eb-garamond"><?php echo htmlspecialchars($concern['concern_type']); ?></h3>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                switch($concern['priority_level']) {
                                                    case 'Urgent': echo 'bg-red-100 text-red-800'; break;
                                                    case 'High': echo 'bg-orange-100 text-orange-800'; break;
                                                    case 'Medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'Low': echo 'bg-green-100 text-green-800'; break;
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($concern['priority_level']); ?>
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                switch($concern['status']) {
                                                    case 'Pending': echo 'bg-gray-100 text-gray-800'; break;
                                                    case 'In Progress': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'Resolved': echo 'bg-green-100 text-green-800'; break;
                                                    case 'Closed': echo 'bg-red-100 text-red-800'; break;
                                                }
                                                ?>">
                                                <?php echo htmlspecialchars($concern['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-sm text-gray-600 mb-2">
                                            <strong>Issue:</strong> <?php echo htmlspecialchars($concern['specific_issue']); ?>
                                        </p>
                                        
                                        <p class="text-sm text-gray-600 mb-2">
                                            <strong>Location:</strong> <?php echo htmlspecialchars($concern['location']); ?>
                                        </p>
                                        
                                        <p class="text-sm text-gray-600 mb-3">
                                            <strong>Description:</strong> <?php echo htmlspecialchars($concern['description']); ?>
                                        </p>
                                        
                                        <div class="text-xs text-gray-500">
                                            <strong>Reported by:</strong> <?php echo htmlspecialchars($concern['full_name']); ?> 
                                            (<?php echo htmlspecialchars($concern['email']); ?>) 
                                            on <?php echo date('M j, Y g:i A', strtotime($concern['created_at'])); ?>
                                        </div>
                                        
                                        <?php if ($concern['admin_response']): ?>
                                            <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                                                <p class="text-sm text-blue-800">
                                                    <strong>Admin Response:</strong> <?php echo htmlspecialchars($concern['admin_response']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ml-4 flex space-x-2">
                                        <button onclick="openViewModal(<?php echo $concern['id']; ?>, '<?php echo htmlspecialchars($concern['concern_type']); ?>', '<?php echo htmlspecialchars($concern['specific_issue']); ?>', '<?php echo htmlspecialchars($concern['location']); ?>', '<?php echo htmlspecialchars($concern['description']); ?>', '<?php echo htmlspecialchars($concern['priority_level']); ?>', '<?php echo htmlspecialchars($concern['full_name']); ?>', '<?php echo htmlspecialchars($concern['email']); ?>', '<?php echo date('M j, Y g:i A', strtotime($concern['created_at'])); ?>', '<?php echo htmlspecialchars($concern['photos'] ?? ''); ?>')" 
                                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                            View
                                        </button>
                                        <button onclick="openUpdateModal(<?php echo $concern['id']; ?>, '<?php echo htmlspecialchars($concern['status']); ?>', '<?php echo htmlspecialchars($concern['admin_response'] ?? ''); ?>')" 
                                                class="bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                            Update Status
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="concern_id" id="concern_id">
                    
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 font-eb-garamond">Update Concern Status</h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Resolved">Resolved</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Admin Response</label>
                            <textarea name="admin_response" id="admin_response" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent" placeholder="Enter your response to the resident..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-barangay-orange hover:bg-orange-600 rounded-lg transition-colors">
                                Update Status
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Concern Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 font-eb-garamond">Community Concern Report</h3>
                        <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Concern Type and Priority -->
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 font-eb-garamond" id="viewConcernType">-</h4>
                                <p class="text-sm text-gray-600" id="viewSpecificIssue">-</p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" id="viewPriority">
                                -
                            </span>
                        </div>
                        
                        <!-- Location -->
                        <div>
                            <h5 class="font-medium text-gray-900 mb-2 font-eb-garamond">Location</h5>
                            <p class="text-gray-700" id="viewLocation">-</p>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <h5 class="font-medium text-gray-900 mb-2 font-eb-garamond">Detailed Description</h5>
                            <p class="text-gray-700 whitespace-pre-wrap" id="viewDescription">-</p>
                        </div>
                        
                        <!-- Photos -->
                        <div id="viewPhotosSection" class="hidden">
                            <h5 class="font-medium text-gray-900 mb-3 font-eb-garamond">Uploaded Photos</h5>
                            <div id="viewPhotos" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <!-- Photos will be inserted here -->
                            </div>
                        </div>
                        
                        <!-- Reporter Information -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h5 class="font-medium text-gray-900 mb-2 font-eb-garamond">Reporter Information</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="font-medium text-gray-600">Name:</span>
                                    <span class="text-gray-900" id="viewReporterName">-</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-600">Email:</span>
                                    <span class="text-gray-900" id="viewReporterEmail">-</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-600">Reported on:</span>
                                    <span class="text-gray-900" id="viewReportedDate">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Confirm Logout</h3>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-sm text-gray-500">Are you sure you want to logout? You will need to login again to access the system.</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button onclick="hideLogoutModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <a href="../auth/logout.php" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 1024) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        // Update Modal Functions
        function openUpdateModal(concernId, currentStatus, currentResponse) {
            document.getElementById('concern_id').value = concernId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('admin_response').value = currentResponse;
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }

        // View Modal Functions
        function openViewModal(concernId, concernType, specificIssue, location, description, priority, reporterName, reporterEmail, reportedDate, photos) {
            // Populate modal with data
            document.getElementById('viewConcernType').textContent = concernType;
            document.getElementById('viewSpecificIssue').textContent = specificIssue;
            document.getElementById('viewLocation').textContent = location;
            document.getElementById('viewDescription').textContent = description;
            document.getElementById('viewReporterName').textContent = reporterName;
            document.getElementById('viewReporterEmail').textContent = reporterEmail;
            document.getElementById('viewReportedDate').textContent = reportedDate;
            
            // Set priority badge
            const priorityElement = document.getElementById('viewPriority');
            priorityElement.textContent = priority;
            priorityElement.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ';
            switch(priority) {
                case 'Urgent': priorityElement.className += 'bg-red-100 text-red-800'; break;
                case 'High': priorityElement.className += 'bg-orange-100 text-orange-800'; break;
                case 'Medium': priorityElement.className += 'bg-yellow-100 text-yellow-800'; break;
                case 'Low': priorityElement.className += 'bg-green-100 text-green-800'; break;
            }
            
            // Handle photos
            const photosSection = document.getElementById('viewPhotosSection');
            const photosContainer = document.getElementById('viewPhotos');
            
            if (photos && photos.trim() !== '') {
                try {
                    const photoArray = JSON.parse(photos);
                    if (photoArray && photoArray.length > 0) {
                        photosContainer.innerHTML = '';
                        photoArray.forEach(photo => {
                            const photoDiv = document.createElement('div');
                            photoDiv.className = 'relative group';
                            photoDiv.innerHTML = `
                                <img src="../uploads/concerns/${photo}" alt="Concern photo" class="w-full h-32 object-cover rounded-lg cursor-pointer" onclick="openImageModal('../uploads/concerns/${photo}')">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200 rounded-lg flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                    </svg>
                                </div>
                            `;
                            photosContainer.appendChild(photoDiv);
                        });
                        photosSection.classList.remove('hidden');
                    } else {
                        photosSection.classList.add('hidden');
                    }
                } catch (e) {
                    console.error('Error parsing photos:', e);
                    photosSection.classList.add('hidden');
                }
            } else {
                photosSection.classList.add('hidden');
            }
            
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        function openImageModal(imageSrc) {
            // Create image modal
            const imageModal = document.createElement('div');
            imageModal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
            imageModal.innerHTML = `
                <div class="relative max-w-4xl max-h-full">
                    <button onclick="this.parentElement.parentElement.remove()" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <img src="${imageSrc}" alt="Full size image" class="max-w-full max-h-full object-contain rounded-lg">
                </div>
            `;
            document.body.appendChild(imageModal);
        }

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('updateModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('updateModal')) {
                closeUpdateModal();
            }
        });

        document.getElementById('viewModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('viewModal')) {
                closeViewModal();
            }
        });

        document.getElementById('logoutModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('logoutModal')) {
                hideLogoutModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeUpdateModal();
                closeViewModal();
                hideLogoutModal();
            }
        });
    </script>
</body>
</html>
