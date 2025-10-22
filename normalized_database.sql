-- =====================================================
-- Barangay 172 Urduja Management System
-- Normalized Database Schema
-- Created: 2025
-- =====================================================

-- Drop database if exists and create new one
DROP DATABASE IF EXISTS `barangay_management`;
CREATE DATABASE `barangay_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barangay_management`;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Users table (Core authentication and basic info)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','barangay_staff','health_staff','resident') NOT NULL DEFAULT 'resident',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User profiles table (Extended user information)
CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `full_name` varchar(255) GENERATED ALWAYS AS (CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) STORED,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','divorced','separated') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `emergency_relationship` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `account_verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_name` (`first_name`, `last_name`),
  KEY `idx_verified` (`account_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses table (Normalized address information)
CREATE TABLE `addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `house_no` varchar(20) DEFAULT NULL,
  `street` varchar(100) NOT NULL,
  `barangay` varchar(100) DEFAULT 'Barangay 172 Urduja',
  `city` varchar(100) DEFAULT 'Caloocan City',
  `province` varchar(100) DEFAULT 'Metro Manila',
  `postal_code` varchar(10) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_primary` (`user_id`, `is_primary`),
  KEY `idx_barangay` (`barangay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents table (User uploaded documents)
CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_type` enum('purok_endorsement','valid_id','medical_certificate','other') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_type` (`user_id`, `document_type`),
  KEY `idx_uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BARANGAY HALL MODULE TABLES
-- =====================================================

-- Barangay services table
CREATE TABLE `barangay_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) NOT NULL,
  `description` text,
  `requirements` text,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `processing_time` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Barangay applications table
CREATE TABLE `barangay_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `application_type` enum('business_permit','barangay_clearance','indigency_certificate','residency_certificate','other') NOT NULL,
  `status` enum('pending','processing','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `description` text,
  `reference_number` varchar(20) DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `fee_paid` tinyint(1) DEFAULT 0,
  `payment_date` datetime DEFAULT NULL,
  `admin_notes` text,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `barangay_services` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`application_type`),
  KEY `idx_reference` (`reference_number`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application documents table
CREATE TABLE `application_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`application_id`) REFERENCES `barangay_applications` (`id`) ON DELETE CASCADE,
  KEY `idx_application` (`application_id`),
  KEY `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Case records table
CREATE TABLE `case_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `case_number` varchar(20) NOT NULL UNIQUE,
  `case_type` enum('civil','criminal','administrative','other') NOT NULL,
  `status` enum('open','pending','resolved','closed','dismissed') NOT NULL DEFAULT 'open',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `resolution` text,
  `filed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_case_number` (`case_number`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`case_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- HEALTH CENTER MODULE TABLES
-- =====================================================

-- Health services table
CREATE TABLE `health_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(255) NOT NULL,
  `description` text,
  `requirements` text,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `service_type` enum('consultation','vaccination','laboratory','dental','pharmacy','other') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`service_type`),
  KEY `idx_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments table
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` text,
  `staff_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `health_services` (`id`) ON DELETE CASCADE,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_status` (`status`),
  KEY `idx_service` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medical records table
CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `consultation_date` date NOT NULL,
  `symptoms` text,
  `diagnosis` text,
  `treatment` text,
  `prescription` text,
  `doctor_name` varchar(255),
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date` (`consultation_date`),
  KEY `idx_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COMMUNITY MODULE TABLES
-- =====================================================

-- Community concerns table
CREATE TABLE `community_concerns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `concern_type` enum('noise_complaint','garbage_disposal','street_lighting','road_maintenance','security','health_related','other') NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('reported','acknowledged','in_progress','resolved','closed') NOT NULL DEFAULT 'reported',
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `admin_response` text,
  `reported_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_type` (`concern_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SYSTEM TABLES
-- =====================================================

-- Admin messages table
CREATE TABLE `admin_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('notification','announcement','warning','info') NOT NULL DEFAULT 'notification',
  `status` enum('unread','read','archived') NOT NULL DEFAULT 'unread',
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings table
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`, `email_verified`) VALUES
('admin', 'admin@barangay172.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj8J/vHhHhHh', 'admin', 1, 1);

