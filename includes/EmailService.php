<?php
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            
            // Default settings
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
            // Debug settings (remove in production)
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            
        } catch (Exception $e) {
            error_log("Email setup failed: " . $e->getMessage());
        }
    }
    
    public function sendRegistrationCredentials($email, $username, $password, $fullName, $role) {
        if (!EMAIL_ENABLED) {
            error_log("Email sending disabled - EMAIL_ENABLED is false");
            return false;
        }
        
        try {
            error_log("EmailService: Starting email send process for $email");
            error_log("EmailService: SMTP Host: " . SMTP_HOST);
            error_log("EmailService: SMTP Username: " . SMTP_USERNAME);
            error_log("EmailService: SMTP Port: " . SMTP_PORT);
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $fullName);
            $this->mailer->Subject = EMAIL_SUBJECT_REGISTRATION;
            
            // Create HTML email body
            $body = $this->createRegistrationEmailBody($username, $password, $fullName, $role);
            $this->mailer->Body = $body;
            
            // Create plain text version
            $this->mailer->AltBody = $this->createPlainTextVersion($username, $password, $fullName, $role);
            
            error_log("EmailService: Attempting to send email to $email");
            $result = $this->mailer->send();
            error_log("EmailService: Send result: " . ($result ? 'true' : 'false'));
            return $result;
            
        } catch (Exception $e) {
            error_log("EmailService: Exception during send - " . $e->getMessage());
            error_log("EmailService: PHPMailer ErrorInfo: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    private function createRegistrationEmailBody($username, $password, $fullName, $role) {
        $roleDisplay = $this->getRoleDisplayName($role);
        
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
                    
                    <a href='" . LOGIN_URL . "' class='btn'>🚀 Login to System</a>
                    
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
        
        return "Welcome to Barangay 172 Urduja!

Dear $fullName,

Thank you for registering with the Barangay 172 Urduja Management System. Your account has been successfully created!

Account Role: $roleDisplay

Your Login Credentials:
Username: $username
Password: $password

Important: Please keep your password secure and do not share it with anyone. You can change your password after logging in.

You can now access the system at: " . LOGIN_URL . "

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
    
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getLastError() {
        return $this->mailer->ErrorInfo;
    }

    public function sendReadyForPickup($email, $fullName, $referenceNo, $serviceName) {
        if (!EMAIL_ENABLED) {
            return false;
        }
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email, $fullName);
            $this->mailer->Subject = 'Your document is Ready for Pick-up';

            $body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Ready for Pick-up</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                    .header { background: #2E8B57; color: white; padding: 24px; text-align: center; }
                    .content { padding: 24px; background: #f9f9f9; }
                    .badge { display: inline-block; background: #10b981; color: white; padding: 6px 12px; border-radius: 9999px; font-weight: bold; font-size: 12px; }
                    .card { background: white; padding: 16px; border-radius: 8px; border-left: 4px solid #2E8B57; }
                    .btn { display: inline-block; background: #ff8829; color: white; padding: 10px 16px; text-decoration: none; border-radius: 6px; margin-top: 16px; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Barangay 172 Urduja</h2>
                        <div class='badge'>Ready for Pick-up</div>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>{$fullName}</strong>,</p>
                        <p>Your requested document is now <strong>Ready for Pick-up</strong>.</p>
                        <div class='card'>
                            <p><strong>Reference No.:</strong> {$referenceNo}</p>
                            <p><strong>Service:</strong> {$serviceName}</p>
                        </div>
                        <p>You may visit the Barangay Hall to claim your document. Please bring a valid ID.</p>
                        <a href='" . APP_URL . "/residents/applications.php' class='btn'>View Application</a>
                        <p style='margin-top: 20px;'>Thank you.</p>
                    </div>
                </div>
            </body>
            </html>";

            $this->mailer->Body = $body;
            $this->mailer->AltBody = "Your document is ready for pick-up. Reference: {$referenceNo}. Service: {$serviceName}.";
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
