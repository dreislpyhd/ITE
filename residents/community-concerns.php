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

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's reported concerns
try {
    $stmt = $conn->prepare("
        SELECT * FROM community_concerns 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_concerns = $stmt->fetchAll();
} catch (Exception $e) {
    $user_concerns = [];
}

// Get notification counts for sidebar
$notifications = $notificationHelper->getNotificationCounts($user_id, 'resident');
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];

// Mark community concerns as viewed
$notificationHelper->markAsViewed($user_id, 'community_concerns');

$message = '';
$error = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Concerns - Resident Dashboard</title>
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
                                         <a href="community-concerns.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Report a Community Concern</h1>
                <p class="text-gray-600">Select the type of concern you'd like to report and fill out the form below.</p>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Community Concerns Content -->
            <div class="bg-white rounded-xl shadow-md p-8">
                <!-- Accordion for Concern Types -->
                <div class="space-y-4 mb-8">
                    <!-- Infrastructure Concerns -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('infrastructure')" id="infrastructure-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Infrastructure Concerns</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="infrastructure-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="infrastructure-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Damaged roads / sidewalks</li>
                                <li>• Clogged drainage / canals</li>
                                <li>• Streetlights not working</li>
                                <li>• Damaged barangay facilities</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Infrastructure Concerns')">
                                File a Report
                            </button>
                        </div>
                    </div>

                    <!-- Utilities & Public Services -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('utilities')" id="utilities-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Utilities & Public Services</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="utilities-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="utilities-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Fallen / unsafe electric posts or wires</li>
                                <li>• Water supply issues</li>
                                <li>• Garbage collection / waste management</li>
                                <li>• Noise disturbances (e.g., from businesses or events)</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Utilities & Public Services')">
                                File a Report
                            </button>
                        </div>
                    </div>

                    <!-- Peace & Order -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('peace')" id="peace-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Peace & Order</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="peace-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="peace-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Neighbor disputes / disturbances</li>
                                <li>• Curfew violations</li>
                                <li>• Vandalism</li>
                                <li>• Reports of suspicious activity</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Peace & Order')">
                                File a Report
                            </button>
                        </div>
                    </div>

                    <!-- Health & Sanitation -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('health')" id="health-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Health & Sanitation</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="health-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="health-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Improper waste disposal</li>
                                <li>• Presence of pests or stray animals</li>
                                <li>• Sanitation concerns in public areas</li>
                                <li>• Dengue-prone areas (stagnant water reports)</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Health & Sanitation')">
                                File a Report
                            </button>
                        </div>
                    </div>

                    <!-- Emergency & Safety Concerns -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('emergency')" id="emergency-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Emergency & Safety Concerns</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="emergency-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="emergency-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Fire hazards (exposed wires, flammable storage)</li>
                                <li>• Accident-prone areas</li>
                                <li>• Flood-prone zones</li>
                                <li>• Disaster-related concerns (post-typhoon, earthquake)</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Emergency & Safety Concerns')">
                                File a Report
                            </button>
                        </div>
                    </div>

                    <!-- Other Community Concerns -->
                    <div class="border border-gray-200 rounded-lg">
                        <button class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-barangay-green border border-gray-200" onclick="toggleAccordion('other')" id="other-header">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">Other Community Concerns</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="other-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>
                        <div class="hidden px-6 py-4 border-t border-gray-200" id="other-content">
                            <ul class="space-y-2 text-barangay-green">
                                <li>• Suggestions / feedback</li>
                                <li>• Requests for barangay assistance not covered above</li>
                            </ul>
                            <button class="mt-4 bg-barangay-green text-white px-4 py-2 rounded-lg hover:bg-green-600 transition-colors" onclick="openReportForm('Other Community Concerns')">
                                File a Report
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- My Reported Concerns Section -->
                <div class="mt-8">
                    <!-- My Reported Concerns Header -->
                    <div class="border border-gray-200 rounded-lg mb-6">
                        <div class="w-full px-6 py-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-barangay-green" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-900">My Reported Concerns</span>
                                </div>
                                <span class="text-sm text-gray-500">Track the status of your submitted reports</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($user_concerns)): ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-lg font-eb-garamond font-medium text-gray-900 mb-2">No concerns reported yet</h3>
                            <p class="text-gray-500">You haven't submitted any community concerns yet. Use the form above to report your first concern.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($user_concerns as $concern): ?>
                                <div class="border border-gray-200 rounded-lg p-6 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-3">
                                                <h3 class="text-lg font-eb-garamond font-semibold text-gray-900"><?php echo htmlspecialchars($concern['concern_type']); ?></h3>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php 
                                                    switch($concern['priority_level']) {
                                                        case 'Urgent': echo 'bg-red-100 text-red-800'; break;
                                                        case 'High': echo 'bg-orange-100 text-orange-800'; break;
                                                        case 'Medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                                        case 'Low': echo 'bg-green-100 text-green-800'; break;
                                                    }
                                                    ?>">
                                                    <?php echo htmlspecialchars($concern['priority_level']); ?>
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php 
                                                    switch($concern['status']) {
                                                        case 'Pending': echo 'bg-gray-100 text-gray-800'; break;
                                                        case 'In Progress': echo 'bg-blue-100 text-blue-800'; break;
                                                        case 'Resolved': echo 'bg-green-100 text-green-800'; break;
                                                        case 'Closed': echo 'bg-red-100 text-red-800'; break;
                                                    }
                                                    ?>">
                                                    <?php echo htmlspecialchars($concern['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                                <div>
                                                    <span class="text-sm font-medium text-gray-600">Issue:</span>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($concern['specific_issue']); ?></p>
                                                </div>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-600">Location:</span>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($concern['location']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <span class="text-sm font-medium text-gray-600">Description:</span>
                                                <p class="text-sm text-gray-900 mt-1"><?php echo htmlspecialchars($concern['description']); ?></p>
                                            </div>
                                            
                                            <div class="text-xs text-gray-500">
                                                <span class="font-medium">Submitted:</span> <?php echo date('M j, Y g:i A', strtotime($concern['created_at'])); ?>
                                                <?php if ($concern['updated_at'] && $concern['updated_at'] !== $concern['created_at']): ?>
                                                    | <span class="font-medium">Last Updated:</span> <?php echo date('M j, Y g:i A', strtotime($concern['updated_at'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($concern['admin_response']): ?>
                                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-400">
                                                    <div class="flex items-start">
                                                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                                        </svg>
                                                        <div>
                                                            <p class="text-sm font-medium text-blue-800 mb-1">Barangay Response:</p>
                                                            <p class="text-sm text-blue-700"><?php echo htmlspecialchars($concern['admin_response']); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="ml-4 flex flex-col space-y-2">
                                            <?php if ($concern['photos'] && $concern['photos'] !== ''): ?>
                                                <button onclick="viewConcernPhotos('<?php echo htmlspecialchars($concern['photos']); ?>')" 
                                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                                                    View Photos
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="viewConcernDetails(<?php echo $concern['id']; ?>, '<?php echo htmlspecialchars($concern['concern_type']); ?>', '<?php echo htmlspecialchars($concern['specific_issue']); ?>', '<?php echo htmlspecialchars($concern['location']); ?>', '<?php echo htmlspecialchars($concern['description']); ?>', '<?php echo htmlspecialchars($concern['priority_level']); ?>', '<?php echo date('M j, Y g:i A', strtotime($concern['created_at'])); ?>', '<?php echo htmlspecialchars($concern['photos'] ?? ''); ?>', '<?php echo htmlspecialchars($concern['admin_response'] ?? ''); ?>')" 
                                                    class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Form Modal -->
    <div id="reportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900">Report Community Concern</h3>
                        <button onclick="closeReportForm()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form id="concernForm" enctype="multipart/form-data">
                        <div class="space-y-6">
                            <!-- Concern Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Concern Type</label>
                                <input type="text" id="concernType" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>

                            <!-- Specific Issue -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Specific Issue</label>
                                <select id="specificIssue" name="specific_issue" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent" required>
                                    <option value="">Select specific issue...</option>
                                </select>
                            </div>

                            <!-- Location/Address -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location/Address</label>
                                <input type="text" name="location" placeholder="Enter the specific location or address" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent" required>
                            </div>

                            <!-- Message -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Detailed Description</label>
                                <textarea name="message" rows="4" placeholder="Please provide a detailed description of the concern..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent" required></textarea>
                            </div>

                            <!-- Photo Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Upload Photos (Optional)</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                    <input type="file" id="photoUpload" name="photos[]" multiple accept="image/*" class="hidden">
                                    <button type="button" onclick="document.getElementById('photoUpload').click()" class="text-barangay-orange hover:text-orange-600 font-medium">
                                        <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        Click to upload photos
                                    </button>
                                    <p class="text-sm text-gray-500 mt-2">Upload up to 5 photos (JPG, PNG, GIF)</p>
                                </div>
                                <div id="photoPreview" class="mt-4 grid grid-cols-2 gap-4 hidden"></div>
                            </div>

                            <!-- Priority Level -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority Level</label>
                                <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent" required>
                                    <option value="low">Low - Can be addressed within a week</option>
                                    <option value="medium" selected>Medium - Should be addressed within 3 days</option>
                                    <option value="high">High - Requires immediate attention</option>
                                    <option value="urgent">Urgent - Emergency situation</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 mt-8">
                            <button type="button" onclick="closeReportForm()" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 bg-barangay-orange text-white rounded-lg hover:bg-orange-600 transition-colors">
                                Submit Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-eb-garamond font-medium text-gray-900 mb-2">Report Submitted Successfully!</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="successMessage">
                        Thank you for reporting your concern. The barangay will review and address it accordingly.
                    </p>
                </div>
                <div class="flex justify-center">
                    <button onclick="hideSuccessModal()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-300">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Accordion functionality
        function toggleAccordion(type) {
            const content = document.getElementById(type + '-content');
            const arrow = document.getElementById(type + '-arrow');
            const header = document.getElementById(type + '-header');
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
                header.classList.add('border-barangay-green', 'border-2');
                header.classList.remove('border-gray-200');
            } else {
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
                header.classList.remove('border-barangay-green', 'border-2');
                header.classList.add('border-gray-200');
            }
        }

        // Report form functionality
        function openReportForm(concernType) {
            document.getElementById('concernType').value = concernType;
            populateSpecificIssues(concernType);
            document.getElementById('reportModal').classList.remove('hidden');
        }

        function closeReportForm() {
            document.getElementById('reportModal').classList.add('hidden');
            document.getElementById('concernForm').reset();
            document.getElementById('photoPreview').classList.add('hidden');
            document.getElementById('photoPreview').innerHTML = '';
        }

        function populateSpecificIssues(concernType) {
            const select = document.getElementById('specificIssue');
            select.innerHTML = '<option value="">Select specific issue...</option>';
            
            const issues = {
                'Infrastructure Concerns': [
                    'Damaged roads / sidewalks',
                    'Clogged drainage / canals',
                    'Streetlights not working',
                    'Damaged barangay facilities'
                ],
                'Utilities & Public Services': [
                    'Fallen / unsafe electric posts or wires',
                    'Water supply issues',
                    'Garbage collection / waste management',
                    'Noise disturbances (e.g., from businesses or events)'
                ],
                'Peace & Order': [
                    'Neighbor disputes / disturbances',
                    'Curfew violations',
                    'Vandalism',
                    'Reports of suspicious activity'
                ],
                'Health & Sanitation': [
                    'Improper waste disposal',
                    'Presence of pests or stray animals',
                    'Sanitation concerns in public areas',
                    'Dengue-prone areas (stagnant water reports)'
                ],
                'Emergency & Safety Concerns': [
                    'Fire hazards (exposed wires, flammable storage)',
                    'Accident-prone areas',
                    'Flood-prone zones',
                    'Disaster-related concerns (post-typhoon, earthquake)'
                ],
                'Other Community Concerns': [
                    'Suggestions / feedback',
                    'Requests for barangay assistance not covered above'
                ]
            };
            
            if (issues[concernType]) {
                issues[concernType].forEach(issue => {
                    const option = document.createElement('option');
                    option.value = issue;
                    option.textContent = issue;
                    select.appendChild(option);
                });
            }
        }

        // Photo upload preview
        document.getElementById('photoUpload').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const preview = document.getElementById('photoPreview');
            
            if (files.length > 0) {
                preview.classList.remove('hidden');
                preview.innerHTML = '';
                
                files.slice(0, 5).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'relative';
                            div.innerHTML = `
                                <img src="${e.target.result}" class="w-full h-32 object-cover rounded-lg">
                                <button type="button" onclick="removePhoto(${index})" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm hover:bg-red-600">
                                    ×
                                </button>
                            `;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            } else {
                preview.classList.add('hidden');
            }
        });

        function removePhoto(index) {
            const input = document.getElementById('photoUpload');
            const dt = new DataTransfer();
            const files = Array.from(input.files);
            
            files.forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            
            input.files = dt.files;
            document.getElementById('photoUpload').dispatchEvent(new Event('change'));
        }

        // Form submission
        document.getElementById('concernForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const concernType = document.getElementById('concernType').value;
            formData.append('concern_type', concernType);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            fetch('submit_concern.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(data.message);
                    closeReportForm();
                    // Reset form
                    this.reset();
                    document.getElementById('photoPreview').innerHTML = '';
                    document.getElementById('photoPreview').classList.add('hidden');
                } else {
                    showErrorModal('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('An error occurred while submitting your concern. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        // Close modal when clicking outside
        document.getElementById('reportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReportForm();
            }
        });
    </script>

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

        // Logout Modal Functions
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        // Success Modal Functions
        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.remove('hidden');
        }

        function hideSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }

        function showErrorModal(message) {
            alert(message); // Keep alert for errors for now
        }

        // Concern Details Modal Functions
        function viewConcernDetails(concernId, concernType, specificIssue, location, description, priority, submittedDate, photos, adminResponse) {
            // Create concern details modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-gray-900">Concern Details</h3>
                            <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">${concernType}</h4>
                                    <p class="text-sm text-gray-600">${specificIssue}</p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getPriorityClass(priority)}">
                                    ${priority}
                                </span>
                            </div>
                            
                            <div>
                                <h5 class="font-medium text-gray-900 mb-2">Location</h5>
                                <p class="text-gray-700">${location}</p>
                            </div>
                            
                            <div>
                                <h5 class="font-medium text-gray-900 mb-2">Description</h5>
                                <p class="text-gray-700 whitespace-pre-wrap">${description}</p>
                            </div>
                            
                            <div>
                                <h5 class="font-medium text-gray-900 mb-2">Submitted</h5>
                                <p class="text-gray-700">${submittedDate}</p>
                            </div>
                            
                            ${adminResponse ? `
                                <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-400">
                                    <h5 class="font-medium text-blue-900 mb-2">Barangay Response</h5>
                                    <p class="text-blue-800">${adminResponse}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function viewConcernPhotos(photos) {
            try {
                const photoArray = JSON.parse(photos);
                if (photoArray && photoArray.length > 0) {
                    // Create photos modal
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
                    modal.innerHTML = `
                        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-2xl font-bold text-gray-900">Uploaded Photos</h3>
                                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    ${photoArray.map(photo => `
                                        <div class="relative group">
                                            <img src="../uploads/concerns/${photo}" alt="Concern photo" class="w-full max-h-96 object-contain rounded-lg cursor-pointer border border-gray-200" onclick="openImageModal('../uploads/concerns/${photo}')">
                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200 rounded-lg flex items-center justify-center">
                                                <svg class="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
            } catch (e) {
                console.error('Error parsing photos:', e);
                alert('Error loading photos');
            }
        }

        function getPriorityClass(priority) {
            switch(priority) {
                case 'Urgent': return 'bg-red-100 text-red-800';
                case 'High': return 'bg-orange-100 text-orange-800';
                case 'Medium': return 'bg-yellow-100 text-yellow-800';
                case 'Low': return 'bg-green-100 text-green-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function openImageModal(imageSrc) {
            // Create image modal
            const imageModal = document.createElement('div');
            imageModal.className = 'fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4';
            imageModal.innerHTML = `
                <div class="relative max-w-4xl max-h-full">
                    <button onclick="this.parentElement.parentElement.remove()" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <img src="${imageSrc}" alt="Full size image" class="max-w-full max-h-full object-contain rounded-lg">
                </div>
            `;
            document.body.appendChild(imageModal);
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
                hideSuccessModal();
            }
        });

        // Close success modal when clicking outside
        document.getElementById('successModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideSuccessModal();
            }
        });
    </script>
</body>
</html>
