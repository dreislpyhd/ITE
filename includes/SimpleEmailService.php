<?php
/**
 * Simple Email Service using cURL and external email API
 * This is a backup solution for when SMTP doesn't work on hosting platforms
 */

class SimpleEmailService {
    private $apiKey;
    private $apiUrl;
    
    public function __construct() {
        // Using EmailJS or similar service as backup
        // You can also use SendGrid, Mailgun, or other email services
        $this->apiKey = 'your-api-key-here'; // Replace with actual API key
        $this->apiUrl = 'https://api.emailjs.com/api/v1.0/email/send';
    }
    
    public function sendRegistrationCredentials($email, $username, $password, $fullName, $role) {
        // For now, let's use a simple mail() function as backup
        return $this->sendViaMailFunction($email, $username, $password, $fullName, $role);
    }
    
    private function sendViaMailFunction($email, $username, $password, $fullName, $role) {
        $roleDisplay = $this->getRoleDisplayName($role);
        
        $subject = "Your Barangay 172 Urduja Management System Account";
        
        $message = "Welcome to Barangay 172 Urduja!\n\n";
        $message .= "Dear $fullName,\n\n";
        $message .= "Thank you for registering with the Barangay 172 Urduja Management System. Your account has been successfully created!\n\n";
        $message .= "Account Role: $roleDisplay\n\n";
        $message .= "Your Login Credentials:\n";
        $message .= "Username: $username\n";
        $message .= "Password: $password\n\n";
        $message .= "Important: Please keep your password secure and do not share it with anyone. You can change your password after logging in.\n\n";
        $message .= "You can now access the system at: https://barangay172.onrender.com/auth/login.php\n\n";
        $message .= "If you have any questions or need assistance, please contact the barangay office.\n\n";
        $message .= "Best regards,\n";
        $message .= "Barangay 172 Urduja Management System\n\n";
        $message .= "This is an automated message. Please do not reply to this email.";
        
        $headers = "From: Barangay 172 Urduja <noreply@barangay172urduja.com>\r\n";
        $headers .= "Reply-To: noreply@barangay172urduja.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $result = mail($email, $subject, $message, $headers);
        
        if ($result) {
            error_log("SimpleEmailService: Email sent successfully via mail() function to $email");
        } else {
            error_log("SimpleEmailService: Failed to send email via mail() function to $email");
        }
        
        return $result;
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
}
?>