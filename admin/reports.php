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

// Get statistics for reports
$stats = [];
try {
    // User statistics
    $stmt = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $role_counts = [];
    while ($row = $stmt->fetch()) {
        $role_counts[$row['role']] = $row['count'];
    }
    $stats['total_users'] = array_sum($role_counts);
    $stats['residents'] = $role_counts['resident'] ?? 0;
    $stats['barangay_hall'] = $role_counts['barangay_hall'] ?? 0;
    $stats['health_center'] = $role_counts['health_center'] ?? 0;
    $stats['admins'] = $role_counts['admin'] ?? 0;
    
    // Recent registrations (last 30 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= date('now', '-30 days')");
    $stats['recent_registrations'] = $stmt->fetch()['count'];
    
    // Barangay services count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM barangay_services");
    $stats['barangay_services'] = $stmt->fetch()['count'];
    
    // Health services count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM health_services");
    $stats['health_services'] = $stmt->fetch()['count'];
    
    // Monthly registrations for chart
    $stmt = $conn->query("SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as count FROM users GROUP BY strftime('%Y-%m', created_at) ORDER BY month DESC LIMIT 12");
    $monthly_data = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_users':
                try {
                    $stmt = $conn->query("SELECT username, full_name, email, role, created_at FROM users ORDER BY created_at DESC");
                    $users = $stmt->fetchAll();
                    
                    $filename = 'users_report_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['Username', 'Full Name', 'Email', 'Role', 'Registration Date']);
                    
                    foreach ($users as $user) {
                        fputcsv($output, [
                            $user['username'],
                            $user['full_name'],
                            $user['email'],
                            ucfirst(str_replace('_', ' ', $user['role'])),
                            date('M j, Y', strtotime($user['created_at']))
                        ]);
                    }
                    
                    fclose($output);
                    exit();
                } catch (Exception $e) {
                    $error = 'Error exporting users: ' . $e->getMessage();
                }
                break;
                
            case 'export_services':
                try {
                    $service_type = $_POST['service_type'];
                    $table = $service_type === 'health' ? 'health_services' : 'barangay_services';
                    
                    $stmt = $conn->query("SELECT * FROM $table ORDER BY created_at DESC");
                    $services = $stmt->fetchAll();
                    
                    $filename = $service_type . '_services_report_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    if ($service_type === 'health') {
                        fputcsv($output, ['Service Name', 'Type', 'Description', 'Schedule', 'Fee', 'Requirements', 'Created Date']);
                        foreach ($services as $service) {
                            fputcsv($output, [
                                $service['service_name'],
                                ucfirst(str_replace('_', ' ', $service['service_type'])),
                                $service['description'],
                                $service['schedule'] ?: 'Not specified',
                                $service['fee'] ?: 'Not specified',

                                date('M j, Y', strtotime($service['created_at']))
                            ]);
                        }
                    } else {
                        fputcsv($output, ['Service Name', 'Description', 'Requirements', 'Processing Time', 'Fee', 'Created Date']);
                        foreach ($services as $service) {
                            fputcsv($output, [
                                $service['service_name'],
                                $service['description'],

                                $service['processing_time'] ?: 'Not specified',
                                $service['fee'] ?: 'Not specified',
                                date('M j, Y', strtotime($service['created_at']))
                            ]);
                        }
                    }
                    
                    fclose($output);
                    exit();
                } catch (Exception $e) {
                    $error = 'Error exporting services: ' . $e->getMessage();
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Brgy. 172 Urduja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=EB+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/night-mode.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        Dashboard
                    </a>
                    <a href="users.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                        </svg>
                        <span class="whitespace-nowrap">User Management</span>
                    </a>
                    <a href="barangay-hall.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Barangay Hall
                    </a>
                    <a href="health-center.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Health Center
                    </a>
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Reports
                    </a>
                    <a href="settings.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Settings
                    </a>
                    <button onclick="showLogoutModal()" class="w-full flex items-center px-4 py-2 text-gray-600 hover:bg-red-50 hover:text-red-600 rounded-lg transition-colors mt-2">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-4 lg:p-8 lg:ml-0 ml-16 lg:ml-0">
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 font-garamond">Reports & Analytics</h1>
                <p class="text-sm lg:text-base text-gray-600">Generate reports and view system analytics</p>
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

            <!-- Statistics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_users'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Recent Registrations</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['recent_registrations'] ?? 0; ?></p>
                            <p class="text-xs text-gray-500">Last 30 days</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Barangay Services</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['barangay_services'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Health Services</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['health_services'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- User Distribution Chart -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">User Distribution</h3>
                    <canvas id="userChart" width="400" height="200"></canvas>
                </div>

                <!-- Registration Trend Chart -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">Registration Trend</h3>
                    <canvas id="trendChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Report Generation -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">Generate Reports</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- User Reports -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3 font-garamond">User Reports</h4>
                        <p class="text-sm text-gray-600 mb-4">Export user data and statistics</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="export_users">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Export Users Report (CSV)
                            </button>
                        </form>
                    </div>

                    <!-- Barangay Services Reports -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3 font-garamond">Barangay Services Report</h4>
                        <p class="text-sm text-gray-600 mb-4">Export barangay services data</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="export_services">
                            <input type="hidden" name="service_type" value="barangay">
                            <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Export Services Report (CSV)
                            </button>
                        </form>
                    </div>

                    <!-- Health Services Reports -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3 font-garamond">Health Services Report</h4>
                        <p class="text-sm text-gray-600 mb-4">Export health services data</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="export_services">
                            <input type="hidden" name="service_type" value="health">
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Export Health Report (CSV)
                            </button>
                        </form>
                    </div>

                    <!-- System Summary -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3 font-garamond">System Summary</h4>
                        <p class="text-sm text-gray-600 mb-4">View comprehensive system overview</p>
                        <button onclick="showSystemSummary()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            View System Summary
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Summary Modal -->
    <div id="summaryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-2xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900 font-garamond">System Summary Report</h3>
                <button onclick="hideSummaryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-sm font-medium text-gray-600">Total Users</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_users'] ?? 0; ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-sm font-medium text-gray-600">Residents</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $stats['residents'] ?? 0; ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-sm font-medium text-gray-600">Barangay Hall Staff</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $stats['barangay_hall'] ?? 0; ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-sm font-medium text-gray-600">Health Center Staff</p>
                        <p class="text-xl font-bold text-gray-900"><?php echo $stats['health_center'] ?? 0; ?></p>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-2 font-garamond">Services Overview</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-orange-50 p-3 rounded-lg">
                            <p class="text-sm font-medium text-orange-600">Barangay Services</p>
                            <p class="text-lg font-bold text-orange-800"><?php echo $stats['barangay_services'] ?? 0; ?></p>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <p class="text-sm font-medium text-green-600">Health Services</p>
                            <p class="text-lg font-bold text-green-800"><?php echo $stats['health_services'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="font-medium text-gray-900 mb-2 font-garamond">Recent Activity</h4>
                    <p class="text-sm text-gray-600">
                        <strong><?php echo $stats['recent_registrations'] ?? 0; ?></strong> new users registered in the last 30 days.
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end mt-6">
                <button onclick="hideSummaryModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // User Distribution Chart
        const userCtx = document.getElementById('userChart').getContext('2d');
        new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: ['Residents', 'Barangay Hall Staff', 'Health Center Staff', 'Admins'],
                datasets: [{
                    data: [
                        <?php echo $stats['residents'] ?? 0; ?>,
                        <?php echo $stats['barangay_hall'] ?? 0; ?>,
                        <?php echo $stats['health_center'] ?? 0; ?>,
                        <?php echo $stats['admins'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#10B981',
                        '#F59E0B',
                        '#8B5CF6',
                        '#EF4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Registration Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column(array_reverse($monthly_data ?? []), 'month')); ?>,
                datasets: [{
                    label: 'New Registrations',
                    data: <?php echo json_encode(array_column(array_reverse($monthly_data ?? []), 'count')); ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function showSystemSummary() {
            document.getElementById('summaryModal').classList.remove('hidden');
        }

        function hideSummaryModal() {
            document.getElementById('summaryModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('summaryModal').addEventListener('click', function(e) {
            if (e.target === this) hideSummaryModal();
        });
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
