-- =====================================================
-- Barangay 172 Urduja Management System Database
-- Complete SQL file for XAMPP import
-- Created: 2025
-- =====================================================

-- Drop database if exists and create new one
DROP DATABASE IF EXISTS `barangay_management`;
CREATE DATABASE `barangay_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `barangay_management`;

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','resident','barangay_staff','health_staff') NOT NULL DEFAULT 'resident',
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
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TABLE: profiles
-- ====================================================the 
CREATE TABLE `profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text NOT NULL,
  `barangay` varchar(100) DEFAULT 'Barangay 172 Urduja',
  `city` varchar(100) DEFAULT 'Caloocan City',
  `province` varchar(100) DEFAULT 'Metro Manila',
  `postal_code` varchar(10) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','divorced','separated') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT 'Filipino',
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `emergency_relationship` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_name` (`first_name`, `last_name`),
  KEY `idx_barangay` (`barangay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: applications
-- =====================================================
CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `application_type` enum('business_permit','barangay_clearance','indigency_certificate','residency_certificate','other') NOT NULL,
  `status` enum('pending','approved','rejected','processing') NOT NULL DEFAULT 'pending',
  `description` text,
  `documents` text COMMENT 'JSON array of uploaded document filenames',
  `admin_notes` text,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `reference_number` varchar(20) DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `fee_paid` tinyint(1) DEFAULT 0,
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`application_type`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: appointments
-- =====================================================
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `service_type` enum('medical_consultation','vaccination','health_checkup','dental_service','other') NOT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` text,
  `staff_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date` (`appointment_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: patient_registrations
-- =====================================================
CREATE TABLE `patient_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL,
  `emergency_contact` varchar(255) NOT NULL,
  `medical_history` text,
  `current_medications` text,
  `insurance_info` varchar(255),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `staff_notes` text,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: concerns
-- =====================================================
CREATE TABLE `concerns` (
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
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_type` (`concern_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: case_records
-- =====================================================
CREATE TABLE `case_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `case_number` varchar(20) NOT NULL UNIQUE,
  `case_type` enum('civil','criminal','administrative','other') NOT NULL,
  `status` enum('open','pending','resolved','closed','dismissed') NOT NULL DEFAULT 'open',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `documents` text COMMENT 'JSON array of uploaded document filenames',
  `resolution` text,
  `filed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` datetime DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_user_id` (`user_id`),
  KEY `idx_case_number` (`case_number`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`case_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: admin_messages
-- =====================================================
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

-- =====================================================
-- TABLE: system_settings
-- =====================================================
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

-- =====================================================
-- TABLE: activity_logs
-- =====================================================
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
-- INSERT SAMPLE DATA
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`, `email_verified`) VALUES
('admin', 'admin@barangay172.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj8J/vHhHhHh', 'admin', 1, 1),
('staff1', 'staff1@barangay172.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj8J/vHhHhHh', 'staff', 1, 1),
('resident1', 'resident1@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj8J/vHhHhHh', 'resident', 1, 1),
('resident2', 'resident2@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj8J/vHhHhHh', 'resident', 1, 1);

-- Insert profiles
INSERT INTO `profiles` (`user_id`, `first_name`, `last_name`, `phone`, `address`, `birth_date`, `gender`, `civil_status`, `occupation`) VALUES
(1, 'Barangay', 'Administrator', '09123456789', 'Barangay Hall, Brgy. 172 Urduja, Caloocan City', '1980-01-01', 'male', 'married', 'Barangay Administrator'),
(2, 'Maria', 'Santos', '09187654321', '123 Main St., Brgy. 172 Urduja, Caloocan City', '1985-05-15', 'female', 'married', 'Barangay Staff'),
(3, 'Juan', 'Dela Cruz', '09234567890', '456 Side St., Brgy. 172 Urduja, Caloocan City', '1990-03-20', 'male', 'single', 'Engineer'),
(4, 'Ana', 'Garcia', '09345678901', '789 Corner St., Brgy. 172 Urduja, Caloocan City', '1988-07-10', 'female', 'married', 'Teacher');

-- Insert sample applications
INSERT INTO `applications` (`user_id`, `application_type`, `status`, `description`, `reference_number`, `fee_amount`) VALUES
(3, 'barangay_clearance', 'approved', 'Barangay clearance for employment purposes', 'BRG-2025-0001', 100.00),
(4, 'business_permit', 'pending', 'Business permit for small sari-sari store', 'BRG-2025-0002', 500.00),
(3, 'indigency_certificate', 'processing', 'Indigency certificate for scholarship application', 'BRG-2025-0003', 50.00);

-- Insert sample appointments
INSERT INTO `appointments` (`user_id`, `service_type`, `appointment_date`, `status`, `notes`) VALUES
(3, 'medical_consultation', '2025-01-15 09:00:00', 'confirmed', 'Regular checkup'),
(4, 'vaccination', '2025-01-20 10:00:00', 'scheduled', 'COVID-19 booster shot'),
(3, 'health_checkup', '2025-01-25 14:00:00', 'scheduled', 'Annual physical examination');

-- Insert sample concerns
INSERT INTO `concerns` (`user_id`, `concern_type`, `priority`, `status`, `description`, `location`) VALUES
(3, 'noise_complaint', 'medium', 'acknowledged', 'Loud music from neighbor during late hours', 'Main Street Area'),
(4, 'garbage_disposal', 'high', 'in_progress', 'Garbage not being collected regularly', 'Corner Street Area'),
(3, 'street_lighting', 'low', 'reported', 'Street light not working properly', 'Side Street Area');

-- Insert sample case records
INSERT INTO `case_records` (`user_id`, `case_number`, `case_type`, `status`, `title`, `description`) VALUES
(3, 'BRG-2025-0001', 'civil', 'open', 'Property Boundary Dispute', 'Dispute over property boundary with neighbor'),
(4, 'BRG-2025-0002', 'administrative', 'pending', 'Community Service Request', 'Request for community service hours');

-- Insert sample admin messages
INSERT INTO `admin_messages` (`user_id`, `subject`, `message`, `type`, `status`) VALUES
(3, 'Application Approved', 'Your barangay clearance application has been approved. You can claim it at the barangay hall.', 'notification', 'unread'),
(4, 'System Maintenance', 'The system will be under maintenance on January 20, 2025 from 2:00 AM to 4:00 AM.', 'announcement', 'unread'),
(3, 'Appointment Reminder', 'Reminder: You have a medical consultation appointment tomorrow at 9:00 AM.', 'notification', 'read');

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

-- Insert sample activity logs
INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`) VALUES
(1, 'login', 'Admin user logged in', '127.0.0.1'),
(3, 'register', 'New resident registered', '127.0.0.1'),
(3, 'submit_application', 'Submitted barangay clearance application', '127.0.0.1'),
(1, 'approve_application', 'Approved application BRG-2025-0001', '127.0.0.1'),
(4, 'submit_concern', 'Submitted noise complaint concern', '127.0.0.1');

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
    p.first_name,
    p.last_name,
    p.phone,
    p.address,
    p.barangay,
    p.city,
    p.province
