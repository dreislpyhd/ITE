<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/email_config.php';
require_once '../includes/EmailService.php';

$message = '';
$error = '';
$debug_info = [];

// Get configuration info
$debug_info['isProduction'] = isset($_ENV['RENDER']) || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false);
$debug_info['smtp_host'] = SMTP_HOST;
$debug_info['smtp_port'] = SMTP_PORT;
$debug_info['smtp_username'] = SMTP_USERNAME;
$debug_info['email_enabled'] = EMAIL_ENABLED;
$debug_info['from_email'] = SMTP_FROM_EMAIL;

if ($_POST) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        $error = 'Please enter an email address.';
    } else {
        try {
            // Enable SMTP debugging
            $emailService = new EmailService();
            
            // Test connection first
            $connectionTest = $emailService->testConnection();
            $debug_info['connection_test'] = $connectionTest ? 'PASSED' : 'FAILED';
            
            if ($connectionTest) {
                // Send test email
                $emailSent = $emailService->sendRegistrationCredentials(
                    $test_email, 
                    'testuser', 
                    'testpass123', 
                    'Test User', 
                    'resident'
                );
                
                $debug_info['email_sent'] = $emailSent ? 'SUCCESS' : 'FAILED';
                $debug_info['last_error'] = $emailService->getLastError();
                
                if ($emailSent) {
                    $message = 'Test email sent successfully! Check the inbox and spam folder.';
                } else {
                    $error = 'Failed to send test email. Error: ' . $emailService->getLastError();
                }
            } else {
                $error = 'SMTP connection failed. Check your email configuration.';
                $debug_info['last_error'] = $emailService->getLastError();
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            $debug_info['exception'] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - Barangay 172 Urduja</title>
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
                    Email Configuration Test
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Test email sending functionality
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
                
                <!-- Debug Information -->
                <div class="mt-6 bg-gray-100 border border-gray-300 rounded p-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Debug Information</h3>
                    <div class="space-y-2 text-sm">
                        <div><strong>Environment:</strong> <?php echo $debug_info['isProduction'] ? 'Production' : 'Development'; ?></div>
                        <div><strong>SMTP Host:</strong> <?php echo htmlspecialchars($debug_info['smtp_host']); ?></div>
                        <div><strong>SMTP Port:</strong> <?php echo htmlspecialchars($debug_info['smtp_port']); ?></div>
                        <div><strong>SMTP Username:</strong> <?php echo htmlspecialchars($debug_info['smtp_username']); ?></div>
                        <div><strong>From Email:</strong> <?php echo htmlspecialchars($debug_info['from_email']); ?></div>
                        <div><strong>Email Enabled:</strong> <?php echo $debug_info['email_enabled'] ? 'Yes' : 'No'; ?></div>
                        <?php if (isset($debug_info['connection_test'])): ?>
                            <div><strong>Connection Test:</strong> <span class="<?php echo $debug_info['connection_test'] === 'PASSED' ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $debug_info['connection_test']; ?></span></div>
                        <?php endif; ?>
                        <?php if (isset($debug_info['email_sent'])): ?>
                            <div><strong>Email Sent:</strong> <span class="<?php echo $debug_info['email_sent'] === 'SUCCESS' ? 'text-green-600' : 'text-red-600'; ?>"><?php echo $debug_info['email_sent']; ?></span></div>
                        <?php endif; ?>
                        <?php if (isset($debug_info['last_error']) && !empty($debug_info['last_error'])): ?>
                            <div><strong>Last Error:</strong> <span class="text-red-600"><?php echo htmlspecialchars($debug_info['last_error']); ?></span></div>
                        <?php endif; ?>
                        <?php if (isset($debug_info['exception'])): ?>
                            <div><strong>Exception:</strong> <span class="text-red-600"><?php echo htmlspecialchars($debug_info['exception']); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <div class="mt-8">
                <a href="users.php" class="text-indigo-600 hover:text-indigo-500">
                    ← Back to User Management
                </a>
            </div>
        </div>
    </div>
</body>
</html>
