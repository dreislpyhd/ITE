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

 // Function to calculate age from birthday
 function calculateAge($birthday) {
     if (!$birthday) return null;
     $birth = new DateTime($birthday);
     $today = new DateTime();
     $age = $today->diff($birth);
     return $age->y;
 }
 
 // Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Debug: Check if user exists
if (!$user) {
    error_log("User not found in database for ID: " . $user_id);
    header('Location: ../auth/login.php?error=User account not found');
    exit();
}

$message = '';
$error = '';



// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Email address is already taken by another user.';
            } else {
                                 // Update basic info
                 $birthday = $_POST['birthday'] ?? null;
                 $civil_status = $_POST['civil_status'] ?? null;
                 $gender = $_POST['gender'] ?? null;
                 $year_started_living = $_POST['year_started_living'] ?? null;
                 
                 $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, birthday = ?, civil_status = ?, gender = ?, year_started_living = ? WHERE id = ?");
                 $stmt->execute([$email, $phone, $birthday, $civil_status, $gender, $year_started_living, $user_id]);
                
                // Handle password change if provided
                if (!empty($current_password)) {
                    if (!password_verify($current_password, $user['password'])) {
                        $error = 'Current password is incorrect.';
                    } elseif (empty($new_password)) {
                        $error = 'New password is required when changing password.';
                                         } elseif (strlen($new_password) < 10) {
                         $error = 'New password must be at least 10 characters long.';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'New password and confirmation password do not match.';
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $user_id]);
                        // Redirect to prevent form resubmission
                        header('Location: profile.php?message=Profile and password updated successfully!');
                        exit();
                    }
                } else {
                    // Redirect to prevent form resubmission
                    header('Location: profile.php?message=Profile updated successfully!');
                    exit();
                }
            }
                 } catch (Exception $e) {
             $error = 'Failed to update profile. Please try again.';
             error_log("Profile update error: " . $e->getMessage());
         }
    }
}