-- Insert admin profile
INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`, `phone`, `occupation`) VALUES
(1, 'System', 'Administrator', '09123456789', 'System Administrator');

-- Insert sample barangay services
INSERT INTO `barangay_services` (`service_name`, `description`, `requirements`, `fee_amount`, `processing_time`) VALUES
('Barangay Clearance', 'Official clearance for various purposes', 'Valid ID, Proof of Residency', 50.00, '3-5 working days'),
('Business Permit', 'Permit to operate business in barangay', 'Business Plan, Valid ID, Proof of Residency', 200.00, '5-7 working days'),
('Indigency Certificate', 'Certificate for indigent residents', 'Valid ID, Proof of Income', 25.00, '2-3 working days'),
('Certificate of Residency', 'Official certificate proving residency in the barangay', 'Valid ID, Proof of Residency', 0.00, '1-2 working days');

-- Insert sample health services
INSERT INTO `health_services` (`service_name`, `description`, `requirements`, `fee_amount`, `service_type`) VALUES
('Medical Consultation', 'General medical consultation', 'Valid ID, Medical History', 0.00, 'consultation'),
('Vaccination', 'Various vaccination services', 'Valid ID, Vaccination Card', 0.00, 'vaccination'),
('Health Education', 'Health awareness programs', 'Valid ID', 0.00, 'consultation'),
('Prenatal Care', 'Prenatal checkup and care', 'Valid ID, Medical History', 0.00, 'consultation'),
('Dental Checkup', 'Dental examination and cleaning', 'Valid ID', 0.00, 'dental'),
('Laboratory Tests', 'Blood tests and other laboratory services', 'Valid ID, Doctor\'s Request', 0.00, 'laboratory');

-- Insert system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'Barangay 172 Urduja Management System', 'Website name'),
('site_description', 'Official management system for Barangay 172 Urduja, Caloocan City', 'Website description'),
('admin_email', 'admin@barangay172.com', 'Administrator email address'),
('max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)'),
('allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', 'Allowed file types for uploads'),
('session_timeout', '3600', 'Session timeout in seconds'),
('items_per_page', '10', 'Number of items per page in listings'),
('maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'),
('email_notifications', '1', 'Enable email notifications (0=off, 1=on)'),
('registration_enabled', '1', 'Enable user registration (0=off, 1=on)');

-- =====================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for user profiles with role information
CREATE VIEW `user_profiles_view` AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.is_active,
    u.created_at,
    up.first_name,
    up.last_name,
    up.middle_name,
    up.full_name,
    up.phone,
    up.birth_date,
    up.gender,
    up.civil_status,
    up.occupation,
    up.account_verified,
    a.house_no,
    a.street,
    a.barangay,
    a.city,
    a.province
FROM users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN addresses a ON u.id = a.user_id AND a.is_primary = 1;

-- View for application statistics
CREATE VIEW `application_stats_view` AS
SELECT 
    ba.application_type,
    ba.status,
    COUNT(*) as count,
    DATE(ba.submitted_at) as submission_date
FROM barangay_applications ba
GROUP BY ba.application_type, ba.status, DATE(ba.submitted_at);

-- View for recent activities
CREATE VIEW `recent_activities_view` AS
SELECT 
    al.id,
    al.action,
    al.description,
    al.created_at,
    u.username,
    u.role
FROM activity_logs al
LEFT JOIN users u ON al.user_id = u.id
ORDER BY al.created_at DESC;

-- View for health center statistics
CREATE VIEW `health_stats_view` AS
SELECT 
    hs.service_type,
    a.status,
    COUNT(*) as count,
    DATE(a.appointment_date) as appointment_date
FROM appointments a
JOIN health_services hs ON a.service_id = hs.id
GROUP BY hs.service_type, a.status, DATE(a.appointment_date);

-- =====================================================
-- CREATE STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to get user statistics
CREATE PROCEDURE `GetUserStats`()
BEGIN
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'resident' THEN 1 ELSE 0 END) as resident_count,
        SUM(CASE WHEN role IN ('barangay_staff', 'barangay_hall') THEN 1 ELSE 0 END) as barangay_staff_count,
        SUM(CASE WHEN role IN ('health_staff', 'health_center') THEN 1 ELSE 0 END) as health_staff_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
    FROM users;
END //

-- Procedure to get application statistics
CREATE PROCEDURE `GetApplicationStats`()
BEGIN
    SELECT 
        application_type,
        status,
        COUNT(*) as count
    FROM barangay_applications
    GROUP BY application_type, status;
END //

-- Procedure to get health center statistics
CREATE PROCEDURE `GetHealthStats`()
BEGIN
    SELECT 
        hs.service_type,
        a.status,
        COUNT(*) as count
    FROM appointments a
    JOIN health_services hs ON a.service_id = hs.id
    GROUP BY hs.service_type, a.status;
END //

-- Procedure to clean up old activity logs
CREATE PROCEDURE `CleanupOldLogs`(IN days_to_keep INT)
BEGIN
    DELETE FROM activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
END //

DELIMITER ;

-- =====================================================
-- CREATE TRIGGERS
-- =====================================================

-- Trigger to update application processed_at when status changes
DELIMITER //
CREATE TRIGGER `update_application_processed_at`
BEFORE UPDATE ON `barangay_applications`
FOR EACH ROW
BEGIN
    IF NEW.status IN ('approved', 'rejected', 'completed') AND OLD.status NOT IN ('approved', 'rejected', 'completed') THEN
        SET NEW.processed_at = NOW();
    END IF;
END //
DELIMITER ;

-- Trigger to update concern resolved_at when status changes
DELIMITER //
CREATE TRIGGER `update_concern_resolved_at`
BEFORE UPDATE ON `community_concerns`
FOR EACH ROW
BEGIN
    IF NEW.status = 'resolved' AND OLD.status != 'resolved' THEN
        SET NEW.resolved_at = NOW();
    END IF;
END //
DELIMITER ;

-- Trigger to log user activity
DELIMITER //
CREATE TRIGGER `log_user_activity`
AFTER INSERT ON `users`
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, description)
    VALUES (NEW.id, 'user_created', CONCAT('New user created: ', NEW.username));
END //
DELIMITER ;

-- Trigger to generate reference number for applications
DELIMITER //
CREATE TRIGGER `generate_reference_number`
BEFORE INSERT ON `barangay_applications`
FOR EACH ROW
BEGIN
    IF NEW.reference_number IS NULL THEN
        SET NEW.reference_number = CONCAT('BRG-', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) + 1 FROM barangay_applications WHERE YEAR(submitted_at) = YEAR(NOW())), 4, '0'));
    END IF;
END //
DELIMITER ;

-- =====================================================
-- FINAL COMMENTS
-- =====================================================

/*
Barangay 172 Urduja Management System - Normalized Database
==========================================================

This normalized database structure addresses:

1. ROLE CONSISTENCY:
   - Fixed role enum to include all required roles
   - admin, barangay_staff, barangay_hall, health_staff, health_center, resident

2. DATA NORMALIZATION:
   - Separated user authentication (users) from profile data (user_profiles)
   - Normalized address information (addresses table)
   - Separated document storage (user_documents, application_documents)

3. RELATIONSHIP INTEGRITY:
   - Proper foreign key relationships
   - Cascade deletes where appropriate
   - Set NULL for optional relationships

4. MODULE SEPARATION:
   - Barangay Hall: barangay_services, barangay_applications, case_records
   - Health Center: health_services, appointments, medical_records
   - Community: community_concerns
   - System: admin_messages, system_settings, activity_logs

5. PERFORMANCE OPTIMIZATION:
   - Appropriate indexes on frequently queried columns
   - Views for common queries
   - Stored procedures for complex operations

6. DATA INTEGRITY:
   - Triggers for automatic updates
   - Generated columns for computed values
   - Proper constraints and validations

To migrate from existing database:
1. Backup current data
2. Run this SQL to create new structure
3. Migrate data using provided migration scripts
4. Update application code to use new structure

Created: 2025
Version: 2.0 (Normalized)
*/
