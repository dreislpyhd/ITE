<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is health_staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'health_staff') {
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

// Get notification counts
$user_id = $_SESSION['user_id'];
$notifications = $notificationHelper->getNotificationCounts($user_id, 'health_staff');
$new_appointments = $notifications['appointments'] ?? 0;
$new_patients = $notifications['residents'] ?? 0;
$total_notifications = $notifications['total'] ?? 0;

// Get notification counts for sidebar badges
$notification_counts = getHealthCenterNotificationCounts($user_id, $conn);

// Mark patient registration notifications as read when visiting this page
try {
    $stmt = $conn->prepare("UPDATE patient_registration_notifications SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
} catch (Exception $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query for patient registrations
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
try {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM patient_registrations pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Get patient registrations
try {
    $stmt = $conn->prepare("
        SELECT pr.*, u.full_name, u.email, u.phone, u.address, u.birthday
        FROM patient_registrations pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE $where_clause 
        ORDER BY pr.created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $patient_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $patient_registrations = [];
}

// Handle patient registration status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $registration_id = $_POST['registration_id'] ?? '';
    $action = $_POST['action'];
    $staff_notes = $_POST['staff_notes'] ?? '';
    
    try {
        if ($action === 'approve') {
            // Get patient registration details for notification
            $stmt = $conn->prepare("SELECT user_id FROM patient_registrations WHERE id = ?");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch();
            
            $update_stmt = $conn->prepare("
                UPDATE patient_registrations 
                SET status = 'approved', approved_by = ?, approved_at = datetime('now'), staff_notes = ?, updated_at = datetime('now') 
                WHERE id = ?
            ");
            $update_stmt->execute([$user_id, $staff_notes, $registration_id]);
            
            // Create notification for the resident
            $notification_message = "Your patient registration has been approved. You can now access health services.";
            $notification_stmt = $conn->prepare("
                INSERT INTO patient_registration_notifications (user_id, registration_id, status, message) 
                VALUES (?, ?, 'approved', ?)
            ");
            $notification_stmt->execute([$registration['user_id'], $registration_id, $notification_message]);
            
        } elseif ($action === 'reject') {
            // Get patient registration details for notification
            $stmt = $conn->prepare("SELECT user_id FROM patient_registrations WHERE id = ?");
            $stmt->execute([$registration_id]);
            $registration = $stmt->fetch();
            
            $update_stmt = $conn->prepare("
                UPDATE patient_registrations 
                SET status = 'rejected', approved_by = ?, approved_at = datetime('now'), staff_notes = ?, updated_at = datetime('now') 
                WHERE id = ?
            ");
            $update_stmt->execute([$user_id, $staff_notes, $registration_id]);
            
            // Create notification for the resident
            $notification_message = "Your patient registration has been rejected. Please review the staff notes for more information.";
            $notification_stmt = $conn->prepare("
                INSERT INTO patient_registration_notifications (user_id, registration_id, status, message) 
                VALUES (?, ?, 'rejected', ?)
            ");
            $notification_stmt->execute([$registration['user_id'], $registration_id, $notification_message]);
            
        } elseif ($action === 'delete') {
            $delete_stmt = $conn->prepare("DELETE FROM patient_registrations WHERE id = ?");
            $delete_stmt->execute([$registration_id]);
        }
        
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    } catch (Exception $e) {
        $error_message = "Error updating patient registration: " . $e->getMessage();
    }
}

// Mark as viewed
$notificationHelper->markAsViewed($user_id, 'residents');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=EB+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    <nav class="bg-barangay-green shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center space-x-4">
                        <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="h-14 w-14 rounded-full">
                        <div>
                            <h1 class="text-xl font-bold text-white font-eb-garamond">Health Center Dashboard</h1>
                            <p class="text-sm text-green-100">Brgy. 172 Urduja Zone 15 District 1 Caloocan City</p>
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
                            <span class="text-barangay-green font-bold text-lg">
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
        <button id="sidebarToggle" class="lg:hidden fixed top-20 left-4 z-40 bg-barangay-green text-white p-1.5 rounded-full shadow-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>

        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white shadow-lg min-h-screen transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out fixed lg:relative z-40">
            <div class="p-4">
                <nav class="space-y-2">
                    <!-- Dashboard -->
                    <a href="index.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors whitespace-nowrap">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5v14M16 5v14"></path>
                        </svg>
                        Dashboard
                    </a>
                    
                    <!-- Patients (Active) -->
                    <a href="health-staff.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-green bg-opacity-10 rounded-lg border-l-4 border-barangay-green whitespace-nowrap relative">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Patients
                        <?php if ($notification_counts['patient_registrations'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Health Services -->
                    <a href="health-services.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors whitespace-nowrap">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Health Services
                    </a>
                    
                    <!-- Appointments -->
                    <a href="appointments.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative whitespace-nowrap">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Appointments
                        <?php if ($new_appointments > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $new_appointments; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Medical Records -->
                    <a href="medical-records.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors whitespace-nowrap">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Medical Records
                    </a>
                    
                    <!-- Reports -->
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors whitespace-nowrap">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Patient Registration Management</h1>
                <p class="text-gray-600">Review and manage patient registration requests</p>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Patients</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                               placeholder="Search by name, email, or phone...">
                    </div>
                    <div class="md:w-48">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="px-6 py-2 bg-barangay-green text-white rounded-lg hover:bg-green-700 transition-colors">
                            Search
                        </button>
                        <?php if (!empty($search) || !empty($status_filter)): ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Patient Registrations Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 font-eb-garamond">Patient Registration Requests</h2>
                    <p class="text-sm text-gray-600">Total: <?php echo $total_records; ?> registrations</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                                                 <thead class="bg-gray-50">
                             <tr>
                                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                 <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                             </tr>
                         </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                                                         <?php if (empty($patient_registrations)): ?>
                                 <tr>
                                     <td colspan="4" class="px-6 py-4 text-center text-gray-500">No patient registrations found</td>
                                 </tr>
                            <?php else: ?>
                                <?php foreach ($patient_registrations as $registration): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-barangay-green rounded-full flex items-center justify-center">
                                                    <span class="text-white font-medium text-sm">
                                                        <?php 
                                                        $nameParts = explode(' ', $registration['full_name']);
                                                        $initials = '';
                                                        if (count($nameParts) >= 2) {
                                                            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                                        } else {
                                                            $initials = strtoupper(substr($registration['full_name'], 0, 2));
                                                        }
                                                        echo $initials;
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($registration['full_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($registration['email']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($registration['phone'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($registration['status'] === 'approved'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    Approved
                                                </span>
                                            <?php elseif ($registration['status'] === 'rejected'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                    Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($registration['created_at'])); ?>
                                        </td>
                                                                                 <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                             <div class="flex space-x-2">
                                                 <button onclick="viewPatientDetails(<?php echo $registration['id']; ?>)" 
                                                         class="bg-barangay-green hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                     View Details
                                                 </button>
                                                 <form method="POST" class="inline">
                                                     <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                     <input type="hidden" name="action" value="delete">
                                                     <button type="submit" onclick="return confirm('Are you sure you want to delete this registration? This action cannot be undone.')" 
                                                             class="bg-orange-400 hover:bg-orange-500 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                         Delete
                                                     </button>
                                                 </form>
                                             </div>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-white bg-barangay-green' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-lg">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Approve Patient Registration</h3>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-sm text-gray-500">Are you sure you want to approve the registration for <span id="approvalPatientName" class="font-medium text-gray-900"></span>?</p>
                        <div class="mt-4">
                            <label for="approvalNotes" class="block text-sm font-medium text-gray-700 mb-2">Staff Notes (Optional)</label>
                            <textarea id="approvalNotes" name="staff_notes" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                                      placeholder="Add any notes about this approval..."></textarea>
                        </div>
                    </div>
                    <form id="approvalForm" method="POST">
                        <input type="hidden" name="registration_id" id="approvalRegistrationId">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="staff_notes" id="approvalNotesInput">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideApprovalModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-barangay-green hover:bg-green-700 rounded-lg transition-colors">
                                Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
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
                            <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Reject Patient Registration</h3>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-sm text-gray-500">Are you sure you want to reject the registration for <span id="rejectionPatientName" class="font-medium text-gray-900"></span>?</p>
                        <div class="mt-4">
                            <label for="rejectionNotes" class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                            <textarea id="rejectionNotes" name="staff_notes" rows="3" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-transparent"
                                      placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                    </div>
                    <form id="rejectionForm" method="POST">
                        <input type="hidden" name="registration_id" id="rejectionRegistrationId">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="staff_notes" id="rejectionNotesInput">
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideRejectionModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-orange-400 hover:bg-orange-500 rounded-lg transition-colors">
                                Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div id="patientDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Patient Registration Details</h3>
                        <button onclick="hidePatientDetailsModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="patientDetailsContent" class="space-y-4">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-gray-900 font-eb-garamond" id="confirmationTitle">Confirm Action</h3>
                        </div>
                    </div>
                    <div class="mb-6">
                        <p class="text-sm text-gray-500" id="confirmationMessage">Are you sure you want to proceed?</p>
                        <div id="confirmationInput" class="mt-4 hidden">
                            <label for="rejectionReason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                            <textarea id="rejectionReason" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-600 focus:border-transparent"
                                      placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button onclick="hideConfirmationModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button id="confirmButton" onclick="executeConfirmedAction()" class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                            Confirm
                        </button>
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

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideLogoutModal();
                hideApprovalModal();
                hideRejectionModal();
                hidePatientDetailsModal();
                hideConfirmationModal();
            }
        });
        
        // Approval Modal Functions
        function showApprovalModal(registrationId, patientName) {
            document.getElementById('approvalRegistrationId').value = registrationId;
            document.getElementById('approvalPatientName').textContent = patientName;
            document.getElementById('approvalModal').classList.remove('hidden');
        }
        
        function hideApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.getElementById('approvalNotes').value = '';
        }
        
        // Rejection Modal Functions
        function showRejectionModal(registrationId, patientName) {
            document.getElementById('rejectionRegistrationId').value = registrationId;
            document.getElementById('rejectionPatientName').textContent = patientName;
            document.getElementById('rejectionModal').classList.remove('hidden');
        }
        
        function hideRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.getElementById('rejectionNotes').value = '';
        }
        
        // Patient Details Modal Functions
        function viewPatientDetails(registrationId) {
            // Show loading state
            let content = document.getElementById('patientDetailsContent');
            content.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-barangay-green mx-auto mb-4"></div><p>Loading patient details...</p></div>';
            document.getElementById('patientDetailsModal').classList.remove('hidden');
            
            // Load patient details via AJAX
            fetch(`get_patient_details.php?id=${registrationId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Calculate age from birthday
                        let ageText = 'N/A';
                        let birthdayText = 'N/A';
                        
                        if (data.patient.birthday) {
                            let birthday = new Date(data.patient.birthday);
                            let today = new Date();
                            let age = today.getFullYear() - birthday.getFullYear();
                            let monthDiff = today.getMonth() - birthday.getMonth();
                            
                            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                                age--;
                            }
                            
                            ageText = age + ' years old';
                            birthdayText = birthday.toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        }
                        
                        content.innerHTML = `
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-3">Personal Information</h4>
                                    <div class="space-y-2">
                                        <div><strong>Name:</strong> ${data.patient.full_name}</div>
                                        <div><strong>Email:</strong> ${data.patient.email}</div>
                                        <div><strong>Phone:</strong> ${data.patient.phone || 'N/A'}</div>
                                        <div><strong>Address:</strong> ${data.patient.address || 'N/A'}</div>
                                        <div><strong>Birthday:</strong> ${birthdayText}</div>
                                        <div><strong>Age:</strong> ${ageText}</div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-3">Medical Information</h4>
                                    <div class="space-y-2">
                                        <div><strong>Blood Type:</strong> ${data.registration.blood_type}</div>
                                        <div><strong>Emergency Contact:</strong> ${data.registration.emergency_contact}</div>
                                        <div><strong>Insurance:</strong> ${data.registration.insurance_info || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6">
                                <h4 class="font-medium text-gray-900 mb-3">Medical History</h4>
                                <p class="text-gray-700">${data.registration.medical_history || 'No medical history provided'}</p>
                            </div>
                            <div class="mt-6">
                                <h4 class="font-medium text-gray-900 mb-3">Current Medications</h4>
                                <p class="text-gray-700">${data.registration.current_medications || 'No current medications'}</p>
                            </div>
                            ${data.registration.staff_notes ? `
                            <div class="mt-6">
                                <h4 class="font-medium text-gray-900 mb-3">Staff Notes</h4>
                                <p class="text-gray-700">${data.registration.staff_notes}</p>
                            </div>
                            ` : ''}
                            ${data.registration.status === 'pending' ? `
                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-3">Actions</h4>
                                <div class="flex space-x-3">
                                    <button onclick="approveFromDetails(${registrationId}, '${data.patient.full_name}')" 
                                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                        Approve Registration
                                    </button>
                                    <button onclick="rejectFromDetails(${registrationId}, '${data.patient.full_name}')" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                        Reject Registration
                                    </button>
                                </div>
                            </div>
                            ` : ''}
                        `;
                    } else {
                        content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading patient details</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading patient details: ' + error.message + '</p></div>';
                });
        }
        
        function hidePatientDetailsModal() {
            document.getElementById('patientDetailsModal').classList.add('hidden');
        }
        
        // Functions to handle approval/rejection from details modal
        function approveFromDetails(registrationId, patientName) {
            showConfirmationModal(
                'Approve Registration',
                `Are you sure you want to approve the registration for ${patientName}?`,
                'approve',
                registrationId,
                ''
            );
        }
        
        function rejectFromDetails(registrationId, patientName) {
            showConfirmationModal(
                'Reject Registration',
                `Please provide a reason for rejecting ${patientName}'s registration:`,
                'reject',
                registrationId,
                '',
                true
            );
        }
        
        // Global variables for confirmation modal
        let pendingAction = null;
        let pendingRegistrationId = null;
        let pendingStaffNotes = null;
        
        function showConfirmationModal(title, message, action, registrationId, staffNotes = '', showInput = false) {
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;
            
            const confirmButton = document.getElementById('confirmButton');
            const inputDiv = document.getElementById('confirmationInput');
            
            if (action === 'approve') {
                confirmButton.textContent = 'Approve';
                confirmButton.className = 'px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors';
            } else if (action === 'reject') {
                confirmButton.textContent = 'Reject';
                confirmButton.className = 'px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors';
            }
            
            if (showInput) {
                inputDiv.classList.remove('hidden');
                document.getElementById('rejectionReason').value = '';
            } else {
                inputDiv.classList.add('hidden');
            }
            
            // Store pending action details
            pendingAction = action;
            pendingRegistrationId = registrationId;
            pendingStaffNotes = staffNotes;
            
            document.getElementById('confirmationModal').classList.remove('hidden');
        }
        
        function hideConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            document.getElementById('confirmationInput').classList.add('hidden');
            pendingAction = null;
            pendingRegistrationId = null;
            pendingStaffNotes = null;
        }
        
        function executeConfirmedAction() {
            if (pendingAction === 'reject') {
                const reason = document.getElementById('rejectionReason').value.trim();
                if (!reason) {
                    alert('Please provide a reason for rejection');
                    return;
                }
                pendingStaffNotes = reason;
            }
            
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="registration_id" value="${pendingRegistrationId}">
                <input type="hidden" name="action" value="${pendingAction}">
                <input type="hidden" name="staff_notes" value="${pendingStaffNotes}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Form submission handlers
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const notes = document.getElementById('approvalNotes').value;
            document.getElementById('approvalNotesInput').value = notes;
            this.submit();
        });
        
        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const notes = document.getElementById('rejectionNotes').value;
            if (!notes.trim()) {
                alert('Please provide a reason for rejection');
                return;
            }
            document.getElementById('rejectionNotesInput').value = notes;
            this.submit();
        });

        // Enhanced search functionality
        const searchInput = document.getElementById('search');
        const statusSelect = document.getElementById('status');
        let searchTimeout;

        // Auto-submit search form after typing stops
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500); // Wait 500ms after user stops typing
        });

        // Auto-submit when status filter changes
        statusSelect.addEventListener('change', function() {
            this.form.submit();
        });

        // Add search result highlighting
        function highlightSearchTerms() {
            const searchTerm = '<?php echo htmlspecialchars($search); ?>';
            if (searchTerm) {
                const tableRows = document.querySelectorAll('tbody tr');
                tableRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                            const regex = new RegExp(`(${searchTerm})`, 'gi');
                            cell.innerHTML = cell.innerHTML.replace(regex, '<mark class="bg-yellow-200">$1</mark>');
                        }
                    });
                });
            }
        }

        // Apply highlighting after page loads
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);
    </script>
</body>
</html>
