<?php
require_once '../includes/config.php';
session_start();
require_once '../includes/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // Debug: Log the user data retrieved
        error_log("User data retrieved: " . print_r($user, true));
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Debug: Log the role and redirect
            error_log("Login successful for user: " . $username . " with role: " . $user['role']);
            
            // Redirect based on role
            switch($user['role']) {
                case 'admin':
                    error_log("Redirecting admin to: ../admin/index.php");
                    header('Location: ../admin/index.php');
                    break;
                case 'barangay_staff':
                    error_log("Redirecting barangay_staff to: ../barangay-hall/index.php");
                    header('Location: ../barangay-hall/index.php');
                    break;
                case 'health_staff':
                    error_log("Redirecting health_staff to: ../health-center/index.php");
                    header('Location: ../health-center/index.php');
                    break;
                case 'resident':
                    error_log("Redirecting resident to: ../residents/index.php");
                    header('Location: ../residents/index.php');
                    break;
                default:
                    error_log("Unknown role: " . $user['role'] . ", redirecting to: ../index.html");
                    header('Location: ../index.html');
            }
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Barangay Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        'barangay-orange': '#ff6700',
                        'barangay-green': '#2E8B57',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-image: url('../assets/images/hall.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.85);
            z-index: -1;
        }
    </style>
</head>
<body class="font-poppins min-h-screen">
    <!-- Navigation -->
    <nav class="bg-barangay-orange shadow-sm border-b border-orange-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../index.html" class="flex items-center space-x-3 text-white hover:text-orange-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <span class="font-medium">Back to Home</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex min-h-screen items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-sm w-full">
            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                <!-- Header -->
                <div class="text-center mb-6">
                    <div class="flex items-center justify-center space-x-3 mb-4">
                        <img src="../assets/images/AM-logo.png" alt="AM Logo" class="h-12 w-12 rounded-full bg-white p-1 shadow-md object-cover">
                        <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="h-20 w-20 rounded-full shadow-md">
                        <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="h-12 w-12 rounded-full bg-white p-1 shadow-md object-cover">
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        Welcome Back
                    </h2>
                    <p class="text-sm text-gray-600">
                        Sign in to your Barangay 172 Urduja account
                    </p>
                </div>
                
                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form class="space-y-4" method="POST">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input id="username" name="username" type="text" required 
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                   placeholder="Enter your username">
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <input id="password" name="password" type="password" required 
                                    class="block w-full pl-10 pr-12 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-barangay-orange focus:border-transparent transition duration-300"
                                    placeholder="Enter your password">
                            <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg id="eyeIcon" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-semibold rounded-xl text-white bg-barangay-orange hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-barangay-orange transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-white group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                </svg>
                            </span>
                            Sign In
                        </button>
                    </div>

                    <!-- Links -->
                    <div class="text-center space-y-3">
                        <a href="register.php" class="block text-sm text-barangay-green hover:text-green-700 font-medium transition-colors">
                            Don't have an account? 
                            <span class="underline">Create one here</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Footer Note -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-500">
                    Secure access to Barangay 172 Urduja services
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const togglePasswordButton = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');

            togglePasswordButton.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                
                // Toggle between eye and eye-slash icons
                if (type === 'password') {
                    // Show eye icon (password hidden)
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    `;
                } else {
                    // Show eye-slash icon (password visible)
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                    `;
                }
            });
        });
    </script>
</body>
</html>
