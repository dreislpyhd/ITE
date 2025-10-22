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
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_appointments = $notifications['appointments'] ?? 0;
$new_patients = $notifications['residents'] ?? 0;
$total_notifications = $notifications['total'] ?? 0;

// Handle search and filtering
$search = $_GET['search'] ?? '';
$patient_filter = $_GET['patient'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query for medical records
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR mr.diagnosis LIKE ? OR mr.treatment LIKE ? OR mr.notes LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($patient_filter)) {
    $where_conditions[] = "mr.user_id = ?";
    $params[] = $patient_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(mr.record_date) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM medical_records mr JOIN users u ON mr.user_id = u.id WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Get medical records
try {
    $stmt = $conn->prepare("SELECT mr.*, u.full_name, u.email FROM medical_records mr 
                          JOIN users u ON mr.user_id = u.id 
                          WHERE $where_clause 
                          ORDER BY mr.record_date DESC 
                          LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $medical_records = $stmt->fetchAll();
} catch (Exception $e) {
    $medical_records = [];
}

// Get patients for filter dropdown
try {
    $patients_stmt = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'resident' ORDER BY full_name");
    $patients_stmt->execute();
    $patients = $patients_stmt->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

// Handle record actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $record_id = $_POST['record_id'] ?? '';
    $action = $_POST['action'];
    
    try {
        if ($action === 'delete') {
            $delete_stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
            $delete_stmt->execute([$record_id]);
        }
        
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    } catch (Exception $e) {
        $error_message = "Error updating record: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Health Center</title>
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
                    <a href="index.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5v14M16 5v14"></path>
                        </svg>
                        Dashboard
                    </a>
                    
                    <!-- Patients -->
                    <a href="health-staff.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <a href="health-services.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Health Services
                    </a>
                    
                    <!-- Appointments -->
                    <a href="appointments.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Appointments
                        <?php if ($new_appointments > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $new_appointments; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Medical Records (Active) -->
                    <a href="medical-records.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-green bg-opacity-10 rounded-lg border-l-4 border-barangay-green">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Medical Records
                    </a>
                    
                    <!-- Reports -->
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Medical Records</h1>
                <p class="text-gray-600">Manage patient medical records and history</p>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                               placeholder="Patient name, diagnosis...">
                    </div>
                    <div>
                        <label for="patient" class="block text-sm font-medium text-gray-700 mb-2">Patient</label>
                        <select id="patient" name="patient" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            <option value="">All Patients</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $patient_filter == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="px-6 py-2 bg-barangay-green text-white rounded-lg hover:bg-green-700 transition-colors">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Medical Records Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 font-eb-garamond">Medical Records List</h2>
                            <p class="text-sm text-gray-600">Total: <?php echo $total_records; ?> records</p>
                        </div>
                        <button onclick="showAddRecordModal()" class="px-4 py-2 bg-barangay-green text-white rounded-lg hover:bg-green-700 transition-colors">
                            Add New Record
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diagnosis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treatment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($medical_records)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No medical records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($medical_records as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-barangay-green rounded-full flex items-center justify-center">
                                                    <span class="text-white font-medium text-sm">
                                                        <?php 
                                                        $nameParts = explode(' ', $record['full_name']);
                                                        $initials = '';
                                                        if (count($nameParts) >= 2) {
                                                            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                                        } else {
                                                            $initials = strtoupper(substr($record['full_name'], 0, 2));
                                                        }
                                                        echo $initials;
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['full_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($record['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($record['diagnosis']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($record['treatment']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($record['record_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewRecord(<?php echo $record['id']; ?>)" class="text-barangay-green hover:text-green-700">View</button>
                                                <button onclick="editRecord(<?php echo $record['id']; ?>)" class="text-barangay-green hover:text-green-700">Edit</button>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" onclick="return confirm('Are you sure you want to delete this medical record? This action cannot be undone.')" 
                                                            class="text-orange-500 hover:text-orange-600">Delete</button>
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

    <!-- Add Record Modal -->
    <div id="addRecordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Add New Medical Record</h3>
                        <button onclick="hideAddRecordModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="add_medical_record.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="patient_id" class="block text-sm font-medium text-gray-700 mb-2">Patient</label>
                                <select id="patient_id" name="patient_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="record_date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <input type="date" id="record_date" name="record_date" required value="<?php echo date('Y-m-d'); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-2">Diagnosis</label>
                            <textarea id="diagnosis" name="diagnosis" rows="3" required 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                      placeholder="Enter diagnosis..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="treatment" class="block text-sm font-medium text-gray-700 mb-2">Treatment</label>
                            <textarea id="treatment" name="treatment" rows="3" required 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                      placeholder="Enter treatment..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea id="notes" name="notes" rows="3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                      placeholder="Additional notes..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideAddRecordModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-barangay-green hover:bg-green-700 rounded-lg transition-colors">
                                Add Record
                            </button>
                        </div>
                    </form>
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

        // Modal Functions
        function showAddRecordModal() {
            document.getElementById('addRecordModal').classList.remove('hidden');
        }

        function hideAddRecordModal() {
            document.getElementById('addRecordModal').classList.add('hidden');
        }

        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        function viewRecord(recordId) {
            // Implement view record functionality
            alert('View record functionality will be implemented');
        }

        function editRecord(recordId) {
            // Implement edit record functionality
            alert('Edit record functionality will be implemented');
        }

        // Close modals when clicking outside
        document.getElementById('addRecordModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('addRecordModal')) {
                hideAddRecordModal();
            }
        });

        document.getElementById('logoutModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('logoutModal')) {
                hideLogoutModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideAddRecordModal();
                hideLogoutModal();
            }
        });
    </script>
</body>
</html>
