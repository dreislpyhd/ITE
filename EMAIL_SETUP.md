# Email Setup Guide for Barangay Management System

This guide will help you set up email functionality using PHPMailer with Gmail SMTP.

## 🚀 Quick Setup Steps

### 1. Install Dependencies
```bash
composer install
```

### 2. Configure Gmail Account
1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account settings
   - Security > 2-Step Verification > App passwords
   - Select "Mail" and generate password
   - Copy the 16-character password

### 3. Update Email Configuration
Edit `includes/email_config.php`:
```php
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your Gmail address
define('SMTP_PASSWORD', 'your-16-char-app-password'); // App password from step 2
define('SYSTEM_URL', 'http://your-domain.com'); // Your actual domain
```

### 4. Test Email Functionality
The system will automatically test email sending during registration.

## 📧 Email Features

### ✅ What's Included:
- **Professional HTML emails** with your branding
- **Automatic credential delivery** after registration
- **Gmail SMTP integration** for reliable delivery
- **Error handling** and logging
- **Responsive email design**

### 🎨 Email Design:
- **Barangay 172 Urduja branding** (orange and green theme)
- **Clean, professional layout**
- **Mobile-responsive design**
- **Both HTML and plain text versions**

## 🔧 Configuration Options

### SMTP Settings (Gmail):
- **Host**: smtp.gmail.com
- **Port**: 587
- **Security**: TLS
- **Authentication**: Required

### Email Templates:
- **Registration confirmation**
- **Login credentials**
- **Password reset** (ready for future use)

## 🚨 Troubleshooting

### Common Issues:

#### 1. "Authentication Failed"
- Check your Gmail username and app password
- Ensure 2FA is enabled
- Verify app password is correct

#### 2. "Connection Failed"
- Check internet connection
- Verify SMTP settings
- Check firewall settings

#### 3. "Email Not Received"
- Check spam/junk folder
- Verify email address is correct
- Check Gmail sending limits

### Debug Mode:
Enable debug logging in `includes/EmailService.php`:
```php
$this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
```

## 📱 Email Preview

Your emails will look like this:

```
┌─────────────────────────────────────┐
│  Barangay 172 Urduja              │
│  Management System                 │
├─────────────────────────────────────┤
│  Welcome to Barangay 172 Urduja!   │
│                                    │
│  Dear [User Name],                 │
│                                    │
│  Your Login Credentials:           │
│  Username: [RES00001]              │
│  Password: [Generated Password]    │
│                                    │
│  [Login Button]                    │
└─────────────────────────────────────┘
```

## 🔒 Security Notes

- **App passwords** are more secure than regular passwords
- **Never commit** real credentials to version control
- **Use environment variables** in production
- **Regular password rotation** recommended

## 🚀 Production Deployment

### Environment Variables:
```bash
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SYSTEM_URL=https://your-domain.com
```

### SSL/TLS:
- **Enable HTTPS** on your domain
- **Use TLS encryption** (already configured)
- **Verify SSL certificates**

## 📞 Support

If you encounter issues:
1. Check error logs in your PHP error log
2. Verify Gmail settings
3. Test SMTP connection
4. Contact system administrator

---

**Note**: This email system is designed for Barangay 172 Urduja and can be customized for other organizations.
