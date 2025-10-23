# Database Normalization Documentation

## Overview

This document outlines the comprehensive database normalization process for the Barangay 172 Urduja Management System. The normalization addresses data redundancy, role inconsistencies, and structural issues in the current database.

## Issues Identified

### 1. Role Inconsistency
- **Problem**: Database schema has `role ENUM('admin','resident','staff')` but code expects `health_center_staff`, `health_center`, `barangay_staff`, `barangay_hall`
- **Impact**: Users with health center roles cannot log in properly
- **Solution**: Updated role enum to include all required roles

### 2. Data Redundancy
- **Problem**: Address information duplicated across multiple tables
- **Impact**: Data inconsistency and maintenance issues
- **Solution**: Normalized address data into separate `addresses` table

### 3. Poor Data Structure
- **Problem**: Mixed concerns in single tables (users table contains both auth and profile data)
- **Impact**: Difficult to maintain and extend
- **Solution**: Separated concerns into focused tables

### 4. Missing Relationships
- **Problem**: Inconsistent foreign key relationships
- **Impact**: Data integrity issues
- **Solution**: Proper foreign key constraints with appropriate cascade rules

## Normalized Database Structure

### Core Tables

#### 1. `users` - Authentication & Basic Info
```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','barangay_staff','barangay_hall','health_staff','health_center','resident') NOT NULL DEFAULT 'resident',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_token VARCHAR(255) DEFAULT NULL,
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_token_expires DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. `user_profiles` - Extended User Information
```sql
CREATE TABLE user_profiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  middle_name VARCHAR(50) DEFAULT NULL,
  full_name VARCHAR(255) GENERATED ALWAYS AS (CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) STORED,
  phone VARCHAR(20) DEFAULT NULL,
  birth_date DATE DEFAULT NULL,
  gender ENUM('male','female','other') DEFAULT NULL,
  civil_status ENUM('single','married','widowed','divorced','separated') DEFAULT NULL,
  occupation VARCHAR(100) DEFAULT NULL,
  nationality VARCHAR(50) DEFAULT 'Filipino',
  emergency_contact VARCHAR(100) DEFAULT NULL,
  emergency_phone VARCHAR(20) DEFAULT NULL,
  emergency_relationship VARCHAR(50) DEFAULT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  account_verified TINYINT(1) DEFAULT 0,
  verified_by INT DEFAULT NULL,
  verified_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
);
```

#### 3. `addresses` - Normalized Address Information
```sql
CREATE TABLE addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  house_no VARCHAR(20) DEFAULT NULL,
  street VARCHAR(100) NOT NULL,
  barangay VARCHAR(100) DEFAULT 'Barangay 172 Urduja',
  city VARCHAR(100) DEFAULT 'Caloocan City',
  province VARCHAR(100) DEFAULT 'Metro Manila',
  postal_code VARCHAR(10) DEFAULT NULL,
  is_primary TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

#### 4. `user_documents` - Document Management
```sql
CREATE TABLE user_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  document_type ENUM('purok_endorsement','valid_id','medical_certificate','other') NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_size INT DEFAULT NULL,
  mime_type VARCHAR(100) DEFAULT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

### Barangay Hall Module Tables

#### 5. `barangay_services` - Available Services
```sql
CREATE TABLE barangay_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(255) NOT NULL,
  description TEXT,
  requirements TEXT,
  fee_amount DECIMAL(10,2) DEFAULT 0.00,
  processing_time VARCHAR(100) DEFAULT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 6. `barangay_applications` - Service Applications
```sql
CREATE TABLE barangay_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  service_id INT NOT NULL,
  application_type ENUM('business_permit','barangay_clearance','indigency_certificate','residency_certificate','other') NOT NULL,
  status ENUM('pending','processing','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  description TEXT,
  reference_number VARCHAR(20) DEFAULT NULL,
  fee_amount DECIMAL(10,2) DEFAULT 0.00,
  fee_paid TINYINT(1) DEFAULT 0,
  payment_date DATETIME DEFAULT NULL,
  admin_notes TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  processed_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES barangay_services (id) ON DELETE CASCADE,
  FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL
);
```

#### 7. `application_documents` - Application Documents
```sql
CREATE TABLE application_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_size INT DEFAULT NULL,
  mime_type VARCHAR(100) DEFAULT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES barangay_applications (id) ON DELETE CASCADE
);
```

#### 8. `case_records` - Legal Cases
```sql
CREATE TABLE case_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  case_number VARCHAR(20) NOT NULL UNIQUE,
  case_type ENUM('civil','criminal','administrative','other') NOT NULL,
  status ENUM('open','pending','resolved','closed','dismissed') NOT NULL DEFAULT 'open',
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  resolution TEXT,
  filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  assigned_to INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
);
```

### Health Center Module Tables

#### 9. `health_services` - Health Services
```sql
CREATE TABLE health_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(255) NOT NULL,
  description TEXT,
  requirements TEXT,
  fee_amount DECIMAL(10,2) DEFAULT 0.00,
  service_type ENUM('consultation','vaccination','laboratory','dental','pharmacy','other') NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 10. `appointments` - Health Appointments
```sql
CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  service_id INT NOT NULL,
  appointment_date DATETIME NOT NULL,
  status ENUM('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  notes TEXT,
  staff_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES health_services (id) ON DELETE CASCADE
);
```

#### 11. `medical_records` - Patient Medical Records
```sql
CREATE TABLE medical_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  appointment_id INT DEFAULT NULL,
  consultation_date DATE NOT NULL,
  symptoms TEXT,
  diagnosis TEXT,
  treatment TEXT,
  prescription TEXT,
  doctor_name VARCHAR(255),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL
);
```

### Community Module Tables

#### 12. `community_concerns` - Community Issues
```sql
CREATE TABLE community_concerns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  concern_type ENUM('noise_complaint','garbage_disposal','street_lighting','road_maintenance','security','health_related','other') NOT NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('reported','acknowledged','in_progress','resolved','closed') NOT NULL DEFAULT 'reported',
  description TEXT NOT NULL,
  location VARCHAR(255) DEFAULT NULL,
  admin_response TEXT,
  reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  assigned_to INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL
);
```

### System Tables

#### 13. `admin_messages` - System Messages
```sql
CREATE TABLE admin_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('notification','announcement','warning','info') NOT NULL DEFAULT 'notification',
  status ENUM('unread','read','archived') NOT NULL DEFAULT 'unread',
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);
```

#### 14. `system_settings` - Configuration
```sql
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 15. `activity_logs` - Audit Trail
```sql
CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);
```

