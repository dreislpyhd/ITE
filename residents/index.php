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

// Include notification badge helper
require_once 'includes/notification_badge.php';

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's applications
$stmt = $conn->prepare("
    SELECT a.*, 
           CASE 
               WHEN a.service_type = 'barangay' THEN bs.service_name
               WHEN a.service_type = 'health' THEN hs.service_name
               ELSE 'Unknown Service'
           END as service_name
    FROM applications a
    LEFT JOIN barangay_services bs ON a.service_type = 'barangay' AND a.service_id = bs.id
    LEFT JOIN health_services hs ON a.service_type = 'health' AND a.service_id = hs.id
    WHERE a.user_id = ?
    ORDER BY a.application_date DESC
");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll();

// Get available services
$barangay_services = $conn->query("SELECT * FROM barangay_services WHERE status = 'active' ORDER BY service_name")->fetchAll();
$health_services = $conn->query("SELECT * FROM health_services WHERE status = 'active' ORDER BY service_name")->fetchAll();

$message = '';
$error = '';

// Check if user needs to change password
// All residents must change password if they haven't done so yet
$needs_password_change = !isset($user['password_changed']) || $user['password_changed'] == 0 || $user['password_changed'] === false;

// Debug: Log password check
error_log("Password check for user {$user['username']}: password_changed=" . ($user['password_changed'] ?? 'null') . ", needs_change=" . ($needs_password_change ? 'true' : 'false'));

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error = 'Password must contain at least one special character (!@#$%^&*(),.?":{}|<>).';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $message = 'Password changed successfully!';
                $needs_password_change = false;
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = 'Failed to change password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error changing password: ' . $e->getMessage();
        }
    }
}

