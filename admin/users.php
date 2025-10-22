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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete':
                if (isset($_POST['user_id'])) {
                    $user_id = $_POST['user_id'];
                    try {
                        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                        if ($stmt->execute([$user_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: users.php?message=User deleted successfully');
                            exit();
                        } else {
                            $error = 'Failed to delete user';
                        }
                    } catch (Exception $e) {
                        $error = 'Error deleting user: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_role':
                if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
                    $user_id = $_POST['user_id'];
                    $new_role = $_POST['new_role'];
                    try {
                        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
                        if ($stmt->execute([$new_role, $user_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: users.php?message=User role updated successfully');
                            exit();
                        } else {
                            $error = 'Failed to update user role';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating user role: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'create_user':
                if (isset($_POST['full_name']) && isset($_POST['email']) && isset($_POST['role'])) {
                    $full_name = trim($_POST['full_name']);
                    $email = trim($_POST['email']);
                    $role = $_POST['role'];
                    $phone = trim($_POST['phone'] ?? '');
                    $address = trim($_POST['address'] ?? '');
                    
                    // Validate required fields
                    if (empty($full_name) || empty($email) || empty($role)) {
                        $error = 'Please fill in all required fields.';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please enter a valid email address.';
                    } elseif (!in_array($role, ['barangay_hall', 'health_center'])) {
                        $error = 'Invalid role selected.';
                    } else {
                        try {
                            // Check if email already exists
                            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                            $check_stmt->execute([$email]);
                            if ($check_stmt->fetch()) {
                                $error = 'Email address already exists.';
                            } else {
                                // Generate username based on role and name
                                $username = generateUsername($conn, $role);
                                
                                // Generate random password
                                $password = generateRandomPassword();
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                
                                // Insert new user
                                $insert_stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, phone, address, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', datetime('now'))");
                                
                                if ($insert_stmt->execute([$full_name, $username, $email, $hashed_password, $role, $phone, $address])) {
                                    // Send email with credentials
                                    $emailSent = false;
                                    $emailError = '';
                                    
                                    try {
                                        // Try PHPMailer first
                                        require_once '../includes/EmailService.php';
                                        $emailService = new EmailService();
                                        
                                        // Log email attempt
                                        error_log("Attempting to send registration email to: $email");
                                        
                                        $emailSent = $emailService->sendRegistrationCredentials($email, $username, $password, $full_name, $role);
                                        
                                        if ($emailSent) {
                                            $message = 'User created successfully! Login credentials have been sent to their email.';
                                            error_log("Registration email sent successfully to: $email");
                                        } else {
                                            $emailError = $emailService->getLastError();
                                            error_log("PHPMailer failed to send registration email to: $email. Error: $emailError");
                                        }
                                    } catch (Exception $e) {
                                        $emailError = $e->getMessage();
                                        error_log("PHPMailer exception while sending registration email to: $email. Error: " . $e->getMessage());
                                    }
                                    
                                    // If PHPMailer failed, try simple mail() function as fallback
                                    if (!$emailSent) {
                                        try {
                                            require_once '../includes/SimpleEmailService.php';
                                            $simpleEmailService = new SimpleEmailService();
                                            
                                            error_log("Trying SimpleEmailService as fallback for: $email");
                                            $emailSent = $simpleEmailService->sendRegistrationCredentials($email, $username, $password, $full_name, $role);
                                            
                                            if ($emailSent) {
                                                $message = 'User created successfully! Login credentials have been sent to their email (via fallback method).';
                                                error_log("SimpleEmailService sent email successfully to: $email");
                                            } else {
                                                error_log("SimpleEmailService also failed for: $email");
                                            }
                                        } catch (Exception $e) {
                                            error_log("SimpleEmailService exception: " . $e->getMessage());
                                        }
                                    }
                                    
                                    // If both methods failed
                                    if (!$emailSent) {
                                        $message = 'User created successfully! However, there was an issue sending the email. Username: ' . $username . ', Password: ' . $password . ' (PHPMailer Error: ' . $emailError . ')';
                                    }
                                    
                                    // Redirect to prevent form resubmission
                                    header('Location: users.php?message=' . urlencode($message));
                                    exit();
                                } else {
                                    $error = 'Failed to create user.';
                                }
                            }
                        } catch (Exception $e) {
                            $error = 'Error creating user: ' . $e->getMessage();
                        }
                    }
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

// Get users with search and pagination
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($role_filter) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $where_clause");
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
    
    // Get users with all details
    $users_stmt = $conn->prepare("SELECT id, username, full_name, email, role, address, phone, status, house_no, street, purok_endorsement, valid_id, created_at, updated_at FROM users WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $per_page;
    $params[] = $offset;
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $users = [];
    $total_pages = 0;
}

// Helper function to generate username
function generateUsername($conn, $role) {
    $prefix = $role === 'barangay_hall' ? 'bh' : 'hc';
    $counter = 1;
    
    do {
        $username = $prefix . str_pad($counter, 3, '0', STR_PAD_LEFT);
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->execute([$username]);
        $exists = $check_stmt->fetch();
        $counter++;
    } while ($exists);
    
    return $username;
}

// Helper function to generate random password
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charactersLength = strlen($characters);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $password;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Brgy. 172 Urduja</title>
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
        
        /* Mobile responsiveness fixes */
        @media (max-width: 1023px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            #sidebar.show {
                transform: translateX(0);
            }
            
            .mobile-overlay {
                display: block;
            }
            
            .mobile-overlay.hidden {
                display: none;
            }
        }
        
        @media (min-width: 1024px) {
            #sidebar {
                transform: translateX(0);
            }
            
            .mobile-overlay {
                display: none !important;
            }
        }
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
                            <h1 class="text-2xl font-bold text-white font-garamond">Brgy. 172 Urduja</h1>
                            <p class="text-sm text-orange-100">Caloocan City</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white text-sm hidden md:block">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex flex-col lg:flex-row">
        <!-- Mobile Menu Button -->
        <div class="lg:hidden fixed top-32 left-4 z-40">
            <button id="mobileMenuBtn" class="bg-white p-2 rounded-lg shadow-lg hover:bg-gray-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white shadow-lg min-h-screen flex flex-col fixed lg:relative z-30">
            <div class="flex-1 overflow-y-auto">
                <div class="p-4">
                    <nav class="space-y-2">
                    <a href="index.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5v14M16 5v14"></path>
                        </svg>
                        <span class="whitespace-nowrap">Dashboard</span>
                    </a>
                    <a href="users.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                        </svg>
                        <span class="whitespace-nowrap">User Management</span>
                    </a>

                    <a href="barangay-hall.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="whitespace-nowrap">Barangay Hall</span>
                    </a>
                    <a href="health-center.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <span class="whitespace-nowrap">Health Center</span>
                    </a>
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="whitespace-nowrap">Reports</span>
                    </a>
                        <a href="settings.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="whitespace-nowrap">Settings</span>
                        </a>
                        <button onclick="showLogoutModal()" class="w-full flex items-center px-4 py-2 text-gray-600 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors mt-2">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            <span class="whitespace-nowrap">Logout</span>
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-4 lg:p-8 lg:ml-0 ml-16 lg:ml-0">
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 font-garamond">User Management</h1>
                <p class="text-sm lg:text-base text-gray-600">Manage all users in the system</p>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $total_users; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Users</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                try {
                                    $active_stmt = $conn->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
                                    echo $active_stmt->fetchColumn();
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Residents</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                try {
                                    $resident_stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'resident'");
                                    echo $resident_stmt->fetchColumn();
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 100 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Staff</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                try {
                                    $staff_stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('barangay_hall', 'health_center')");
                                    echo $staff_stmt->fetchColumn();
                                } catch (Exception $e) {
                                    echo '0';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-64">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by username, name, or email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Filter by Role</label>
                        <select id="role" name="role" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            <option value="">All Roles</option>
                            <option value="resident" <?php echo $role_filter === 'resident' ? 'selected' : ''; ?>>Resident</option>
                            <option value="barangay_hall" <?php echo $role_filter === 'barangay_hall' ? 'selected' : ''; ?>>Barangay Hall Staff</option>
                            <option value="health_center" <?php echo $role_filter === 'health_center' ? 'selected' : ''; ?>>Health Center Staff</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="showCreateUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create User
                        </button>
                        <button type="submit" class="bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Search
                        </button>
                        <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Clear
                        </a>
                        <button type="button" onclick="exportUsers()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Export Users
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 font-garamond">Users (<?php echo $total_users; ?>)</h3>
                </div>
                
                <?php if (empty($users)): ?>
                    <div class="p-6 text-center text-gray-500">
                        No users found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role & Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <div class="text-sm text-gray-500">Username: <?php echo htmlspecialchars($user['username']); ?></div>
                                                <div class="text-xs text-gray-400">ID: #<?php echo $user['id']; ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-1">
                                                <div class="text-sm text-gray-900">
                                                    <span class="font-medium">Email:</span> <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                                <?php if ($user['phone']): ?>
                                                    <div class="text-sm text-gray-700">
                                                        <span class="font-medium">Phone:</span> <?php echo htmlspecialchars($user['phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($user['house_no'] && $user['street']): ?>
                                                    <div class="text-sm text-gray-700">
                                                        <span class="font-medium">Address:</span> <?php echo htmlspecialchars($user['house_no'] . ' ' . $user['street']); ?>
                                                    </div>
                                                <?php elseif ($user['address']): ?>
                                                    <div class="text-sm text-gray-700">
                                                        <span class="font-medium">Address:</span> <?php echo htmlspecialchars($user['address']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="space-y-2">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                <?php echo $user['role'] === 'resident' ? 'bg-green-100 text-green-800' : 
                                                      ($user['role'] === 'barangay_hall' ? 'bg-orange-100 text-orange-800' : 
                                                      ($user['role'] === 'health_center' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800')); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                            </span>
                                                <div>
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                        <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                                              ($user['status'] === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500 space-y-1">
                                                <div>Created: <?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></div>
                                                <?php if ($user['updated_at'] && $user['updated_at'] !== $user['created_at']): ?>
                                                    <div>Updated: <?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="showUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                        class="text-blue-600 hover:text-blue-800 transition-colors">
                                                    View Details
                                                </button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                    <button onclick="showRoleModal(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>')" 
                                                            class="text-barangay-orange hover:text-orange-600 transition-colors">
                                                        Change Role
                                                    </button>
                                                    <button onclick="confirmDelete(<?php echo $user['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-800 transition-colors">
                                                        Delete
                                                    </button>
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>" 
                               class="px-3 py-2 rounded-lg <?php echo $i === $page ? 'bg-barangay-orange text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-garamond">User Details</h3>
                <button onclick="hideUserDetailsModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div id="userDetailsContent" class="space-y-6">
                <!-- User details will be populated here -->
            </div>
            
            <div class="flex justify-end mt-6">
                <button onclick="hideUserDetailsModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Role Change Modal -->
    <div id="roleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900 font-garamond">Change User Role</h3>
                <button onclick="hideRoleModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="POST" id="roleForm">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="roleUserId">
                
                <div class="mb-4">
                    <label for="newRole" class="block text-sm font-medium text-gray-700 mb-2">New Role</label>
                    <select id="newRole" name="new_role" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                        <option value="resident">Resident</option>
                        <option value="barangay_hall">Barangay Hall Staff</option>
                        <option value="health_center">Health Center Staff</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideRoleModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-2xl bg-white">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 mt-4 font-garamond">Delete User</h3>
                <p class="text-gray-600 mt-2">Are you sure you want to delete this user? This action cannot be undone.</p>
                
                <form method="POST" id="deleteForm" class="mt-6">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 font-garamond">Create New User</h3>
                <button onclick="hideCreateUserModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="POST" id="createUserForm" class="space-y-6">
                <input type="hidden" name="action" value="create_user">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                               placeholder="Enter full name">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                               placeholder="Enter email address">
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <select id="role" name="role" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            <option value="">Select Role</option>
                            <option value="barangay_hall">Barangay Hall Admin</option>
                            <option value="health_center">Health Center Admin</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                               placeholder="Enter phone number">
                    </div>
                </div>
                
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea id="address" name="address" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"
                              placeholder="Enter address"></textarea>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Account Creation Info</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Username will be auto-generated (e.g., bh001, hc001)</li>
                                    <li>Password will be auto-generated and sent via email</li>
                                    <li>User will be set to "Active" status by default</li>
                                    <li>Login credentials will be emailed to the provided email address</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideCreateUserModal()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showUserDetails(user) {
            const content = document.getElementById('userDetailsContent');
            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-3 font-garamond">Basic Information</h4>
                            <div class="space-y-2">
                                <div><span class="font-medium">Full Name:</span> ${user.full_name}</div>
                                <div><span class="font-medium">Username:</span> ${user.username}</div>
                                <div><span class="font-medium">Email:</span> ${user.email}</div>
                                <div><span class="font-medium">User ID:</span> #${user.id}</div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-3 font-garamond">Contact Information</h4>
                            <div class="space-y-2">
                                <div><span class="font-medium">Phone:</span> ${user.phone || 'Not provided'}</div>
                                <div><span class="font-medium">House No:</span> ${user.house_no || 'Not provided'}</div>
                                <div><span class="font-medium">Street:</span> ${user.street || 'Not provided'}</div>
                                <div><span class="font-medium">Complete Address:</span> ${user.address || 'Not provided'}</div>
                                <div><span class="font-medium">Purok Endorsement:</span> ${user.purok_endorsement ? '<span class="text-green-600">✓ Uploaded</span>' : '<span class="text-red-600">✗ Not uploaded</span>'}</div>
                                <div><span class="font-medium">Valid ID:</span> ${user.valid_id ? '<span class="text-green-600">✓ Uploaded</span>' : '<span class="text-red-600">✗ Not uploaded</span>'}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-3 font-garamond">Account Details</h4>
                            <div class="space-y-2">
                                <div><span class="font-medium">Role:</span> 
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        ${user.role === 'resident' ? 'bg-green-100 text-green-800' : 
                                          (user.role === 'barangay_hall' ? 'bg-orange-100 text-orange-800' : 
                                          (user.role === 'health_center' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800'))}">
                                        ${user.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    </span>
                                </div>
                                <div><span class="font-medium">Status:</span> 
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        ${user.status === 'active' ? 'bg-green-100 text-green-800' : 
                                          (user.status === 'inactive' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')}">
                                        ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-3 font-garamond">Timestamps</h4>
                            <div class="space-y-2">
                                <div><span class="font-medium">Created:</span> ${new Date(user.created_at).toLocaleString()}</div>
                                <div><span class="font-medium">Last Updated:</span> ${new Date(user.updated_at).toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('userDetailsModal').classList.remove('hidden');
        }

        function hideUserDetailsModal() {
            document.getElementById('userDetailsModal').classList.add('hidden');
        }

        function showRoleModal(userId, currentRole) {
            document.getElementById('roleUserId').value = userId;
            document.getElementById('newRole').value = currentRole;
            document.getElementById('roleModal').classList.remove('hidden');
        }

        function hideRoleModal() {
            document.getElementById('roleModal').classList.add('hidden');
        }

        function confirmDelete(userId) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function showCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }

        function hideCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
            // Reset form
            document.getElementById('createUserForm').reset();
        }

        // Close modals when clicking outside
        document.getElementById('userDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) hideUserDetailsModal();
        });

        document.getElementById('roleModal').addEventListener('click', function(e) {
            if (e.target === this) hideRoleModal();
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) hideDeleteModal();
        });

        document.getElementById('createUserModal').addEventListener('click', function(e) {
            if (e.target === this) hideCreateUserModal();
        });

        function exportUsers() {
            // Get current search parameters
            const search = document.getElementById('search').value;
            const role = document.getElementById('role').value;
            
            // Create export URL
            let exportUrl = 'export_users.php?';
            if (search) exportUrl += 'search=' + encodeURIComponent(search) + '&';
            if (role) exportUrl += 'role=' + encodeURIComponent(role);
            
            // Download the file
            window.open(exportUrl, '_blank');
        }
    </script>
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>
    
    <?php include '../includes/logout_modal.php'; ?>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.createElement('div');
            
            // Create overlay for mobile
            overlay.className = 'mobile-overlay fixed inset-0 bg-black bg-opacity-50 z-20 hidden';
            overlay.id = 'mobileOverlay';
            document.body.appendChild(overlay);
            
            // Toggle mobile menu
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('hidden');
            });
            
            // Close mobile menu when clicking overlay
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.add('hidden');
            });
            
            // Close mobile menu on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebar.classList.remove('show');
                    overlay.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
