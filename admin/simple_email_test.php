<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/SimpleEmailService.php';

$message = '';
$error = '';

if ($_POST) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        $error = 'Please enter an email address.';
    } else {
        try {
            $simpleEmailService = new SimpleEmailService();
            
            $emailSent = $simpleEmailService->sendRegistrationCredentials(
                $test_email, 
                'testuser', 
                'testpass123', 
                'Test User', 
                'resident'
            );
            
            if ($emailSent) {
                $message = 'Test email sent successfully using simple mail() function! Check the inbox and spam folder.';
            } else {
                $error = 'Failed to send test email using simple mail() function.';
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Email Test - Barangay 172 Urduja</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-poppins { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="font-poppins bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Simple Email Test
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Test using PHP mail() function (no SMTP required)
                </p>
            </div>
            
            <form class="mt-8 space-y-6" method="POST">
                <div>
                    <label for="test_email" class="block text-sm font-medium text-gray-700">
                        Test Email Address
                    </label>
                    <input id="test_email" name="test_email" type="email" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Enter email address to test">
                </div>

                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Send Test Email
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="mt-8 space-y-2">
                <a href="test_email.php" class="block text-indigo-600 hover:text-indigo-500">
                    ← Back to SMTP Email Test
                </a>
                <a href="users.php" class="block text-indigo-600 hover:text-indigo-500">
                    ← Back to User Management
                </a>
            </div>
        </div>
    </div>
</body>
</html>
