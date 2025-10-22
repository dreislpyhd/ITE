<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
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

// Update last viewed applications timestamp when user visits this page
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_viewed_applications = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Silently fail if update doesn't work
    }
}

// Get notification counts for sidebar
$user_id = $_SESSION['user_id'];
$notifications = $notificationHelper->getNotificationCounts($user_id, 'resident');
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];

// Count status updates for notification badge
$status_update_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        // Get user's last viewed applications timestamp
        $stmt = $conn->prepare("SELECT last_viewed_applications FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $last_viewed = $user['last_viewed_applications'] ?? null;
        
        // Count applications with status updates that occurred after last viewed
        if ($last_viewed) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM applications 
                WHERE user_id = ? 
                AND status IN ('processing', 'approved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
                AND (processed_date > ? OR updated_at > ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $last_viewed, $last_viewed]);
        } else {
            // If never viewed before, show all status updates
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM applications 
                WHERE user_id = ? 
                AND status IN ('processing', 'approved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
            ");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $result = $stmt->fetch();
        $status_update_count = $result['update_count'] ?? 0;
    } catch (Exception $e) {
        $status_update_count = 0;
    }
}

// Mark applications as viewed
$notificationHelper->markAsViewed($user_id, 'applications');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancel_application':
                $application_id = $_POST['application_id'];
                $user_id = $_SESSION['user_id'];
                
                try {
                    // Check if application belongs to the user and is still pending
                    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$application_id, $user_id]);
                    $application = $stmt->fetch();
                    
                    if (!$application) {
                        $error = 'Application not found or you do not have permission to cancel it.';
                    } elseif ($application['status'] !== 'pending') {
                        $error = 'Only pending applications can be cancelled.';
                    } else {
                        // Cancel the application
                        $stmt = $conn->prepare("UPDATE applications SET status = 'cancelled', updated_at = datetime('now') WHERE id = ? AND user_id = ?");
                        if ($stmt->execute([$application_id, $user_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: applications.php?message=Application cancelled successfully');
                            exit();
                        } else {
                            $error = 'Failed to cancel application.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error cancelling application: ' . $e->getMessage();
                }
                break;
                
            case 'delete_application':
                $application_id = $_POST['application_id'];
                $user_id = $_SESSION['user_id'];
                
                try {
                    // Check if application belongs to the user
                    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$application_id, $user_id]);
                    $application = $stmt->fetch();
                    
                    if (!$application) {
                        $error = 'Application not found or you do not have permission to delete it.';
                    } elseif ($application['status'] === 'pending') {
                        $error = 'Please cancel pending applications first before deleting.';
                    } else {
                        // Delete the application
                        $stmt = $conn->prepare("DELETE FROM applications WHERE id = ? AND user_id = ?");
                        if ($stmt->execute([$application_id, $user_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: applications.php?message=Application deleted successfully');
                            exit();
                        } else {
                            $error = 'Failed to delete application.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting application: ' . $e->getMessage();
                }
                break;

            case 'mark_claimed':
                $application_id = $_POST['application_id'];
                $user_id = $_SESSION['user_id'];
                
                try {
                    // Verify ownership and that it's Ready for Pick-up (approved with remark)
                    $stmt = $conn->prepare("SELECT status, remarks FROM applications WHERE id = ? AND user_id = ?");
                    $stmt->execute([$application_id, $user_id]);
                    $application = $stmt->fetch();
                    
                    if (!$application) {
                        $error = 'Application not found or you do not have permission to update it.';
                    } elseif (strtolower($application['status']) !== 'approved' || stripos($application['remarks'] ?? '', 'ready for pick-up') === false) {
                        $error = 'Only applications marked as Ready for Pick-up can be claimed.';
                    } else {
                        // Set to claimed
                        $stmt = $conn->prepare("UPDATE applications SET status = 'claimed', remarks = 'Claimed by resident', processed_date = datetime('now'), updated_at = datetime('now') WHERE id = ? AND user_id = ?");
                        if ($stmt->execute([$application_id, $user_id])) {
                            header('Location: applications.php?message=Application marked as claimed');
                            exit();
                        } else {
                            $error = 'Failed to mark as claimed.';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error updating application: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get messages from URL parameters (for redirects)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get user information
$user_id = $_SESSION['user_id'];

// Get user's applications with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Get total count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) FROM applications a
        WHERE a.user_id = ?
    ");
    $count_stmt->execute([$user_id]);
    $total_applications = $count_stmt->fetchColumn();
    $total_pages = ceil($total_applications / $per_page);
    
    // Get applications
    $stmt = $conn->prepare("
        SELECT a.*, 
               CASE 
                   WHEN a.service_type = 'barangay' THEN bs.service_name
                   WHEN a.service_type = 'health' THEN hs.service_name
                   ELSE 'Unknown Service'
               END as service_name,
               CASE 
                   WHEN a.service_type = 'barangay' THEN bs.fee
                   WHEN a.service_type = 'health' THEN hs.fee
                   ELSE 0
               END as service_fee,
               CASE 
                   WHEN a.service_type = 'barangay' THEN bs.processing_time
                   WHEN a.service_type = 'health' THEN 'Immediate'
                   ELSE 'Unknown'
               END as processing_time
        FROM applications a
        LEFT JOIN barangay_services bs ON a.service_type = 'barangay' AND a.service_id = bs.id
        LEFT JOIN health_services hs ON a.service_type = 'health' AND a.service_id = hs.id
        WHERE a.user_id = ?
        ORDER BY a.application_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $per_page, $offset]);
    $applications = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $applications = [];
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Resident Dashboard</title>
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
                            <h1 class="text-xl font-bold text-white font-eb-garamond">Barangay 172 Urduja</h1>
                            <p class="text-sm text-orange-100">Zone 15 District 1 Caloocan City</p>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="h-14 w-14 rounded-full">
                            <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="h-16 w-16 rounded-full">
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php
                    $fullName = $_SESSION['full_name'] ?? 'Resident';
                    $nameParts = explode(' ', $fullName);
                    $initials = '';
                    if (count($nameParts) >= 2) {
                        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($fullName, 0, 2));
                    }
                    ?>
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                        <span class="text-barangay-orange font-bold text-sm"><?php echo $initials; ?></span>
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
                    <a href="profile.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        My Profile
                    </a>
                    <a href="services.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Available Services
                        <?php if ($notification_counts['patient_registrations'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="applications.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="flex-1">My Applications</span>
                        <?php if ($status_update_count > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="appointments.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        My Appointments
                        <?php if ($new_applications > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $new_applications; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="community-concerns.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                        </svg>
                        Community Concerns
                        <?php if ($new_concerns > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $new_concerns; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Logout Button -->
                    <div class="mt-8 pt-4 border-t border-gray-200">
                                            <button onclick="showLogoutModal()" class="flex items-center px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors w-full text-left">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8 lg:ml-0 ml-0">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">My Applications</h1>
                <p class="text-gray-600">Track the status of your barangay service applications</p>
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

            <!-- Applications Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-eb-garamond font-semibold text-gray-900">Applications (<?php echo $total_applications; ?>)</h3>
                </div>
                
                <?php if (empty($applications)): ?>
                    <div class="p-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No applications</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by applying for barangay services.</p>
                        <div class="mt-6">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-barangay-orange hover:bg-orange-600">
                                Apply for Services
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No.</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($applications as $app): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">BRG-<?php echo str_pad($app['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('Y', strtotime($app['application_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['service_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo ucfirst($app['service_type']); ?> Service</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $status = $app['status'] ?? 'pending';
                                                $statusClass = $status === 'approved' ? 'bg-green-100 text-green-800' : 
                                                    ($status === 'pending' ? 'bg-orange-100 text-orange-800' : 
                                                    ($status === 'rejected' ? 'bg-red-100 text-red-800' : 
                                                    ($status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                                    ($status === 'claimed' ? 'bg-gray-100 text-gray-800' : 'bg-gray-100 text-gray-800'))));
                                                if (strtolower($status) === 'claimed') {
                                                    $statusText = 'Claimed';
                                                } else {
                                                    $statusText = (strtolower($status) === 'approved' && stripos($app['remarks'] ?? '', 'ready for pick-up') !== false)
                                                        ? 'Ready for Pick-up' : ucfirst($status);
                                                }
                                            ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div>Applied: <?php echo date('M j, Y g:i A', strtotime($app['application_date'])); ?></div>
                                            <?php if ($app['processed_date']): ?>
                                                <div>Processed: <?php echo date('M j, Y g:i A', strtotime($app['processed_date'])); ?></div>
                                            <?php endif; ?>
                                            <?php if ($app['status'] === 'claimed'): ?>
                                                <div>Claimed: <?php echo date('M j, Y g:i A', strtotime($app['processed_date'])); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                    View
                                                </button>
                                                <?php if ($app['status'] === 'pending'): ?>
                                                    <button onclick="showCancelConfirmModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['service_name']); ?>')" 
                                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                        Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="showDeleteConfirmModal(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['service_name']); ?>')" 
                                                            class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (strtolower($app['status']) === 'approved' && stripos($app['remarks'] ?? '', 'ready for pick-up') !== false): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="mark_claimed">
                                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                        <button type="submit" class="bg-barangay-green hover:bg-green-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                            Claim
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <nav class="flex space-x-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" 
                               class="px-3 py-2 rounded-lg <?php echo $i === $page ? 'bg-barangay-orange text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div id="cancelConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-eb-garamond font-medium text-gray-900">Cancel Application</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to cancel your application for "<span id="cancelServiceName" class="font-medium text-gray-900"></span>"?
                        </p>
                        <p class="text-xs text-red-500 mt-2">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="hideCancelConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        No, Keep It
                    </button>
                    <form method="POST" id="cancelApplicationForm" class="inline">
                        <input type="hidden" name="action" value="cancel_application">
                        <input type="hidden" name="application_id" id="cancelApplicationId">
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Yes, Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-eb-garamond font-medium text-gray-900">Delete Application</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to permanently delete your application for "<span id="deleteServiceName" class="font-medium text-gray-900"></span>"?
                        </p>
                        <p class="text-xs text-red-500 mt-2">This action is permanent and cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="hideDeleteConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        No, Keep It
                    </button>
                    <form method="POST" id="deleteApplicationForm" class="inline">
                        <input type="hidden" name="action" value="delete_application">
                        <input type="hidden" name="application_id" id="deleteApplicationId">
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Yes, Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Application Modal -->
    <div id="viewApplicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Application Details</h3>
                <button onclick="hideViewApplicationModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="applicationDetails" class="space-y-6">
                <!-- Application details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function showCancelConfirmModal(applicationId, serviceName) {
            document.getElementById('cancelApplicationId').value = applicationId;
            document.getElementById('cancelServiceName').textContent = serviceName;
            document.getElementById('cancelConfirmModal').classList.remove('hidden');
        }
        
        function hideCancelConfirmModal() {
            document.getElementById('cancelConfirmModal').classList.add('hidden');
        }
        
        function showDeleteConfirmModal(applicationId, serviceName) {
            document.getElementById('deleteApplicationId').value = applicationId;
            document.getElementById('deleteServiceName').textContent = serviceName;
            document.getElementById('deleteConfirmModal').classList.remove('hidden');
        }
        
        function hideDeleteConfirmModal() {
            document.getElementById('deleteConfirmModal').classList.add('hidden');
        }
        
        function viewApplication(application) {
            const modal = document.getElementById('viewApplicationModal');
            const detailsContainer = document.getElementById('applicationDetails');
            
            // Format the application details
            const details = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Application Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><span class="font-medium">Service:</span> ${application.service_name}</p>
                            <p><span class="font-medium">Service Type:</span> ${application.service_type.charAt(0).toUpperCase() + application.service_type.slice(1)} Service</p>
                            <p><span class="font-medium">Purpose:</span> ${application.purpose || 'Not specified'}</p>
                            <p><span class="font-medium">Status:</span> <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusColor(application.status)}">${application.status.charAt(0).toUpperCase() + application.status.slice(1)}</span></p>
                            <p><span class="font-medium">Applied:</span> ${new Date(application.application_date).toLocaleString()}</p>
                            ${application.processed_date ? `<p><span class="font-medium">Processed:</span> ${new Date(application.processed_date).toLocaleString()}</p>` : ''}
                            ${application.status === 'completed' ? `<p><span class="font-medium">Completed:</span> ${new Date(application.processed_date).toLocaleString()}</p>` : ''}
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Reference Number</h4>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p class="text-xl md:text-2xl font-bold text-green-700">
                                Reference No.: BRG-${String(application.id).padStart(6,'0')} (${new Date(application.application_date).getFullYear()})
                            </p>
                        </div>
                    </div>
                </div>
                
                ${application.requirements_files ? 
                    `<div>
                        <h4 class="font-semibold text-gray-900 mb-3">Uploaded Documents</h4>
                        <div class="space-y-2">
                            ${JSON.parse(application.requirements_files).map(file => 
                                `<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">${file}</span>
                                    <a href="../uploads/applications/${file}" target="_blank" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium transition duration-300">
                                        View
                                    </a>
                                </div>`
                            ).join('')}
                        </div>
                    </div>` : 
                    '<div><h4 class="font-semibold text-gray-900 mb-3">Uploaded Documents</h4><p class="text-gray-500">No documents uploaded</p></div>'
                }
                
                ${application.remarks ? 
                    `<div>
                        <h4 class="font-semibold text-gray-900 mb-3">Remarks</h4>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg">${application.remarks}</p>
                    </div>` : ''
                }
            `;
            
            detailsContainer.innerHTML = details;
            modal.classList.remove('hidden');
        }
        
        function hideViewApplicationModal() {
            document.getElementById('viewApplicationModal').classList.add('hidden');
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'approved': return 'bg-green-100 text-green-800';
                case 'pending': return 'bg-orange-100 text-orange-800';
                case 'rejected': return 'bg-red-100 text-red-800';
                case 'processing': return 'bg-blue-100 text-blue-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const cancelModal = document.getElementById('cancelConfirmModal');
            const deleteModal = document.getElementById('deleteConfirmModal');
            const viewModal = document.getElementById('viewApplicationModal');
            
            if (event.target === cancelModal) {
                hideCancelConfirmModal();
            }
            if (event.target === deleteModal) {
                hideDeleteConfirmModal();
            }
            if (event.target === viewModal) {
                hideViewApplicationModal();
            }
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideCancelConfirmModal();
                hideDeleteConfirmModal();
                hideViewApplicationModal();
                hideLogoutModal();
            }
        });

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // Close logout modal when clicking outside
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutModal();
            }
        });
    </script>
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-eb-garamond font-medium text-gray-900 mb-2">Confirm Logout</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Are you sure you want to logout? You will need to login again to access your account.
                    </p>
                </div>
                <div class="flex justify-center space-x-3">
                    <button onclick="hideLogoutModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-300">
                        Cancel
                    </button>
                    <a href="../auth/logout.php" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-300">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.createElement('div');
            
            // Create overlay for mobile
            sidebarOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-30 hidden';
            sidebarOverlay.id = 'sidebarOverlay';
            document.body.appendChild(sidebarOverlay);
            
            // Toggle sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('-translate-x-full');
                sidebarOverlay.classList.toggle('hidden');
            }
            
            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            });
            
            // Toggle sidebar when button is clicked
            sidebarToggle.addEventListener('click', toggleSidebar);
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth < 1024) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.add('-translate-x-full');
                        sidebarOverlay.classList.add('hidden');
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
