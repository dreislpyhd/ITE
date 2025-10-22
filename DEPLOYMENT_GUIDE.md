# Barangay 172 Urduja Management System - Render Deployment Guide

This guide will help you deploy the Barangay Management System to Render.com.

## Prerequisites

1. **GitHub Account**: Your code should be in a GitHub repository
2. **Render Account**: Sign up at [render.com](https://render.com)
3. **Email Service**: Set up email credentials (Gmail, SendGrid, etc.)

## Step 1: Prepare Your Repository

1. **Push your code to GitHub**:
   ```bash
   git add .
   git commit -m "Prepare for Render deployment"
   git push origin main
   ```

2. **Ensure all files are included**:
   - `render.yaml` (deployment configuration)
   - `composer.json` (PHP dependencies)
   - `public/` directory with entry point
   - All your application files

## Step 2: Deploy to Render

### Option A: Using render.yaml (Recommended)

1. **Connect GitHub to Render**:
   - Go to [render.com](https://render.com)
   - Sign in with your GitHub account
   - Click "New +" → "Blueprint"

2. **Import from GitHub**:
   - Select your repository
   - Render will automatically detect the `render.yaml` file
   - Click "Apply" to deploy

### Option B: Manual Setup

1. **Create Web Service**:
   - Go to Render Dashboard
   - Click "New +" → "Web Service"
   - Connect your GitHub repository

2. **Configure Service**:
   - **Name**: `barangay-management-system`
   - **Environment**: `PHP`
   - **Build Command**: `composer install --no-dev --optimize-autoloader`
   - **Start Command**: `php -S 0.0.0.0:$PORT -t public`

3. **Add Environment Variables**:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app-name.onrender.com
   ```

4. **Create Database**:
   - Click "New +" → "PostgreSQL"
   - **Name**: `barangay-db`
   - **Plan**: Free (for testing)
   - **Database Name**: `barangay_management`
   - **User**: `barangay_user`

5. **Link Database to Service**:
   - In your web service settings
   - Go to "Environment" tab
   - Add database environment variables:
     ```
     DB_HOST=<from database>
     DB_PORT=<from database>
     DB_DATABASE=<from database>
     DB_USERNAME=<from database>
     DB_PASSWORD=<from database>
     ```

## Step 3: Configure Email Service

1. **Get Email Credentials**:
   - For Gmail: Enable 2FA and create an App Password
   - For SendGrid: Create API key
   - For other providers: Get SMTP credentials

2. **Add Email Environment Variables**:
   ```
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@barangay172.com
   MAIL_FROM_NAME=Barangay 172 Urduja
   ```

## Step 4: Deploy and Test

1. **Deploy**:
   - Click "Deploy" or push to your main branch
   - Wait for deployment to complete (5-10 minutes)

2. **Test the Application**:
   - Visit your Render URL
   - Test registration and login
   - Verify database tables are created
   - Test email functionality

## Step 5: Post-Deployment Setup

1. **Access Admin Panel**:
   - Go to `/auth/login.php`
   - Login with default credentials:
     - **Username**: `admin`
     - **Password**: `admin123`

2. **Change Default Passwords**:
   - Update admin password
   - Update staff passwords
   - Configure email settings

3. **Upload Files**:
   - Ensure `uploads/` directory has proper permissions
   - Test file upload functionality

## Environment Variables Reference

### Required Variables
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-name.onrender.com
DB_HOST=<from database>
DB_PORT=<from database>
DB_DATABASE=<from database>
DB_USERNAME=<from database>
DB_PASSWORD=<from database>
```

### Optional Variables
```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@barangay172.com
MAIL_FROM_NAME=Barangay 172 Urduja
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**:
   - Check environment variables
   - Ensure database is created and linked
   - Verify PostgreSQL extension is enabled

2. **File Upload Issues**:
   - Check file permissions
   - Verify upload directory exists
   - Check file size limits

3. **Email Not Working**:
   - Verify email credentials
   - Check SMTP settings
   - Test with different email providers

4. **Page Not Loading**:
   - Check build logs
   - Verify PHP version compatibility
   - Check for syntax errors

### Debug Mode

To enable debug mode temporarily:
1. Set `APP_DEBUG=true` in environment variables
2. Redeploy the service
3. Check logs for detailed error messages
4. **Remember to set back to `false` for production**

## Security Considerations

1. **Change Default Passwords**:
   - Admin: `admin123`
   - Health Staff: `health123`
   - Barangay Staff: `barangay123`

2. **Environment Variables**:
   - Never commit sensitive data to Git
   - Use Render's environment variable system
   - Rotate passwords regularly

3. **HTTPS**:
   - Render provides HTTPS by default
   - Update `APP_URL` to use HTTPS

4. **Database Security**:
   - Use strong database passwords
   - Limit database access
   - Regular backups

## Monitoring and Maintenance

1. **Logs**:
   - Monitor application logs in Render dashboard
   - Set up log alerts for errors

2. **Performance**:
   - Monitor response times
   - Optimize database queries
   - Use caching where appropriate

3. **Backups**:
   - Set up regular database backups
   - Export data periodically
   - Test restore procedures

## Support

For deployment issues:
1. Check Render documentation
2. Review application logs
3. Test locally with same configuration
4. Contact Render support if needed

---

**Note**: This deployment guide assumes you're using the free tier of Render. For production use, consider upgrading to a paid plan for better performance and reliability.
