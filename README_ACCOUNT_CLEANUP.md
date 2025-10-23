# Automatic Account Cleanup System

This system automatically deletes resident accounts that haven't uploaded required documents within 30 days of registration.

## How It Works

1. **25 Days**: Warning messages are sent to residents approaching the deadline
2. **30 Days**: Accounts are automatically deleted if documents are missing
3. **Daily Execution**: The cleanup script runs automatically via cron job

## Features

- ✅ Automatic warning messages at 25 days
- ✅ Automatic account deletion at 30 days
- ✅ File cleanup (removes uploaded documents)
- ✅ Comprehensive logging
- ✅ Dashboard widget for residents
- ✅ Statistics and monitoring

## Setup Instructions

### 1. Update Database

First, run the database update script to add the `message_type` field:

```bash
# Via web browser
http://localhost:8000/update_admin_messages_table.php

# Or via command line
php update_admin_messages_table.php
```

### 2. Test the Cleanup Script

Test the cleanup script manually to ensure it works:

```bash
# Run cleanup
php cleanup_expired_accounts.php

# Check statistics
php cleanup_expired_accounts.php --stats
```

### 3. Set Up Automatic Execution (Cron Job)

#### On Windows (Task Scheduler):
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger to Daily at 2:00 AM
4. Set action to start program: `php`
5. Add arguments: `C:\path\to\your\project\cleanup_expired_accounts.php`
6. Set start in: `C:\path\to\your\project`

#### On Linux/Mac (Cron):
```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * php /path/to/your/project/cleanup_expired_accounts.php

# Or run every 6 hours
0 */6 * * * php /path/to/your/project/cleanup_expired_accounts.php
```

#### On XAMPP (Windows):
Create a batch file `run_cleanup.bat`:
```batch
@echo off
cd /d "C:\xampp\htdocs\your-project"
php cleanup_expired_accounts.php
```

Then use Windows Task Scheduler to run this batch file daily.

### 4. Add Deadline Widget to Resident Dashboard

Include the deadline widget in your resident dashboard:

```php
<?php include 'deadline_widget.php'; ?>
```

## File Structure

```
├── cleanup_expired_accounts.php      # Main cleanup script
├── update_admin_messages_table.php   # Database update script
├── residents/deadline_widget.php     # Resident dashboard widget
├── logs/                             # Log files directory
│   └── account_cleanup.log          # Cleanup activity logs
└── README_ACCOUNT_CLEANUP.md        # This file
```

## Configuration

### Customize Deadlines

Edit `cleanup_expired_accounts.php` to change the deadline periods:

```php
// Change from 30 days to 45 days
AND created_at < DATE_SUB(NOW(), INTERVAL 45 DAY)

// Change warning from 25 days to 40 days
AND created_at < DATE_SUB(NOW(), INTERVAL 40 DAY)
```

### Customize Warning Messages

Modify the message content in the `sendWarningMessages()` method:

```php
$message = "Dear " . $account['full_name'] . ",\n\n";
$message .= "Custom warning message here...\n";
```

## Monitoring

### Check Logs

View cleanup activity logs:
```bash
# View recent logs
tail -f logs/account_cleanup.log

# Search for specific activities
grep "Deleted expired account" logs/account_cleanup.log
```

### Check Statistics

View current cleanup statistics:
```bash
php cleanup_expired_accounts.php --stats
```

Or visit via web browser:
```
http://localhost:8000/cleanup_expired_accounts.php
```

### Dashboard Monitoring

The resident dashboard widget shows:
- Days remaining until deadline
- Document upload status
- Warning messages
- Progress bar visualization

## Safety Features

- **Backup**: Always backup your database before running cleanup
- **Logging**: All actions are logged with timestamps
- **Dry Run**: Test with `--stats` flag first
- **File Cleanup**: Removes uploaded documents when deleting accounts
- **Message Cleanup**: Removes associated admin messages

## Troubleshooting

### Common Issues

1. **Permission Denied**: Ensure PHP has write access to logs directory
2. **Database Connection**: Check database credentials in config.php
3. **Cron Not Running**: Verify cron service is active and paths are correct
4. **File Not Found**: Ensure absolute paths are correct in cron jobs

### Testing

Test the system with a test account:
1. Create a test resident account
2. Wait for the cleanup script to run
3. Check logs for activity
4. Verify account deletion

### Manual Cleanup

Run cleanup manually if needed:
```bash
php cleanup_expired_accounts.php
```

## Security Considerations

- The cleanup script should only be accessible to authorized users
- Log files contain sensitive information - secure them appropriately
- Consider encrypting log files in production
- Monitor cleanup activities for unusual patterns

## Support

If you encounter issues:
1. Check the logs in `logs/account_cleanup.log`
2. Verify database connectivity
3. Test the script manually first
4. Check file permissions and paths

## License

This system is part of the Barangay 172 Management System.
