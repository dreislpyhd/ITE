# MySQL Migration Guide

This guide will help you migrate your Barangay Management System from SQLite to MySQL.

## Prerequisites

1. **MySQL Server**: Make sure you have MySQL installed and running on your system
2. **PHP MySQL Extension**: Ensure PHP has the MySQL PDO extension enabled
3. **Backup**: Always backup your existing SQLite database before migration

## Step 1: Update Configuration

The configuration file (`includes/config.php`) has been updated with MySQL settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'barangay_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

**Important**: Update the `DB_USER` and `DB_PASS` values according to your MySQL setup.

## Step 2: Create MySQL Database

Run the setup script to create the MySQL database and tables:

```bash
php setup_mysql_database.php
```

This script will:
- Connect to your MySQL server
- Create the `barangay_management` database
- Create all necessary tables with proper MySQL syntax
- Insert sample data and default admin user

## Step 3: Migrate Existing Data (Optional)

If you have existing data in your SQLite database that you want to preserve:

1. **Backup your SQLite database**:
   ```bash
   cp barangay_management.db barangay_management.db.backup
   ```

2. **Run the migration script**:
   ```bash
   php migrate_sqlite_to_mysql.php
   ```

This script will transfer all your existing data from SQLite to MySQL.

## Step 4: Test the Application

1. Make sure your web server is running
2. Access your application
3. Try logging in with the default admin account:
   - Username: `admin`
   - Password: `admin123`

## Step 5: Clean Up (After Successful Migration)

Once you've confirmed everything is working:

1. **Remove the old SQLite database**:
   ```bash
   rm barangay_management.db
   ```

2. **Remove migration scripts** (optional):
   ```bash
   rm setup_mysql_database.php
   rm migrate_sqlite_to_mysql.php
   rm MYSQL_MIGRATION_README.md
   ```

## Key Changes Made

### Database Connection
- Changed from SQLite file-based connection to MySQL server connection
- Updated PDO connection string and parameters

### Table Structure
- Changed `INTEGER PRIMARY KEY AUTOINCREMENT` to `INT AUTO_INCREMENT PRIMARY KEY`
- Updated data types (e.g., `TEXT` to `VARCHAR(255)` where appropriate)
- Added `ENGINE=InnoDB` for MySQL compatibility
- Added proper character set and collation (`utf8mb4`)
- Enhanced ENUM fields for better data validation

### Foreign Keys
- Added proper `ON DELETE` constraints
- Improved referential integrity

## Troubleshooting

### Common Issues

1. **Connection Failed**
   - Verify MySQL server is running
   - Check username/password in config
   - Ensure MySQL user has proper permissions

2. **Table Creation Errors**
   - Make sure the database exists
   - Check MySQL user permissions
   - Verify MySQL version compatibility

3. **Data Migration Issues**
   - Ensure SQLite database file exists
   - Check for data type compatibility issues
   - Verify foreign key relationships

### MySQL User Setup

If you need to create a dedicated MySQL user:

```sql
CREATE USER 'barangay_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON barangay_management.* TO 'barangay_user'@'localhost';
FLUSH PRIVILEGES;
```

Then update your config file accordingly.

## Benefits of MySQL Migration

1. **Better Performance**: MySQL is optimized for web applications
2. **Scalability**: Better handling of concurrent users
3. **Advanced Features**: Stored procedures, triggers, views
4. **Backup & Recovery**: Better backup and restore capabilities
5. **Monitoring**: Built-in performance monitoring tools
6. **Security**: More robust user management and access control

## Support

If you encounter any issues during migration, check:
1. MySQL error logs
2. PHP error logs
3. Web server error logs
4. Database connection parameters

---

**Note**: This migration is irreversible. Always backup your data before proceeding.
