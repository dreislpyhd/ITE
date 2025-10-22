<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$error = '';

// Handle manual email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $fullName = $_POST['full_name'];
    $role = $_POST['role'];
    
    try {
        require_once '../includes/SimpleEmailService.php';
        $emailService = new SimpleEmailService();
        
        // Create and send email manually
        $subject = 'Your Barangay 172 Urduja Management System Account';
        $htmlBody = $emailService->createRegistrationEmailBody($username, $password, $fullName, $role);
        $textBody = $emailService->createPlainTextVersion($username, $password, $fullName, $role);
        
        // Use PHP's mail function
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: Barangay 172 Urduja <noreply@barangay172urduja.com>';
        $headers[] = 'Reply-To: noreply@barangay172urduja.com';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        $mailSent = mail($email, $subject, $htmlBody, implode("\r\n", $headers));
        
        if ($mailSent) {
            $message = "Email sent successfully to $email";
        } else {
            $error = "Failed to send email to $email";
        }
        
    } catch (Exception $e) {
        $error = "Error sending email: " . $e->getMessage();
    }
}

// Get pending emails from file
$pendingEmails = [];
if (file_exists('pending_emails.txt')) {
    $lines = file('pending_emails.txt', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (trim($line)) {
            $parts = explode(' | ', $line);
            if (count($parts) >= 6) {
                $pendingEmails[] = [
                    'email' => str_replace('Email: ', '', $parts[0]),
                    'username' => str_replace('Username: ', '', $parts[1]),
                    'password' => str_replace('Password: ', '', $parts[2]),
                    'name' => str_replace('Name: ', '', $parts[3]),
                    'role' => str_replace('Role: ', '', $parts[4]),
                    'date' => str_replace('Date: ', '', $parts[5])
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Management - Brgy. 172 Urduja</title>
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
                            <h1 class="text-2xl font-bold text-white font-garamond">Email Management</h1>
                            <p class="text-sm text-orange-100">Caloocan City</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-white hover:text-orange-200 font-medium transition duration-300">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto p-4 lg:p-8">
        <div class="mb-6 lg:mb-8">
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 font-garamond">Email Management</h1>
            <p class="text-sm lg:text-base text-gray-600">Manage pending email deliveries and send credentials manually</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Pending Emails -->
        <div class="bg-white rounded-xl shadow-md p-4 lg:p-6 mb-6 lg:mb-8">
            <h2 class="text-lg lg:text-xl font-bold text-gray-900 font-garamond mb-4">Pending Email Deliveries (<?php echo count($pendingEmails); ?>)</h2>
            
            <?php if (empty($pendingEmails)): ?>
                <p class="text-gray-500">No pending emails.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pendingEmails as $index => $emailData): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($emailData['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($emailData['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($emailData['username']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($emailData['role']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($emailData['date']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="showSendModal(<?php echo $index; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">Send Email</button>
                                        <button onclick="showCredentials(<?php echo $index; ?>)" class="text-green-600 hover:text-green-900">View Credentials</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Email Configuration Status -->
        <div class="bg-white rounded-xl shadow-md p-4 lg:p-6">
            <h2 class="text-lg lg:text-xl font-bold text-gray-900 font-garamond mb-4">Email Configuration Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-blue-800">SMTP Configuration</h3>
                    <p class="text-sm text-blue-600">Host: <?php echo defined('SMTP_HOST') ? SMTP_HOST : 'Not configured'; ?></p>
                    <p class="text-sm text-blue-600">Port: <?php echo defined('SMTP_PORT') ? SMTP_PORT : 'Not configured'; ?></p>
                    <p class="text-sm text-blue-600">Status: <?php echo defined('EMAIL_ENABLED') && EMAIL_ENABLED ? 'Enabled' : 'Disabled'; ?></p>
                </div>
                <div class="p-4 bg-green-50 rounded-lg">
                    <h3 class="font-semibold text-green-800">Fallback Service</h3>
                    <p class="text-sm text-green-600">Status: Active</p>
                    <p class="text-sm text-green-600">Credentials are logged for manual processing</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    <div id="sendModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white font-garamond mb-4">Send Email Manually</h3>
                    <form method="POST" id="sendEmailForm">
                        <input type="hidden" name="send_email" value="1">
                        <input type="hidden" name="email" id="modalEmail">
                        <input type="hidden" name="username" id="modalUsername">
                        <input type="hidden" name="password" id="modalPassword">
                        <input type="hidden" name="full_name" id="modalFullName">
                        <input type="hidden" name="role" id="modalRole">
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            This will attempt to send the registration email using PHP's mail() function.
                        </p>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="hideSendModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                Send Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Credentials Modal -->
    <div id="credentialsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white font-garamond mb-4">Login Credentials</h3>
                    <div class="space-y-2">
                        <p><strong>Email:</strong> <span id="credEmail"></span></p>
                        <p><strong>Name:</strong> <span id="credName"></span></p>
                        <p><strong>Username:</strong> <span id="credUsername"></span></p>
                        <p><strong>Password:</strong> <span id="credPassword"></span></p>
                        <p><strong>Role:</strong> <span id="credRole"></span></p>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button onclick="hideCredentialsModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const pendingEmails = <?php echo json_encode($pendingEmails); ?>;
        
        function showSendModal(index) {
            const emailData = pendingEmails[index];
            document.getElementById('modalEmail').value = emailData.email;
            document.getElementById('modalUsername').value = emailData.username;
            document.getElementById('modalPassword').value = emailData.password;
            document.getElementById('modalFullName').value = emailData.name;
            document.getElementById('modalRole').value = emailData.role;
            document.getElementById('sendModal').classList.remove('hidden');
        }
        
        function hideSendModal() {
            document.getElementById('sendModal').classList.add('hidden');
        }
        
        function showCredentials(index) {
            const emailData = pendingEmails[index];
            document.getElementById('credEmail').textContent = emailData.email;
            document.getElementById('credName').textContent = emailData.name;
            document.getElementById('credUsername').textContent = emailData.username;
            document.getElementById('credPassword').textContent = emailData.password;
            document.getElementById('credRole').textContent = emailData.role;
            document.getElementById('credentialsModal').classList.remove('hidden');
        }
        
        function hideCredentialsModal() {
            document.getElementById('credentialsModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.id === 'sendModal' || e.target.id === 'credentialsModal') {
                hideSendModal();
                hideCredentialsModal();
            }
        });
    </script>
</body>
</html>
