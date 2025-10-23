<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is encoder
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['encoder1', 'encoder2', 'encoder3', 'barangay_staff', 'barangay_hall'])) {
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

$message = '';
$error = '';

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        
        if (empty($full_name) || empty($email)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $email, $user_id])) {
                    $message = 'Profile updated successfully!';
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Error updating profile: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
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
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $message = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}

// Get notification counts for sidebar
$notifications = $notificationHelper->getNotificationCounts($user_id);
$new_concerns = $notifications['concerns'];
$new_applications = $notifications['applications'];
$new_residents = $notifications['residents'];
$total_notifications = $notifications['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Barangay Hall</title>
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
<body class="bg-gray-50 font-poppins">
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 p-8 lg:ml-64 ml-0">
            <!-- Back Button -->
            <div class="mb-6">
                <a href="index.php" class="inline-flex items-center text-gray-600 hover:text-barangay-orange transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
            
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 font-eb-garamond">My Profile</h1>
                <p class="text-gray-600">Manage your account information and password</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Profile Information Card -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-barangay-orange bg-opacity-10 p-3 rounded-full">
                            <svg class="w-6 h-6 text-barangay-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold text-gray-900 font-eb-garamond">Profile Information</h2>
                            <p class="text-sm text-gray-600">Update your account details</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" id="username" name="username" disabled
                                   value="<?php echo htmlspecialchars($user['username']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                            <p class="mt-1 text-xs text-gray-500">Username cannot be changed</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                            <input type="text" disabled
                                   value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed">
                        </div>

                        <button type="submit" class="w-full bg-barangay-orange hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition duration-300">
                            Update Profile
                        </button>
                    </form>
                </div>

                <!-- Change Password Card -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-red-100 p-3 rounded-full">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-xl font-semibold text-gray-900 font-eb-garamond">Change Password</h2>
                            <p class="text-sm text-gray-600">Update your password</p>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password" required
                                       class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
                                <button type="button" onclick="togglePasswordVisibility('current_password', 'toggleCurrentPassword')" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 hover:text-gray-800">
                                    <svg id="toggleCurrentPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password" required
                                       class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
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

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent">
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

                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition duration-300">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                `;
            } else {
                input.type = 'password';
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
    </script>
</body>
</html>
