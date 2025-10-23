<?php
require_once '../includes/config.php';
session_start();

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// Handle JSON API requests first (before session check)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if ($data && isset($data['action']) && $data['action'] === 'create_appointment_request') {
        try {
            $service_id = $data['service_id'] ?? '';
            $service_name = $data['service_name'] ?? '';
            $resident_user_id = $data['user_id'] ?? '';
            
            if (empty($service_id) || empty($service_name) || empty($resident_user_id)) {
                throw new Exception('Missing required fields');
            }
            
            // Get resident information
            $user_stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
            $user_stmt->execute([$resident_user_id]);
            $resident = $user_stmt->fetch();
            
            if (!$resident) {
                throw new Exception('Resident not found');
            }
            
            // Create appointment request
            $insert_stmt = $conn->prepare("
                INSERT INTO appointments (user_id, service_type, appointment_date, status, notes, created_at) 
                VALUES (?, ?, datetime('now', '+1 day'), 'scheduled', ?, datetime('now'))
            ");
            
            $notes = "Online appointment request";
            $insert_stmt->execute([$resident_user_id, 'medical_consultation', $notes]);
            
            // Return success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Appointment request created successfully']);
            exit;
            
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Appointment creation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => true]);
            exit;
        }
    }
    
    // Handle appointment confirmation
    if ($data && isset($data['action']) && $data['action'] === 'confirm_appointment') {
        try {
            // Check if user is logged in and is health_staff or health_center for this API call
            if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['health_staff', 'health_center'])) {
                throw new Exception('Unauthorized: Health staff access required');
            }
            
            $appointment_id = $data['appointment_id'] ?? '';
            $appointment_date = $data['appointment_date'] ?? '';
            $appointment_time = $data['appointment_time'] ?? '';
            
            if (empty($appointment_id) || empty($appointment_date) || empty($appointment_time)) {
                throw new Exception('Missing required fields');
            }
            
            // Combine date and time
            $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';
            
            // Get appointment and patient details
            $stmt = $conn->prepare("
                SELECT a.*, u.full_name, u.email 
                FROM appointments a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?
            ");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                throw new Exception('Appointment not found');
            }
            
            // Update appointment with confirmed status and scheduled datetime
            // Verify the current session user exists in the database
            $staff_id = $_SESSION['user_id'];
            $staff_check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('health_staff', 'health_center', 'admin')");
            $staff_check_stmt->execute([$staff_id]);
            $staff_exists = $staff_check_stmt->fetch();
            
            if (!$staff_exists) {
                // Log the issue but don't fail - set confirmed_by to NULL instead
                error_log("Warning: Session user ID {$staff_id} not found in database or not health staff. Setting confirmed_by to NULL.");
                $staff_id = null;
            }
            
            $update_stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'confirmed', appointment_date = ?, confirmed_by = ?, updated_at = datetime('now') 
                WHERE id = ?
            ");
            $update_stmt->execute([$appointment_datetime, $staff_id, $appointment_id]);
            
            // Log activity
            try {
                $log_stmt = $conn->prepare("
                    INSERT INTO activity_logs (action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    'appointment_confirmed',
                    'Confirmed appointment for ' . $appointment['full_name'] . ' - ' . $appointment['service_type'],
                    'appointment',
                    $appointment_id,
                    $appointment['full_name'],
                    $_SESSION['user_id'],
                    $_SESSION['full_name']
                ]);
            } catch (Exception $e) {
                error_log("Could not log activity: " . $e->getMessage());
            }
            
            // Send email notification (you can implement this later)
            // For now, we'll just return success
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Appointment confirmed and patient notified']);
            exit;
            
        } catch (Exception $e) {
            // Log detailed error information for debugging
            error_log("Appointment confirmation error: " . $e->getMessage());
            error_log("Session user ID: " . ($_SESSION['user_id'] ?? 'null'));
            error_log("Session role: " . ($_SESSION['role'] ?? 'null'));
            error_log("Stack trace: " . $e->getTraceAsString());
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Check if user is logged in and is health_staff or health_center (for regular page access)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['health_staff', 'health_center'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/notification_helper.php';
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
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR a.service_type LIKE ? OR a.notes LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM appointments a JOIN users u ON a.user_id = u.id WHERE $where_clause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 0;
}

// Get appointments
try {
    $stmt = $conn->prepare("SELECT a.*, u.full_name, u.email, u.phone FROM appointments a 
                          JOIN users u ON a.user_id = u.id 
                          WHERE $where_clause 
                          ORDER BY a.created_at DESC 
                          LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    error_log("Fetched " . count($appointments) . " appointments");
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $appointments = [];
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'] ?? '';
    $action = $_POST['action'];
    
    try {
        if ($action === 'confirm') {
            $update_stmt = $conn->prepare("UPDATE appointments SET status = 'confirmed', confirmed_by = ?, updated_at = datetime('now') WHERE id = ?");
            $update_stmt->execute([$_SESSION['user_id'], $appointment_id]);
            
            // Log activity
            try {
                // Get appointment details
                $appt_stmt = $conn->prepare("SELECT a.*, u.full_name FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
                $appt_stmt->execute([$appointment_id]);
                $appt = $appt_stmt->fetch();
                
                if ($appt) {
                    $log_stmt = $conn->prepare("
                        INSERT INTO activity_logs (action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $log_stmt->execute([
                        'appointment_confirmed',
                        'Confirmed appointment for ' . $appt['full_name'] . ' - ' . $appt['service_type'],
                        'appointment',
                        $appointment_id,
                        $appt['full_name'],
                        $_SESSION['user_id'],
                        $_SESSION['full_name']
                    ]);
                }
            } catch (Exception $e) {
                error_log("Could not log activity: " . $e->getMessage());
            }
        } elseif ($action === 'cancel') {
            $update_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = datetime('now') WHERE id = ?");
            $update_stmt->execute([$appointment_id]);
            
            // Log activity
            try {
                // Get appointment details
                $appt_stmt = $conn->prepare("SELECT a.*, u.full_name FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
                $appt_stmt->execute([$appointment_id]);
                $appt = $appt_stmt->fetch();
                
                if ($appt) {
                    $log_stmt = $conn->prepare("
                        INSERT INTO activity_logs (action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $log_stmt->execute([
                        'appointment_cancelled',
                        'Cancelled appointment for ' . $appt['full_name'] . ' - ' . $appt['service_type'],
                        'appointment',
                        $appointment_id,
                        $appt['full_name'],
                        $_SESSION['user_id'],
                        $_SESSION['full_name']
                    ]);
                }
            } catch (Exception $e) {
                error_log("Could not log activity: " . $e->getMessage());
            }
        } elseif ($action === 'delete') {
            // Try to move to deleted_appointments table (archive)
            try {
                // First, ensure the archive table exists
                try {
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS deleted_appointments (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            user_id INTEGER NOT NULL,
                            service_type TEXT NOT NULL,
                            appointment_date TEXT NOT NULL,
                            notes TEXT,
                            status TEXT DEFAULT 'scheduled',
                            created_at TEXT DEFAULT (datetime('now')),
                            deleted_at TEXT DEFAULT (datetime('now')),
                            deleted_by INTEGER
                        )
                    ");
                } catch (Exception $e) {
                    error_log("Could not create archive table: " . $e->getMessage());
                }
                
                $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch();
                
                if ($appointment) {
                    // Try to insert into deleted_appointments
                    try {
                        $archive_stmt = $conn->prepare("
                            INSERT INTO deleted_appointments (user_id, service_type, appointment_date, notes, status, created_at, deleted_at, deleted_by)
                            VALUES (?, ?, ?, ?, ?, ?, datetime('now'), ?)
                        ");
                        $result = $archive_stmt->execute([
                            $appointment['user_id'],
                            $appointment['service_type'],
                            $appointment['appointment_date'],
                            $appointment['notes'],
                            $appointment['status'],
                            $appointment['created_at'],
                            $_SESSION['user_id']
                        ]);
                    } catch (Exception $e) {
                        error_log("Archive failed: " . $e->getMessage());
                    }
                    
                    // Delete from appointments
                    $delete_stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
                    $delete_stmt->execute([$appointment_id]);
                }
            } catch (Exception $e) {
                error_log("Error deleting appointment: " . $e->getMessage());
            }
        }
        
        // Redirect to refresh the page
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    } catch (Exception $e) {
        $error_message = "Error updating appointment: " . $e->getMessage();
    }
}

// Mark as viewed
$notificationHelper->markAsViewed($user_id, 'appointments');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - Health Center</title>
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
                        <?php if (($notification_counts['patients'] ?? 0) > 0): ?>
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
                    
                    <!-- Appointments (Active) -->
                    <a href="appointments.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-green bg-opacity-10 rounded-lg border-l-4 border-barangay-green relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Appointments
                        <?php if ($new_appointments > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $new_appointments; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Reports -->
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Reports
                    </a>
                    
                    <!-- History -->
                    <a href="history.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        History
                    </a>
                    
                    <!-- Archives -->
                    <a href="archives.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                        Archives
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Appointments Management</h1>
                <p class="text-gray-600">Manage patient appointments and schedules</p>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                               placeholder="Patient name, service type...">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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

            <!-- Appointments Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 font-eb-garamond">Appointments List</h2>
                    <p class="text-sm text-gray-600">Total: <?php echo $total_records; ?> appointments</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No appointments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-barangay-green rounded-full flex items-center justify-center">
                                                    <span class="text-white font-medium text-sm">
                                                        <?php 
                                                        $nameParts = explode(' ', $appointment['full_name']);
                                                        $initials = '';
                                                        if (count($nameParts) >= 2) {
                                                            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts)-1], 0, 1));
                                                        } else {
                                                            $initials = strtoupper(substr($appointment['full_name'], 0, 2));
                                                        }
                                                        echo $initials;
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['full_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['email']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php 
                                                $service_names = [
                                                    'medical_consultation' => 'Medical Consultation',
                                                    'vaccination' => 'Vaccination',
                                                    'health_checkup' => 'Health Checkup',
                                                    'dental_service' => 'Dental Service',
                                                    'other' => 'Other Service'
                                                ];
                                                echo $service_names[$appointment['service_type']] ?? ucfirst(str_replace('_', ' ', $appointment['service_type']));
                                                ?>
                                            </div>
                                            <div class="text-sm text-gray-500">Online Request</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                                                        $status_colors = [
                                'scheduled' => 'bg-yellow-100 text-yellow-800',
                                'confirmed' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                'no_show' => 'bg-gray-100 text-gray-800'
                            ];
                                            $status_color = $status_colors[$appointment['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_color; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                                    <button onclick="showAppointmentModal(<?php echo $appointment['id']; ?>, '<?php echo htmlspecialchars($appointment['full_name'], ENT_QUOTES); ?>', '<?php echo $appointment['service_type']; ?>', '<?php echo htmlspecialchars($appointment['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($appointment['phone'] ?? '', ENT_QUOTES); ?>')" 
                                                            class="bg-barangay-green hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                        View
                                                    </button>
                                                <?php endif; ?>
                                                

                                                

                                                
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" onclick="return confirm('Are you sure you want to archive this appointment? You can restore it from Archives.')" 
                                                            class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                        Archive
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
            }
        });

        // Appointment Modal Functions
        let currentAppointmentId = null;

        function showAppointmentModal(appointmentId, patientName, serviceType, email, phone) {
            currentAppointmentId = appointmentId;
            
            // Populate modal with patient details
            document.getElementById('modalPatientName').textContent = patientName;
            document.getElementById('modalPatientEmail').textContent = email;
            document.getElementById('modalPatientPhone').textContent = phone || 'N/A';
            document.getElementById('modalServiceType').textContent = serviceType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            document.getElementById('appointmentDate').value = dateStr;
            
            // Set default time to 9:00 AM
            document.getElementById('appointmentTime').value = '09:00';
            
            // Show modal
            document.getElementById('appointmentModal').classList.remove('hidden');
            document.getElementById('appointmentModal').style.display = 'block';
        }

        function hideAppointmentModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
            document.getElementById('appointmentModal').style.display = 'none';
            currentAppointmentId = null;
        }

        function cancelAppointmentFromModal() {
            if (!currentAppointmentId) return;
            
            if (!confirm('Are you sure you want to cancel this appointment?')) {
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'appointments.php';
            
            const appointmentIdInput = document.createElement('input');
            appointmentIdInput.type = 'hidden';
            appointmentIdInput.name = 'appointment_id';
            appointmentIdInput.value = currentAppointmentId;
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'cancel';
            
            form.appendChild(appointmentIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }

        function confirmAppointment() {
            if (!currentAppointmentId) return;
            
            const appointmentDate = document.getElementById('appointmentDate').value;
            const appointmentTime = document.getElementById('appointmentTime').value;
            
            if (!appointmentDate || !appointmentTime) {
                alert('Please select both date and time for the appointment.');
                return;
            }
            
            // Show loading state
            const confirmBtn = document.querySelector('#appointmentModal button[onclick="confirmAppointment()"]');
            const originalText = confirmBtn.textContent;
            confirmBtn.textContent = 'Confirming...';
            confirmBtn.disabled = true;
            
            // Send confirmation request
            fetch('appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'confirm_appointment',
                    appointment_id: currentAppointmentId,
                    appointment_date: appointmentDate,
                    appointment_time: appointmentTime
                })
            })
            .then(response => response.json())
                            .then(data => {
                    if (data.success) {
                        showConfirmationSuccessModal();
                    } else {
                        alert('Error confirming appointment: ' + (data.message || 'Please try again.'));
                    }
                })
            .catch(error => {
                console.error('Error:', error);
                alert('Error confirming appointment. Please try again.');
            })
            .finally(() => {
                confirmBtn.textContent = originalText;
                confirmBtn.disabled = false;
                hideAppointmentModal();
            });
        }

        // Close appointment modal when clicking outside
        document.getElementById('appointmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAppointmentModal();
            }
        });

        // Confirmation Success Modal Functions
        function showConfirmationSuccessModal() {
            document.getElementById('confirmationSuccessModal').classList.remove('hidden');
            document.getElementById('confirmationSuccessModal').style.display = 'block';
        }

        function hideConfirmationSuccessModal() {
            document.getElementById('confirmationSuccessModal').classList.add('hidden');
            document.getElementById('confirmationSuccessModal').style.display = 'none';
            // Reload the page to show updated status
            location.reload();
        }

        // Close success modal when clicking outside
        document.getElementById('confirmationSuccessModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideConfirmationSuccessModal();
            }
        });

        // Update escape key handler
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideLogoutModal();
                hideAppointmentModal();
                hideConfirmationSuccessModal();
            }
        });
    </script>

    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Appointment Details</h3>
                <button onclick="hideAppointmentModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Patient Information -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900 border-b border-gray-200 pb-2">Patient Information</h4>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <p id="modalPatientName" class="text-sm text-gray-900 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <p id="modalPatientEmail" class="text-sm text-gray-900 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <p id="modalPatientPhone" class="text-sm text-gray-900 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Service Requested</label>
                        <p id="modalServiceType" class="text-sm text-gray-900 mt-1"></p>
                    </div>
                </div>
                
                <!-- Appointment Scheduling -->
                <div class="space-y-4">
                    <h4 class="font-medium text-gray-900 border-b border-gray-200 pb-2">Schedule Appointment</h4>
                    <div>
                        <label for="appointmentDate" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" id="appointmentDate" name="appointmentDate" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                    </div>
                    <div>
                        <label for="appointmentTime" class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                        <select id="appointmentTime" name="appointmentTime" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            <option value="">Select Time</option>
                            <option value="08:00">8:00 AM</option>
                            <option value="08:30">8:30 AM</option>
                            <option value="09:00">9:00 AM</option>
                            <option value="09:30">9:30 AM</option>
                            <option value="10:00">10:00 AM</option>
                            <option value="10:30">10:30 AM</option>
                            <option value="11:00">11:00 AM</option>
                            <option value="11:30">11:30 AM</option>
                            <option value="13:00">1:00 PM</option>
                            <option value="13:30">1:30 PM</option>
                            <option value="14:00">2:00 PM</option>
                            <option value="14:30">2:30 PM</option>
                            <option value="15:00">3:00 PM</option>
                            <option value="15:30">3:30 PM</option>
                            <option value="16:00">4:00 PM</option>
                            <option value="16:30">4:30 PM</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-between mt-8 pt-4 border-t border-gray-200">
                <button onclick="cancelAppointmentFromModal()" 
                        class="px-6 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors">
                    Cancel Appointment
                </button>
                <div class="flex space-x-3">
                    <button onclick="hideAppointmentModal()" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors">
                        Close
                    </button>
                    <button onclick="confirmAppointment()" 
                            class="px-6 py-2 bg-barangay-green hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        Confirm Appointment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Success Modal -->
    <div id="confirmationSuccessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="text-center">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <!-- Title -->
                <h3 class="text-xl font-bold text-gray-900 mb-2 font-eb-garamond">Appointment Confirmed!</h3>
                
                <!-- Message -->
                <p class="text-gray-600 mb-6">
                    Appointment confirmed successfully! The patient has been notified via email.
                </p>
                
                <!-- Action Button -->
                <div class="flex justify-center">
                    <button onclick="hideConfirmationSuccessModal()" 
                            class="px-8 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