// Handle document uploads
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_documents') {
    $upload_dir = '../uploads/documents/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_success = true;
    $uploaded_files = [];
    
    // Handle Purok Leader Endorsement
    if (isset($_FILES['purok_endorsement']) && $_FILES['purok_endorsement']['error'] == 0) {
        $file = $_FILES['purok_endorsement'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        
        if (in_array($file['type'], $allowed_types)) {
            $filename = 'purok_endorsement_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploaded_files['purok_endorsement'] = $filename;
            } else {
                $upload_success = false;
                $error = 'Failed to upload Purok Leader Endorsement.';
            }
        } else {
            $upload_success = false;
            $error = 'Purok Leader Endorsement must be a JPEG, PNG, JPG, or PDF file.';
        }
    }
    
    // Handle Valid ID/Proof of Billing
    if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] == 0) {
        $file = $_FILES['valid_id'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        
        if (in_array($file['type'], $allowed_types)) {
            $filename = 'valid_id_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $uploaded_files['valid_id'] = $filename;
            } else {
                $upload_success = false;
                $error = 'Failed to upload Valid ID/Proof of Billing.';
            }
        } else {
            $upload_success = false;
            $error = 'Valid ID/Proof of Billing must be a JPEG, PNG, JPG, or PDF file.';
        }
    }
    
    // Update database with uploaded files
    if ($upload_success && !empty($uploaded_files)) {
        try {
            $update_fields = [];
            $params = [];
            
            if (isset($uploaded_files['purok_endorsement'])) {
                $update_fields[] = 'purok_endorsement = ?';
                $params[] = $uploaded_files['purok_endorsement'];
            }
            
            if (isset($uploaded_files['valid_id'])) {
                $update_fields[] = 'valid_id = ?';
                $params[] = $uploaded_files['valid_id'];
            }
            
            if (!empty($update_fields)) {
                $params[] = $user_id;
                $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?");
                $stmt->execute($params);
                // Redirect to prevent form resubmission
                header('Location: profile.php?message=Documents uploaded successfully!');
                exit();
            }
        } catch (Exception $e) {
            $error = 'Failed to save document information. Please try again.';
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

// Refresh user data after any updates
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Resident Dashboard</title>
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
                    <a href="profile.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        My Profile
                    </a>
                                         <?php if (isset($user['account_verified']) && $user['account_verified']): ?>
                         <a href="services.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                             <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                             </svg>
                             Available Services
                             <?php if (isset($notification_counts['patient_registrations']) && $notification_counts['patient_registrations'] > 0): ?>
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
                <div class="flex items-center space-x-3">
                    <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">My Profile</h1>
                    <?php if (isset($user['account_verified']) && $user['account_verified']): ?>
                        <div class="flex items-center space-x-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Verified Account</span>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="text-gray-600">Manage your account information and settings</p>
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



            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Profile Information -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-eb-garamond font-semibold text-gray-900 mb-6">Profile Information</h3>
                    
                    <div class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-16 h-16 bg-barangay-orange rounded-full flex items-center justify-center">
                                <span class="text-white text-xl font-bold"><?php echo isset($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U'; ?></span>
                            </div>
                            <div>
                                <h4 class="text-lg font-medium text-gray-900"><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'Unknown User'; ?></h4>
                                <p class="text-sm text-gray-500">Resident</p>
                            </div>
                        </div>
                        
                                                 <div class="border-t pt-4 space-y-3">
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Username:</span>
                                 <p class="text-gray-900"><?php echo isset($user['username']) ? htmlspecialchars($user['username']) : 'Unknown'; ?></p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Full Name:</span>
                                 <p class="text-gray-900"><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'Not provided'; ?></p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Birthday:</span>
                                 <p class="text-gray-900">
                                     <?php if (isset($user['birthday']) && $user['birthday']): ?>
                                         <?php echo date('F j, Y', strtotime($user['birthday'])); ?>
                                         <span class="ml-2 text-sm text-gray-500">(<?php echo calculateAge($user['birthday']); ?> years old)</span>
                                     <?php else: ?>
                                         <span class="text-red-600">Not provided</span>
                                         <span class="ml-2 text-xs text-red-500 italic">!please update</span>
                                     <?php endif; ?>
                                 </p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Gender:</span>
                                 <p class="text-gray-900">
                                     <?php if (isset($user['gender']) && $user['gender']): ?>
                                         <?php echo ucfirst($user['gender']); ?>
                                     <?php else: ?>
                                         <span class="text-red-600">Not provided</span>
                                         <span class="ml-2 text-xs text-red-500 italic">!please update</span>
                                     <?php endif; ?>
                                 </p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Civil Status:</span>
                                 <p class="text-gray-900">
                                     <?php if (isset($user['civil_status']) && $user['civil_status']): ?>
                                         <?php echo ucfirst($user['civil_status']); ?>
                                     <?php else: ?>
                                         <span class="text-red-600">Not provided</span>
                                         <span class="ml-2 text-xs text-red-500 italic">!please update</span>
                                     <?php endif; ?>
                                 </p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Years Living in Brgy. 172:</span>
                                 <p class="text-gray-900">
                                     <?php 
                                     if (isset($user['year_started_living']) && $user['year_started_living']): 
                                         $current_year = date('Y');
                                         $years_living = $current_year - $user['year_started_living'];
                                         echo $years_living . ' years (since ' . $user['year_started_living'] . ')';
                                     else: 
                                         echo '<span class="text-red-600">Not provided</span><span class="ml-2 text-xs text-red-500 italic">!please update</span>';
                                     endif; 
                                     ?>
                                 </p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Address:</span>
                                 <p class="text-gray-900">
                                     <?php if (isset($user['house_no']) && isset($user['street']) && $user['house_no'] && $user['street']): ?>
                                         <?php echo htmlspecialchars($user['house_no'] . ' ' . $user['street'] . ', Zone 15, Brgy. 172, Caloocan City'); ?>
                                     <?php elseif (isset($user['address']) && $user['address']): ?>
                                         <?php echo htmlspecialchars($user['address']); ?>
                                     <?php else: ?>
                                         <span class="text-red-600">Not provided</span>
                                         <span class="ml-2 text-xs text-red-500 italic">!please update</span>
                                     <?php endif; ?>
                                 </p>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Account Status:</span>
                                 <span class="px-2 py-1 text-xs font-medium rounded-full 
                                     <?php echo (isset($user['status']) && $user['status'] === 'active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                     <?php echo isset($user['status']) ? ucfirst($user['status']) : 'Unknown'; ?>
                                 </span>
                             </div>
                             <div>
                                 <span class="text-sm font-medium text-gray-500">Member Since:</span>
                                 <p class="text-gray-900"><?php echo isset($user['created_at']) && $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : 'Unknown'; ?></p>
                             </div>
                         </div>
                    </div>
                </div>

                <!-- Update Profile Form -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-eb-garamond font-semibold text-gray-900 mb-6">Update Profile</h3>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                        </div>
                        
                                                 <div>
                             <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                             <input type="tel" id="phone" name="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                    placeholder="Enter your phone number">
                         </div>
                         
                         <div>
                             <label for="birthday" class="block text-sm font-medium text-gray-700 mb-2">Birthday</label>
                             <input type="date" id="birthday" name="birthday" value="<?php echo isset($user['birthday']) ? htmlspecialchars($user['birthday']) : ''; ?>" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                    onchange="updateAge()">
                             <div class="mt-2">
                                 <span class="text-sm font-medium text-gray-700">Age: </span>
                                 <span id="ageDisplay" class="text-sm text-gray-600">
                                     <?php if (isset($user['birthday']) && $user['birthday']): ?>
                                         <?php echo calculateAge($user['birthday']); ?> years old
                                     <?php else: ?>
                                         <span class="text-red-600">Not set</span>
                                         <span class="ml-2 text-xs text-red-500 italic">!please update</span>
                                     <?php endif; ?>
                                 </span>
                             </div>
                             <p class="text-xs text-gray-500 mt-1">Your age will be automatically calculated</p>
                         </div>
                         
                         <div>
                             <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                             <select id="gender" name="gender" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                 <option value="">Select gender</option>
                                 <option value="male" <?php echo (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                 <option value="female" <?php echo (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                             </select>
                         </div>
                         
                         <div>
                             <label for="civil_status" class="block text-sm font-medium text-gray-700 mb-2">Civil Status</label>
                             <select id="civil_status" name="civil_status" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                 <option value="">Select civil status</option>
                                 <option value="single" <?php echo (isset($user['civil_status']) && $user['civil_status'] === 'single') ? 'selected' : ''; ?>>Single</option>
                                 <option value="married" <?php echo (isset($user['civil_status']) && $user['civil_status'] === 'married') ? 'selected' : ''; ?>>Married</option>
                                 <option value="widowed" <?php echo (isset($user['civil_status']) && $user['civil_status'] === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                 <option value="divorced" <?php echo (isset($user['civil_status']) && $user['civil_status'] === 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                 <option value="separated" <?php echo (isset($user['civil_status']) && $user['civil_status'] === 'separated') ? 'selected' : ''; ?>>Separated</option>
                             </select>
                         </div>
                         
                         <div>
                             <label for="year_started_living" class="block text-sm font-medium text-gray-700 mb-2">Year Started Living in Brgy. 172</label>
                             <select id="year_started_living" name="year_started_living" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                     onchange="updateYearsLiving()">
                                 <option value="">Select year</option>
                                 <?php 
                                 $current_year = date('Y');
                                 for ($year = $current_year; $year >= 1950; $year--) {
                                     $selected = (isset($user['year_started_living']) && $user['year_started_living'] == $year) ? 'selected' : '';
                                     echo "<option value=\"$year\" $selected>$year</option>";
                                 }
                                 ?>
                             </select>
                             <div class="mt-2">
                                 <span class="text-sm font-medium text-gray-700">Years Living in Brgy. 172: </span>
                                 <span id="yearsLivingDisplay" class="text-sm text-gray-600">
                                     <?php 
                                     if (isset($user['year_started_living']) && $user['year_started_living']) {
                                         $years_living = $current_year - $user['year_started_living'];
                                         echo $years_living . ' years';
                                     } else {
                                         echo '<span class="text-red-600">Not set</span><span class="ml-2 text-xs text-red-500 italic">!please update</span>';
                                     }
                                     ?>
                                 </span>
                             </div>
                             <p class="text-xs text-gray-500 mt-1">Years will be automatically calculated</p>
                         </div>
                        
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Change Password (Optional)</h4>
                            
                            <div class="space-y-4">
                                                                 <div>
                                     <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                     <div class="relative">
                                         <input type="password" id="current_password" name="current_password" 
                                                class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                                placeholder="Enter current password">
                                         <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePasswordVisibility('current_password')">
                                             <svg id="current_password_eye" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                             </svg>
                                         </button>
                                     </div>
                                 </div>
                                
                                                                 <div>
                                     <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                     <div class="relative">
                                         <input type="password" id="new_password" name="new_password" 
                                                class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                                placeholder="Enter new password"
                                                onkeyup="checkPasswordStrength(this.value)">
                                         <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePasswordVisibility('new_password')">
                                             <svg id="new_password_eye" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                             </svg>
                                         </button>
                                     </div>
                                     
                                     <!-- Password Requirements -->
                                     <div class="mt-3 space-y-2">
                                         <div class="flex items-center space-x-2">
                                             <span id="length-check" class="text-gray-400">○</span>
                                             <span class="text-xs text-gray-600">Minimum 10 characters</span>
                                         </div>
                                         <div class="flex items-center space-x-2">
                                             <span id="uppercase-check" class="text-gray-400">○</span>
                                             <span class="text-xs text-gray-600">Contains uppercase letter</span>
                                         </div>
                                         <div class="flex items-center space-x-2">
                                             <span id="lowercase-check" class="text-xs text-gray-400">○</span>
                                             <span class="text-xs text-gray-600">Contains lowercase letter</span>
                                         </div>
                                         <div class="flex items-center space-x-2">
                                             <span id="number-check" class="text-gray-400">○</span>
                                             <span class="text-xs text-gray-600">Contains number</span>
                                         </div>
                                         <div class="flex items-center space-x-2">
                                             <span id="special-check" class="text-gray-400">○</span>
                                             <span class="text-xs text-gray-600">Contains special character</span>
                                         </div>
                                     </div>
                                 </div>
                                
                                                                 <div>
                                     <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                     <div class="relative">
                                         <input type="password" id="confirm_password" name="confirm_password" 
                                                class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                                                placeholder="Confirm new password">
                                         <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePasswordVisibility('confirm_password')">
                                             <svg id="confirm_password_eye" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                             </svg>
                                         </button>
                                     </div>
                                 </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Document Upload Section -->
            <div class="mt-8 bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-eb-garamond font-semibold text-gray-900 mb-6">Verification Documents</h3>
                <p class="text-sm text-gray-600 mb-6">Upload required documents for address verification and barangay services access.</p>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Current Documents Status -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900">Current Documents</h4>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Purok Leader Endorsement</span>
                                    <p class="text-xs text-gray-500">Required for address verification</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                                                         <?php if (isset($user['purok_endorsement']) && $user['purok_endorsement']): ?>
                                         <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Uploaded</span>
                                         <button onclick="viewDocument('purok_endorsement', '<?php echo htmlspecialchars($user['purok_endorsement']); ?>', 'Purok Leader Endorsement')" 
                                                 class="text-barangay-orange hover:text-orange-600 text-sm">View</button>
                                     <?php else: ?>
                                         <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Not Uploaded</span>
                                     <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <span class="text-sm font-medium text-gray-700">Valid ID / Proof of Billing</span>
                                    <p class="text-xs text-gray-500">Required for address verification</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                                                         <?php if (isset($user['valid_id']) && $user['valid_id']): ?>
                                         <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Uploaded</span>
                                         <button onclick="viewDocument('valid_id', '<?php echo htmlspecialchars($user['valid_id']); ?>', 'Valid ID / Proof of Billing')" 
                                                 class="text-barangay-orange hover:text-orange-600 text-sm">View</button>
                                     <?php else: ?>
                                         <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Not Uploaded</span>
                                     <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                                         <!-- Upload Form - Only for Unverified Accounts -->
                     <?php if (!isset($user['account_verified']) || !$user['account_verified']): ?>
                         <div>
                             <h4 class="text-md font-medium text-gray-900 mb-4">Upload Documents</h4>
                             
                             <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                 <input type="hidden" name="action" value="upload_documents">
                                 
                                 <div>
                                     <label for="purok_endorsement" class="block text-sm font-medium text-gray-700 mb-2">
                                         Purok Leader Endorsement <span class="text-red-500">*</span>
                                     </label>
                                     <input type="file" id="purok_endorsement" name="purok_endorsement" 
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                     <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF (Max 5MB)</p>
                                 </div>
                                 
                                 <div>
                                     <label for="valid_id" class="block text-sm font-medium text-gray-700 mb-2">
                                         Valid ID / Proof of Billing <span class="text-red-500">*</span>
                                     </label>
                                     <input type="file" id="valid_id" name="valid_id" 
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                     <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, PDF (Max 5MB)</p>
                                 </div>
                                 
                                 <button type="submit" class="w-full bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                     Upload Documents
                                 </button>
                             </form>
                             
                             <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                 <h5 class="text-sm font-medium text-blue-900 mb-2">Document Requirements:</h5>
                                 <ul class="text-xs text-blue-800 space-y-1">
                                     <li>• Purok Leader Endorsement: Letter from your purok leader confirming your residence</li>
                                     <li>• Valid ID: Government-issued ID (e.g., Driver's License, Passport, UMID)</li>
                                     <li>• Proof of Billing: Recent utility bill (electricity, water, internet) showing your address</li>
                                     <li>• All documents must be clear, legible, and not expired when the verification is successful</li>
                                 </ul>
                             </div>
                         </div>
                     <?php else: ?>
                         <!-- Verified Account Message -->
                         <div class="text-center py-8">
                             <div class="mx-auto h-16 w-16 text-green-500 mb-4">
                                 <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                 </svg>
                             </div>
                             <h4 class="text-lg font-medium text-gray-900 mb-2">Documents Already Verified</h4>
                             <p class="text-sm text-gray-500">Your account has been verified. No further document uploads are required.</p>
                         </div>
                     <?php endif; ?>
                </div>
            </div>
                 </div>
     </div>

     <!-- Document Viewer Modal -->
     <div id="documentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
         <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
             <div class="flex justify-between items-center mb-4">
                 <h3 class="text-lg font-eb-garamond font-medium text-gray-900" id="documentModalTitle">Document Viewer</h3>
                 <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600">
                     <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                     </svg>
                 </button>
             </div>
             <div class="mb-4">
                 <div id="documentContent" class="w-full">
                     <!-- Document content will be loaded here -->
                 </div>
             </div>
             <div class="flex justify-end">
                 <button onclick="closeDocumentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-300">
                     Close
                 </button>
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
                 <h3 class="text-lg font-eb-garamond font-medium text-gray-900 mb-2">Account Verification Required</h3>
                 <div class="mt-2 px-7 py-3">
                     <p class="text-sm text-gray-500 mb-4">
                         Your account is not yet verified. You need to complete your profile verification before you can access these features.
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
                     <button onclick="hideVerificationModal()" class="px-4 py-2 bg-barangay-orange text-white rounded-md hover:bg-orange-600 transition duration-300">
                         Upload Documents
                     </button>
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
                 closeDocumentModal();
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
         
         // Close document modal when clicking outside
         document.getElementById('documentModal').addEventListener('click', function(e) {
             if (e.target === this) {
                 closeDocumentModal();
             }
         });
         
         // Toggle Password Visibility
         function togglePasswordVisibility(fieldId) {
             const passwordField = document.getElementById(fieldId);
             const eyeIcon = document.getElementById(fieldId + '_eye');
             
             if (passwordField.type === 'password') {
                 passwordField.type = 'text';
                 eyeIcon.innerHTML = `
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                 `;
             } else {
                 passwordField.type = 'password';
                 eyeIcon.innerHTML = `
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                 `;
             }
         }
         
         // Document Viewer Functions
         function viewDocument(documentType, filename, documentTitle) {
             const modal = document.getElementById('documentModal');
             const modalTitle = document.getElementById('documentModalTitle');
             const content = document.getElementById('documentContent');
             
             modalTitle.textContent = documentTitle;
             
             // Get file extension
             const fileExt = filename.split('.').pop().toLowerCase();
             
             if (fileExt === 'pdf') {
                 // Display PDF
                 content.innerHTML = `
                     <iframe src="../uploads/documents/${filename}" 
                             class="w-full h-96 border border-gray-300 rounded-lg"
                             frameborder="0">
                     </iframe>
                 `;
             } else if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
                 // Display image
                 content.innerHTML = `
                     <div class="flex justify-center">
                         <img src="../uploads/documents/${filename}" 
                              alt="${documentTitle}" 
                              class="max-w-full max-h-96 object-contain border border-gray-300 rounded-lg">
                     </div>
                 `;
             } else {
                 // Fallback for other file types
                 content.innerHTML = `
                     <div class="text-center py-8">
                         <p class="text-gray-500 mb-4">This file type cannot be previewed.</p>
                         <a href="../uploads/documents/${filename}" 
                            target="_blank" 
                            class="text-barangay-orange hover:text-orange-600 underline">
                             Download File
                         </a>
                     </div>
                 `;
             }
             
             modal.classList.remove('hidden');
         }
         
         function closeDocumentModal() {
             document.getElementById('documentModal').classList.add('hidden');
         }
         
         // Update age display when birthday changes
         function updateAge() {
             const birthdayInput = document.getElementById('birthday');
             const ageDisplay = document.getElementById('ageDisplay');
             
             if (birthdayInput.value) {
                 const birthDate = new Date(birthdayInput.value);
                 const today = new Date();
                 const age = today.getFullYear() - birthDate.getFullYear();
                 const monthDiff = today.getMonth() - birthDate.getMonth();
                 
                 if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                     age--;
                 }
                 
                 ageDisplay.textContent = `${age} years old`;
             } else {
                 ageDisplay.innerHTML = '<span class="text-red-600">Not set</span><span class="ml-2 text-xs text-red-500 italic">!please update</span>';
             }
         }
         
         // Update years living display when year started living changes
         function updateYearsLiving() {
             const yearStartedInput = document.getElementById('year_started_living');
             const yearsLivingDisplay = document.getElementById('yearsLivingDisplay');
             
             if (yearStartedInput.value) {
                 const currentYear = new Date().getFullYear();
                 const yearsLiving = currentYear - parseInt(yearStartedInput.value);
                 yearsLivingDisplay.textContent = `${yearsLiving} years`;
             } else {
                 yearsLivingDisplay.innerHTML = '<span class="text-red-600">Not set</span><span class="ml-2 text-xs text-red-500 italic">!please update</span>';
             }
         }
         
         // Password Strength Checker
         function checkPasswordStrength(password) {
             const lengthCheck = document.getElementById('length-check');
             const uppercaseCheck = document.getElementById('uppercase-check');
             const lowercaseCheck = document.getElementById('lowercase-check');
             const numberCheck = document.getElementById('number-check');
             const specialCheck = document.getElementById('special-check');
             
             // Check length (minimum 10 characters)
             if (password.length >= 10) {
                 lengthCheck.innerHTML = '✓';
                 lengthCheck.className = 'text-green-500 font-bold';
             } else {
                 lengthCheck.innerHTML = '○';
                 lengthCheck.className = 'text-gray-400';
             }
             
             // Check for uppercase letter
             if (/[A-Z]/.test(password)) {
                 uppercaseCheck.innerHTML = '✓';
                 uppercaseCheck.className = 'text-green-500 font-bold';
             } else {
                 uppercaseCheck.innerHTML = '○';
                 uppercaseCheck.className = 'text-gray-400';
             }
             
             // Check for lowercase letter
             if (/[a-z]/.test(password)) {
                 lowercaseCheck.innerHTML = '✓';
                 lowercaseCheck.className = 'text-green-500 font-bold';
             } else {
                 lowercaseCheck.innerHTML = '○';
                 lowercaseCheck.className = 'text-gray-400';
             }
             
             // Check for number
             if (/\d/.test(password)) {
                 numberCheck.innerHTML = '✓';
                 numberCheck.className = 'text-green-500 font-bold';
             } else {
                 numberCheck.innerHTML = '○';
                 numberCheck.className = 'text-gray-400';
             }
             
             // Check for special character
             if (/[@$!%*?&]/.test(password)) {
                 specialCheck.innerHTML = '✓';
                 specialCheck.className = 'text-green-500 font-bold';
             } else {
                 specialCheck.innerHTML = '○';
                 specialCheck.className = 'text-gray-400';
             }
         }
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
