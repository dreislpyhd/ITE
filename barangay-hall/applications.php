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
// Email notifications
try {
    require_once '../includes/EmailService.php';
    $emailService = new EmailService();
} catch (Exception $e) {
    $emailService = null;
}
$db = new Database();
$conn = $db->getConnection();
$notificationHelper = new NotificationHelper();

// Include notification badge helper
require_once 'includes/notification_badge.php';

// Mark applications as viewed when staff visits this page
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_viewed_applications = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error updating last_viewed_applications: " . $e->getMessage());
    }
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                
                $application_id = $_POST['application_id'];
                $new_status = $_POST['status'];
                $remarks = trim($_POST['remarks'] ?? '');
                
                try {
                    $stmt = $conn->prepare("UPDATE applications SET status = ?, remarks = ?, updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$new_status, $remarks, $application_id])) {
                        // Send Ready for Pick-up email if status is approved with matching remark
                        if ($emailService && strtolower($new_status) === 'approved' && stripos($remarks, 'ready for pick-up') !== false) {
                            $infoStmt = $conn->prepare("SELECT a.id, a.user_id, a.application_date, a.created_at, a.service_type, a.remarks,
                                u.full_name, u.email,
                                CASE WHEN a.service_type = 'barangay' THEN bs.service_name
                                     WHEN a.service_type = 'health' THEN hs.service_name
                                     ELSE 'Unknown Service' END as service_name
                                FROM applications a
                                LEFT JOIN users u ON a.user_id = u.id
                                LEFT JOIN barangay_services bs ON a.service_type = 'barangay' AND a.service_id = bs.id
                                LEFT JOIN health_services hs ON a.service_type = 'health' AND a.service_id = hs.id
                                WHERE a.id = ?");
                            $infoStmt->execute([$application_id]);
                            if ($row = $infoStmt->fetch()) {
                                $year = !empty($row['application_date']) ? date('Y', strtotime($row['application_date']))
                                       : (!empty($row['created_at']) ? date('Y', strtotime($row['created_at'])) : date('Y'));
                                $referenceNo = 'BRG-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT) . ' (' . $year . ')';
                                $emailService->sendReadyForPickup($row['email'], $row['full_name'], $referenceNo, $row['service_name']);
                            }
                        }
                        // Redirect to prevent form resubmission
                        header('Location: applications.php?message=Application status updated successfully');
                        exit();
                    } else {
                        $error = 'Failed to update application status';
                    }
                } catch (Exception $e) {
                    $error = 'Error updating application: ' . $e->getMessage();
                }
                break;
                
            case 'start_processing':
                $application_id = $_POST['application_id'];
                $remarks = trim($_POST['remarks'] ?? '');
                
                try {
                    $stmt = $conn->prepare("UPDATE applications SET status = 'processing', remarks = ?, updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$remarks, $application_id])) {
                        // Redirect to prevent form resubmission
                        header('Location: applications.php?message=Application is now being processed');
                        exit();
                    } else {
                        $error = 'Failed to start processing application';
                    }
                } catch (Exception $e) {
                    $error = 'Error starting processing: ' . $e->getMessage();
                }
                break;
                
            case 'generate_certificate':
                $application_id = $_POST['application_id'];
                
                try {
                    // Update status to processing
                    $stmt = $conn->prepare("UPDATE applications SET status = 'processing', updated_at = datetime('now') WHERE id = ?");
                    if ($stmt->execute([$application_id])) {
                        // Return success response for AJAX
                        echo json_encode(['success' => true, 'message' => 'Certificate generated successfully']);
                        exit();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to generate certificate']);
                        exit();
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error generating certificate: ' . $e->getMessage()]);
                    exit();
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

// Get all applications with user and service details
try {
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            u.full_name,
            u.email,
            u.phone,
            u.house_no,
            u.street,
            u.address,
            u.birthday,
            u.year_started_living,
            u.created_at as user_created_at,
            CASE 
                WHEN a.service_type = 'barangay' THEN bs.service_name
                WHEN a.service_type = 'health' THEN hs.service_name
                ELSE 'Unknown Service'
            END as service_name
        FROM applications a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN barangay_services bs ON a.service_type = 'barangay' AND a.service_id = bs.id
        LEFT JOIN health_services hs ON a.service_type = 'health' AND a.service_id = hs.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Error loading applications: ' . $e->getMessage();
    $applications = [];
}

// Mark applications as viewed
$user_id = $_SESSION['user_id'];
$notificationHelper->markAsViewed($user_id, 'applications');

// Get notification counts for sidebar
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];

// Get status counts for statistics
try {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
    $stmt->execute();
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
} catch (Exception $e) {
    $status_counts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications Management - Barangay Hall</title>
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
                    <a href="applications.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Applications
                    </a>
                    <a href="community-concerns.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                        </svg>
                        Community Concerns
                        <?php if ($notification_counts['concerns'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
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
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Applications Management</h1>
                <p class="text-gray-600 mt-2">Review and manage all resident document requests</p>
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

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Applications</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo array_sum($status_counts); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $status_counts['pending'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $status_counts['approved'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Rejected</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $status_counts['rejected'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">All Applications</h3>
                </div>
                
                <?php if (empty($applications)): ?>
                    <div class="p-6 text-center text-gray-500">
                        No applications found.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                                                         <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference No.</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant</th>
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
                                             <div class="text-xs text-gray-500"><?php echo date('Y', strtotime($app['created_at'])); ?></div>
                                         </td>
                                         <td class="px-6 py-4">
                                             <div>
                                                 <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['full_name']); ?></div>
                                                 <div class="text-sm text-gray-500"><?php echo htmlspecialchars($app['email']); ?></div>
                                                 <div class="text-xs text-gray-400">
                                                     <?php 
                                                     if ($app['house_no'] && $app['street']) {
                                                         echo htmlspecialchars($app['house_no'] . ' ' . $app['street'] . ', Zone 15, Brgy. 172, Caloocan City');
                                                     } elseif ($app['address']) {
                                                         echo htmlspecialchars($app['address']);
                                                     } else {
                                                         echo 'Address not provided';
                                                     }
                                                     ?>
                                                 </div>
                                             </div>
                                         </td>
                                         <td class="px-6 py-4">
                                             <div class="text-sm text-gray-900"><?php echo htmlspecialchars($app['service_name'] ?? 'N/A'); ?></div>
                                         </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            $status = $app['status'] ?? 'pending';
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'processing' => 'bg-blue-100 text-blue-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'claimed' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $status_color = $status_colors[$status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $status_color; ?>">
                                                <?php 
                                                if (strtolower($status) === 'claimed') {
                                                    echo 'Claimed';
                                                } else {
                                                    echo ucfirst($status);
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('g:i A', strtotime($app['created_at'])); ?>
                                            </div>
                                            <?php if ($app['processed_date']): ?>
                                                <div class="text-xs text-gray-500">
                                                    Completed: <?php echo date('M d, Y g:i A', strtotime($app['processed_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="processApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                                                    class="bg-barangay-green hover:bg-green-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                Process
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Process Application Modal -->
    <div id="viewApplicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Process Application</h3>
                <button onclick="hideViewApplicationModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="applicationDetails" class="space-y-6">
                <!-- Application details will be loaded here -->
            </div>
            
            <!-- Certificate Actions -->
            <div id="certificateActions" class="mt-8 pt-6 border-t border-gray-200">
                <button id="generateCertificateBtn" onclick="generateCertificate()" 
                        class="w-full bg-barangay-green hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 flex items-center justify-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Generate Certificate
                </button>
            </div>
        </div>
    </div>

    <!-- Certificate Modal -->
    <div id="certificateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-end items-center mb-6">
                <button onclick="hideCertificateModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="certificateContent" class="bg-white border-2 border-gray-300 p-8">
                <!-- Certificate content will be loaded here -->
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="hideCertificateModal()" 
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                    Close
                </button>
                <button onclick="printCertificate()" 
                        class="bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                    Print Certificate
                </button>
                <button onclick="downloadCertificate()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                    Download Certificate
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-8 border w-96 shadow-lg rounded-2xl bg-white text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-barangay-green mx-auto mb-4"></div>
            <h3 class="text-lg font-medium text-gray-900 mb-2 font-eb-garamond">Processing Certificate</h3>
            <p class="text-sm text-gray-500">Please wait while we process your certificate...</p>
        </div>
    </div>

    <!-- Ready for Pick-up Success Modal -->
    <div id="readyForPickupModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 font-eb-garamond">Success!</h3>
                <p class="text-gray-600 mb-6">Ready for Pick-up notification has been sent.</p>
                <button onclick="hideReadyForPickupModal()" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentApplication = null;
        
        function processApplication(application) {
            currentApplication = application;
            const modal = document.getElementById('viewApplicationModal');
            const detailsContainer = document.getElementById('applicationDetails');
            
            // Generate Indigency Number if it's an indigency service
            const serviceName = (application.service_name || '').toLowerCase();
            const isIndigency = serviceName.includes('indigency') || serviceName.includes('indigent');
            let indigencyNumber = '';
            
            if (isIndigency) {
                const today = new Date();
                const monthStr = String(today.getMonth() + 1).padStart(2, '0');
                const dayStr = String(today.getDate()).padStart(2, '0');
                const yearStr = String(today.getFullYear());
                const incrementNum = String(application.id).padStart(3, '0');
                indigencyNumber = `${monthStr}${dayStr}${yearStr}-IN-C180${incrementNum}`;
            }
            
            // Format the application details
            const details = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3 font-eb-garamond">Applicant Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><span class="font-medium">Name:</span> ${application.full_name}</p>
                            <p><span class="font-medium">Email:</span> ${application.email}</p>
                            <p><span class="font-medium">Phone:</span> ${application.phone || 'Not provided'}</p>
                            <p><span class="font-medium">Address:</span> ${application.house_no && application.street ? 
                                application.house_no + ' ' + application.street + ', Zone 15, Brgy. 172, Caloocan City' : 
                                (application.address || 'Not provided')}</p>
                            ${isIndigency ? `<p><span class="font-medium">Indigency No.:</span> <span class="font-bold text-green-600">${indigencyNumber}</span></p>` : ''}
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3 font-eb-garamond">Application Information</h4>
                        <div class="space-y-2 text-sm">
                            <p><span class="font-medium">Service:</span> ${application.service_name}</p>
                            <p><span class="font-medium">Purpose:</span> ${application.purpose || 'Not specified'}</p>
                            <p><span class="font-medium">Status:</span> <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">${getStatusDisplay(application.status, application.remarks)}</span></p>
                            <p><span class="font-medium">Submitted:</span> ${new Date(application.created_at).toLocaleString()}</p>
                            ${application.processed_date ? `<p><span class="font-medium">Completed:</span> ${new Date(application.processed_date).toLocaleString()}</p>` : ''}
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-3 font-eb-garamond">Uploaded Documents</h4>
                    ${application.requirements_files ? 
                        `<div class="space-y-2">
                            ${JSON.parse(application.requirements_files).map(file => 
                                `<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-700">${file}</span>
                                    <a href="../uploads/applications/${file}" target="_blank" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium transition duration-300">
                                        View
                                    </a>
                                </div>`
                            ).join('')}
                        </div>` : 
                        '<p class="text-gray-500">No documents uploaded</p>'
                    }
                </div>
                
                ${application.remarks ? 
                    `<div>
                        <h4 class="font-semibold text-gray-900 mb-3 font-eb-garamond">Current Remarks</h4>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg">${application.remarks}</p>
                    </div>` : ''
                }
            `;
            
            detailsContainer.innerHTML = details;
            modal.classList.remove('hidden');
            // Render the certificate action buttons based on status
            renderCertificateActions(currentApplication);
        }
        
        function hideViewApplicationModal() {
            document.getElementById('viewApplicationModal').classList.add('hidden');
        }
        
        function getStatusDisplay(status, remarks) {
            if ((status || '').toLowerCase() === 'approved' && /ready for pick-up/i.test(remarks || '')) {
                return 'Ready for Pick-up';
            }
            return (status || '').charAt(0).toUpperCase() + (status || '').slice(1);
        }

        function renderCertificateActions(application) {
            const actions = document.getElementById('certificateActions');
            if (!actions) {
                console.error('certificateActions element not found');
                return;
            }
            
            const status = (application?.status || '').toLowerCase();
            console.log('Application status:', status, 'Application:', application);
            
            if (status === 'claimed') {
                // For claimed applications, show only view and download options (no generate button)
                actions.innerHTML = `
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button onclick="viewCertificate()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                                View Certificate
                            </button>
                            <button onclick="downloadCertificate()" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                                Download Certificate
                            </button>
                        </div>
                        <span class="text-gray-600 font-medium px-4 py-2">
                            Certificate Claimed
                        </span>
                    </div>
                `;
            } else if (status === 'processing' || status === 'approved') {
                // For processing/approved applications, show view, download, and ready for pick-up
                const isReadyForPickup = status === 'approved' && /ready for pick-up/i.test(application?.remarks || '');
                
                actions.innerHTML = `
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button onclick="viewCertificate()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                                View
                            </button>
                            <button onclick="downloadCertificate()" 
                                    class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                                Download
                            </button>
                        </div>
                        ${!isReadyForPickup ? `
                        <button onclick="updateForPickup()" 
                                class="bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                            Ready for Pick-up
                        </button>
                        ` : `
                        <span class="text-green-600 font-medium px-4 py-2">
                            Ready for Pick-up
                        </span>
                        `}
                    </div>
                `;
            } else {
                // For pending applications, show generate certificate button
                actions.innerHTML = `
                    <button id="generateCertificateBtn" onclick="generateCertificate()" 
                            class="w-full bg-barangay-green hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Generate Certificate
                    </button>
                `;
            }
        }
        
        function generateCertificate() {
            if (!currentApplication) return;
            
            // Show loading modal
            document.getElementById('loadingModal').classList.remove('hidden');
            
            // Send AJAX request to update status
            const formData = new FormData();
            formData.append('action', 'generate_certificate');
            formData.append('application_id', currentApplication.id);
            
            fetch('applications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide loading modal after 2 seconds
                    setTimeout(() => {
                        document.getElementById('loadingModal').classList.add('hidden');
                        // Mark as generated and update UI
                        currentApplication.status = 'processing';
                        renderCertificateActions(currentApplication);
                        showCertificate();
                    }, 2000);
                } else {
                    alert('Error: ' + data.message);
                    document.getElementById('loadingModal').classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error generating certificate');
                document.getElementById('loadingModal').classList.add('hidden');
            });
        }
        

        
        function showCertificate() {
            if (!currentApplication) {
                alert('No application selected');
                return;
            }
            
            console.log('Showing certificate for:', currentApplication);
            
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) {
                alert('Certificate content element not found');
                return;
            }
            
            // Generate the appropriate certificate content based on service type
            generateCertificateContent();
            
            console.log('Certificate content set, showing modal');
            document.getElementById('certificateModal').classList.remove('hidden');
        }
        
        function hideCertificateModal() {
            document.getElementById('certificateModal').classList.add('hidden');
        }
        
        function showReadyForPickupModal() {
            document.getElementById('readyForPickupModal').classList.remove('hidden');
        }
        
        function hideReadyForPickupModal() {
            document.getElementById('readyForPickupModal').classList.add('hidden');
        }
        
        function viewCertificate() {
            console.log('View certificate clicked');
            if (!currentApplication) {
                alert('No application selected');
                return;
            }
            
            // Always generate fresh certificate content then show it
            generateCertificateContent();
            showCertificate();
        }
        
        function downloadCertificate() {
            console.log('Download certificate clicked');
            if (!currentApplication) {
                alert('No application selected');
                return;
            }
            
            // Always generate fresh certificate content then print it
            generateCertificateContent();
            printCertificate();
        }
        
        function generateCertificateContent() {
            console.log('Generating certificate content for:', currentApplication);
            
            if (!currentApplication) return;
            
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            // Determine certificate type based on service name
            const serviceName = (currentApplication.service_name || '').toLowerCase();
            
            if (serviceName.includes('indigency') || serviceName.includes('indigent')) {
                generateIndigencyCertificate();
            } else if (serviceName.includes('clearance') || serviceName.includes('barangay clearance')) {
                generateClearanceCertificate();
            } else if (serviceName.includes('permit') || serviceName.includes('barangay permit')) {
                generateBarangayPermitCertificate();
            } else if (serviceName.includes('residency') || serviceName.includes('certificate of residency')) {
                generateResidencyCertificate();
            } else {
                // Default to general barangay certification
                generateGeneralCertificate();
            }
        }

        function generateIndigencyCertificate() {
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            const today = new Date();
            const day = today.getDate();
            const month = today.getMonth();
            const year = today.getFullYear();
            
            const daySuffix = getDaySuffix(day);
            const monthName = getMonthName(month);
            
            // Generate Indigency Number: mmddyyyy-IN-C180 + incrementing number
            const monthStr = String(month + 1).padStart(2, '0');
            const dayStr = String(day).padStart(2, '0');
            const yearStr = String(year);
            
            // Generate a simple incrementing number based on application ID
            const incrementNum = String(currentApplication.id).padStart(3, '0');
            const indigencyNo = `${monthStr}${dayStr}${yearStr}-IN-C180${incrementNum}`;
            
            // Calculate validity period (6 months from today)
            const validityDate = new Date(today);
            validityDate.setMonth(validityDate.getMonth() + 6);
            const validityMonth = validityDate.getMonth() + 1;
            const validityDay = validityDate.getDate();
            const validityYear = validityDate.getFullYear();
            const validityPeriod = `${validityMonth}/${validityDay}/${validityYear}`;
            
                         const certificate = `
                 <div class="text-center mb-8" style="font-family: 'Times New Roman', serif;">
                     <div class="flex justify-center items-center mb-6" style="display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                         <div class="flex items-center space-x-4" style="display: flex; align-items: center; gap: 16px;">
                             <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="w-16 h-16" style="width: 64px; height: 64px;">
                             <div class="text-center">
                                 <h1 class="text-lg font-bold" style="font-size: 16px; margin-bottom: 4px;">Republic of the Philippines</h1>
                                 <h2 class="text-xl font-bold text-red-600" style="font-size: 20px; color: #dc2626; margin-bottom: 4px;">BARANGAY 172 URDUJA</h2>
                                 <p class="text-sm" style="font-size: 14px;">Zone 15, District I, Caloocan City</p>
                             </div>
                             <div class="flex items-center space-x-2" style="display: flex; align-items: center; gap: 8px;">
                                 <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                                 <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                             </div>
                         </div>
                     </div>
                     <hr class="border-gray-400 mb-8" style="border: 1px solid #9ca3af;">
                     
                     <div class="text-center mb-8">
                         <h3 class="text-lg font-bold" style="font-size: 18px; margin-bottom: 8px;">OFFICE OF THE PUNONG BARANGAY</h3>
                        <h4 class="text-2xl font-bold underline" style="font-size: 24px; text-decoration: underline; margin-bottom: 16px;">CERTIFICATION OF INDIGENCY</h4>
                    </div>
                    
                    <div class="text-left mb-6" style="text-align: left; margin-bottom: 24px;">
                        <p class="mb-2" style="margin-bottom: 8px;"><strong>Indigency No.:</strong> ${indigencyNo}</p>
                        <p class="mb-4" style="margin-bottom: 16px;"><strong>Validity Period:</strong> ${validityPeriod}</p>
                     </div>
                     
                                           <div class="text-justify leading-relaxed mb-8" style="text-align: justify; line-height: 1.6; margin-bottom: 32px;">
                        <p class="mb-4" style="margin-bottom: 16px; font-weight: bold; text-align: center;">TO ALL CONCERNED:</p>
                        
                          <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certifies that <strong>${currentApplication.full_name}</strong>, a resident of 
                            <strong>${currentApplication.house_no || ''} ${currentApplication.street || ''}, Zone 15, District I, Camarin Road, Caloocan City</strong>, 
                            is an indigent of Barangay 172, Zone 15, District I, Camarin Road, Caloocan City.
                          </p>
                          
                          <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certification is issued upon the request of the above-named person to apply for 
                            <strong>${currentApplication.purpose || 'FINANCIAL ASSISTANCE'}</strong>.
                        </p>
                        
                        <p class="text-left" style="text-align: left;">
                            Issued this ${day}${daySuffix} of ${monthName} ${year} at Magat Salamat St. Urduja Village, Barangay 172 Zone 15 District I Caloocan City.
                        </p>
                    </div>
                    
                    <div class="mt-12" style="margin-top: 48px;">
                        <div class="border-t-2 border-gray-400 pt-2 w-48" style="border-top: 2px solid #9ca3af; padding-top: 8px; width: 192px; margin-left: 0;">
                            <p class="text-sm text-left" style="font-size: 14px; text-align: left;">Signature of the Bearer</p>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-xs text-left text-gray-600" style="margin-top: 32px; font-size: 12px; text-align: left; color: #4b5563;">
                        <p style="margin-bottom: 4px;"><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this certification.</p>
                        <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
                    </div>
                    
                    <div class="mt-8 flex justify-center space-x-8 text-xs" style="margin-top: 32px; display: flex; justify-content: center; gap: 32px; font-size: 12px;">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>8442-15-31 / 8442-40-61</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>barangay1722023urduja@gmail.com</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>Magat Salamat Street, Urduja Village, Caloocan City</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-orange-500 text-white py-2 px-4 rounded" style="margin-top: 16px; background-color: #f97316; color: white; padding: 8px 16px; border-radius: 4px;">
                        <p class="text-sm font-bold" style="font-size: 14px; font-weight: bold;">Malapit sa TAO, Sanay sa TAO</p>
                    </div>
                </div>
            `;
            
            certificateContent.innerHTML = certificate;
            console.log('Indigency certificate content generated successfully');
        }

        function generateClearanceCertificate() {
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            const today = new Date();
            const day = today.getDate();
            const month = today.getMonth();
            const year = today.getFullYear();
            
            const daySuffix = getDaySuffix(day);
            const monthName = getMonthName(month);
            
            const certificate = `
                <div class="text-center mb-8" style="font-family: 'Times New Roman', serif;">
                    <div class="flex justify-center items-center mb-6" style="display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                        <div class="flex items-center space-x-4" style="display: flex; align-items: center; gap: 16px;">
                            <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="w-16 h-16" style="width: 64px; height: 64px;">
                            <div class="text-center">
                                <h1 class="text-lg font-bold" style="font-size: 16px; margin-bottom: 4px;">Republic of the Philippines</h1>
                                <h2 class="text-xl font-bold text-red-600" style="font-size: 20px; color: #dc2626; margin-bottom: 4px;">BARANGAY 172 URDUJA</h2>
                                <p class="text-sm" style="font-size: 14px;">Zone 15, District I, Caloocan City</p>
                            </div>
                            <div class="flex items-center space-x-2" style="display: flex; align-items: center; gap: 8px;">
                                <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                                <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                            </div>
                        </div>
                    </div>
                    <hr class="border-gray-400 mb-8" style="border: 1px solid #9ca3af;">
                    
                    <div class="text-center mb-8">
                        <h3 class="text-lg font-bold" style="font-size: 18px; margin-bottom: 8px;">OFFICE OF THE PUNONG BARANGAY</h3>
                        <h4 class="text-2xl font-bold underline" style="font-size: 24px; text-decoration: underline; margin-bottom: 16px;">BARANGAY CLEARANCE</h4>
                    </div>
                    
                    <div class="text-justify leading-relaxed mb-8" style="text-align: justify; line-height: 1.6; margin-bottom: 32px;">
                        <p class="mb-4" style="margin-bottom: 16px; text-align: left;">
                            <strong>${monthName} ${day}, ${year}</strong>
                          </p>
                          
                          <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certifies that <strong>${currentApplication.full_name}</strong>, 
                            <strong>${calculateAge(currentApplication.birthday || '1990-01-01')}</strong> years old, 
                            is a bona fide resident of this barangay with a postal address at 
                            <strong>${currentApplication.house_no || ''} ${currentApplication.street || ''}, Zone 15, Brgy. 172, Caloocan City</strong>, 
                            and has resided in this barangay for <strong>${calculateYearsFromYearStarted(currentApplication.year_started_living)}</strong> years.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            Upon verification of records, the said individual has no derogatory record and is known to have a good moral standing in the community.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certification is issued upon the request of the above-named person as 
                            <strong>${currentApplication.purpose || 'Barangay Clearance'}</strong>.
                          </p>
                          
                          <p class="text-left" style="text-align: left;">
                              Issued this ${day}${daySuffix} of ${monthName} ${year} at Barangay 172 Urduja, Caloocan City.
                          </p>
                      </div>
                     
                     <div class="mt-12" style="margin-top: 48px;">
                         <div class="border-t-2 border-gray-400 pt-2 w-48" style="border-top: 2px solid #9ca3af; padding-top: 8px; width: 192px; margin-left: 0;">
                             <p class="text-sm text-left" style="font-size: 14px; text-align: left;">Signature of the Bearer</p>
                         </div>
                     </div>
                     
                     <div class="mt-8 text-xs text-left text-gray-600" style="margin-top: 32px; font-size: 12px; text-align: left; color: #4b5563;">
                         <p style="margin-bottom: 4px;"><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this certification.</p>
                         <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
                     </div>
                     
                     <div class="mt-8 flex justify-center space-x-8 text-xs" style="margin-top: 32px; display: flex; justify-content: center; gap: 32px; font-size: 12px;">
                         <div class="flex items-center">
                             <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                             <span>8442-15-31 / 8442-40-61</span>
                         </div>
                         <div class="flex items-center">
                             <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                             <span>barangay1722023urduja@gmail.com</span>
                         </div>
                         <div class="flex items-center">
                             <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                             <span>Magat Salamat Street, Urduja Village, Caloocan City</span>
                         </div>
                     </div>
                     
                     <div class="mt-4 bg-orange-500 text-white py-2 px-4 rounded" style="margin-top: 16px; background-color: #f97316; color: white; padding: 8px 16px; border-radius: 4px;">
                         <p class="text-sm font-bold" style="font-size: 14px; font-weight: bold;">Malapit sa TAO, Sanay sa TAO</p>
                     </div>
                 </div>
             `;
            
            certificateContent.innerHTML = certificate;
            console.log('Clearance certificate content generated successfully');
        }

        function generateGeneralCertificate() {
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            const today = new Date();
            const day = today.getDate();
            const month = today.getMonth();
            const year = today.getFullYear();
            
            const daySuffix = getDaySuffix(day);
            const monthName = getMonthName(month);
            
            const certificate = `
                <div class="text-center mb-8" style="font-family: 'Times New Roman', serif;">
                    <div class="flex justify-center items-center mb-6" style="display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                        <div class="flex items-center space-x-4" style="display: flex; align-items: center; gap: 16px;">
                            <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="w-16 h-16" style="width: 64px; height: 64px;">
                            <div class="text-center">
                                <h1 class="text-lg font-bold" style="font-size: 16px; margin-bottom: 4px;">Republic of the Philippines</h1>
                                <h2 class="text-xl font-bold text-red-600" style="font-size: 20px; color: #dc2626; margin-bottom: 4px;">BARANGAY 172 URDUJA</h2>
                                <p class="text-sm" style="font-size: 14px;">Zone 15, District I, Caloocan City</p>
                            </div>
                            <div class="flex items-center space-x-2" style="display: flex; align-items: center; gap: 8px;">
                                <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                                <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                            </div>
                        </div>
                    </div>
                    <hr class="border-gray-400 mb-8" style="border: 1px solid #9ca3af;">
                    
                    <div class="text-center mb-8">
                        <h3 class="text-lg font-bold" style="font-size: 18px; margin-bottom: 8px;">OFFICE OF THE PUNONG BARANGAY</h3>
                        <h4 class="text-2xl font-bold underline" style="font-size: 24px; text-decoration: underline; margin-bottom: 16px;">BARANGAY CERTIFICATION</h4>
                    </div>
                    
                    <div class="text-justify leading-relaxed mb-8" style="text-align: justify; line-height: 1.6; margin-bottom: 32px;">
                        <p class="mb-4" style="margin-bottom: 16px; text-align: left;">
                            <strong>${monthName} ${day}, ${year}</strong>
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certifies that <strong>${currentApplication.full_name}</strong>, 
                            <strong>${calculateAge(currentApplication.birthday || '1990-01-01')}</strong> years old, 
                            is a bona fide resident of this barangay with a postal address at 
                            <strong>${currentApplication.house_no || ''} ${currentApplication.street || ''}, Zone 15, Brgy. 172, Caloocan City</strong>, 
                            and has resided in this barangay for <strong>${calculateYearsFromYearStarted(currentApplication.year_started_living)}</strong> years.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            Upon verification of records, the said individual has no derogatory record and is known to have a good moral standing in the community.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certification is issued upon the request of the above-named person as 
                            <strong>${currentApplication.purpose || 'Proof of Residency'}</strong>.
                        </p>
                        
                        <p class="text-left" style="text-align: left;">
                            Issued this ${day}${daySuffix} of ${monthName} ${year} at Barangay 172 Urduja, Caloocan City.
                        </p>
                    </div>
                    
                    <div class="mt-12" style="margin-top: 48px;">
                        <div class="border-t-2 border-gray-400 pt-2 w-48" style="border-top: 2px solid #9ca3af; padding-top: 8px; width: 192px; margin-left: 0;">
                            <p class="text-sm text-left" style="font-size: 14px; text-align: left;">Signature of the Bearer</p>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-xs text-left text-gray-600" style="margin-top: 32px; font-size: 12px; text-align: left; color: #4b5563;">
                        <p style="margin-bottom: 4px;"><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this certification.</p>
                        <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
                    </div>
                    
                    <div class="mt-8 flex justify-center space-x-8 text-xs" style="margin-top: 32px; display: flex; justify-content: center; gap: 32px; font-size: 12px;">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>8442-15-31 / 8442-40-61</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>barangay1722023urduja@gmail.com</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>Magat Salamat Street, Urduja Village, Caloocan City</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-orange-500 text-white py-2 px-4 rounded" style="margin-top: 16px; background-color: #f97316; color: white; padding: 8px 16px; border-radius: 4px;">
                        <p class="text-sm font-bold" style="font-size: 14px; font-weight: bold;">Malapit sa TAO, Sanay sa TAO</p>
                    </div>
                </div>
            `;
            
            certificateContent.innerHTML = certificate;
            console.log('General certificate content generated successfully');
        }

        function generateBarangayPermitCertificate() {
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            const today = new Date();
            const day = today.getDate();
            const month = today.getMonth();
            const year = today.getFullYear();
            
            const daySuffix = getDaySuffix(day);
            const monthName = getMonthName(month);
            
            // Parse purpose to extract event details
            const purpose = currentApplication.purpose || '';
            let eventName = '';
            let eventPurpose = '';
            let eventDateTime = '';
            let eventVenue = '';
            
            // Extract details from purpose string
            if (purpose.includes('|')) {
                const parts = purpose.split('|');
                if (parts.length >= 3) {
                    eventPurpose = parts[0].trim();
                    if (parts[1].includes('Date/Time:')) {
                        eventDateTime = parts[1].replace('Date/Time:', '').trim();
                    }
                    if (parts[2].includes('Venue:')) {
                        eventVenue = parts[2].replace('Venue:', '').trim();
                    }
                }
            } else {
                eventPurpose = purpose;
            }
            
            // Extract event name from purpose (before the first dash)
            if (eventPurpose.includes(' - ')) {
                const purposeParts = eventPurpose.split(' - ');
                eventName = purposeParts[1] || purposeParts[0];
                eventPurpose = purposeParts[0];
            } else {
                eventName = eventPurpose;
            }
            
            const certificate = `
                <div class="text-center mb-8" style="font-family: 'Times New Roman', serif;">
                    <div class="flex justify-center items-center mb-6" style="display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                        <div class="flex items-center space-x-4" style="display: flex; align-items: center; gap: 16px;">
                            <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="w-16 h-16" style="width: 64px; height: 64px;">
                            <div class="text-center">
                                <h1 class="text-lg font-bold" style="font-size: 16px; margin-bottom: 4px;">Republic of the Philippines</h1>
                                <h2 class="text-xl font-bold text-red-600" style="font-size: 20px; color: #dc2626; margin-bottom: 4px;">BARANGAY 172 URDUJA</h2>
                                <p class="text-sm" style="font-size: 14px;">Zone 15, District I, Caloocan City</p>
                            </div>
                            <div class="flex items-center space-x-2" style="display: flex; align-items: center; gap: 8px;">
                                <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                                <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                            </div>
                        </div>
                    </div>
                    <hr class="border-gray-400 mb-8" style="border: 1px solid #9ca3af;">
                    
                    <div class="text-center mb-8">
                        <h3 class="text-lg font-bold" style="font-size: 18px; margin-bottom: 8px;">OFFICE OF THE PUNONG BARANGAY</h3>
                        <h4 class="text-2xl font-bold underline" style="font-size: 24px; text-decoration: underline; margin-bottom: 16px;">BARANGAY PERMIT</h4>
                    </div>
                    
                    <div class="text-left mb-6" style="text-align: left; margin-bottom: 24px;">
                        <div class="flex justify-between items-center mb-4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <span><strong>Permit No.:</strong> ________________</span>
                            <span><strong>Date Issued:</strong> ________________</span>
                        </div>
                    </div>
                    
                    <div class="text-justify leading-relaxed mb-8" style="text-align: justify; line-height: 1.6; margin-bottom: 32px;">
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This is to certify that <strong>${currentApplication.full_name}</strong>, of legal age, with address at 
                            <strong>${currentApplication.house_no || ''} ${currentApplication.street || ''}, Zone 15, Brgy. 172, Caloocan City</strong>, 
                            has been granted permission by the Barangay Government of Barangay 172, Urduja, Caloocan City to conduct the following activity/event:
                        </p>
                        
                        <div class="ml-8 mb-4" style="margin-left: 32px; margin-bottom: 16px;">
                            <p class="mb-2" style="margin-bottom: 8px;"><strong>Name/Description of Event:</strong> ${eventName || '____________________________'}</p>
                            <p class="mb-2" style="margin-bottom: 8px;"><strong>Purpose of Event:</strong> ${eventPurpose || '___________________________________'}</p>
                            <p class="mb-2" style="margin-bottom: 8px;"><strong>Date and Time of Event:</strong> ${eventDateTime || '______________________________'}</p>
                            <p class="mb-2" style="margin-bottom: 8px;"><strong>Venue/Location within Barangay:</strong> ${eventVenue || '_______________________'}</p>
                        </div>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This permit is issued after proper coordination with this Barangay and upon compliance with the necessary requirements. 
                            The organizer is expected to maintain peace and order, ensure cleanliness, and abide by barangay ordinances and other applicable laws throughout the duration of the activity.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            Failure to observe these conditions shall be grounds for the revocation of this permit and/or imposition of appropriate sanctions.
                        </p>
                        
                        <p class="text-left" style="text-align: left;">
                            Issued this ${day}${daySuffix} day of ${monthName}, ${year}, at Barangay 172, Urduja, Caloocan City.
                        </p>
                    </div>
                    
                    <div class="mt-12" style="margin-top: 48px;">
                        <div class="border-t-2 border-gray-400 pt-2 w-48" style="border-top: 2px solid #9ca3af; padding-top: 8px; width: 192px; margin-left: 0;">
                            <p class="text-sm text-left" style="font-size: 14px; text-align: left;">Signature of the Bearer</p>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-xs text-left text-gray-600" style="margin-top: 32px; font-size: 12px; text-align: left; color: #4b5563;">
                        <p style="margin-bottom: 4px;"><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this permit.</p>
                        <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
                    </div>
                    
                    <div class="mt-8 flex justify-center space-x-8 text-xs" style="margin-top: 32px; display: flex; justify-content: center; gap: 32px; font-size: 12px;">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>8442-15-31 / 8442-40-61</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>barangay1722023urduja@gmail.com</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>Magat Salamat Street, Urduja Village, Caloocan City</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-orange-500 text-white py-2 px-4 rounded" style="margin-top: 16px; background-color: #f97316; color: white; padding: 8px 16px; border-radius: 4px;">
                        <p class="text-sm font-bold" style="font-size: 14px; font-weight: bold;">Malapit sa TAO, Sanay sa TAO</p>
                    </div>
                </div>
            `;
            
            certificateContent.innerHTML = certificate;
            console.log('Barangay Permit certificate content generated successfully');
        }

        function generateResidencyCertificate() {
            const certificateContent = document.getElementById('certificateContent');
            if (!certificateContent) return;
            
            const today = new Date();
            const day = today.getDate();
            const month = today.getMonth();
            const year = today.getFullYear();
            
            const daySuffix = getDaySuffix(day);
            const monthName = getMonthName(month);
            
            const certificate = `
                <div class="text-center mb-8" style="font-family: 'Times New Roman', serif;">
                    <div class="flex justify-center items-center mb-6" style="display: flex; justify-content: center; align-items: center; margin-bottom: 24px;">
                        <div class="flex items-center space-x-4" style="display: flex; align-items: center; gap: 16px;">
                            <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="w-16 h-16" style="width: 64px; height: 64px;">
                            <div class="text-center">
                                <h1 class="text-lg font-bold" style="font-size: 16px; margin-bottom: 4px;">Republic of the Philippines</h1>
                                <h2 class="text-xl font-bold text-red-600" style="font-size: 20px; color: #dc2626; margin-bottom: 4px;">BARANGAY 172 URDUJA</h2>
                                <p class="text-sm" style="font-size: 14px;">Zone 15, District I, Caloocan City</p>
                            </div>
                            <div class="flex items-center space-x-2" style="display: flex; align-items: center; gap: 8px;">
                                <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                                <img src="../assets/images/bagongpilipinas.png" alt="Bagong Pilipinas Logo" class="w-12 h-12" style="width: 48px; height: 48px;">
                            </div>
                        </div>
                    </div>
                    <hr class="border-gray-400 mb-8" style="border: 1px solid #9ca3af;">
                    
                    <div class="text-center mb-8">
                        <h3 class="text-lg font-bold" style="font-size: 18px; margin-bottom: 8px;">OFFICE OF THE PUNONG BARANGAY</h3>
                        <h4 class="text-2xl font-bold tracking-wider" style="font-size: 24px; letter-spacing: 0.1em; margin-bottom: 16px;">C E R T I F I C A T E  O F  R E S I D E N C Y</h4>
                    </div>
                    
                    <div class="text-justify leading-relaxed mb-8" style="text-align: justify; line-height: 1.6; margin-bottom: 32px;">
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This is to certify that <strong>${currentApplication.full_name}</strong>, 
                            <strong>${calculateAge(currentApplication.birthday || '1990-01-01')}</strong> years old, 
                            is a bonafide resident of Barangay 172, Urduja, Caloocan City, and has been residing at 
                            <strong>${currentApplication.house_no || ''} ${currentApplication.street || ''}, Zone 15, Brgy. 172, Caloocan City</strong> 
                            for the past <strong>${calculateYearsFromYearStarted(currentApplication.year_started_living)}</strong> years up to the present.
                        </p>
                        
                        <p class="mb-4" style="margin-bottom: 16px; text-indent: 40px;">
                            This certification is issued upon the request of the above-named person for 
                            <strong>${currentApplication.purpose || 'employment'}</strong> and for whatever legal purpose it may serve.
                        </p>
                        
                        <p class="text-left" style="text-align: left;">
                            Issued this ${day}${daySuffix} day of ${monthName}, ${year} at Barangay 172, Urduja, Caloocan City.
                        </p>
                    </div>
                    
                    <div class="mt-12" style="margin-top: 48px;">
                        <div class="border-t-2 border-gray-400 pt-2 w-48" style="border-top: 2px solid #9ca3af; padding-top: 8px; width: 192px; margin-left: 0;">
                            <p class="text-sm text-left" style="font-size: 14px; text-align: left;">Signature of the Bearer</p>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-xs text-left text-gray-600" style="margin-top: 32px; font-size: 12px; text-align: left; color: #4b5563;">
                        <p style="margin-bottom: 4px;"><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this certification.</p>
                        <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
                    </div>
                    
                    <div class="mt-8 flex justify-center space-x-8 text-xs" style="margin-top: 32px; display: flex; justify-content: center; gap: 32px; font-size: 12px;">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>8442-15-31 / 8442-40-61</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>barangay1722023urduja@gmail.com</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-2" style="width: 16px; height: 16px; background-color: #10b981; border-radius: 50%; margin-right: 8px;"></div>
                            <span>Magat Salamat Street, Urduja Village, Caloocan City</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 bg-orange-500 text-white py-2 px-4 rounded" style="margin-top: 16px; background-color: #f97316; color: white; padding: 8px 16px; border-radius: 4px;">
                        <p class="text-sm font-bold" style="font-size: 14px; font-weight: bold;">Malapit sa TAO, Sanay sa TAO</p>
                    </div>
                </div>
            `;
            
            certificateContent.innerHTML = certificate;
            console.log('Certificate of Residency content generated successfully');
        }

        function updateForPickup() {
            if (!currentApplication) {
                alert('No application selected');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('application_id', currentApplication.id);
            formData.append('status', 'approved');
            formData.append('remarks', 'Ready for pick-up');
            
            fetch('applications.php', { method: 'POST', body: formData })
                .then(response => {
                    if (response.ok) {
                        // Update current application object
                        currentApplication.status = 'approved';
                        currentApplication.remarks = 'Ready for pick-up';
                        currentApplication.processed_date = new Date().toISOString();
                        
                        // Re-render modal details to reflect new status and buttons
                        processApplication(currentApplication);
                        
                        // Show success modal
                        showReadyForPickupModal();
                        
                        // Refresh the page to show updated status in the table
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        throw new Error('Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update status: ' + error.message);
                });
        }
        
        function printCertificate() {
            console.log('Print certificate clicked');
            
            const certificateContentEl = document.getElementById('certificateContent');
            if (!certificateContentEl) {
                alert('Certificate content element not found');
                return;
            }
            
            const certificateContent = certificateContentEl.innerHTML;
            if (!certificateContent || certificateContent.trim() === '' || certificateContent.includes('Certificate content will be loaded here')) {
                alert('No certificate content to print. Please generate certificate first.');
                return;
            }
            
            console.log('Opening print window...');
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Barangay Certificate</title>
                    <style>
                        body { 
                            font-family: 'Times New Roman', serif; 
                            margin: 20px; 
                            background-color: white;
                        }
                        .certificate { 
                            border: 2px solid #333; 
                            padding: 40px; 
                            background-color: white;
                            max-width: 800px;
                            margin: 0 auto;
                        }
                        .text-center { text-align: center; }
                        .text-justify { text-align: justify; }
                        .text-left { text-align: left; }
                        .text-right { text-align: right; }
                        .mb-4 { margin-bottom: 16px; }
                        .mb-6 { margin-bottom: 24px; }
                        .mb-8 { margin-bottom: 32px; }
                        .mt-4 { margin-top: 16px; }
                        .mt-8 { margin-top: 32px; }
                        .mt-12 { margin-top: 48px; }
                        .leading-relaxed { line-height: 1.6; }
                        .text-lg { font-size: 18px; }
                        .text-xl { font-size: 20px; }
                        .text-2xl { font-size: 24px; }
                        .text-sm { font-size: 14px; }
                        .text-xs { font-size: 12px; }
                        .font-bold { font-weight: bold; }
                        .underline { text-decoration: underline; }
                        .text-red-600 { color: #dc2626; }
                        .text-blue-600 { color: #2563eb; }
                        .text-gray-600 { color: #4b5563; }
                        .bg-red-600 { background-color: #dc2626; }
                        .bg-blue-600 { background-color: #2563eb; }
                        .bg-yellow-400 { background-color: #fbbf24; }
                        .bg-green-500 { background-color: #10b981; }
                        .bg-orange-500 { background-color: #f97316; }
                        .text-white { color: white; }
                        .rounded-full { border-radius: 9999px; }
                        .border-t-2 { border-top-width: 2px; }
                        .border-gray-400 { border-color: #9ca3af; }
                        .w-20 { width: 80px; }
                        .h-20 { height: 80px; }
                        .w-16 { width: 64px; }
                        .h-16 { height: 64px; }
                        .w-4 { width: 16px; }
                        .h-4 { height: 16px; }
                        .w-48 { width: 192px; }
                        .mx-auto { margin-left: auto; margin-right: auto; }
                        .flex { display: flex; }
                        .flex-col { flex-direction: column; }
                        .items-center { align-items: center; }
                        .items-end { align-items: flex-end; }
                        .justify-center { justify-content: center; }
                        .justify-between { justify-content: space-between; }
                        .space-x-8 > * + * { margin-left: 32px; }
                        .gap-32 { gap: 32px; }
                        .mr-2 { margin-right: 8px; }
                        .py-2 { padding-top: 8px; padding-bottom: 8px; }
                        .px-4 { padding-left: 16px; padding-right: 16px; }
                        .rounded { border-radius: 4px; }
                        hr { border: 1px solid #9ca3af; }
                        
                        @media print {
                            body { margin: 0; }
                            .certificate { 
                                border: none; 
                                padding: 20px; 
                                box-shadow: none;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="certificate">
                        ${certificateContent}
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
        
        function getDaySuffix(day) {
            if (day >= 11 && day <= 13) return 'th';
            switch (day % 10) {
                case 1: return 'st';
                case 2: return 'nd';
                case 3: return 'rd';
                default: return 'th';
            }
        }
        
        function getMonthName(month) {
            const months = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            return months[month];
        }
        
        function calculateAge(birthday) {
            const birthDate = new Date(birthday);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }
        
        function calculateYearsOfResidence(createdAt) {
            if (!createdAt) return '26'; // Default fallback
            
            const registrationDate = new Date(createdAt);
            const today = new Date();
            let years = today.getFullYear() - registrationDate.getFullYear();
            const monthDiff = today.getMonth() - registrationDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < registrationDate.getDate())) {
                years--;
            }
            
            // Ensure minimum of 1 year
            return Math.max(years, 1);
        }
        
        function calculateYearsFromYearStarted(yearStarted) {
            if (!yearStarted) return '26'; // Default fallback
            
            const currentYear = new Date().getFullYear();
            const yearsLiving = currentYear - parseInt(yearStarted);
            
            // Ensure minimum of 1 year
            return Math.max(yearsLiving, 1);
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewApplicationModal');
            const certificateModal = document.getElementById('certificateModal');
            const loadingModal = document.getElementById('loadingModal');
            const readyForPickupModal = document.getElementById('readyForPickupModal');
            
            if (event.target === viewModal) {
                hideViewApplicationModal();
            }
            if (event.target === certificateModal) {
                hideCertificateModal();
            }
            if (event.target === readyForPickupModal) {
                hideReadyForPickupModal();
            }
            if (event.target === loadingModal) {
                // Don't allow closing loading modal by clicking outside
            }
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideViewApplicationModal();
                hideCertificateModal();
                hideReadyForPickupModal();
                hideLogoutModal();
            }
        });

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

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('logoutModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('logoutModal')) {
                hideLogoutModal();
            }
        });
    </script>

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
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>
</body>
</html>