// Handle new application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'apply') {
    $service_type = $_POST['service_type'];
    $service_id = $_POST['service_id'];
    
    if (empty($service_type) || empty($service_id)) {
        $error = 'Please select a service to apply for.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO applications (user_id, service_type, service_id, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $service_type, $service_id]);
            // Redirect to prevent form resubmission
            header('Location: index.php?message=Application submitted successfully!');
            exit();
        } catch (Exception $e) {
            $error = 'Failed to submit application. Please try again.';
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
    <title>Resident Dashboard - Barangay Management System</title>
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
                    <a href="index.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
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
                     <?php if ($user['account_verified']): ?>
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
                         <a href="applications.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                             <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                             </svg>
                             My Applications
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
                    <?php else: ?>
                        <div class="px-4 py-2 text-gray-400 cursor-pointer hover:bg-gray-50" onclick="showVerificationModal()">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <span class="text-sm">Available Services</span>
                                <span class="ml-2 text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full">Locked</span>
                            </div>
                        </div>
                        <div class="px-4 py-2 text-gray-400 cursor-pointer hover:bg-gray-50" onclick="showVerificationModal()">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-sm">My Applications</span>
                                <span class="ml-2 text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full">Locked</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Logout Button (Always visible) -->
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Welcome Back!</h1>
                <p class="text-gray-600">Manage your applications and access barangay services</p>
            </div>
            
            <!-- Debug Info (Remove after testing) -->
            <!-- Password needs change: <?php echo $needs_password_change ? 'YES' : 'NO'; ?> -->
            
            <!-- Password Change Required Warning -->
            <?php if ($needs_password_change): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-6 h-6 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-red-800 mb-1">Password Change Required</h3>
                        <p class="text-red-700 mb-3">For security reasons, you must change your password before accessing the system. Your current password is the default password.</p>
                        <button onclick="showPasswordChangeModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Change Password Now
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Messages -->


            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

                                                  <!-- Account Verification Status -->
                         <div class="mb-6 bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                             <h3 class="text-lg font-semibold text-gray-900 mb-4 font-eb-garamond">Account Verification Status</h3>
                             
                             <!-- Verification Progress Steps -->
                             <div class="flex items-center justify-between mb-4">
                                 <!-- Step 1: Documents Uploaded -->
                                 <div class="flex flex-col items-center">
                                     <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center <?php echo ($user['purok_endorsement'] && $user['valid_id']) ? 'bg-green-500 border-green-500 text-white' : 'bg-gray-100 border-gray-300 text-gray-400'; ?>">
                                         <?php if ($user['purok_endorsement'] && $user['valid_id']): ?>
                                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                             </svg>
                                         <?php else: ?>
                                             <span class="text-sm font-medium">1</span>
                                         <?php endif; ?>
                                     </div>
                                     <span class="text-xs text-gray-600 mt-2 text-center">Documents<br>Uploaded</span>
                                 </div>
                                 
                                 <!-- Connector Line 1 -->
                                 <div class="flex-1 h-0.5 mx-4 <?php echo ($user['purok_endorsement'] && $user['valid_id']) ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                 
                                 <!-- Step 2: Waiting for Verification -->
                                 <div class="flex flex-col items-center">
                                     <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center <?php echo ($user['purok_endorsement'] && $user['valid_id'] && !$user['account_verified']) ? 'bg-yellow-500 border-yellow-500 text-white' : ($user['account_verified'] ? 'bg-green-500 border-green-500 text-white' : 'bg-gray-100 border-gray-300 text-gray-400'); ?>">
                                         <?php if ($user['purok_endorsement'] && $user['valid_id'] && !$user['account_verified']): ?>
                                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                             </svg>
                                         <?php elseif ($user['account_verified']): ?>
                                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                             </svg>
                                         <?php else: ?>
                                             <span class="text-sm font-medium">2</span>
                                         <?php endif; ?>
                                     </div>
                                     <span class="text-xs text-gray-600 mt-2 text-center">Waiting for<br>Verification</span>
                                 </div>
                                 
                                 <!-- Connector Line 2 -->
                                 <div class="flex-1 h-0.5 mx-4 <?php echo ($user['account_verified']) ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>
                                 
                                 <!-- Step 3: Verified -->
                                 <div class="flex flex-col items-center">
                                     <div class="w-12 h-12 rounded-full border-2 flex items-center justify-center <?php echo ($user['account_verified']) ? 'bg-green-500 border-green-500 text-white' : 'bg-gray-100 border-gray-300 text-gray-400'; ?>">
                                         <?php if ($user['account_verified']): ?>
                                             <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                             </svg>
                                         <?php else: ?>
                                             <span class="text-sm font-medium">3</span>
                                         <?php endif; ?>
                                     </div>
                                     <span class="text-xs text-gray-600 mt-2 text-center">Account<br>Verified</span>
                                 </div>
                             </div>
                             
                             <!-- Status Message -->
                             <?php if (!$user['account_verified']): ?>
                                 <?php if ($user['purok_endorsement'] && $user['valid_id']): ?>
                                     <!-- Documents uploaded, waiting for verification -->
                                     <div class="text-center py-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                         <p class="text-yellow-800 text-sm font-medium">📋 Documents Submitted Successfully!</p>
                                         <p class="text-yellow-700 text-xs mt-1">Your account is now pending verification by Barangay Staff. You will be notified once verified.</p>
                                     </div>
                                 <?php else: ?>
                                     <!-- Documents not uploaded -->
                                     <div class="text-center py-3 bg-red-50 border border-red-200 rounded-lg">
                                         <p class="text-red-800 text-sm font-medium">⚠️ Action Required: Upload Required Documents</p>
                                         <p class="text-red-700 text-xs mt-1">Please upload your Purok Leader Endorsement and Valid ID/Proof of Billing in your profile.</p>
                                         <p class="text-red-600 text-xs mt-2 font-semibold">⚠️ Important: Accounts not verified within 30 days will be automatically deleted.</p>
                                         <a href="profile.php" class="inline-block mt-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-xs font-medium transition duration-300">
                                             Go to Profile
                                         </a>
                                     </div>
                                 <?php endif; ?>
                             <?php else: ?>
                                 <!-- Account verified -->
                                 <div class="text-center py-3 bg-green-50 border border-green-200 rounded-lg">
                                     <p class="text-green-800 text-sm font-medium">✅ Account Verified Successfully!</p>
                                     <p class="text-green-700 text-xs mt-1">You now have full access to all features and services.</p>
                                 </div>
                             <?php endif; ?>
                         </div>



            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Applications -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">Recent Applications</h3>
                        <?php if ($user['account_verified']): ?>
                            <a href="applications.php" class="text-barangay-orange hover:text-orange-600 text-sm font-medium">View All</a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$user['account_verified']): ?>
                        <!-- Unverified Account - Show Verification Prompt -->
                        <div class="text-center py-8">
                            <div class="mx-auto h-12 w-12 text-gray-400 mb-4">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">You need to verify your account to view applications.</p>
                            <button onclick="showVerificationModal()" class="bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-300">
                                Verify My Account
                            </button>
                        </div>
                    <?php elseif (empty($applications)): ?>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">No applications yet</p>
                            <p class="text-xs text-gray-400">Apply for barangay services to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($applications, 0, 5) as $app): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($app['service_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($app['application_date'])); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php echo $app['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                              ($app['status'] === 'pending' ? 'bg-orange-100 text-orange-800' : 
                                              ($app['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>


            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 mb-4">
                    <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2 font-eb-garamond">Account Verification Required</h3>
                                 <div class="mt-2 px-7 py-3">
                     <p class="text-sm text-gray-500 mb-4">
                         Your account is not yet verified. You need to complete your profile verification before you can apply for any services.
                     </p>
                     <p class="text-sm text-gray-500 mb-4">
                         Please upload the required documents (Purok Leader Endorsement and Valid ID/Proof of Billing) in your profile page.
                     </p>
                     <p class="text-sm text-red-600 font-semibold mb-6">
                         ⚠️ Important: Accounts not verified within 30 days will be automatically deleted.
                     </p>
                 </div>
                <div class="flex justify-center space-x-3">
                    <button onclick="hideVerificationModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-300">
                        Cancel
                    </button>
                    <a href="profile.php" class="px-4 py-2 bg-barangay-orange text-white rounded-md hover:bg-orange-600 transition duration-300">
                        Verify My Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Verification Modal Functions
        function showVerificationModal() {
            document.getElementById('verificationModal').classList.remove('hidden');
        }

        function hideVerificationModal() {
            document.getElementById('verificationModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('verificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideVerificationModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideVerificationModal();
                hideLogoutModal();
            }
        });

        // Password Change Modal Functions
        function showPasswordChangeModal() {
            document.getElementById('passwordChangeModal').classList.remove('hidden');
        }

        function hidePasswordChangeModal() {
            document.getElementById('passwordChangeModal').classList.add('hidden');
        }

        // Toggle Password Visibility
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                // Change to "eye-off" icon
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                `;
            } else {
                input.type = 'password';
                // Change to "eye" icon
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                `;
            }
        }

        // Real-time password validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Check length
                const lengthCheck = document.getElementById('length-check');
                if (password.length >= 8) {
                    lengthCheck.classList.remove('text-gray-500');
                    lengthCheck.classList.add('text-green-600', 'font-semibold');
                } else {
                    lengthCheck.classList.remove('text-green-600', 'font-semibold');
                    lengthCheck.classList.add('text-gray-500');
                }
                
                // Check uppercase
                const uppercaseCheck = document.getElementById('uppercase-check');
                if (/[A-Z]/.test(password)) {
                    uppercaseCheck.classList.remove('text-gray-500');
                    uppercaseCheck.classList.add('text-green-600', 'font-semibold');
                } else {
                    uppercaseCheck.classList.remove('text-green-600', 'font-semibold');
                    uppercaseCheck.classList.add('text-gray-500');
                }
                
                // Check lowercase
                const lowercaseCheck = document.getElementById('lowercase-check');
                if (/[a-z]/.test(password)) {
                    lowercaseCheck.classList.remove('text-gray-500');
                    lowercaseCheck.classList.add('text-green-600', 'font-semibold');
                } else {
                    lowercaseCheck.classList.remove('text-green-600', 'font-semibold');
                    lowercaseCheck.classList.add('text-gray-500');
                }
                
                // Check number
                const numberCheck = document.getElementById('number-check');
                if (/[0-9]/.test(password)) {
                    numberCheck.classList.remove('text-gray-500');
                    numberCheck.classList.add('text-green-600', 'font-semibold');
                } else {
                    numberCheck.classList.remove('text-green-600', 'font-semibold');
                    numberCheck.classList.add('text-gray-500');
                }
                
                // Check special character
                const specialCheck = document.getElementById('special-check');
                if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                    specialCheck.classList.remove('text-gray-500');
                    specialCheck.classList.add('text-green-600', 'font-semibold');
                } else {
                    specialCheck.classList.remove('text-green-600', 'font-semibold');
                    specialCheck.classList.add('text-gray-500');
                }
                
                // Check if passwords match
                checkPasswordMatch();
            });
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const matchMessage = document.getElementById('match-message');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    matchMessage.textContent = '✓ Passwords match';
                    matchMessage.classList.remove('text-red-600');
                    matchMessage.classList.add('text-green-600');
                } else {
                    matchMessage.textContent = '✗ Passwords do not match';
                    matchMessage.classList.remove('text-green-600');
                    matchMessage.classList.add('text-red-600');
                }
            } else {
                matchMessage.textContent = '';
            }
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
    </script>
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>

    <!-- Password Change Modal -->
    <div id="passwordChangeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full <?php echo $needs_password_change ? '' : 'hidden'; ?> z-50">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-xl bg-white">
            <div class="mt-3">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2 text-center font-eb-garamond">Change Your Password</h3>
                <p class="text-sm text-gray-600 mb-4 text-center">Create a strong password with at least 8 characters</p>
                
                <form method="POST" id="passwordChangeForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            <button type="button" onclick="togglePasswordVisibility('new_password', 'toggleNewPassword')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 hover:text-gray-800">
                                <svg id="toggleNewPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="mt-2 text-xs text-gray-600">
                            <p class="font-semibold mb-1">Password must contain:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li id="length-check" class="text-gray-500">At least 8 characters</li>
                                <li id="uppercase-check" class="text-gray-500">One uppercase letter (A-Z)</li>
                                <li id="lowercase-check" class="text-gray-500">One lowercase letter (a-z)</li>
                                <li id="number-check" class="text-gray-500">One number (0-9)</li>
                                <li id="special-check" class="text-gray-500">One special character (!@#$%^&*)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            <button type="button" onclick="togglePasswordVisibility('confirm_password', 'toggleConfirmPassword')" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 hover:text-gray-800">
                                <svg id="toggleConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <p id="match-message" class="mt-1 text-sm"></p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="submit" class="bg-barangay-orange hover:bg-orange-600 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                            Change Password
                        </button>
                    </div>
                </form>
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
    </script>
</body>
</html>
