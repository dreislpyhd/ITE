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
$db = new Database();
$conn = $db->getConnection();
$notificationHelper = new NotificationHelper();

// Include notification badge helper
require_once 'includes/notification_badge.php';

// Get notification counts
$user_id = $_SESSION['user_id'];
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $service_name = trim($_POST['service_name']);
                $requirements = trim($_POST['requirements']);
                
                if (empty($service_name)) {
                    $error = 'Service name is required';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO barangay_services (service_name, requirements) VALUES (?, ?)");
                        if ($stmt->execute([$service_name, $requirements])) {
                            // Redirect to prevent form resubmission
                            header('Location: services.php?message=Service added successfully');
                            exit();
                        } else {
                            $error = 'Failed to add service';
                        }
                    } catch (Exception $e) {
                        $error = 'Error adding service: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_service':
                $service_id = $_POST['service_id'];
                $service_name = trim($_POST['service_name']);
                $requirements = trim($_POST['requirements']);
                $status = $_POST['status'];
                
                if (empty($service_name)) {
                    $error = 'Service name is required';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE barangay_services SET service_name = ?, requirements = ?, status = ? WHERE id = ?");
                        if ($stmt->execute([$service_name, $requirements, $status, $service_id])) {
                            // Redirect to prevent form resubmission
                            header('Location: services.php?message=Service updated successfully');
                            exit();
                        } else {
                            $error = 'Failed to update service';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating service: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_service':
                $service_id = $_POST['service_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM barangay_services WHERE id = ?");
                    if ($stmt->execute([$service_id])) {
                        // Redirect to prevent form resubmission
                        header('Location: services.php?message=Service deleted successfully');
                        exit();
                    } else {
                        $error = 'Failed to delete service';
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting service: ' . $e->getMessage();
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

// Get all services
try {
    $stmt = $conn->prepare("SELECT * FROM barangay_services ORDER BY service_name ASC");
    $stmt->execute();
    $services = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $services = [];
}

// Get service statistics
try {
    $total_services = $conn->query("SELECT COUNT(*) FROM barangay_services")->fetchColumn();
    $active_services = $conn->query("SELECT COUNT(*) FROM barangay_services WHERE status = 'active'")->fetchColumn();
    $inactive_services = $conn->query("SELECT COUNT(*) FROM barangay_services WHERE status = 'inactive'")->fetchColumn();
} catch (Exception $e) {
    $total_services = 0;
    $active_services = 0;
    $inactive_services = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates Management - Barangay Hall</title>
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
                    <a href="services.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
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
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">Certificates Management</h1>
                <p class="text-gray-600 mt-2">Manage all barangay certificates and document requests</p>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>



            <!-- Add Service Button -->
            <div class="mb-6">
                <button onclick="showAddConfirmModal()" class="bg-barangay-green hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add New Certificate
                </button>
            </div>

            <!-- Certificates Table -->
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">Available Certificates</h3>
                </div>
                
                <?php if (empty($services)): ?>
                    <div class="p-6 text-center text-gray-500">
                        No certificates found. Add your first certificate to get started.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requirements</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($services as $service): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                // Split by comma but preserve content within parentheses
                                                $requirements = preg_split('/,(?![^(]*\))/', $service['requirements']);
                                                echo '<ul class="list-disc list-inside space-y-1">';
                                                foreach ($requirements as $requirement) {
                                                    $requirement = trim($requirement);
                                                    // Format text inside parentheses to be italic, smaller, and gray
                                                    $formatted_requirement = preg_replace('/\(([^)]+)\)/', '<span class="text-xs italic text-gray-500">($1)</span>', htmlspecialchars($requirement));
                                                    echo '<li class="text-gray-700">' . $formatted_requirement . '</li>';
                                                }
                                                echo '</ul>';
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($service['status'] === 'active'): ?>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="showEditConfirmModal(<?php echo htmlspecialchars(json_encode($service)); ?>)" 
                                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                    Edit
                                                </button>
                                                <button onclick="confirmDeleteService(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['service_name']); ?>')" 
                                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-sm font-medium transition duration-300">
                                                    Delete
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
        </div>
    </div>

    <!-- Add Service Modal -->
    <div id="addServiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Add New Certificate</h3>
                    <button onclick="hideAddServiceModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_service">
                    
                    <div>
                        <label for="service_name" class="block text-sm font-medium text-gray-700 mb-1">Certificate Name *</label>
                        <input type="text" id="service_name" name="service_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="requirements" class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                        <textarea id="requirements" name="requirements" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"></textarea>
                    </div>
                    

                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hideAddServiceModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Add Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Edit Certificate</h3>
                    <button onclick="hideEditServiceModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_service">
                    <input type="hidden" id="edit_service_id" name="service_id">
                    
                    <div>
                        <label for="edit_service_name" class="block text-sm font-medium text-gray-700 mb-1">Certificate Name *</label>
                        <input type="text" id="edit_service_name" name="service_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                    </div>
                    
                    <div>
                        <label for="edit_requirements" class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                        <textarea id="edit_requirements" name="requirements" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent"></textarea>
                    </div>
                    

                    
                    <div>
                        <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="edit_status" name="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="hideEditServiceModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Update Certificate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Delete Certificate</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to delete the certificate "<span id="deleteServiceName" class="font-medium text-gray-900"></span>"?
                        </p>
                        <p class="text-xs text-red-500 mt-2">This action cannot be undone.</p>
                    </div>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="hideDeleteConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Cancel
                    </button>
                    <form method="POST" id="deleteServiceForm" class="inline">
                        <input type="hidden" name="action" value="delete_service">
                        <input type="hidden" name="service_id" id="deleteServiceId">
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Certificate Confirmation Modal -->
    <div id="addConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-green-100 rounded-full">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Add New Certificate</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to add a new certificate? This will create a new entry in the system.
                        </p>
                    </div>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="hideAddConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Cancel
                    </button>
                    <button onclick="proceedToAddCertificate()" 
                            class="bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Proceed
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Certificate Confirmation Modal -->
    <div id="editConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-blue-100 rounded-full">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <div class="mt-2 text-center">
                    <h3 class="text-lg font-medium text-gray-900 font-eb-garamond">Edit Certificate</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Are you sure you want to edit the certificate "<span id="editServiceName" class="font-medium text-gray-900"></span>"?
                        </p>
                    </div>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="hideEditConfirmModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Cancel
                    </button>
                    <button onclick="proceedToEditCertificate()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                        Proceed
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddServiceModal() {
            document.getElementById('addServiceModal').classList.remove('hidden');
        }
        
        function hideAddServiceModal() {
            document.getElementById('addServiceModal').classList.add('hidden');
        }
        
        function showEditServiceModal(service) {
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_service_name').value = service.service_name;
            document.getElementById('edit_requirements').value = service.requirements;
            document.getElementById('edit_status').value = service.status;
            
            document.getElementById('editServiceModal').classList.remove('hidden');
        }
        
        function hideEditServiceModal() {
            document.getElementById('editServiceModal').classList.add('hidden');
        }
        
        function confirmDeleteService(serviceId, serviceName) {
            document.getElementById('deleteServiceId').value = serviceId;
            document.getElementById('deleteServiceName').textContent = serviceName;
            document.getElementById('deleteConfirmModal').classList.remove('hidden');
        }
        
        function hideDeleteConfirmModal() {
            document.getElementById('deleteConfirmModal').classList.add('hidden');
        }
        
        // Confirmation modal functions
        function showAddConfirmModal() {
            document.getElementById('addConfirmModal').classList.remove('hidden');
        }
        
        function hideAddConfirmModal() {
            document.getElementById('addConfirmModal').classList.add('hidden');
        }
        
        function proceedToAddCertificate() {
            hideAddConfirmModal();
            showAddServiceModal();
        }
        
        function showEditConfirmModal(service) {
            document.getElementById('editServiceName').textContent = service.service_name;
            // Store service data for later use
            window.currentEditService = service;
            document.getElementById('editConfirmModal').classList.remove('hidden');
        }
        
        function hideEditConfirmModal() {
            document.getElementById('editConfirmModal').classList.add('hidden');
        }
        
        function proceedToEditCertificate() {
            hideEditConfirmModal();
            if (window.currentEditService) {
                showEditServiceModal(window.currentEditService);
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addServiceModal');
            const editModal = document.getElementById('editServiceModal');
            const deleteModal = document.getElementById('deleteConfirmModal');
            const addConfirmModal = document.getElementById('addConfirmModal');
            const editConfirmModal = document.getElementById('editConfirmModal');
            
            if (event.target === addModal) {
                hideAddServiceModal();
            }
            if (event.target === editModal) {
                hideEditServiceModal();
            }
            if (event.target === deleteModal) {
                hideDeleteConfirmModal();
            }
            if (event.target === addConfirmModal) {
                hideAddConfirmModal();
            }
            if (event.target === editConfirmModal) {
                hideEditConfirmModal();
            }
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideAddServiceModal();
                hideEditServiceModal();
                hideDeleteConfirmModal();
                hideAddConfirmModal();
                hideEditConfirmModal();
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

    </script>

    <?php include '../includes/logout_modal.php'; ?>
    
    <!-- Include Success Modal -->
    <?php include '../includes/success-modal.php'; ?>
</body>
</html>
