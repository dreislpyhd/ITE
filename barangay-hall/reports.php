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

$message = '';
$error = '';

// Get statistics for reports (filtered by encoder's assigned streets)
$stats = [];
try {
    $user_role = $_SESSION['role'];
    $is_encoder = in_array($user_role, ['encoder1', 'encoder2', 'encoder3']);
    
    if ($is_encoder) {
        $assigned_streets = getStreetsForEncoder($user_role);
        $street_placeholders = implode(',', array_fill(0, count($assigned_streets), '?'));
        
        // Resident statistics (filtered by streets)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['total_residents'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND account_verified = 1 AND street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['verified_residents'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND (account_verified = 0 OR account_verified IS NULL) AND street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['unverified_residents'] = $stmt->fetch()['count'];
        
        // Application statistics (filtered by streets)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['total_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.status = 'pending' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['pending_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.status = 'processing' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['processing_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.status = 'approved' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['approved_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.status = 'rejected' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['rejected_applications'] = $stmt->fetch()['count'];
        
        // Community concerns statistics (filtered by streets)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM community_concerns cc LEFT JOIN users u ON cc.user_id = u.id WHERE u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['total_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM community_concerns cc LEFT JOIN users u ON cc.user_id = u.id WHERE cc.status = 'pending' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['pending_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM community_concerns cc LEFT JOIN users u ON cc.user_id = u.id WHERE cc.status = 'processing' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['processing_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM community_concerns cc LEFT JOIN users u ON cc.user_id = u.id WHERE cc.status = 'resolved' AND u.street IN ($street_placeholders)");
        $stmt->execute($assigned_streets);
        $stats['resolved_concerns'] = $stmt->fetch()['count'];
    } else {
        // For barangay_staff and barangay_hall, show all
        // Resident statistics
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'resident'");
        $stats['total_residents'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND account_verified = 1");
        $stats['verified_residents'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND (account_verified = 0 OR account_verified IS NULL)");
        $stats['unverified_residents'] = $stmt->fetch()['count'];
        
        // Application statistics
        $stmt = $conn->query("SELECT COUNT(*) as count FROM applications");
        $stats['total_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
        $stats['pending_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'processing'");
        $stats['processing_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
        $stats['approved_applications'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
        $stats['rejected_applications'] = $stmt->fetch()['count'];
        
        // Community concerns statistics
        $stmt = $conn->query("SELECT COUNT(*) as count FROM community_concerns");
        $stats['total_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM community_concerns WHERE status = 'pending'");
        $stats['pending_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM community_concerns WHERE status = 'processing'");
        $stats['processing_concerns'] = $stmt->fetch()['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM community_concerns WHERE status = 'resolved'");
        $stats['resolved_concerns'] = $stmt->fetch()['count'];
    }
    
    // Barangay services statistics
    $stmt = $conn->query("SELECT COUNT(*) as count FROM barangay_services");
    $stats['total_services'] = $stmt->fetch()['count'];
    
    // Recent activity (last 30 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM applications WHERE application_date >= datetime('now', '-30 days')");
    $stats['recent_applications'] = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM community_concerns WHERE created_at >= datetime('now', '-30 days')");
    $stats['recent_concerns'] = $stmt->fetch()['count'];
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'resident' AND created_at >= datetime('now', '-30 days')");
    $stats['recent_registrations'] = $stmt->fetch()['count'];
    
    // Monthly data for charts
    $stmt = $conn->query("SELECT strftime('%Y-%m', application_date) as month, COUNT(*) as count FROM applications GROUP BY strftime('%Y-%m', application_date) ORDER BY month DESC LIMIT 12");
    $stats['monthly_applications'] = $stmt->fetchAll();
    
    $stmt = $conn->query("SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as count FROM community_concerns WHERE created_at IS NOT NULL GROUP BY strftime('%Y-%m', created_at) ORDER BY month DESC LIMIT 12");
    $stats['monthly_concerns'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_residents':
                try {
                    $stmt = $conn->query("SELECT username, full_name, email, address, phone, account_verified, created_at FROM users WHERE role = 'resident' ORDER BY created_at DESC");
                    $residents = $stmt->fetchAll();
                    
                    $filename = 'residents_report_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['Username', 'Full Name', 'Email', 'Address', 'Phone', 'Verified', 'Registration Date']);
                    
                    foreach ($residents as $resident) {
                        fputcsv($output, [
                            $resident['username'],
                            $resident['full_name'],
                            $resident['email'],
                            $resident['address'],
                            $resident['phone'],
                            $resident['account_verified'] ? 'Yes' : 'No',
                            date('M j, Y', strtotime($resident['created_at']))
                        ]);
                    }
                    
                    fclose($output);
                    exit();
                } catch (Exception $e) {
                    $error = 'Error exporting residents: ' . $e->getMessage();
                }
                break;
                
            case 'export_applications':
                try {
                    $stmt = $conn->query("
                        SELECT a.id, a.service_type, a.service_id, a.status, a.application_date,
                               u.full_name, u.email,
                               CASE WHEN a.service_type = 'barangay' THEN bs.service_name
                                    WHEN a.service_type = 'health' THEN hs.service_name
                                    ELSE 'Unknown Service' END as service_name
                        FROM applications a
                        LEFT JOIN users u ON a.user_id = u.id
                        LEFT JOIN barangay_services bs ON a.service_type = 'barangay' AND a.service_id = bs.id
                        LEFT JOIN health_services hs ON a.service_type = 'health' AND a.service_id = hs.id
                        ORDER BY a.application_date DESC
                    ");
                    $applications = $stmt->fetchAll();
                    
                    $filename = 'applications_report_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    fputcsv($output, ['ID', 'Service Type', 'Service Name', 'Status', 'Applicant', 'Email', 'Application Date']);
                    
                    foreach ($applications as $app) {
                        fputcsv($output, [
                            $app['id'],
                            ucfirst($app['service_type']),
                            $app['service_name'],
                            ucfirst($app['status']),
                            $app['full_name'],
                            $app['email'],
                            $app['application_date'] ? date('M j, Y', strtotime($app['application_date'])) : 'N/A'
                        ]);
                    }
                    
                    fclose($output);
                    exit();
                } catch (Exception $e) {
                    $error = 'Error exporting applications: ' . $e->getMessage();
                }
                break;
                
            case 'export_concerns':
                try {
                    // Try to get the table structure first
                    $stmt = $conn->query("DESCRIBE community_concerns");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Build query based on available columns
                    $select_columns = ['c.id', 'c.concern_type', 'c.description', 'c.status', 'c.created_at', 'c.admin_response'];
                    if (in_array('priority', $columns)) {
                        $select_columns[] = 'c.priority';
                    }
                    if (in_array('location', $columns)) {
                        $select_columns[] = 'c.location';
                    }
                    
                    $select_clause = implode(', ', $select_columns);
                    
                    $stmt = $conn->query("
                        SELECT {$select_clause}, u.full_name, u.email
                        FROM community_concerns c
                        LEFT JOIN users u ON c.user_id = u.id
                        ORDER BY c.created_at DESC
                    ");
                    
                    $concerns = $stmt->fetchAll();
                    
                    $filename = 'concerns_report_' . date('Y-m-d_H-i-s') . '.csv';
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    
                    $output = fopen('php://output', 'w');
                    
                    // Build headers based on available columns
                    $headers = ['ID', 'Concern Type', 'Description', 'Status'];
                    if (in_array('priority', $columns)) {
                        $headers[] = 'Priority';
                    }
                    if (in_array('location', $columns)) {
                        $headers[] = 'Location';
                    }
                    $headers = array_merge($headers, ['Reporter', 'Email', 'Report Date', 'Admin Response']);
                    
                    fputcsv($output, $headers);
                    
                    foreach ($concerns as $concern) {
                        $row = [
                            $concern['id'],
                            ucfirst(str_replace('_', ' ', $concern['concern_type'])),
                            $concern['description'],
                            ucfirst($concern['status'])
                        ];
                        
                        if (in_array('priority', $columns)) {
                            $row[] = ucfirst($concern['priority']);
                        }
                        if (in_array('location', $columns)) {
                            $row[] = $concern['location'] ?? 'N/A';
                        }
                        
                        $row = array_merge($row, [
                            $concern['full_name'],
                            $concern['email'],
                            date('M j, Y', strtotime($concern['created_at'])),
                            $concern['admin_response'] ?? 'N/A'
                        ]);
                        
                        fputcsv($output, $row);
                    }
                    
                    fclose($output);
                    exit();
                } catch (Exception $e) {
                    $error = 'Error exporting concerns: ' . $e->getMessage();
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
    <title>Reports - Barangay Hall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="barangay-staff.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors relative">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Residents Account
                        <?php if ($notification_counts['residents'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                            </span>
                        <?php endif; ?>
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
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
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
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Reports & Analytics</h1>
                <p class="text-gray-600">Generate reports and view analytics for barangay operations</p>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Residents -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-50 text-blue-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Residents</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_residents']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Applications -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50 text-green-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Applications</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_applications']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Concerns -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-50 text-orange-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Concerns</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_concerns']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Services -->
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-50 text-purple-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Services</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_services']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Applications Status -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond mb-4">Applications Status</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <span class="text-sm font-medium text-orange-600"><?php echo $stats['pending_applications']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Processing</span>
                            <span class="text-sm font-medium text-blue-600"><?php echo $stats['processing_applications']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Approved</span>
                            <span class="text-sm font-medium text-green-600"><?php echo $stats['approved_applications']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Rejected</span>
                            <span class="text-sm font-medium text-red-600"><?php echo $stats['rejected_applications']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Concerns Status -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond mb-4">Community Concerns Status</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <span class="text-sm font-medium text-orange-600"><?php echo $stats['pending_concerns']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Processing</span>
                            <span class="text-sm font-medium text-blue-600"><?php echo $stats['processing_concerns']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Resolved</span>
                            <span class="text-sm font-medium text-green-600"><?php echo $stats['resolved_concerns']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Reports Section -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond mb-4">Export Reports</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="export_residents">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Residents
                        </button>
                    </form>
                    
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="export_applications">
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Applications
                        </button>
                    </form>
                    
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="export_concerns">
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Concerns
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond mb-4">Recent Activity (Last 30 Days)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $stats['recent_applications']; ?></div>
                        <div class="text-sm text-gray-600">New Applications</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600"><?php echo $stats['recent_concerns']; ?></div>
                        <div class="text-sm text-gray-600">New Concerns</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $stats['recent_registrations']; ?></div>
                        <div class="text-sm text-gray-600">New Registrations</div>
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
    </script>
</body>
</html>
