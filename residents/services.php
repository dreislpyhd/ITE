<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user exists
if (!$user) {
    error_log("User not found in database for ID: " . $user_id);
    header('Location: ../auth/login.php?error=User account not found');
    exit();
}

// Function to calculate age from birthday
function calculateAge($birthday) {
    if (!$birthday) return null;
    $birth = new DateTime($birthday);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

// Check patient registration status
$patient_status = null;
$patient_registration = null;
try {
    $stmt = $conn->prepare("SELECT * FROM patient_registrations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $patient_registration = $stmt->fetch();
    if ($patient_registration) {
        $patient_status = $patient_registration['status'];
    }
} catch (Exception $e) {
    error_log("Error checking patient status: " . $e->getMessage());
}

// Get available services
$barangay_services = $conn->query("SELECT * FROM barangay_services WHERE status = 'active' ORDER BY service_name")->fetchAll();
$health_services = $conn->query("SELECT * FROM health_services WHERE status = 'active' ORDER BY service_name")->fetchAll();

$message = '';
$error = '';

// Count status updates for notification badge
$status_update_count = 0;
$patient_notification_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        // Get user's last viewed applications timestamp
        $stmt = $conn->prepare("SELECT last_viewed_applications FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_viewed = $stmt->fetch();
        $last_viewed = $user_viewed['last_viewed_applications'] ?? null;
        
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
        
        // Count unread patient registration notifications BEFORE marking them as read
        $stmt = $conn->prepare("
            SELECT COUNT(*) as notification_count 
            FROM patient_registration_notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notification_result = $stmt->fetch();
        $patient_notification_count = $notification_result['notification_count'] ?? 0;
        
    } catch (Exception $e) {
        $status_update_count = 0;
        $patient_notification_count = 0;
    }
}

// Mark patient registration notifications as read when visiting this page
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE patient_registration_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
    }
}

