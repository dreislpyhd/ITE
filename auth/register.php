<?php
require_once '../includes/config.php';
session_start();
require_once '../includes/database.php';
require_once '../includes/EmailService.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = isset($_POST['no_middle_name']) ? '' : trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $house_no = trim($_POST['house_no']);
    $street = trim($_POST['street']);
    $role = 'resident'; // Default to resident
    $terms_accepted = isset($_POST['terms_accepted']);
    
    // Validation
    if (empty($last_name) || empty($first_name) || empty($email) || empty($house_no) || empty($street)) {
        $error = 'Please fill in all required fields.';
    } elseif (!$terms_accepted) {
        $error = 'You must accept the terms and conditions.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email address already exists. Please use a different email.';
        } else {
            // Generate username based on role
            $username = generateUsername($conn, $role);
            
            // Generate random password
            $password = generateRandomPassword();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Create full name
            $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
            
            // Create complete address
            $complete_address = $house_no . ' ' . $street . ', Zone 15, Brgy. 172, Caloocan City';
            
            // Insert user with new address fields
            try {
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, house_no, street, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $house_no, $street, $complete_address]);
            } catch (PDOException $e) {
                // If new columns don't exist, try with just address
                if (strpos($e->getMessage(), 'no column named house_no') !== false || strpos($e->getMessage(), 'no column named street') !== false) {
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, address) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $full_name, $email, $role, $complete_address]);
                } else {
                    throw $e; // Re-throw if it's a different error
                }
            }
            
            if ($stmt->rowCount() > 0) {
                // Send email with credentials using EmailService
                $emailSent = false;
                $emailError = '';
                
                try {
                    // Try the main EmailService first
                    $emailService = new EmailService();
                    $emailSent = $emailService->sendRegistrationCredentials($email, $username, $password, $full_name, $role);
                    
                    if (!$emailSent) {
                        $emailError = $emailService->getLastError();
                    }
                } catch (Exception $e) {
                    $emailError = $e->getMessage();
                    error_log("Main email service failed: " . $emailError);
                }
                
                // If main email service failed, try fallback service
                if (!$emailSent) {
                    try {
                        require_once '../includes/SimpleEmailService.php';
                        $fallbackService = new SimpleEmailService();
                        $emailSent = $fallbackService->sendRegistrationCredentials($email, $username, $password, $full_name, $role);
                        
                        if ($emailSent) {
                            $success = 'Registration successful! Your login credentials have been logged and will be processed. Please contact support if you need immediate access.';
                        } else {
                            $success = 'Registration successful! However, there was an issue with email delivery. Your credentials have been logged for manual processing.';
                        }
                    } catch (Exception $e) {
                        $success = 'Registration successful! However, there was an issue with email delivery. Your credentials have been logged for manual processing.';
                        error_log("Fallback email service error: " . $e->getMessage());
                    }
                } else {
                    $success = 'Registration successful! Your login credentials have been sent to your email address. Please check your email.';
                }
                
                // Log the credentials for backup
                error_log("REGISTRATION SUCCESS - Email: $email, Username: $username, Password: $password, Name: $full_name, Role: $role");
                
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

// Function to generate username based on role
function generateUsername($conn, $role) {
    $prefix = '';
    switch($role) {
        case 'resident':
            $prefix = 'RES';
            break;
        case 'barangay_hall':
            $prefix = 'BH';
            break;
        case 'health_center':
            $prefix = 'BCH';
            break;
        default:
            $prefix = 'USER';
    }
    
    // Get the next number for this role
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->execute([$role]);
    $result = $stmt->fetch();
    $nextNumber = $result['count'] + 1;
    
    // Format with leading zeros
    $formattedNumber = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    
    return $prefix . $formattedNumber;
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Barangay Management System</title>
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
     <div class="flex min-h-screen items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
         <div class="max-w-2xl w-full">
             <!-- Register Card -->
             <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-4">
                 <!-- Header -->
                 <div class="text-center mb-4">
                     <div class="flex items-center justify-center space-x-3 mb-4">
                         <img src="../assets/images/AM-logo.png" alt="AM Logo" class="h-12 w-12 rounded-full bg-white p-1 shadow-md object-cover">
                         <img src="../assets/images/b172logo.png" alt="Barangay 172 Logo" class="h-20 w-20 rounded-full shadow-md">
                         <img src="../assets/images/caloocanlogo.png" alt="Caloocan Logo" class="h-12 w-12 rounded-full bg-white p-1 shadow-md object-cover">
                     </div>
                     <h2 class="text-2xl font-bold text-gray-900 mb-2">
                         Create Account
                     </h2>
                     <p class="text-sm text-gray-600">
                         Join Barangay 172 Urduja Management System
                     </p>
                 </div>
                
                 <!-- Success Message -->
                 <?php if ($success): ?>
                     <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-3 py-2 rounded-xl flex items-center">
                         <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                         </svg>
                         <span class="text-sm"><?php echo htmlspecialchars($success); ?></span>
                     </div>
                 <?php endif; ?>
                 
                 <!-- Error Message -->
                 <?php if ($error): ?>
                     <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl flex items-center">
                         <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                         </svg>
                         <span class="text-sm"><?php echo htmlspecialchars($error); ?></span>
                     </div>
                 <?php endif; ?>
                
                 <!-- Register Form -->
                 <form class="space-y-3" method="POST">
                     <!-- Two Column Layout -->
                     <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                         <!-- Left Column -->
                         <div class="space-y-3">
                             <!-- Name Fields -->
                             <div class="space-y-2">
                                 <div>
                                     <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                                         Last Name <span class="text-red-500">*</span>
                                     </label>
                                     <input id="last_name" name="last_name" type="text" required 
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                            placeholder="Enter your last name">
                                 </div>

                                 <div>
                                     <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                                         First Name <span class="text-red-500">*</span>
                                     </label>
                                     <input id="first_name" name="first_name" type="text" required 
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                            placeholder="Enter your first name">
                                 </div>

                                 <div>
                                     <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">
                                         Middle Name
                                     </label>
                                     <div class="flex items-center space-x-2">
                                         <input id="middle_name" name="middle_name" type="text" 
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                                placeholder="Enter your middle name">
                                         <div class="flex items-center">
                                             <input id="no_middle_name" name="no_middle_name" type="checkbox" 
                                                    class="h-4 w-4 text-barangay-green focus:ring-barangay-green border-gray-300 rounded">
                                             <label for="no_middle_name" class="ml-2 text-xs text-gray-600 whitespace-nowrap">
                                                 No middle name
                                             </label>
                                         </div>
                                     </div>
                                 </div>
                             </div>

                             <div>
                                 <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                     Email Address <span class="text-red-500">*</span>
                                 </label>
                                 <input id="email" name="email" type="email" required 
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                        placeholder="Enter your email address">
                                 <p class="text-xs text-blue-500 mt-1">Your login credentials will be sent to this email</p>
                             </div>
                        </div>

                         <!-- Right Column -->
                         <div class="space-y-3">
                             <!-- Address Section -->
                             <div class="space-y-2">
                                 <div>
                                     <label for="house_no" class="block text-sm font-medium text-gray-700 mb-1">
                                         House No. <span class="text-red-500">*</span>
                                 </label>
                                     <input id="house_no" name="house_no" type="text" required 
                                           class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" 
                                            placeholder="Example: 123, 45A, 10-B">
                                 </div>

                                 <div>
                                     <label for="street" class="block text-sm font-medium text-gray-700 mb-1">
                                         Street <span class="text-red-500">*</span>
                                     </label>
                                     <select id="street" name="street" required 
                                             class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-barangay-green focus:border-transparent transition-all duration-200 text-gray-900 bg-white">
                                        <option value="">Select street</option>
                                        <option value="Aaron">Aaron</option>
                                        <option value="Abraham">Abraham</option>
                                        <option value="Adam">Adam</option>
                                        <option value="Almond St">Almond St</option>
                                        <option value="Athena">Athena</option>
                                        <option value="Babylonia">Babylonia</option>
                                        <option value="Bethel">Bethel</option>
                                        <option value="Camia">Camia</option>
                                        <option value="Camia St">Camia St</option>
                                        <option value="Carmel">Carmel</option>
                                        <option value="Carnation">Carnation</option>
                                        <option value="Cattleya Rd">Cattleya Rd</option>
                                        <option value="Colosse">Colosse</option>
                                        <option value="Cornelius">Cornelius</option>
                                        <option value="Dahlia">Dahlia</option>
                                        <option value="Daisy">Daisy</option>
                                        <option value="Datu Puti">Datu Puti</option>
                                        <option value="David">David</option>
                                        <option value="Diamond">Diamond</option>
                                        <option value="Earth">Earth</option>
                                        <option value="Elijah">Elijah</option>
                                        <option value="Emerald">Emerald</option>
                                        <option value="Ephesus">Ephesus</option>
                                        <option value="Ephraim">Ephraim</option>
                                        <option value="Everlasting">Everlasting</option>
                                        <option value="Galathia">Galathia</option>
                                        <option value="Garnet">Garnet</option>
                                        <option value="Germanium">Germanium</option>
                                        <option value="Gladiola">Gladiola</option>
                                        <option value="Humabon">Humabon</option>
                                        <option value="Ipil St">Ipil St</option>
                                        <option value="Isaac">Isaac</option>
                                        <option value="Jacob">Jacob</option>
                                        <option value="Jade St">Jade St</option>
                                        <option value="Jasmin">Jasmin</option>
                                        <option value="Jenemiah">Jenemiah</option>
                                        <option value="Joseph">Joseph</option>
                                        <option value="Joshua">Joshua</option>
                                        <option value="Jupiter">Jupiter</option>
                                        <option value="Kabiling">Kabiling</option>
                                        <option value="Kalantiaw">Kalantiaw</option>
                                        <option value="Kalayaan">Kalayaan</option>
                                        <option value="Kapayapaan">Kapayapaan</option>
                                        <option value="Kingfisher">Kingfisher</option>
                                        <option value="Kudarat">Kudarat</option>
                                        <option value="Kulambo">Kulambo</option>
                                        <option value="Kumintang">Kumintang</option>
                                        <option value="Lakandula">Lakandula</option>
                                        <option value="Lapu-Lapu">Lapu-Lapu</option>
                                        <option value="Lilac">Lilac</option>
                                        <option value="Lotus">Lotus</option>
                                        <option value="Magnolia">Magnolia</option>
                                        <option value="Maragtas">Maragtas</option>
                                        <option value="Maricudo">Maricudo</option>
                                        <option value="Mark">Mark</option>
                                        <option value="Mars">Mars</option>
                                        <option value="Matthew">Matthew</option>
                                        <option value="Mercury">Mercury</option>
                                        <option value="Minda Mora">Minda Mora</option>
                                        <option value="Moses">Moses</option>
                                        <option value="Narra">Narra</option>
                                        <option value="Nightingale">Nightingale</option>
                                        <option value="Noah">Noah</option>
                                        <option value="Panday Pira">Panday Pira</option>
                                        <option value="Paul">Paul</option>
                                        <option value="Pearl">Pearl</option>
                                        <option value="Peter">Peter</option>
                                        <option value="Philip">Philip</option>
                                        <option value="Pine Street">Pine Street</option>
                                        <option value="Quintos Villa">Quintos Villa</option>
                                        <option value="Rosal">Rosal</option>
                                        <option value="Rosas">Rosas</option>
                                        <option value="Rose">Rose</option>
                                        <option value="Ruby">Ruby</option>
                                        <option value="Sampaguita">Sampaguita</option>
                                        <option value="Samson">Samson</option>
                                        <option value="Samuel">Samuel</option>
                                        <option value="Sapphire">Sapphire</option>
                                        <option value="Saturn">Saturn</option>
                                        <option value="Siagu">Siagu</option>
                                        <option value="Sikatuna Ave">Sikatuna Ave</option>
                                        <option value="Silver">Silver</option>
                                        <option value="Simeon">Simeon</option>
                                        <option value="Sinai">Sinai</option>
                                        <option value="Soliman">Soliman</option>
                                        <option value="Star">Star</option>
                                        <option value="Sumakwel">Sumakwel</option>
                                        <option value="Sun">Sun</option>
                                        <option value="Tarhata">Tarhata</option>
                                        <option value="Topaz">Topaz</option>
                                        <option value="Venus">Venus</option>
                                        <option value="Yakal">Yakal</option>
                                        <option value="Zabarte Rd">Zabarte Rd</option>
                                        <option value="Zenia">Zenia</option>
                                    </select>
                                </div>

                                 <!-- Zone Information (Read-only) -->
                                 <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                     <label class="block text-sm font-medium text-gray-700 mb-1">
                                         Zone Information
                                     </label>
                                     <div class="text-xs text-gray-600 space-y-1">
                                         <div><span class="font-medium">Zone No:</span> Zone 15</div>
                                         <div><span class="font-medium">Barangay:</span> Brgy. 172</div>
                                         <div><span class="font-medium">City:</span> Caloocan City</div>
                                     </div>
                                     <p class="text-xs text-blue-500 mt-1">This information is automatically set for all residents</p>
                                 </div>
                             </div>

                        </div>
                    </div>

                     <!-- Full Width Section -->
                     <div class="pt-2">
                         <!-- Terms and Conditions -->
                         <div class="flex items-center mb-4">
                             <input id="terms_accepted" name="terms_accepted" type="checkbox" required 
                                    class="h-4 w-4 text-barangay-green focus:ring-barangay-green border-gray-300 rounded">
                             <label for="terms_accepted" class="ml-2 text-sm text-gray-700">
                                 I agree to the 
                                 <button type="button" id="termsBtn" class="text-barangay-green font-medium underline hover:text-green-700 transition-colors">
                                     Terms and Conditions
                                 </button> 
                                 <span class="text-red-500">*</span>
                             </label>
                         </div>

                         <div>
                             <button type="submit" 
                                     class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-semibold rounded-lg text-white bg-barangay-orange hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-barangay-orange transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                                 <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                     <svg class="h-4 w-4 text-white group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                     </svg>
                                 </span>
                                 Register
                             </button>
                         </div>

                         <!-- Links -->
                         <div class="text-center mt-4">
                             <a href="login.php" class="text-sm text-barangay-green hover:text-green-700 font-medium transition-colors">
                                 Already have an account? 
                                 <span class="underline">Sign in here</span>
                             </a>
                         </div>
                     </div>
                </form>
            </div>

             <!-- Footer Note -->
             <div class="mt-6 text-center">
                 <p class="text-xs text-gray-500">
                     Secure registration for Barangay 172 Urduja services
                 </p>
             </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-2xl bg-white">
            <!-- Modal Header -->
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900">Terms and Conditions and Privacy Policy</h3>
                <button id="closeTermsModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="text-sm text-gray-700 space-y-3 max-h-96 overflow-y-auto pr-2">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3">Terms and Conditions of Barangay 172 Urduja Management System</h4>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="font-medium text-gray-800">1. Accuracy and Truthfulness</p>
                            <p class="text-gray-600 ml-4">All information provided to the system must be true, accurate, and up-to-date. Providing false information may result in account suspension or cancellation.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">2. Privacy and Security</p>
                            <p class="text-gray-600 ml-4">Your personal information is protected in accordance with Republic Act No. 10173 (Data Privacy Act of 2012) and will not be shared with unauthorized persons. The system uses advanced security measures and encryption protocols to protect your data as required by law.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">3. Account Responsibility</p>
                            <p class="text-gray-600 ml-4">You are responsible for the use of your account and password. Do not share your login credentials with others. Report immediately if there is any suspicious activity.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">4. Compliance with Laws and Regulations</p>
                            <p class="text-gray-600 ml-4">Must comply with all applicable laws including but not limited to Republic Act No. 10173 (Data Privacy Act of 2012), Republic Act No. 8792 (Electronic Commerce Act of 2000), Republic Act No. 10175 (Cybercrime Prevention Act of 2012), and all local ordinances of Caloocan City and Barangay 172. Violations may result in legal action and penalties as prescribed by law.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">5. Information Updates</p>
                            <p class="text-gray-600 ml-4">Information must be updated immediately if there are changes in personal details, address, contact number, or other important information.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">6. System Access and Use</p>
                            <p class="text-gray-600 ml-4">The barangay has the right to suspend, restrict, or cancel accounts when necessary for security or if there are violations of the terms.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">7. Consent and Agreement</p>
                            <p class="text-gray-600 ml-4">By clicking the "Register" button, you agree that you have read and understood all these terms and conditions.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">8. Changes to Terms</p>
                            <p class="text-gray-600 ml-4">The barangay has the right to change these terms and conditions at any time. Changes will be communicated to users through the system.</p>
                        </div>
                    </div>
                </div>

                <!-- Privacy Policy Section -->
                <div class="bg-blue-50 p-4 rounded-lg mt-4">
                    <h4 class="font-semibold text-gray-800 mb-3">Privacy Policy</h4>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="font-medium text-gray-800">1. Information Collection</p>
                            <p class="text-gray-600 ml-4">We collect your personal information such as name, address, email, and contact number for barangay services in compliance with Republic Act No. 10173 (Data Privacy Act of 2012). This information is used only for official purposes and legitimate government functions.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">2. Information Usage</p>
                            <p class="text-gray-600 ml-4">Your information is used for processing applications, providing services, and communication about barangay programs. We do not share your information with third parties unless required by law.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">3. Data Protection</p>
                            <p class="text-gray-600 ml-4">We use advanced security measures in compliance with Republic Act No. 10173 (Data Privacy Act of 2012) to protect your personal information. All data is encrypted and stored on secure servers as mandated by law.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">4. User Rights</p>
                            <p class="text-gray-600 ml-4">You have the right to access, update, or delete your personal information as provided under Republic Act No. 10173 (Data Privacy Act of 2012). You may also request a copy of your data at any time in accordance with the law.</p>
                        </div>
                        
                        <div>
                            <p class="font-medium text-gray-800">5. Cookies and Tracking</p>
                            <p class="text-gray-600 ml-4">We use cookies to improve user experience. You can disable cookies in your browser, but this may affect website functionality.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end mt-6">
                <button id="acceptTermsBtn" class="bg-barangay-green hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    I Understand and Agree
                </button>
            </div>
        </div>
    </div>

    <script>
        // Show terms modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            const termsModal = document.getElementById('termsModal');
            termsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });

        // Handle middle name checkbox
        document.getElementById('no_middle_name').addEventListener('change', function() {
            const middleNameField = document.getElementById('middle_name');
            if (this.checked) {
                middleNameField.value = '';
                middleNameField.disabled = true;
                middleNameField.classList.add('bg-gray-100');
            } else {
                middleNameField.disabled = false;
                middleNameField.classList.remove('bg-gray-100');
            }
        });



        // Terms and Conditions Modal
        const termsModal = document.getElementById('termsModal');
        const termsBtn = document.getElementById('termsBtn');
        const closeTermsModal = document.getElementById('closeTermsModal');
        const acceptTermsBtn = document.getElementById('acceptTermsBtn');
        const termsCheckbox = document.getElementById('terms_accepted');

        // Open modal
        termsBtn.addEventListener('click', function() {
            termsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });

        // Close modal
        function closeModal() {
            termsModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        closeTermsModal.addEventListener('click', closeModal);
        acceptTermsBtn.addEventListener('click', function() {
            termsCheckbox.checked = true;
            closeModal();
        });

        // Close modal when clicking outside
        termsModal.addEventListener('click', function(e) {
            if (e.target === termsModal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !termsModal.classList.contains('hidden')) {
                closeModal();
            }
        });
    </script>
</body>
</html>
