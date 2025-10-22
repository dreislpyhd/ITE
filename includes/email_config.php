<?php
// Email Configuration for PHPMailer with Gmail SMTP
// IMPORTANT: You need to set up an App Password in your Gmail account

// Check if we're in production (Render.com) or local development
$isProduction = isset($_ENV['RENDER']) || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false);

if ($isProduction) {
    // Production configuration using environment variables
    define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
    define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
    define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');
    define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'caloocancitybrgy.172@gmail.com');
    define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? 'cqqb wxtp wlkc dltc');
    define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'caloocancitybrgy.172@gmail.com');
    define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Barangay 172 Urduja Management System');
    
    // Production URLs
    define('SYSTEM_URL', $_ENV['SYSTEM_URL'] ?? 'https://barangay172.onrender.com');
    define('LOGIN_URL', SYSTEM_URL . '/auth/login.php');
    
    // Email sending status - force enable in production
    define('EMAIL_ENABLED', true);
    
    // Log configuration for debugging
    error_log("Email Config - Production Mode");
    error_log("SMTP_HOST: " . SMTP_HOST);
    error_log("SMTP_PORT: " . SMTP_PORT);
    error_log("SMTP_USERNAME: " . SMTP_USERNAME);
    error_log("EMAIL_ENABLED: " . (EMAIL_ENABLED ? 'true' : 'false'));
} else {
    // Local development configuration
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_SECURE', 'tls');
    define('SMTP_USERNAME', 'caloocancitybrgy.172@gmail.com');
    define('SMTP_PASSWORD', 'cqqb wxtp wlkc dltc');
    define('SMTP_FROM_EMAIL', 'noreply@barangay172urduja.com');
    define('SMTP_FROM_NAME', 'Barangay 172 Urduja Management System');
    
    // Local URLs
    define('SYSTEM_URL', 'http://localhost:8000');
    define('LOGIN_URL', SYSTEM_URL . '/auth/login.php');
    
    // Email sending status
    define('EMAIL_ENABLED', true);
}

// Email Templates
define('EMAIL_SUBJECT_REGISTRATION', 'Your Barangay 172 Urduja Management System Account');
define('EMAIL_SUBJECT_RESET_PASSWORD', 'Password Reset Request - Barangay 172 Urduja');

/*
 * 🔑 SETUP INSTRUCTIONS:
 * 
 * 1. ENABLE 2-FACTOR AUTHENTICATION on your Gmail account:
 *    - Go to myaccount.google.com → Security → 2-Step Verification
 *    - Turn it ON and follow the setup process
 * 
 * 2. GENERATE APP PASSWORD:
 *    - Go to Security → 2-Step Verification → App passwords
 *    - Select "Mail" from dropdown
 *    - Click "Generate"
 *    - Copy the 16-character password (e.g., "abcd efgh ijkl mnop")
 * 
 * 3. UPDATE THIS FILE:
 *    - Replace 'your-email@gmail.com' with your actual Gmail address
 *    - Replace 'your-16-char-app-password' with the App Password from step 2
 * 
 * 4. TEST:
 *    - Try registering a new user
 *    - Check if email is received
 *    - Check spam folder if no email arrives
 * 
 * ⚠️ SECURITY NOTES:
 * - NEVER use your regular Gmail password
 * - ONLY use App Passwords for applications
 * - Keep your App Password secure
 * - You can revoke App Passwords anytime from Google Account settings
 */
?>
