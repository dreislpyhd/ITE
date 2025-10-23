<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is barangay_staff, barangay_hall, or encoder
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'barangay_hall', 'encoder1', 'encoder2', 'encoder3'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
require_once '../includes/notification_helper.php';
require_once '../includes/street_assignments.php';
$db = new Database();
$conn = $db->getConnection();
$notificationHelper = new NotificationHelper();

// Include notification badge helper
require_once 'includes/notification_badge.php';

// Mark resident verification requests as viewed when staff visits this page
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_viewed_residents = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error updating last_viewed_residents: " . $e->getMessage());
    }
}

$message = '';
$error = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verify_account':
                if (isset($_POST['user_id'])) {
                    $user_id = $_POST['user_id'];
                    try {
                        $stmt = $conn->prepare("UPDATE users SET account_verified = 1, verified_by = ?, verified_at = datetime('now') WHERE id = ? AND role = 'resident'");
                        if ($stmt->execute([$_SESSION['user_id'], $user_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: barangay-staff.php?message=Resident account verified successfully');
                            exit();
                        } else {
                            $error = 'Failed to verify account';
                        }
                    } catch (Exception $e) {
                        $error = 'Error verifying account: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'send_message':
                if (isset($_POST['user_id']) && isset($_POST['message'])) {
                    $user_id = $_POST['user_id'];
                    $message_text = trim($_POST['message']);
                    $admin_id = $_SESSION['user_id'];
                    
                    if (!empty($message_text)) {
                        try {
                            $stmt = $conn->prepare("INSERT INTO admin_messages (user_id, admin_id, message, created_at) VALUES (?, ?, ?, datetime('now'))");
                            if ($stmt->execute([$user_id, $admin_id, $message_text])) {
                                // Redirect to prevent form resubmission
                                header('Location: barangay-staff.php?message=Message sent successfully');
                                exit();
                            } else {
                                $error = 'Failed to send message';
                            }
                        } catch (Exception $e) {
                            $error = 'Error sending message: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Message cannot be empty';
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

// Get residents with verification status
$search = $_GET['search'] ?? '';
$verification_filter = $_GET['verification'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $where_conditions = ["role = 'resident'"];
    $params = [];
    
    // Add street filter for encoders
    $user_role = $_SESSION['role'];
    if (in_array($user_role, ['encoder1', 'encoder2', 'encoder3'])) {
        $assigned_streets = getStreetsForEncoder($user_role);
        $street_placeholders = implode(',', array_fill(0, count($assigned_streets), '?'));
        $where_conditions[] = "street IN ($street_placeholders)";
        $params = array_merge($params, $assigned_streets);
    }
    
    if ($search) {
        $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($verification_filter === 'verified') {
        $where_conditions[] = "account_verified = 1";
    } elseif ($verification_filter === 'unverified') {
        $where_conditions[] = "(account_verified = 0 OR account_verified IS NULL)";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $where_clause");
    $count_stmt->execute($params);
    $total_residents = $count_stmt->fetchColumn();
    $total_pages = ceil($total_residents / $per_page);
    
    // Get residents with all details
    $residents_stmt = $conn->prepare("
        SELECT id, username, full_name, email, address, phone, status, house_no, street, 
               purok_endorsement, valid_id, account_verified, verified_at, verified_by,
               created_at, updated_at
        FROM users 
        WHERE $where_clause 
        ORDER BY account_verified ASC, created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $per_page;
    $params[] = $offset;
    $residents_stmt->execute($params);
    $residents = $residents_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $residents = [];
    $total_pages = 0;
}

// Mark residents as viewed
$user_id = $_SESSION['user_id'];
$notificationHelper->markAsViewed($user_id, 'residents');

// Get notification counts for sidebar
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];

// Get verification statistics (filtered by encoder's assigned streets)
try {
    if (in_array($user_role, ['encoder1', 'encoder2', 'encoder3'])) {
        // Filter by encoder's assigned streets
        $assigned_streets = getStreetsForEncoder($user_role);
        $street_placeholders = implode(',', array_fill(0, count($assigned_streets), '?'));
        
        $verified_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'resident' AND account_verified = 1 AND street IN ($street_placeholders)");
        $verified_stmt->execute($assigned_streets);
        $verified_count = $verified_stmt->fetchColumn();
        
        $unverified_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'resident' AND (account_verified = 0 OR account_verified IS NULL) AND street IN ($street_placeholders)");
        $unverified_stmt->execute($assigned_streets);
        $unverified_count = $unverified_stmt->fetchColumn();
        
        $total_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'resident' AND street IN ($street_placeholders)");
        $total_stmt->execute($assigned_streets);
        $total_residents_count = $total_stmt->fetchColumn();
    } else {
        // barangay_staff and barangay_hall see all
        $verified_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND account_verified = 1")->fetchColumn();
        $unverified_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND (account_verified = 0 OR account_verified IS NULL)")->fetchColumn();
        $total_residents_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'resident'")->fetchColumn();
    }
} catch (Exception $e) {
    $verified_count = 0;
    $unverified_count = 0;
    $total_residents_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Account Management - Barangay Hall</title>
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
    
    <!-- Search and Filter Enhancement Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filter changes
            const verificationFilter = document.getElementById('verification');
            if (verificationFilter) {
                verificationFilter.addEventListener('change', function() {
                    this.form.submit();
                });
            }
            
            // Search with Enter key
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
                
                // Auto-search with delay (debounced)
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.length >= 2 || this.value.length === 0) {
                            this.form.submit();
                        }
                    }, 500);
                });
            }
            
            // Clear search and filters
            const clearButton = document.querySelector('a[href="barangay-staff.php"]');
            if (clearButton) {
                clearButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = 'barangay-staff.php';
                });
            }
            
            // Show loading state during search
            const searchForm = document.querySelector('form[method="GET"]');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    const searchBtn = this.querySelector('button[type="submit"]');
                    if (searchBtn) {
                        searchBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Searching...';
                        searchBtn.disabled = true;
                    }
                });
            }
        });
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
                    <a href="profile.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        My Profile
                    </a>
                    <a href="barangay-staff.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Residents Account
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
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Residents Account Management</h1>
                <p class="text-gray-600">Verify resident accounts and manage document verification</p>
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
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Residents</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $total_residents_count; ?></p>
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
                            <p class="text-sm font-medium text-gray-600">Verified Accounts</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $verified_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Verification</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $unverified_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">Search & Filter Residents</h3>
                    <?php if ($search || $verification_filter): ?>
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Active filters:</span>
                            <?php if ($search): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">
                                    Search: "<?php echo htmlspecialchars($search); ?>"
                                </span>
                            <?php endif; ?>
                            <?php if ($verification_filter): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Status: <?php echo ucfirst($verification_filter); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-64">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                            <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search Residents
                        </label>
                        <p class="text-xs text-gray-500 mb-2">Press Enter or type 2+ characters to search</p>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by username, name, or email..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent transition-colors">
                    </div>
                    <div>
                        <label for="verification" class="block text-sm font-medium text-gray-700 mb-2">
                            Filter by Status
                        </label>
                        <select id="verification" name="verification" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent transition-colors">
                            <option value="">All Residents</option>
                            <option value="verified" <?php echo $verification_filter === 'verified' ? 'selected' : ''; ?>>✓ Verified Only</option>
                            <option value="unverified" <?php echo $verification_filter === 'unverified' ? 'selected' : ''; ?>>⏳ Pending Verification</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <a href="barangay-staff.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Clear
                        </a>
                    </div>
                </form>
                
                <?php if ($search || $verification_filter): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                <span class="font-medium">Results:</span> <?php echo $total_residents; ?> resident<?php echo $total_residents !== 1 ? 's' : ''; ?> found
                            </div>
                            <a href="barangay-staff.php" class="text-sm text-barangay-orange hover:text-orange-600 font-medium">
                                Clear all filters
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Residents Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">Residents (<?php echo $total_residents; ?>)</h3>
                </div>
                
                <?php if (empty($residents)): ?>
                    <div class="p-6 text-center text-gray-500">
                        No residents found matching your criteria.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resident Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($residents as $resident): ?>
                                    <tr class="hover:bg-gray-50" 
                                        data-user-id="<?php echo $resident['id']; ?>"
                                        data-purok-endorsement="<?php echo htmlspecialchars($resident['purok_endorsement'] ?? ''); ?>"
                                        data-valid-id="<?php echo htmlspecialchars($resident['valid_id'] ?? ''); ?>"
                                        data-verified="<?php echo $resident['account_verified'] ? 'true' : 'false'; ?>">
                                                                                 <td class="px-6 py-4">
                                             <div class="flex items-center">
                                                 <div>
                                                     <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                                     <div class="text-sm text-gray-500">Username: <?php echo htmlspecialchars($resident['username']); ?></div>
                                                     <div class="text-sm text-gray-500">Email: <?php echo htmlspecialchars($resident['email']); ?></div>
                                                     <?php if ($resident['house_no'] && $resident['street']): ?>
                                                         <div class="text-sm text-gray-500">Address: <?php echo htmlspecialchars($resident['house_no'] . ' ' . $resident['street']); ?></div>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                         </td>

                                        <td class="px-6 py-4">
                                            <?php if ($resident['account_verified']): ?>
                                                <div class="space-y-2">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">✓ Verified</span>
                                                    <div class="text-xs text-gray-500">
                                                        Verified on: <?php echo date('M j, Y g:i A', strtotime($resident['verified_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="space-y-2">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-800">⏳ Pending</span>
                                                    <div class="text-xs text-gray-500">
                                                        Registered: <?php echo date('M j, Y', strtotime($resident['created_at'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                             <div class="flex space-x-2">
                                                 <button onclick="showViewAccountModal(<?php echo $resident['id']; ?>, '<?php echo htmlspecialchars($resident['full_name']); ?>', '<?php echo htmlspecialchars($resident['purok_endorsement'] ?? ''); ?>', '<?php echo htmlspecialchars($resident['valid_id'] ?? ''); ?>', <?php echo $resident['account_verified'] ? 'true' : 'false'; ?>)" 
                                                         class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                     View Account
                                                 </button>
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&verification=<?php echo urlencode($verification_filter); ?>" 
                               class="px-3 py-2 rounded-lg <?php echo $i === $page ? 'bg-barangay-orange text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

         <!-- View Account Modal -->
     <div id="viewAccountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
         <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
             <div class="mt-3">
                 <div class="flex items-center justify-between mb-4">
                     <h3 class="text-xl font-medium text-gray-900 font-eb-garamond">View Resident Account</h3>
                     <button onclick="hideViewAccountModal()" class="text-gray-400 hover:text-gray-600">
                         <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                         </svg>
                     </button>
                 </div>
                 
                 <div class="space-y-6">
                     <!-- Resident Information -->
                     <div>
                         <h4 class="text-lg font-medium text-gray-900 mb-3 font-eb-garamond">Resident Information</h4>
                         <div class="bg-gray-50 p-4 rounded-lg">
                             <p class="text-sm text-gray-600"><span class="font-medium">Name:</span> <span id="viewResidentName"></span></p>
                             <p class="text-sm text-gray-600"><span class="font-medium">Username:</span> <span id="viewResidentUsername"></span></p>
                             <p class="text-sm text-gray-600"><span class="font-medium">Email:</span> <span id="viewResidentEmail"></span></p>
                             <p class="text-sm text-gray-600"><span class="font-medium">Address:</span> <span id="viewResidentAddress"></span></p>
                         </div>
                     </div>
                     
                     <!-- Documents Section -->
                     <div>
                         <h4 class="text-lg font-medium text-gray-900 mb-3 font-eb-garamond">Document Status</h4>
                         <div class="space-y-4">
                             <!-- Purok Endorsement -->
                             <div class="border border-gray-200 rounded-lg p-4">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <span class="text-sm font-medium text-gray-700">Purok Leader Endorsement</span>
                                         <p class="text-xs text-gray-500">Required for address verification</p>
                                     </div>
                                     <div class="flex items-center space-x-2">
                                         <span id="purokStatus" class="px-2 py-1 text-xs font-medium rounded-full"></span>
                                         <button id="purokViewBtn" onclick="viewDocument('purok_endorsement', this.getAttribute('data-filename'), 'Purok Leader Endorsement')" 
                                                 class="text-barangay-orange hover:text-orange-600 text-sm hidden">View</button>
                                     </div>
                                 </div>
                             </div>
                             
                             <!-- Valid ID -->
                             <div class="border border-gray-200 rounded-lg p-4">
                                 <div class="flex items-center justify-between">
                                     <div>
                                         <span class="text-sm font-medium text-gray-700">Valid ID / Proof of Billing</span>
                                         <p class="text-xs text-gray-500">Required for address verification</p>
                                     </div>
                                     <div class="flex items-center space-x-2">
                                         <span id="validIdStatus" class="px-2 py-1 text-xs font-medium rounded-full"></span>
                                         <button id="validIdViewBtn" onclick="viewDocument('valid_id', this.getAttribute('data-filename'), 'Valid ID / Proof of Billing')" 
                                                 class="text-barangay-orange hover:text-orange-600 text-sm hidden">View</button>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                     
                                              <!-- Action Buttons -->
                         <div id="actionButtons" class="flex justify-center space-x-4 pt-4 border-t border-gray-200">
                             <input type="hidden" name="action" value="verify_account">
                             <input type="hidden" name="user_id" id="viewUserId">
                             <button onclick="showVerifyConfirmModal()" id="verifyBtn" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300 hidden">
                                 Verify Account
                             </button>
                             <div id="waitingMessage" class="text-center py-2 px-4 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
                                 <span class="text-yellow-800 text-sm font-medium">⏳ Waiting for Required Documents</span>
                             </div>
                             <button onclick="showDeclineConfirmModal()" id="declineBtn" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                                 Decline
                             </button>
                             <button onclick="hideViewAccountModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition duration-300">
                                 Close
                             </button>
                         </div>
                 </div>
             </div>
                  </div>
     </div>
 
     <!-- Document Viewer Modal -->
     <div id="documentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
         <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
             <div class="flex justify-between items-center mb-4">
                 <h3 class="text-lg font-medium text-gray-900 font-eb-garamond" id="documentModalTitle">Document Viewer</h3>
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
 
     <!-- Verify Confirmation Modal -->
     <div id="verifyConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
         <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
             <div class="mt-3">
                 <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full">
                     <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                     </svg>
                 </div>
                 <div class="mt-2 text-center">
                     <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Verify Account</h3>
                     <div class="mt-2 px-7 py-3">
                         <p class="text-sm text-gray-500">
                             Are you sure you want to verify this account? This will grant the resident full access to all features.
                         </p>
                     </div>
                 </div>
                 <div class="flex justify-center space-x-3 mt-4">
                     <button onclick="proceedWithVerification()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Yes, Verify
                     </button>
                     <button onclick="hideVerifyConfirmModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Cancel
                     </button>
                 </div>
             </div>
         </div>
     </div>

     <!-- Decline Confirmation Modal -->
     <div id="declineConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
         <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
             <div class="mt-3">
                 <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                     <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                     </svg>
                 </div>
                 <div class="mt-2 text-center">
                     <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Decline Account</h3>
                     <div class="mt-2 px-7 py-3">
                         <p class="text-sm text-gray-500">
                             Are you sure you want to decline this account? This action cannot be undone.
                         </p>
                     </div>
                 </div>
                 <div class="flex justify-center space-x-3 mt-4">
                     <button onclick="proceedWithDecline()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Yes, Decline
                     </button>
                     <button onclick="hideDeclineConfirmModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Cancel
                     </button>
                 </div>
             </div>
         </div>
     </div>

     <!-- Notification Modal -->
     <div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50" style="display: none;">
         <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
             <div class="mt-3">
                 <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                     <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                         <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                     </svg>
                 </div>
                 <div class="mt-2 text-center">
                     <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Document Upload Notification</h3>
                     <div class="mt-2 px-7 py-3">
                         <p class="text-sm text-gray-500">
                             <span id="notificationResidentName" class="font-medium text-gray-900"></span> has uploaded documents. Please check to verify account.
                         </p>
                     </div>
                 </div>
                 <div class="flex justify-center space-x-3 mt-4">
                     <button onclick="openViewAccountModalFromNotification()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Check Documents
                     </button>
                     <button onclick="hideNotificationModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                         Close
                     </button>
                 </div>
             </div>
         </div>
     </div>

     <!-- Message Modal -->
    <div id="messageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Send Message to Resident</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Send a message to <span id="messageResidentName" class="font-medium text-gray-900"></span>
                        </p>
                    </div>
                </div>
                <form method="POST" class="mt-4">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="user_id" id="messageUserId">
                    <div class="mb-4">
                        <textarea name="message" rows="4" placeholder="Enter your message here..." required
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent resize-none"></textarea>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Send Message
                        </button>
                        <button type="button" onclick="hideMessageModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
                 function showViewAccountModal(userId, userName, purokEndorsement, validId, isVerified) {
             console.log('showViewAccountModal called with:', { userId, userName, purokEndorsement, validId, isVerified });
             
             // Set resident information
             document.getElementById('viewUserId').value = userId;
             document.getElementById('viewResidentName').textContent = userName;
             
             // Get resident details from the table row using data attributes or find by user ID
             let username = '';
             let email = '';
             let address = '';
             
             // Try to find the row by user ID first
             const row = document.querySelector(`tr[data-user-id="${userId}"]`);
             if (row) {
                 username = row.querySelector('td:first-child .text-sm:nth-child(2)')?.textContent.replace('Username: ', '') || '';
                 email = row.querySelector('td:first-child .text-sm:nth-child(3)')?.textContent.replace('Email: ', '') || '';
                 address = row.querySelector('td:first-child .text-sm:nth-child(4)')?.textContent.replace('Address: ', '') || 'Not provided';
             } else {
                 // Fallback: try to find by name
                 const allRows = document.querySelectorAll('tbody tr');
                 for (let r of allRows) {
                     const nameElement = r.querySelector('.text-sm.font-medium');
                     if (nameElement && nameElement.textContent === userName) {
                         username = r.querySelector('td:first-child .text-sm:nth-child(2)')?.textContent.replace('Username: ', '') || '';
                         email = r.querySelector('td:first-child .text-sm:nth-child(3)')?.textContent.replace('Email: ', '') || '';
                         address = r.querySelector('td:first-child .text-sm:nth-child(4)')?.textContent.replace('Address: ', '') || 'Not provided';
                         break;
                     }
                 }
             }
             
             document.getElementById('viewResidentUsername').textContent = username;
             document.getElementById('viewResidentEmail').textContent = email;
             document.getElementById('viewResidentAddress').textContent = address;
             
             // Handle Purok Endorsement
             if (purokEndorsement) {
                 document.getElementById('purokStatus').textContent = '✓ Uploaded';
                 document.getElementById('purokStatus').className = 'px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800';
                 document.getElementById('purokStatus').setAttribute('data-filename', purokEndorsement);
                 document.getElementById('purokViewBtn').classList.remove('hidden');
                 document.getElementById('purokViewBtn').setAttribute('data-filename', purokEndorsement);
                 console.log('Purok button should be visible');
             } else {
                 document.getElementById('purokStatus').textContent = '✗ Not uploaded';
                 document.getElementById('purokStatus').className = 'px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800';
                 document.getElementById('purokViewBtn').classList.add('hidden');
                 console.log('Purok button should be hidden');
             }
             
             // Handle Valid ID
             if (validId) {
                 document.getElementById('validIdStatus').textContent = '✓ Uploaded';
                 document.getElementById('validIdStatus').className = 'px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800';
                 document.getElementById('validIdStatus').setAttribute('data-filename', validId);
                 document.getElementById('validIdViewBtn').classList.remove('hidden');
                 document.getElementById('validIdViewBtn').setAttribute('data-filename', validId);
                 console.log('Valid ID button should be visible');
             } else {
                 document.getElementById('validIdStatus').textContent = '✗ Not uploaded';
                 document.getElementById('validIdStatus').className = 'px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800';
                 document.getElementById('validIdViewBtn').classList.add('hidden');
                 console.log('Valid ID button should be hidden');
             }
             
             // Show/hide action buttons based on verification status and document uploads
             if (isVerified) {
                 // Account already verified - hide all action buttons
                 document.getElementById('verifyBtn').classList.add('hidden');
                 document.getElementById('waitingMessage').classList.add('hidden');
                 document.getElementById('declineBtn').classList.add('hidden');
             } else {
                 // Check if both required documents are uploaded
                 const hasPurokEndorsement = !!purokEndorsement;
                 const hasValidId = !!validId;
                 const allDocumentsUploaded = hasPurokEndorsement && hasValidId;
                 
                 if (allDocumentsUploaded) {
                     // All documents uploaded - show verify button
                     document.getElementById('verifyBtn').classList.remove('hidden');
                     document.getElementById('waitingMessage').classList.add('hidden');
                     document.getElementById('declineBtn').classList.remove('hidden');
                 } else {
                     // Missing documents - show waiting message
                     document.getElementById('verifyBtn').classList.add('hidden');
                     document.getElementById('waitingMessage').classList.remove('hidden');
                     document.getElementById('declineBtn').classList.remove('hidden');
                 }
             }
             
             // Show the modal
             const modal = document.getElementById('viewAccountModal');
             modal.style.display = 'block';
             modal.classList.remove('hidden');
             console.log('View Account modal should now be visible');
         }
 
         function hideViewAccountModal() {
             console.log('hideViewAccountModal function called');
             const modal = document.getElementById('viewAccountModal');
             if (modal) {
                 modal.style.display = 'none';
                 modal.classList.add('hidden');
                 console.log('View Account modal hidden successfully');
                 console.log('Modal display style:', modal.style.display);
                 console.log('Modal hidden class:', modal.classList.contains('hidden'));
             } else {
                 console.error('View Account modal element not found');
             }
         }
 
         // Modal Functions
         function showVerifyConfirmModal() {
             const modal = document.getElementById('verifyConfirmModal');
             modal.style.display = 'block';
             modal.classList.remove('hidden');
         }
         
         function hideVerifyConfirmModal() {
             const modal = document.getElementById('verifyConfirmModal');
             modal.style.display = 'none';
             modal.classList.add('hidden');
         }
         
         function showDeclineConfirmModal() {
             const modal = document.getElementById('declineConfirmModal');
             modal.style.display = 'block';
             modal.classList.remove('hidden');
         }
         
         function hideDeclineConfirmModal() {
             const modal = document.getElementById('declineConfirmModal');
             modal.style.display = 'none';
             modal.classList.add('hidden');
         }
         
         function proceedWithVerification() {
             // Create and submit the form programmatically
             const form = document.createElement('form');
             form.method = 'POST';
             form.action = '';
             
             const actionInput = document.createElement('input');
             actionInput.type = 'hidden';
             actionInput.name = 'action';
             actionInput.value = 'verify_account';
             
             const userIdInput = document.createElement('input');
             userIdInput.type = 'hidden';
             userIdInput.name = 'user_id';
             userIdInput.value = document.getElementById('viewUserId').value;
             
             form.appendChild(actionInput);
             form.appendChild(userIdInput);
             document.body.appendChild(form);
             form.submit();
         }
         
         function proceedWithDecline() {
             // You can implement decline logic here
             alert('Account declined. You may want to send a message to the resident explaining the reason.');
             hideDeclineConfirmModal();
             hideViewAccountModal();
         }
         
         // Notification Modal Functions
         let currentNotificationUserId = null;
         
         function showNotificationModal(residentName, userId) {
             console.log('showNotificationModal called with:', { residentName, userId });
             currentNotificationUserId = userId;
             document.getElementById('notificationResidentName').textContent = residentName;
             
             const modal = document.getElementById('notificationModal');
             modal.style.display = 'block';
             modal.classList.remove('hidden');
             
             console.log('Current notification user ID set to:', currentNotificationUserId);
         }
         
         function hideNotificationModal() {
             const modal = document.getElementById('notificationModal');
             modal.style.display = 'none';
             modal.classList.add('hidden');
             currentNotificationUserId = null;
         }
         
                  function openViewAccountModalFromNotification() {
             console.log('openViewAccountModalFromNotification called. Current notification user ID:', currentNotificationUserId);
             
             if (currentNotificationUserId) {
                 const residentName = document.getElementById('notificationResidentName').textContent;
                 console.log('Opening View Account modal for:', residentName);
                 
                 // Find the resident row to get the actual document data
                 const residentRow = document.querySelector(`tr[data-user-id="${currentNotificationUserId}"]`);
                 
                 if (residentRow) {
                     // Get the actual document data from the row
                     const purokEndorsement = residentRow.getAttribute('data-purok-endorsement') || '';
                     const validId = residentRow.getAttribute('data-valid-id') || '';
                     const isVerified = residentRow.getAttribute('data-verified') === 'true';
                     
                     console.log('Found resident data:', { purokEndorsement, validId, isVerified });
                     
                     // Hide notification modal first
                     hideNotificationModal();
                     
                     // Open the View Account modal with the same data
                     showViewAccountModal(currentNotificationUserId, residentName, purokEndorsement, validId, isVerified);
                     
                 } else {
                     console.error('Resident row not found');
                     alert('Error: Could not find resident information. Please try again.');
                 }
             } else {
                 console.error('No current notification user ID');
                 alert('Error: No resident selected for notification.');
             }
         }
         

         
         // Document Viewer Functions
         function viewDocument(documentType, filename, documentTitle) {
             console.log('viewDocument called with:', documentType, filename, documentTitle);
             
             // First, let's test if we can find the modal
             const modal = document.getElementById('documentModal');
             console.log('Modal element found:', modal);
             
             if (!modal) {
                 alert('Document modal not found!');
                 return;
             }
             
             // Test if we can find the title and content elements
             const modalTitle = document.getElementById('documentModalTitle');
             const content = document.getElementById('documentContent');
             console.log('Modal title element:', modalTitle);
             console.log('Modal content element:', content);
             
             if (!modalTitle || !content) {
                 alert('Modal elements not found!');
                 return;
             }
             
             // Set the title
             modalTitle.textContent = documentTitle;
             
             // Get the actual filename from the data attribute
             let statusElementId;
             if (documentType === 'purok_endorsement') {
                 statusElementId = 'purokStatus';
             } else if (documentType === 'valid_id') {
                 statusElementId = 'validIdStatus';
             } else {
                 statusElementId = documentType + 'Status';
             }
             
             const statusElement = document.getElementById(statusElementId);
             console.log('Looking for status element with ID:', statusElementId);
             console.log('Status element found:', statusElement);
             
             if (!statusElement) {
                 alert('Status element not found for: ' + documentType + ' (ID: ' + statusElementId + ')');
                 return;
             }
             
             const actualFilename = statusElement.getAttribute('data-filename');
             console.log('Actual filename:', actualFilename);
             
             if (actualFilename) {
                 // Get file extension
                 const fileExt = actualFilename.split('.').pop().toLowerCase();
                 console.log('File extension:', fileExt);
                 
                 if (fileExt === 'pdf') {
                     // Display PDF
                     content.innerHTML = `
                         <iframe src="../uploads/documents/${actualFilename}" 
                                 class="w-full h-96 border border-gray-300 rounded-lg"
                                 frameborder="0">
                         </iframe>
                     `;
                 } else if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
                     // Display image
                     content.innerHTML = `
                         <div class="flex justify-center">
                             <img src="../uploads/documents/${actualFilename}" 
                                  alt="${documentTitle}" 
                                  class="max-w-full max-h-96 object-contain border border-gray-300 rounded-lg">
                         </div>
                     `;
                 } else {
                     // Fallback for other file types
                     content.innerHTML = `
                         <div class="text-center py-8">
                             <p class="text-gray-500 mb-4">This file type cannot be previewed.</p>
                             <a href="../uploads/documents/${actualFilename}" 
                                target="_blank" 
                                class="text-barangay-orange hover:text-orange-600 underline">
                                 Download File
                             </a>
                         </div>
                     `;
                 }
             } else {
                 content.innerHTML = `
                     <div class="text-center py-8">
                         <p class="text-gray-500">Document not available.</p>
                     </div>
                 `;
             }
             
             // Show the modal
             modal.style.display = 'block';
             modal.classList.remove('hidden');
             console.log('Modal should now be visible');
             
             // Test if modal is actually visible
             setTimeout(() => {
                 const isVisible = modal.style.display !== 'none' && !modal.classList.contains('hidden');
                 console.log('Modal visibility check:', isVisible);
                 if (!isVisible) {
                     alert('Modal still hidden after removing hidden class!');
                 }
             }, 100);
         }
         
         function closeDocumentModal() {
             const modal = document.getElementById('documentModal');
             if (modal) {
                 modal.style.display = 'none';
                 modal.classList.add('hidden');
                 console.log('Document modal hidden successfully');
             } else {
                 console.error('Document modal element not found');
             }
         }

        function showMessageModal(userId, userName) {
            document.getElementById('messageUserId').value = userId;
            document.getElementById('messageResidentName').textContent = userName;
            document.getElementById('messageModal').classList.remove('hidden');
        }

        function hideMessageModal() {
            document.getElementById('messageModal').classList.add('hidden');
        }

                              // Close modals when clicking outside
             window.onclick = function(event) {
                 const viewAccountModal = document.getElementById('viewAccountModal');
                 const documentModal = document.getElementById('documentModal');
                 const messageModal = document.getElementById('messageModal');
                 const verifyConfirmModal = document.getElementById('verifyConfirmModal');
                 const declineConfirmModal = document.getElementById('declineConfirmModal');
                 const notificationModal = document.getElementById('notificationModal');
                 
                 if (event.target === viewAccountModal) {
                     hideViewAccountModal();
                 }
                 if (event.target === documentModal) {
                     closeDocumentModal();
                 }
                 if (event.target === messageModal) {
                     hideMessageModal();
                 }
                 if (event.target === verifyConfirmModal) {
                     hideVerifyConfirmModal();
                 }
                 if (event.target === declineConfirmModal) {
                     hideDeclineConfirmModal();
                 }
                 if (event.target === notificationModal) {
                     hideNotificationModal();
                 }
             }
             
             // Close modals with Escape key
             document.addEventListener('keydown', function(e) {
                 if (e.key === 'Escape') {
                     hideViewAccountModal();
                     closeDocumentModal();
                     hideMessageModal();
                     hideVerifyConfirmModal();
                     hideDeclineConfirmModal();
                     hideNotificationModal();
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
