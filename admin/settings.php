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

// Get current admin info
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                
                if (empty($full_name) || empty($email)) {
                    $error = 'Full name and email are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    try {
                        // Check if email already exists for other users
                        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $_SESSION['user_id']]);
                        
                        if ($stmt->fetch()) {
                            $error = 'Email address already exists. Please use a different email.';
                        } else {
                            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                            if ($stmt->execute([$full_name, $email, $_SESSION['user_id']])) {
                                $_SESSION['full_name'] = $full_name;
                                // Redirect to prevent form resubmission
                                header('Location: settings.php?message=Profile updated successfully.');
                                exit();
                            } else {
                                $error = 'Failed to update profile.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating profile: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New password and confirmation password do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } else {
                    try {
                        // Verify current password
                        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                        
                        if (!password_verify($current_password, $user['password'])) {
                            $error = 'Current password is incorrect.';
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                                // Redirect to prevent form resubmission
                                header('Location: settings.php?message=Password changed successfully.');
                                exit();
                            } else {
                                $error = 'Failed to change password.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Error changing password: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'system_settings':
                $system_name = trim($_POST['system_name']);
                $system_description = trim($_POST['system_description']);
                $contact_email = trim($_POST['contact_email']);
                $contact_phone = trim($_POST['contact_phone']);
                $address = trim($_POST['address']);
                
                if (empty($system_name)) {
                    $error = 'System name is required.';
                } else {
                    try {
                        // Update or insert system settings
                        $stmt = $conn->prepare("INSERT OR REPLACE INTO system_settings (setting_key, setting_value) VALUES 
                            ('system_name', ?), ('system_description', ?), ('contact_email', ?), ('contact_phone', ?), ('address', ?)");
                        if ($stmt->execute([$system_name, $system_description, $contact_email, $contact_phone, $address])) {
                            // Redirect to prevent form resubmission
                            header('Location: settings.php?message=System settings updated successfully.');
                            exit();
                        } else {
                            $error = 'Failed to update system settings.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating system settings: ' . $e->getMessage();
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

// Get system settings
$system_settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $system_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

// Create tables if they don't exist
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT,
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
    <title>Settings - Brgy. 172 Urduja</title>
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
                    <a href="health-center.php" class="flex items-center px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
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
                    <a href="settings.php" class="flex items-center px-4 py-2 text-gray-700 bg-barangay-orange bg-opacity-10 rounded-lg border-l-4 border-barangay-orange">
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
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 font-garamond">Settings</h1>
                <p class="text-sm lg:text-base text-gray-600">Manage your profile and system settings</p>
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
                <!-- Profile Settings -->
                <div class="space-y-6">
                    <!-- Update Profile -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">Update Profile</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input type="text" id="full_name" name="full_name" required 
                                       value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" 
                                       disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500">
                                <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                            </div>
                            
                            <button type="submit" class="w-full bg-barangay-orange hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">Change Password</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" id="new_password" name="new_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                            </div>
                            
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="space-y-6">
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">System Settings</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="system_settings">
                            
                            <div>
                                <label for="system_name" class="block text-sm font-medium text-gray-700 mb-2">System Name</label>
                                <input type="text" id="system_name" name="system_name" required 
                                       value="<?php echo htmlspecialchars($system_settings['system_name'] ?? 'Barangay 172 Urduja Management System'); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="system_description" class="block text-sm font-medium text-gray-700 mb-2">System Description</label>
                                <textarea id="system_description" name="system_description" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                          placeholder="Enter system description"><?php echo htmlspecialchars($system_settings['system_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                                <input type="email" id="contact_email" name="contact_email" 
                                       value="<?php echo htmlspecialchars($system_settings['contact_email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                       placeholder="contact@barangay172.com">
                            </div>
                            
                            <div>
                                <label for="contact_phone" class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                                <input type="text" id="contact_phone" name="contact_phone" 
                                       value="<?php echo htmlspecialchars($system_settings['contact_phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                       placeholder="+63 XXX XXX XXXX">
                            </div>
                            
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea id="address" name="address" rows="2" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-green focus:border-transparent"
                                          placeholder="Brgy. 172 Urduja, Caloocan City"><?php echo htmlspecialchars($system_settings['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-barangay-green hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition duration-300">
                                Update System Settings
                            </button>
                        </form>
                    </div>

                    <!-- System Information -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 font-garamond">System Information</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">PHP Version:</span>
                                <span class="font-medium text-gray-900"><?php echo PHP_VERSION; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Database:</span>
                                <span class="font-medium text-gray-900">SQLite</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Server:</span>
                                <span class="font-medium text-gray-900"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in Server'; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Last Updated:</span>
                                <span class="font-medium text-gray-900"><?php echo date('M j, Y g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