## Key Improvements

### 1. Role Consistency
- **Before**: `role ENUM('admin','resident','staff')`
- **After**: `role ENUM('admin','barangay_staff','barangay_hall','health_staff','health_center','resident')`
- **Benefit**: All user roles are properly supported

### 2. Data Normalization
- **Before**: Address data scattered across multiple tables
- **After**: Centralized in `addresses` table with proper relationships
- **Benefit**: Eliminates data redundancy and inconsistency

### 3. Proper Relationships
- **Before**: Missing or inconsistent foreign keys
- **After**: Proper foreign key constraints with appropriate cascade rules
- **Benefit**: Data integrity and referential integrity

### 4. Module Separation
- **Before**: Mixed concerns in single tables
- **After**: Clear separation between barangay hall, health center, and community modules
- **Benefit**: Easier maintenance and extension

### 5. Performance Optimization
- **Before**: Missing indexes on frequently queried columns
- **After**: Strategic indexes for common queries
- **Benefit**: Improved query performance

## Migration Process

### Step 1: Backup Current Data
```bash
# Create backup of current database
mysqldump -u username -p barangay_management > backup_before_migration.sql
```

### Step 2: Run Migration Script
```bash
# Run the migration script
php database_migration.php
```

### Step 3: Verify Migration
```bash
# Check that all data was migrated correctly
php check_migration.php
```

### Step 4: Update Application Code
- Replace `Database` class with `DatabaseNormalized`
- Update queries to use new table structure
- Test all functionality

## Role Mapping

| Old Role | New Role | Description |
|----------|----------|-------------|
| `admin` | `admin` | System Administrator |
| `staff` | `barangay_staff` | Barangay Hall Staff |
| `barangay_staff` | `barangay_staff` | Barangay Hall Staff |
| `barangay_hall` | `barangay_hall` | Barangay Hall Manager |
| `health_center_staff` | `health_staff` | Health Center Staff |
| `health_center` | `health_center` | Health Center Manager |
| `resident` | `resident` | Community Resident |

## Benefits of Normalization

### 1. Data Integrity
- Proper foreign key relationships
- Consistent data validation
- Reduced data anomalies

### 2. Performance
- Optimized indexes
- Reduced table sizes
- Better query performance

### 3. Maintainability
- Clear table structure
- Separated concerns
- Easier to extend

### 4. Scalability
- Modular design
- Efficient storage
- Better resource utilization

## Usage Examples

### Getting User with Profile
```php
$db = new DatabaseNormalized();
$user = $db->getUserWithProfile($userId);
```

### Getting Users by Role
```php
$db = new DatabaseNormalized();
$healthStaff = $db->getUsersByRole('health_staff');
```

### Getting Application Details
```php
$db = new DatabaseNormalized();
$application = $db->getApplicationWithDetails($applicationId);
```

## Views and Stored Procedures

### Useful Views
- `user_profiles_view` - Complete user information
- `application_stats_view` - Application statistics
- `health_stats_view` - Health center statistics
- `recent_activities_view` - Recent system activities

### Stored Procedures
- `GetUserStats()` - User statistics by role
- `GetApplicationStats()` - Application statistics
- `GetHealthStats()` - Health center statistics
- `CleanupOldLogs()` - Clean up old activity logs

## Triggers

### Automatic Updates
- `update_application_processed_at` - Updates processed_at when status changes
- `update_concern_resolved_at` - Updates resolved_at when concern is resolved
- `log_user_activity` - Logs user creation
- `generate_reference_number` - Auto-generates application reference numbers

## Security Considerations

### 1. Password Security
- Passwords are hashed using PHP's `password_hash()`
- Salt is automatically generated
- Secure password verification

### 2. SQL Injection Prevention
- All queries use prepared statements
- Parameterized queries
- Input validation and sanitization

### 3. Access Control
- Role-based access control
- Session management
- Activity logging for audit trail

## Maintenance

### Regular Tasks
1. **Backup**: Daily database backups
2. **Log Cleanup**: Monthly cleanup of old activity logs
3. **Index Maintenance**: Quarterly index optimization
4. **Data Validation**: Regular data integrity checks

### Monitoring
- Monitor query performance
- Track user activity
- Check for data anomalies
- Review error logs

## Conclusion

The normalized database structure provides a solid foundation for the Barangay 172 Urduja Management System. It addresses all identified issues while providing better performance, maintainability, and scalability. The migration process ensures a smooth transition from the current structure to the improved normalized structure.

## Files Created

1. `normalized_database.sql` - Complete normalized database schema
2. `database_migration.php` - Migration script for existing data
3. `includes/database_normalized.php` - Updated database class
4. `DATABASE_NORMALIZATION_README.md` - This documentation

## Next Steps

1. **Test Migration**: Run migration on test environment
2. **Update Code**: Modify application code to use new structure
3. **Deploy**: Deploy to production environment
4. **Monitor**: Monitor system performance and data integrity
5. **Document**: Update user documentation and training materials