FROM users u
LEFT JOIN profiles p ON u.id = p.user_id;

-- View for application statistics
CREATE VIEW `application_stats_view` AS
SELECT 
    application_type,
    status,
    COUNT(*) as count,
    DATE(submitted_at) as submission_date
FROM applications
GROUP BY application_type, status, DATE(submitted_at);

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
        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
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
    FROM applications
    GROUP BY application_type, status;
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
BEFORE UPDATE ON `applications`
FOR EACH ROW
BEGIN
    IF NEW.status IN ('approved', 'rejected') AND OLD.status NOT IN ('approved', 'rejected') THEN
        SET NEW.processed_at = NOW();
    END IF;
END //
DELIMITER ;

-- Trigger to update concern resolved_at when status changes
DELIMITER //
CREATE TRIGGER `update_concern_resolved_at`
BEFORE UPDATE ON `concerns`
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

-- =====================================================
-- SET PRIVILEGES (if needed)
-- =====================================================

-- Grant privileges to application user (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON barangay_management.* TO 'barangay_user'@'localhost';

-- =====================================================
-- FINAL COMMENTS
-- =====================================================

/*
Barangay 172 Urduja Management System Database
==============================================

This SQL file contains:
- Complete database structure
- Sample data for testing
- Views for common queries
- Stored procedures for statistics
- Triggers for automatic updates
- Activity logging system

To import into XAMPP:
1. Open phpMyAdmin
2. Create new database or use existing
3. Import this SQL file
4. Update config.php with correct database credentials

Default admin credentials:
- Username: admin
- Password: admin123
- Email: admin@barangay172.com

System Features:
- User management (Admin, Resident, Staff)
- Application processing
- Appointment scheduling
- Concern reporting
- Case management
- Message system
- Activity logging
- System settings

Created: 2025
Version: 1.0
*/
