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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_health_service':
                $service_name = trim($_POST['service_name']);
                $description = trim($_POST['description']);

                $schedule = trim($_POST['schedule']);
                $fee = trim($_POST['fee']);
                $service_type = $_POST['service_type'];
                
                if (empty($service_name) || empty($description)) {
                    $error = 'Service name and description are required.';
                } else {
                    try {
                        $stmt = $conn->prepare("INSERT INTO health_services (service_name, description, schedule, fee, service_type, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
                        if ($stmt->execute([$service_name, $description, $schedule, $fee, $service_type])) {
                            $message = 'Health service added successfully.';
                        } else {
                            $error = 'Failed to add health service.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error adding health service: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_health_service':
                $service_id = $_POST['service_id'];
                $service_name = trim($_POST['service_name']);
                $description = trim($_POST['description']);

                $schedule = trim($_POST['schedule']);
                $fee = trim($_POST['fee']);
                $service_type = $_POST['service_type'];
                
                if (empty($service_name) || empty($description)) {
                    $error = 'Service name and description are required.';
                } else {
                    try {
                        $stmt = $conn->prepare("UPDATE health_services SET service_name = ?, description = ?, schedule = ?, fee = ?, service_type = ?, updated_at = datetime('now') WHERE id = ?");
                        if ($stmt->execute([$service_name, $description, $schedule, $fee, $service_type, $service_id])) {
                            $message = 'Health service updated successfully.';
                        } else {
                            $error = 'Failed to update health service.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating health service: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_health_service':
                $service_id = $_POST['service_id'];
                try {
                    $stmt = $conn->prepare("DELETE FROM health_services WHERE id = ?");
                    if ($stmt->execute([$service_id])) {
                        $message = 'Health service deleted successfully.';
                    } else {
                        $error = 'Failed to delete health service.';
                    }
                } catch (Exception $e) {
                    $error = 'Error deleting health service: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get health services
try {
    $stmt = $conn->query("SELECT * FROM health_services ORDER BY created_at DESC");
    $health_services = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
    $health_services = [];
}

// Create table if it doesn't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS health_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        service_name TEXT NOT NULL,
        description TEXT NOT NULL,

        schedule TEXT,
        fee TEXT,
        service_type TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Center Services - Brgy. 172 Urduja</title>
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
                    <a href="health-center.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        Health Center
                    </a>
                    <a href="reports.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
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
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 font-garamond">Health Center Services</h1>
                <p class="text-sm lg:text-base text-gray-600">Manage health services, medical programs, and health records</p>
            </div>

            <!-- Services List -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($health_services)): ?>
                    <div class="col-span-full bg-white rounded-xl shadow-md p-12">
                        <div class="flex flex-col items-center justify-center text-center">
                            <svg class="w-20 h-20 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Services Available</h3>
                            <p class="text-gray-500">There are currently no health services to display.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($health_services as $service): ?>
                        <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow overflow-hidden">
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($service['description']); ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($service['schedule']): ?>
                                    <div class="mb-3">
                                        <p class="text-xs font-semibold text-gray-700 mb-1">Schedule:</p>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($service['schedule']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