// Handle patient registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'register_patient') {
    $blood_type = trim($_POST['blood_type'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $medical_history = trim($_POST['medical_history'] ?? '');
    $current_medications = trim($_POST['current_medications'] ?? '');
    $insurance_info = trim($_POST['insurance_info'] ?? '');
    $patient_terms = isset($_POST['patient_terms']);
    $user_id = $_SESSION['user_id'];
    
    if (empty($blood_type)) {
        $error = 'Please select your blood type.';
    } elseif (empty($emergency_contact)) {
        $error = 'Please provide emergency contact information.';
    } elseif (!$patient_terms) {
        $error = 'Please agree to the terms and conditions.';
    } else {
        try {
            // Check if user already has a pending or approved patient registration
            // Allow re-registration if previous was rejected
            $stmt = $conn->prepare("SELECT id, status FROM patient_registrations WHERE user_id = ? AND status IN ('pending', 'approved') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $existing = $stmt->fetch();
            if ($existing) {
                $error = 'You already have a pending or approved patient registration.';
            } else {
                // Insert patient registration
                $stmt = $conn->prepare("INSERT INTO patient_registrations (user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', datetime('now'))");
                $stmt->execute([$user_id, $blood_type, $emergency_contact, $medical_history, $current_medications, $insurance_info]);
                
                // Create notification for health center staff
                try {
                    $registration_id = $conn->lastInsertId();
                    $stmt = $conn->prepare("INSERT INTO patient_registration_notifications (user_id, registration_id, status, message, is_read, created_at) VALUES (?, ?, 'pending', 'New patient registration submitted', 0, datetime('now'))");
                    $stmt->execute([$user_id, $registration_id]);
                } catch (Exception $e) {
                    error_log("Failed to create patient registration notification: " . $e->getMessage());
                }
                
                // Redirect to prevent form resubmission
                header('Location: services.php?message=Patient registration submitted successfully! Please wait for approval from health center staff.');
                exit();
            }
        } catch (Exception $e) {
            $error = 'Failed to submit patient registration. Please try again.';
            error_log("Patient registration error: " . $e->getMessage());
            error_log("SQL Error Details: " . $e->getTraceAsString());
            // Log the specific data being inserted for debugging
            error_log("Patient registration data - User ID: $user_id, Blood Type: $blood_type, Emergency Contact: $emergency_contact");
        }
    }
}

// Handle new application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'apply') {
    $service_type = $_POST['service_type'];
    $service_id = $_POST['service_id'];
    $purpose = trim($_POST['purpose'] ?? '');
    $other_purpose = trim($_POST['other_purpose'] ?? '');
    $specify_purpose = trim($_POST['specify_purpose'] ?? '');
    $event_datetime = trim($_POST['event_datetime'] ?? '');
    $event_venue = trim($_POST['event_venue'] ?? '');
    $terms = isset($_POST['terms']);
    $user_id = $_SESSION['user_id'];
    
    // If "Other" is selected, use the other_purpose value
    if ($purpose === 'Other') {
        if (empty($other_purpose)) {
            $error = 'Please specify your purpose when selecting "Other".';
        } else {
            $purpose = $other_purpose;
        }
    }
    
    // If it's a Barangay Permit with specific purpose, append the specify_purpose and event details
    if (!empty($specify_purpose)) {
        $purpose = $purpose . ' - ' . $specify_purpose;
        
        // Add event details for Barangay Permit
        if (!empty($event_datetime) || !empty($event_venue)) {
            $purpose .= ' | Date/Time: ' . $event_datetime . ' | Venue: ' . $event_venue;
        }
    }
    
    if (empty($service_type) || empty($service_id)) {
        $error = 'Please select a service to apply for.';
    } elseif (empty($purpose)) {
        $error = 'Please specify the purpose of your request.';
    } elseif (!$terms) {
        $error = 'Please agree to the terms and conditions.';
    } elseif (!isset($user['account_verified']) || !$user['account_verified']) {
        $error = 'Your account must be verified before you can submit applications. Please complete your profile verification first.';
    } else {
        try {
            // Check if user already has a pending application for this service
            $stmt = $conn->prepare("SELECT id FROM applications WHERE user_id = ? AND service_type = ? AND service_id = ? AND status IN ('pending', 'processing')");
            $stmt->execute([$user_id, $service_type, $service_id]);
            if ($stmt->fetch()) {
                $error = 'You already have a pending application for this service.';
            } else {
                // Handle file uploads
                $uploaded_files = [];
                if (isset($_FILES['requirements']) && !empty($_FILES['requirements']['name'][0])) {
                    $upload_dir = '../uploads/applications/';
                    
                    // Create upload directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    foreach ($_FILES['requirements']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['requirements']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['requirements']['name'][$key];
                            $file_size = $_FILES['requirements']['size'][$key];
                            
                            // Validate file size (5MB limit)
                            if ($file_size > 5 * 1024 * 1024) {
                                $error = 'File size exceeds 5MB limit: ' . $file_name;
                                break;
                            }
                            
                            // Generate unique filename
                            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                            $unique_filename = uniqid() . '_' . $user_id . '.' . $file_extension;
                            $file_path = $upload_dir . $unique_filename;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $uploaded_files[] = $unique_filename;
                            } else {
                                $error = 'Failed to upload file: ' . $file_name;
                                break;
                            }
                        }
                    }
                }
                
                if (empty($error)) {
                    // Insert application with purpose and uploaded files
                    $uploaded_files_json = !empty($uploaded_files) ? json_encode($uploaded_files) : null;
                    $current_datetime = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("INSERT INTO applications (user_id, service_type, service_id, purpose, requirements_files, status, application_date) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                    $stmt->execute([$user_id, $service_type, $service_id, $purpose, $uploaded_files_json, $current_datetime]);
                    
                    // Create notification for barangay staff
                    $application_id = $conn->lastInsertId();
                    
                    // Get all barangay staff and admin users to notify
                    $staff_stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('barangay_staff', 'barangay_hall', 'admin')");
                    $staff_stmt->execute();
                    $staff_users = $staff_stmt->fetchAll();
                    
                    // Get service name for notification
                    if ($service_type === 'barangay') {
                        $service_stmt = $conn->prepare("SELECT service_name FROM barangay_services WHERE id = ?");
                    } else {
                        $service_stmt = $conn->prepare("SELECT service_name FROM health_services WHERE id = ?");
                    }
                    $service_stmt->execute([$service_id]);
                    $service_row = $service_stmt->fetch();
                    $service_name = $service_row['service_name'] ?? 'Unknown Service';
                    
                    // Insert notification for each staff member
                    $notif_stmt = $conn->prepare("INSERT INTO application_notifications (user_id, application_id, message, created_at) VALUES (?, ?, ?, ?)");
                    foreach ($staff_users as $staff) {
                        $message = "New application for {$service_name} from {$user['full_name']}";
                        $notif_stmt->execute([$staff['id'], $application_id, $message, $current_datetime]);
                    }
                    
                    // Redirect to prevent form resubmission
                    header('Location: services.php?message=Application submitted successfully!');
                    exit();
                }
            }
        } catch (Exception $e) {
            // Show more specific error message for debugging
            $error = 'Failed to submit application: ' . $e->getMessage();
            error_log("Application submission error: " . $e->getMessage());
            error_log("SQL Error Details: " . $e->getTraceAsString());
            // Also log the specific data being inserted for debugging
            error_log("Submission data - User ID: $user_id, Service Type: $service_type, Service ID: $service_id, Purpose: $purpose");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Certificates - Resident Dashboard</title>
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
                    <a href="services.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Available Services
                        <?php if ($patient_notification_count > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </a>
                    <a href="applications.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
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
                    <a href="appointments.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        My Appointments
                    </a>
                    <a href="community-concerns.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                        </svg>
                        Community Concerns
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Available Services</h1>
                <p class="text-gray-600">Browse and apply for barangay certificates and health services</p>
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

            

            <!-- Patient Registration Notifications -->
            <?php if ($patient_notification_count > 0): ?>
                <?php
                // Get the latest notification
                $stmt = $conn->prepare("
                    SELECT * FROM patient_registration_notifications 
                    WHERE user_id = ? AND is_read = 0 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $latest_notification = $stmt->fetch();
                
                // Only show notification if we actually got a result
                if ($latest_notification): ?>
                <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M13 13h.01M9 13h.01M13 9h.01M9 9h.01M13 5h.01M9 5h.01"></path>
                    </svg>
                    <div class="flex-1">
                        <h4 class="text-sm font-medium text-blue-800">Patient Registration Update</h4>
                        <p class="text-sm text-blue-700 mt-1"><?php echo htmlspecialchars($latest_notification['message'] ?? ''); ?></p>
                        <p class="text-xs text-blue-600 mt-1">Status: <?php echo ucfirst($latest_notification['status'] ?? ''); ?></p>
                    </div>
                    <button onclick="dismissNotification()" class="text-blue-400 hover:text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Service Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Barangay Services -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center mb-6">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 font-eb-garamond">Barangay Certificates</h3>
                    </div>
                    
                    <?php if (empty($barangay_services)): ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">No barangay certificates available</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($barangay_services as $service): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900 font-eb-garamond"><?php echo htmlspecialchars($service['service_name'] ?? ''); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                                            <div class="mt-2 text-sm text-gray-500">
                                                <span class="font-medium">Requirements:</span>
                                                <?php 
                                                if ($service['requirements']) {
                                                    $requirements = preg_split('/,(?![^(]*\))/', $service['requirements']);
                                                    echo '<ul class="list-disc list-inside space-y-1 mt-1">';
                                                    foreach ($requirements as $requirement) {
                                                        $requirement = trim($requirement);
                                                        // Format text inside parentheses to be italic, smaller, and gray
                                                        $formatted_requirement = preg_replace('/\(([^)]+)\)/', '<span class="text-xs italic text-gray-400">($1)</span>', htmlspecialchars($requirement));
                                                        echo '<li class="text-gray-600">' . $formatted_requirement . '</li>';
                                                    }
                                                    echo '</ul>';
                                                } else {
                                                    echo '<span class="text-gray-600">None specified</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <button onclick="applyForService('barangay', <?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['service_name'] ?? ''); ?>')" 
                                                class="ml-4 bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                            Request
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Health Services -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center mb-6">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 font-eb-garamond">Health Services</h3>
                    </div>
                    
                    <?php if ($patient_status === null): ?>
                        <!-- Registration Required Notice -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-yellow-800">Registration Required</h4>
                                    <p class="text-sm text-yellow-700 mt-1">You must register as a patient to access health services. Please click the button below to register.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Register as Patient Button -->
                        <div class="text-center py-8">
                            <div class="mb-4">
                                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2 font-eb-garamond">Register as Patient</h4>
                            <p class="text-sm text-gray-600 mb-6">Register with the health center to access medical services and appointments</p>
                            <button onclick="registerAsPatient()" 
                                    class="bg-barangay-green hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 flex items-center mx-auto">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Register as Patient
                            </button>
                        </div>
                    <?php else: ?>
                        <!-- Patient Registration Status Indicator -->
                        <div class="text-center py-8">
                            <div class="mb-6">
                                <!-- Status Circles -->
                                <div class="flex justify-center items-center space-x-4 mb-6">
                                    <!-- Submitted Circle -->
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 rounded-full bg-green-500 flex items-center justify-center mb-2">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </div>
                                        <span class="text-xs text-gray-600">Submitted</span>
                                    </div>
                                    
                                    <!-- Connecting Line -->
                                    <div class="w-8 h-0.5 bg-gray-300"></div>
                                    
                                    <!-- Review Circle -->
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 rounded-full <?php echo $patient_status === 'pending' ? 'bg-yellow-500' : ($patient_status === 'approved' ? 'bg-green-500' : 'bg-red-500'); ?> flex items-center justify-center mb-2">
                                            <?php if ($patient_status === 'pending'): ?>
                                                <svg class="w-6 h-6 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            <?php elseif ($patient_status === 'approved'): ?>
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-xs text-gray-600">Under Review</span>
                                    </div>
                                    
                                    <!-- Connecting Line -->
                                    <div class="w-8 h-0.5 <?php echo $patient_status === 'approved' ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                    
                                    <!-- Approved Circle -->
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 rounded-full <?php echo $patient_status === 'approved' ? 'bg-green-500' : 'bg-gray-300'; ?> flex items-center justify-center mb-2">
                                            <?php if ($patient_status === 'approved'): ?>
                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-xs text-gray-600">Approved</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status Message -->
                            <div class="mb-6">
                                <?php if ($patient_status === 'pending'): ?>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-medium text-yellow-800">Registration Under Review</h4>
                                                <p class="text-sm text-yellow-700 mt-1">Your patient registration is currently being reviewed by health center staff. You will be notified once a decision is made.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif ($patient_status === 'approved'): ?>
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-medium text-green-800">Registration Approved!</h4>
                                                <p class="text-sm text-green-700 mt-1">Your patient registration has been approved. You can now access health services and schedule appointments.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01m21 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-red-800">Registration Rejected</h4>
                                                <p class="text-sm text-red-700 mt-1 mb-3">Your patient registration was not approved. Please review the staff notes and submit a new registration.</p>
                                                <?php if (!empty($patient_registration['staff_notes'])): ?>
                                                    <div class="bg-white border border-red-200 rounded p-2 mb-3">
                                                        <p class="text-xs font-medium text-gray-700">Staff Notes:</p>
                                                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($patient_registration['staff_notes']); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                <button onclick="showPatientRegistrationModal()" 
                                                        class="bg-barangay-green hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300 inline-flex items-center">
                                                    <i class="fas fa-redo mr-2"></i>Register Again
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Registration Details -->
                            <?php if ($patient_registration): ?>
                                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                    <h5 class="text-sm font-medium text-gray-700 mb-3">Registration Details</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="font-medium text-gray-600">Blood Type:</span>
                                            <span class="ml-2 text-gray-800"><?php echo isset($patient_registration['blood_type']) ? htmlspecialchars($patient_registration['blood_type']) : 'Not specified'; ?></span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-600">Emergency Contact:</span>
                                            <span class="ml-2 text-gray-800"><?php echo isset($patient_registration['emergency_contact']) ? htmlspecialchars($patient_registration['emergency_contact']) : 'Not specified'; ?></span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-600">Birthday:</span>
                                            <span class="ml-2 text-gray-800">
                                                <?php 
                                                if ($user['birthday']) {
                                                    echo date('F j, Y', strtotime($user['birthday']));
                                                } else {
                                                    echo 'Not provided';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-gray-600">Age:</span>
                                            <span class="ml-2 text-gray-800">
                                                <?php 
                                                if ($user['birthday']) {
                                                    $age = calculateAge($user['birthday']);
                                                    echo $age . ' years old';
                                                } else {
                                                    echo 'Not available';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (isset($patient_registration['medical_history']) && $patient_registration['medical_history']): ?>
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-600">Medical History:</span>
                                                <span class="ml-2 text-gray-800"><?php echo htmlspecialchars($patient_registration['medical_history'] ?? ''); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($patient_registration['current_medications']) && $patient_registration['current_medications']): ?>
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-600">Current Medications:</span>
                                                <span class="ml-2 text-gray-800"><?php echo htmlspecialchars($patient_registration['current_medications'] ?? ''); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($patient_registration['insurance_info']) && $patient_registration['insurance_info']): ?>
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-600">Insurance Info:</span>
                                                <span class="ml-2 text-gray-800"><?php echo htmlspecialchars($patient_registration['insurance_info'] ?? ''); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="md:col-span-2">
                                            <span class="font-medium text-gray-600">Submitted:</span>
                                            <span class="ml-2 text-gray-800"><?php echo date('M j, Y g:i A', strtotime($patient_registration['created_at'])); ?></span>
                                        </div>
                                        <?php if (isset($patient_registration['approved_at']) && $patient_registration['approved_at']): ?>
                                            <div class="md:col-span-2">
                                                <span class="font-medium text-gray-600">Processed:</span>
                                                <span class="ml-2 text-gray-800"><?php echo date('M j, Y g:i A', strtotime($patient_registration['approved_at'])); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Health Services Section -->
                    <div class="border-t border-gray-200 pt-6">
                        <h5 class="text-sm font-medium text-gray-700 mb-4">
                            Available Health Services 
                            <?php if ($patient_status !== 'approved'): ?>
                                (After Registration)
                            <?php endif; ?>
                        </h5>
                        <?php if ($patient_status !== 'approved'): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <p class="text-sm text-yellow-800">
                                        <strong>Health services are locked.</strong> 
                                        <?php if ($patient_status === 'pending'): ?>
                                            Your patient registration is currently being reviewed. You'll be able to access health services once approved.
                                        <?php elseif ($patient_status === 'rejected'): ?>
                                            Your patient registration was not approved. Please contact the health center for more information.
                                        <?php else: ?>
                                            You need to register as a patient first to access health services.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="space-y-3 <?php echo ($patient_status === 'approved') ? '' : 'opacity-60'; ?>">
                            <?php if (empty($health_services)): ?>
                                <div class="text-center py-4">
                                    <p class="text-sm text-gray-500">No health services available</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($health_services, 0, 3) as $service): ?>
                                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900 font-eb-garamond"><?php echo htmlspecialchars($service['service_name'] ?? ''); ?></h4>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                                            <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                                <span><span class="font-medium">Fee:</span> ₱<?php echo number_format($service['fee'], 2); ?></span>
                                                <span><span class="font-medium">Processing:</span> Immediate</span>
                                            </div>
                                        </div>
                                            <div class="ml-4 flex items-center space-x-2">
                                                <?php if ($patient_status === 'approved'): ?>
                                                    <button onclick="requestHealthService(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['service_name'] ?? ''); ?>')" 
                                                            class="bg-barangay-green hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                        Request Service
                                        </button>
                                                <?php else: ?>
                                                    <div class="text-gray-400">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                                <?php if (count($health_services) > 3): ?>
                                    <div class="text-center py-2">
                                        <p class="text-sm text-gray-500">+<?php echo count($health_services) - 3; ?> more services available</p>
                        </div>
                                <?php endif; ?>
                    <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Modal -->
    <div id="applicationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="text-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Request Certificate</h3>
                <p class="text-gray-600 mt-2">Please fill out the form below to request your certificate</p>
                <p class="text-sm text-gray-500 mt-1">Certificate: <span id="serviceName" class="font-medium text-gray-700"></span></p>
            </div>
            
            <form method="POST" id="applicationForm" class="space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="service_type" id="serviceType">
                <input type="hidden" name="service_id" id="serviceId">
                
                <!-- Personal Information (Read-only) -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-3 font-eb-garamond">Personal Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Full Name</label>
                            <p class="text-sm text-gray-900 font-medium" id="displayName"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Age</label>
                            <p class="text-sm text-gray-900" id="displayAge">
                                <?php 
                                if (isset($user['birthday']) && $user['birthday']) {
                                    echo calculateAge($user['birthday']) . ' years old';
                                } else {
                                    echo 'Not set';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                            <p class="text-sm text-gray-900" id="displayAddress">
                                <?php 
                                if ($user['house_no'] && $user['street']) {
                                    echo htmlspecialchars($user['house_no'] . ' ' . $user['street'] . ', Zone 15, Brgy. 172, Caloocan City');
                                } elseif ($user['address']) {
                                    echo htmlspecialchars($user['address']);
                                } else {
                                    echo 'Not provided';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Years Living in Brgy. 172</label>
                            <p class="text-sm text-gray-900" id="displayYearsLiving">
                                <?php 
                                if (isset($user['year_started_living']) && $user['year_started_living']) {
                                    $current_year = date('Y');
                                    $years_living = $current_year - $user['year_started_living'];
                                    echo $years_living . ' years (since ' . $user['year_started_living'] . ')';
                                } else {
                                    echo 'Not provided';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Purpose -->
                <div>
                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">Purpose of Request *</label>
                    <select id="purpose" name="purpose" required onchange="togglePurposeFields()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                        <option value="">Select purpose</option>
                        <option value="Employment">Employment</option>
                        <option value="Business Permit">Business Permit</option>
                        <option value="School Requirements">School Requirements</option>
                        <option value="Government Transaction">Government Transaction</option>
                        <option value="Bank Transaction">Bank Transaction</option>
                        <option value="Travel Requirements">Travel Requirements</option>
                        <option value="Insurance">Insurance</option>
                        <option value="Legal Purposes">Legal Purposes</option>
                        <option value="Financial Assistance">Financial Assistance</option>
                        <option value="Community Events">Community Events</option>
                        <option value="Private Gatherings">Private Gatherings</option>
                        <option value="Fundraising">Fundraising</option>
                        <option value="Commercial Activities">Commercial Activities</option>
                        <option value="Religious Activity">Religious Activity</option>
                        <option value="Educational / Civic Activities">Educational / Civic Activities</option>
                        <option value="Community-Oriented Activities">Community-Oriented Activities</option>
                        <option value="Other">Other</option>
                    </select>
                    
                    <!-- Other Purpose Input Field (Hidden by default) -->
                    <div id="otherPurposeField" class="mt-3 hidden">
                        <label for="other_purpose" class="block text-sm font-medium text-gray-700 mb-2">Please specify other purpose *</label>
                        <input type="text" id="other_purpose" name="other_purpose" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                               placeholder="Please specify your purpose">
                    </div>
                    
                    <!-- Specify Input Field for Barangay Permit (Hidden by default) -->
                    <div id="specifyField" class="mt-3 hidden">
                        <label for="specify_purpose" class="block text-sm font-medium text-gray-700 mb-2">Please specify *</label>
                        <input type="text" id="specify_purpose" name="specify_purpose" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                               placeholder="Please provide more details about your activity/event">
                    </div>
                    
                    <!-- Barangay Permit Specific Fields (Hidden by default) -->
                    <div id="barangayPermitFields" class="mt-4 hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="event_datetime" class="block text-sm font-medium text-gray-700 mb-2">Date and Time of Event *</label>
                                <input type="text" id="event_datetime" name="event_datetime" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                                       placeholder="e.g., December 25, 2024 at 6:00 PM">
                            </div>
                            <div>
                                <label for="event_venue" class="block text-sm font-medium text-gray-700 mb-2">Venue/Location within Barangay *</label>
                                <input type="text" id="event_venue" name="event_venue" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                                       placeholder="e.g., Barangay Hall, Community Center, etc.">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Requirements Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Upload Requirements *</label>
                    <div id="requirementsContainer" class="space-y-3">
                        <!-- Requirements will be dynamically loaded here -->
                    </div>
                    
                    <!-- Additional Documents (Optional) -->
                    <div class="mt-4 pt-3 border-t border-gray-200">
                        <div class="flex items-center space-x-3">
                            <div class="flex-1">
                                <label for="additional_docs" class="block text-xs font-medium text-gray-600 mb-1">Additional Documents (Optional)</label>
                                <input type="file" id="additional_docs" name="requirements[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                                       class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-600 focus:border-transparent">
                            </div>
                            <div class="text-xs text-gray-500 w-16 text-center">5MB max</div>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-xs text-gray-500">
                        <p>Accepted formats: PDF, JPG, PNG, DOC, DOCX</p>
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="flex items-start space-x-3">
                    <input type="checkbox" id="terms" name="terms" required 
                           class="mt-1 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <label for="terms" class="text-sm text-gray-700">
                        I agree to the <a href="#" onclick="showTermsModal()" class="text-green-600 hover:text-green-700 underline">Terms and Conditions</a> and confirm that all uploaded documents are authentic and accurate. I understand that providing false information may result in the rejection of my application.
                    </label>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="hideApplicationModal()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Terms and Conditions</h3>
                <button onclick="hideTermsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="max-h-96 overflow-y-auto text-sm text-gray-700 space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">1. Application Process</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>All applications must be submitted with complete and accurate information</li>
                        <li>Required documents must be uploaded in the specified format (PDF, JPG, PNG, DOC, DOCX)</li>
                        <li>File size limit is 5MB per document</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">2. Document Requirements</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>All uploaded documents must be authentic and valid</li>
                        <li>Documents must be clearly legible and not expired</li>
                        <li>Providing false or fraudulent documents will result in immediate rejection</li>
                        <li>Additional documents may be requested during the review process</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">3. Processing and Approval</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Processing time varies depending on the type of certificate requested</li>
                        <li>You will be notified via email about the status of your application</li>
                        <li>Approved certificates can be claimed at the Barangay Hall during office hours</li>
                        <li>Rejected applications will include a reason for rejection</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">4. Privacy and Data Protection</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Your personal information will be handled in accordance with the Data Privacy Act</li>
                        <li>Information provided will only be used for the purpose of processing your application</li>
                        <li>Your data will not be shared with third parties without your consent</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">5. Liability</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>The Barangay Hall is not liable for any delays due to incomplete applications</li>
                        <li>Applicants are responsible for ensuring all information provided is accurate</li>
                        <li>Providing false information may result in legal consequences</li>
                    </ul>
                </div>
            </div>
            
            <div class="flex justify-end mt-6 pt-4 border-t">
                <button onclick="hideTermsModal()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                    I Understand
                </button>
            </div>
        </div>
    </div>

    <script>
        function applyForService(serviceType, serviceId, serviceName) {
            console.log('applyForService called:', serviceType, serviceId, serviceName);
            
            try {
                const modal = document.getElementById('applicationModal');
                const serviceTypeInput = document.getElementById('serviceType');
                const serviceIdInput = document.getElementById('serviceId');
                const serviceNameSpan = document.getElementById('serviceName');
                
                if (!modal || !serviceTypeInput || !serviceIdInput || !serviceNameSpan) {
                    throw new Error('Required modal elements not found');
                }
                
                serviceTypeInput.value = serviceType;
                serviceIdInput.value = serviceId;
                serviceNameSpan.textContent = serviceName;
                
                // Load requirements based on service type
                loadRequirements(serviceType, serviceName);
                
                modal.classList.remove('hidden');
                modal.style.display = 'block';
                console.log('Modal should be visible now');
            } catch (error) {
                console.error('Error in applyForService:', error);
                alert('Error opening form. Please try again.');
            }
        }
        
        function requestHealthService(serviceId, serviceName) {
            // Show custom modal instead of browser confirm
            showHealthServiceModal(serviceId, serviceName);
        }
        
        function showHealthServiceModal(serviceId, serviceName) {
            document.getElementById('healthServiceServiceName').textContent = serviceName;
            document.getElementById('healthServiceModal').classList.remove('hidden');
            document.getElementById('healthServiceModal').style.display = 'block';
            
            // Store service info for later use
            window.currentHealthService = { serviceId, serviceName };
        }
        
        function hideHealthServiceModal() {
            document.getElementById('healthServiceModal').classList.add('hidden');
            document.getElementById('healthServiceModal').style.display = 'none';
            window.currentHealthService = null;
        }
        
        function confirmHealthServiceRequest() {
            if (window.currentHealthService) {
                const { serviceId, serviceName } = window.currentHealthService;
                
                // Show loading state
                const okButton = document.querySelector('#healthServiceModal button[onclick="confirmHealthServiceRequest()"]');
                const originalText = okButton.textContent;
                okButton.textContent = 'Sending...';
                okButton.disabled = true;
                
                // Send appointment request to health center
                fetch('../health-center/appointments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'create_appointment_request',
                        service_id: serviceId,
                        service_name: serviceName,
                        user_id: <?php echo $_SESSION['user_id']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal instead of alert
                        showAppointmentSuccessModal();
                    } else {
                        // Show error message
                        alert('Error sending appointment request: ' + (data.message || 'Please try again.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error sending appointment request. Please try again.');
                })
                .finally(() => {
                    // Reset button state
                    okButton.textContent = originalText;
                    okButton.disabled = false;
                    hideHealthServiceModal();
                });
            } else {
                hideHealthServiceModal();
            }
        }
        
        function loadRequirements(serviceType, serviceName) {
            const container = document.getElementById('requirementsContainer');
            container.innerHTML = '';
            
            // Get the actual requirements from the service data displayed on the page
            let serviceRequirements = [];
            
            // Find the service card that matches the selected service
            const serviceCards = document.querySelectorAll('.border.border-gray-200.rounded-lg.p-4');
            let foundService = null;
            
            serviceCards.forEach(card => {
                const serviceTitle = card.querySelector('h4').textContent.trim();
                if (serviceTitle === serviceName) {
                    foundService = card;
                }
            });
            
            if (foundService) {
                // Extract requirements from the bullet points
                const requirementList = foundService.querySelector('ul');
                if (requirementList) {
                    const requirementItems = requirementList.querySelectorAll('li');
                    requirementItems.forEach((item, index) => {
                        // Clean up the requirement text (remove parentheses formatting)
                        let requirementText = item.textContent.trim();
                        // Remove parentheses and their content for the label
                        requirementText = requirementText.replace(/\s*\([^)]*\)/g, '');
                        
                        serviceRequirements.push({
                            id: `requirement_${index}`,
                            label: requirementText,
                            required: true
                        });
                    });
                }
            }
            
            // If no requirements found from the page, use default based on service type
            if (serviceRequirements.length === 0) {
                if (serviceType === 'barangay') {
                    if (serviceName.toLowerCase().includes('clearance')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('residency')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('indigency')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true },
                            { id: 'income_declaration', label: 'Income Declaration', required: true },
                            { id: 'family_composition', label: 'Family Composition', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('business') && !serviceName.toLowerCase().includes('cedula')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true },
                            { id: 'business_registration', label: 'Business Registration', required: true },
                            { id: 'location_clearance', label: 'Location Clearance', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('cedula')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('permit') && serviceName.toLowerCase().includes('cedula')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true }
                        ];
                    } else if (serviceName.toLowerCase().includes('barangay permit')) {
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true },
                            { id: 'event_details', label: 'Event/Activity Details', required: true },
                            { id: 'location_clearance', label: 'Location Clearance', required: true }
                        ];
                    } else {
                        // Default for other barangay certificates
                        serviceRequirements = [
                            { id: 'valid_id', label: 'Valid ID', required: true },
                            { id: 'endorsement_slip', label: 'Endorsement Slip from Purok Leader', required: true }
                        ];
                    }
                } else if (serviceType === 'health') {
                    serviceRequirements = [
                        { id: 'valid_id', label: 'Valid ID', required: true },
                        { id: 'medical_form', label: 'Medical Certificate Form', required: true }
                    ];
                }
            }
            
            // Render requirements
            serviceRequirements.forEach((req, index) => {
                const requirementDiv = document.createElement('div');
                requirementDiv.className = 'flex items-center space-x-3';
                requirementDiv.innerHTML = `
                    <div class="flex-1">
                        <label for="${req.id}" class="block text-xs font-medium text-gray-600 mb-1">${req.label}</label>
                        <input type="file" id="${req.id}" name="requirements[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                               class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-600 focus:border-transparent" ${req.required ? 'required' : ''}>
                    </div>
                    <div class="text-xs text-gray-500 w-16 text-center">5MB max</div>
                `;
                container.appendChild(requirementDiv);
            });
        }
        
        function hideApplicationModal() {
            const modal = document.getElementById('applicationModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
            // Reset form when modal is closed
            document.getElementById('applicationForm').reset();
        }
        

        
        function showTermsModal() {
            const modal = document.getElementById('termsModal');
            modal.classList.remove('hidden');
            modal.style.display = 'block';
        }
        
        function hideTermsModal() {
            const modal = document.getElementById('termsModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        
        function togglePurposeFields() {
            const purposeSelect = document.getElementById('purpose');
            const otherField = document.getElementById('otherPurposeField');
            const otherInput = document.getElementById('other_purpose');
            const specifyField = document.getElementById('specifyField');
            const specifyInput = document.getElementById('specify_purpose');
            const barangayPermitFields = document.getElementById('barangayPermitFields');
            const eventDatetimeInput = document.getElementById('event_datetime');
            const eventVenueInput = document.getElementById('event_venue');
            
            // Get the current service name to check if it's Barangay Permit
            const serviceNameSpan = document.getElementById('serviceName');
            const isBarangayPermit = serviceNameSpan && serviceNameSpan.textContent.includes('Barangay Permit');
            
            // Hide all fields first
                otherField.classList.add('hidden');
            specifyField.classList.add('hidden');
            barangayPermitFields.classList.add('hidden');
                otherInput.required = false;
            specifyInput.required = false;
            eventDatetimeInput.required = false;
            eventVenueInput.required = false;
                otherInput.value = '';
            specifyInput.value = '';
            eventDatetimeInput.value = '';
            eventVenueInput.value = '';
            
            if (purposeSelect.value === 'Other') {
                otherField.classList.remove('hidden');
                otherInput.required = true;
            } else if (isBarangayPermit && isBarangayPermitPurpose(purposeSelect.value)) {
                // Show specify field and barangay permit fields for Barangay Permit specific purposes
                specifyField.classList.remove('hidden');
                barangayPermitFields.classList.remove('hidden');
                specifyInput.required = true;
                eventDatetimeInput.required = true;
                eventVenueInput.required = true;
            }
        }
        
        function isBarangayPermitPurpose(purpose) {
            const barangayPermitPurposes = [
                'Community Events',
                'Private Gatherings', 
                'Fundraising',
                'Commercial Activities',
                'Religious Activity',
                'Educational / Civic Activities',
                'Community-Oriented Activities'
            ];
            return barangayPermitPurposes.includes(purpose);
        }

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

        // Close logout modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLogoutModal();
                hideHealthServiceModal();
                hideAppointmentSuccessModal();
            }
        });

        // Close health service modal when clicking outside
        document.getElementById('healthServiceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideHealthServiceModal();
            }
        });

        // Appointment Success Modal Functions
        function showAppointmentSuccessModal() {
            document.getElementById('appointmentSuccessModal').classList.remove('hidden');
            document.getElementById('appointmentSuccessModal').style.display = 'block';
        }

        function hideAppointmentSuccessModal() {
            document.getElementById('appointmentSuccessModal').classList.add('hidden');
            document.getElementById('appointmentSuccessModal').style.display = 'none';
        }

        // Close success modal when clicking outside
        document.getElementById('appointmentSuccessModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideAppointmentSuccessModal();
            }
        });
        
        // Patient Registration Function
        function registerAsPatient() {
            // Check if user is already registered as a patient
            fetch('check_patient_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.is_registered) {
                        alert('You are already registered as a patient!');
                    } else if (data.has_pending_registration) {
                        alert('You have a pending patient registration. Please wait for approval.');
                    } else {
                        // Show patient registration modal
                        showPatientRegistrationModal();
                    }
                })
                .catch(error => {
                    console.error('Error checking patient status:', error);
                    // If there's an error, show the registration modal anyway
                    showPatientRegistrationModal();
                });
        }
        
        function showPatientRegistrationModal() {
            const modal = document.getElementById('patientRegistrationModal');
            modal.classList.remove('hidden');
            modal.style.display = 'block';
        }
        
        function hidePatientRegistrationModal() {
            const modal = document.getElementById('patientRegistrationModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
            // Reset form when modal is closed
            document.getElementById('patientRegistrationForm').reset();
        }
        
        function showPatientTermsModal() {
            const modal = document.getElementById('patientTermsModal');
            modal.classList.remove('hidden');
            modal.style.display = 'block';
        }
        
        function hidePatientTermsModal() {
            const modal = document.getElementById('patientTermsModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    </script>
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>

    <!-- Patient Registration Modal -->
    <div id="patientRegistrationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="text-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Register as Patient</h3>
                <p class="text-gray-600 mt-2">Please fill out the form below to register with the health center</p>
            </div>
            
            <form method="POST" id="patientRegistrationForm" class="space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register_patient">
                
                <!-- Personal Information (Read-only) -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-700 mb-3 font-eb-garamond">Personal Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Full Name</label>
                            <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Age</label>
                            <p class="text-sm text-gray-900">
                                <?php 
                                if (isset($user['birthday']) && $user['birthday']) {
                                    echo calculateAge($user['birthday']) . ' years old';
                                } else {
                                    echo 'Not set';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
                            <p class="text-sm text-gray-900">
                                <?php 
                                if ($user['house_no'] && $user['street']) {
                                    echo htmlspecialchars($user['house_no'] . ' ' . $user['street'] . ', Zone 15, Brgy. 172, Caloocan City');
                                } elseif ($user['address']) {
                                    echo htmlspecialchars($user['address']);
                                } else {
                                    echo 'Not provided';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Contact Number</label>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Medical Information -->
                <div>
                    <h4 class="text-sm font-medium text-gray-700 mb-3 font-eb-garamond">Medical Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="blood_type" class="block text-sm font-medium text-gray-700 mb-2">Blood Type</label>
                            <select id="blood_type" name="blood_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent">
                                <option value="">Select blood type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="Unknown">Unknown</option>
                            </select>
                        </div>
                        <div>
                            <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                                   placeholder="Emergency contact name and number">
                        </div>
                    </div>
                </div>
                
                <!-- Medical History -->
                <div>
                    <label for="medical_history" class="block text-sm font-medium text-gray-700 mb-2">Medical History (Optional)</label>
                    <textarea id="medical_history" name="medical_history" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                              placeholder="Please list any existing medical conditions, allergies, or previous surgeries..."></textarea>
                </div>
                
                <!-- Current Medications -->
                <div>
                    <label for="current_medications" class="block text-sm font-medium text-gray-700 mb-2">Current Medications (Optional)</label>
                    <textarea id="current_medications" name="current_medications" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                              placeholder="List any medications you are currently taking..."></textarea>
                </div>
                
                <!-- Insurance Information -->
                <div>
                    <label for="insurance_info" class="block text-sm font-medium text-gray-700 mb-2">Insurance Information (Optional)</label>
                    <input type="text" id="insurance_info" name="insurance_info" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent"
                           placeholder="e.g., PhilHealth, Private Insurance, etc.">
                </div>
                
                <!-- Terms and Conditions -->
                <div class="flex items-start space-x-3">
                    <input type="checkbox" id="patient_terms" name="patient_terms" required 
                           class="mt-1 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <label for="patient_terms" class="text-sm text-gray-700">
                        I agree to the <a href="#" onclick="showPatientTermsModal()" class="text-green-600 hover:text-green-700 underline">Terms and Conditions</a> and confirm that all information provided is accurate. I understand that providing false information may result in the rejection of my registration.
                    </label>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="hidePatientRegistrationModal()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                        Submit Registration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient Terms Modal -->
    <div id="patientTermsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-eb-garamond">Patient Registration Terms</h3>
                <button onclick="hidePatientTermsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="max-h-96 overflow-y-auto text-sm text-gray-700 space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">1. Registration Process</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Patient registration requires approval from health center staff</li>
                        <li>All information provided must be accurate and up-to-date</li>
                        <li>You will be notified via email once your registration is approved</li>
                        <li>Registration may take 1-3 business days to process</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">2. Medical Information</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Medical information will be kept confidential and secure</li>
                        <li>Information will only be shared with authorized health center staff</li>
                        <li>You are responsible for keeping your medical information updated</li>
                        <li>Providing false medical information may result in registration rejection</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">3. Health Services Access</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Approved patients can access all health center services</li>
                        <li>Appointments can be scheduled through the online system</li>
                        <li>Medical records will be maintained securely</li>
                        <li>You can request copies of your medical records</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2 font-eb-garamond">4. Privacy and Data Protection</h4>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>Your health information is protected under medical privacy laws</li>
                        <li>Information will not be shared without your consent</li>
                        <li>You have the right to access and correct your medical information</li>
                        <li>Data is stored securely and encrypted</li>
                    </ul>
                </div>
            </div>
            
            <div class="flex justify-end mt-6 pt-4 border-t">
                <button onclick="hidePatientTermsModal()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                    I Understand
                </button>
            </div>
        </div>
    </div>

    <!-- Appointment Success Modal -->
    <div id="appointmentSuccessModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="text-center">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                
                <!-- Title -->
                <h3 class="text-xl font-bold text-gray-900 mb-2 font-eb-garamond">Request Sent Successfully!</h3>
                
                <!-- Message -->
                <p class="text-gray-600 mb-6">
                    Your appointment request has been sent successfully! The Health Center Staff will contact you with confirmation.
                </p>
                
                <!-- Action Button -->
                <div class="flex justify-center">
                    <button onclick="hideAppointmentSuccessModal()" 
                            class="px-8 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Service Request Modal -->
    <div id="healthServiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-2xl bg-white">
            <div class="text-center">
                <!-- Icon -->
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
                
                <!-- Title -->
                <h3 class="text-xl font-bold text-gray-900 mb-2 font-eb-garamond">Request Medical Consultation?</h3>
                
                <!-- Service Name -->
                <p class="text-gray-600 mb-4">
                    You are requesting: <span id="healthServiceServiceName" class="font-medium text-gray-900"></span>
                </p>
                
                <!-- Description -->
                <p class="text-sm text-gray-500 mb-6">
                    Once your request is submitted, we'll send a confirmation to your email or notify you here in your account.
                </p>
                
                <!-- Action Buttons -->
                <div class="flex justify-center space-x-4">
                    <button onclick="hideHealthServiceModal()" 
                            class="px-6 py-2 text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmHealthServiceRequest()" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2 font-eb-garamond">Confirm Logout</h3>
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

        // Dismiss notification function
        function dismissNotification() {
            // Hide the notification element
            const notificationElement = document.querySelector('.bg-blue-50.border.border-blue-200');
            if (notificationElement) {
                notificationElement.style.display = 'none';
            }
            
            // Mark notifications as read via AJAX
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove notification badge from sidebar
                    const notificationBadge = document.querySelector('.bg-red-500.text-white.text-xs');
                    if (notificationBadge) {
                        notificationBadge.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error dismissing notification:', error);
            });
        }

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }
    </script>
</body>
</html>
