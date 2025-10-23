<?php
// Simplified Email Service without Composer
// This uses PHP's built-in mail() function with better formatting

class SimpleEmailService {
    
    public function sendRegistrationCredentials($email, $username, $password, $fullName, $role) {
        $subject = 'Your Barangay 172 Urduja Management System Account';
        
        // Create HTML email body
        $htmlBody = $this->createRegistrationEmailBody($username, $password, $fullName, $role);
        
        // Create plain text version
        $textBody = $this->createPlainTextVersion($username, $password, $fullName, $role);
        
        // Email headers
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: Barangay 172 Urduja <noreply@barangay172urduja.com>';
        $headers[] = 'Reply-To: noreply@barangay172urduja.com';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        // Try to send email
        $mailSent = mail($email, $subject, $htmlBody, implode("\r\n", $headers));
        
        if ($mailSent) {
            // Log successful email
            error_log("Email sent successfully to: $email");
            return true;
        } else {
            // Log failed email
            error_log("Failed to send email to: $email");
            return false;
        }
    }
    
    private function createRegistrationEmailBody($username, $password, $fullName, $role) {
        $roleDisplay = $this->getRoleDisplayName($role);
        $loginUrl = 'http://localhost:8000/auth/login.php';
        
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Account Registration</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: linear-gradient(135deg, #ff8829, #2E8B57); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.9; }
                .content { padding: 30px; background: #f9f9f9; }
                .credentials { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #ff8829; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .username, .password { font-family: 'Courier New', monospace; background: #f0f0f0; padding: 8px 12px; border-radius: 4px; margin: 5px 0; display: inline-block; font-weight: bold; }
                .btn { display: inline-block; background: #2E8B57; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; }
                .btn:hover { background: #1e6b47; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; background: #f0f0f0; color: #666; font-size: 14px; }
                .role-badge { background: #ff8829; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Barangay 172 Urduja</h1>
                    <p>Management System</p>
                </div>
                
                <div class='content'>
                    <h2>Welcome to Barangay 172 Urduja!</h2>
                    
                    <p>Dear <strong>$fullName</strong>,</p>
                    
                    <p>Thank you for registering with the Barangay 172 Urduja Management System. Your account has been successfully created!</p>
                    
                    <p><strong>Account Role:</strong> <span class='role-badge'>$roleDisplay</span></p>
                    
                    <div class='credentials'>
                        <h3>🔐 Your Login Credentials:</h3>
                        <p><strong>Username:</strong> <span class='username'>$username</span></p>
                        <p><strong>Password:</strong> <span class='password'>$password</span></p>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> Please keep your password secure and do not share it with anyone. 
                        You can change your password after logging in.
                    </div>
                    
                    <p>You can now access the system using the button below:</p>
                    
                    <a href='$loginUrl' class='btn'>🚀 Login to System</a>
                    
                    <p>If you have any questions or need assistance, please contact the barangay office.</p>
                    
                    <p>Best regards,<br>
                    <strong>Barangay 172 Urduja Management System</strong></p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Barangay 172 Urduja. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function createPlainTextVersion($username, $password, $fullName, $role) {
        $roleDisplay = $this->getRoleDisplayName($role);
        $loginUrl = 'http://localhost:8000/auth/login.php';
        
        return "Welcome to Barangay 172 Urduja!

Dear $fullName,

Thank you for registering with the Barangay 172 Urduja Management System. Your account has been successfully created!

Account Role: $roleDisplay

Your Login Credentials:
Username: $username
Password: $password

Important: Please keep your password secure and do not share it with anyone. You can change your password after logging in.

You can now access the system at: $loginUrl

If you have any questions or need assistance, please contact the barangay office.

Best regards,
Barangay 172 Urduja Management System

This is an automated message. Please do not reply to this email.";
    }
    
    private function getRoleDisplayName($role) {
        switch($role) {
            case 'resident':
                return 'Resident';
            case 'barangay_hall':
                return 'Barangay Hall Staff';
            case 'health_center':
                return 'Health Center Staff';
            default:
                return 'User';
        }
    }
    
    // Test function to check if mail function is available
    public function isMailAvailable() {
        return function_exists('mail');
    }
    
    // Get mail configuration info
    public function getMailInfo() {
        $info = array();
        $info['mail_function'] = function_exists('mail') ? 'Available' : 'Not Available';
        $info['sendmail_path'] = ini_get('sendmail_path');
        $info['smtp_host'] = ini_get('SMTP');
        $info['smtp_port'] = ini_get('smtp_port');
        return $info;
    }
}
?>
