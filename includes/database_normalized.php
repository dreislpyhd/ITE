<?php
require_once 'config.php';

class DatabaseNormalized {
    private $connection;
    
    public function __construct() {
        $this->connect();
        $this->createTables();
    }
    
    private function connect() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function createTables() {
        // Users table (Core authentication and basic info)
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin','barangay_staff','health_staff','resident') NOT NULL DEFAULT 'resident',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                email_verified TINYINT(1) NOT NULL DEFAULT 0,
                verification_token VARCHAR(255) DEFAULT NULL,
                reset_token VARCHAR(255) DEFAULT NULL,
                reset_token_expires DATETIME DEFAULT NULL,
                last_login DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_username (username),
                INDEX idx_role (role),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // User profiles table (Extended user information)
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS user_profiles (
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
                FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_name (first_name, last_name),
                INDEX idx_verified (account_verified)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Addresses table (Normalized address information)
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS addresses (
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
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_user_primary (user_id, is_primary),
                INDEX idx_barangay (barangay)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Documents table (User uploaded documents)
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS user_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                document_type ENUM('purok_endorsement','valid_id','medical_certificate','other') NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                file_size INT DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_user_type (user_id, document_type),
                INDEX idx_uploaded_at (uploaded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Barangay services table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS barangay_services (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_name VARCHAR(255) NOT NULL,
                description TEXT,
                requirements TEXT,
                fee_amount DECIMAL(10,2) DEFAULT 0.00,
                processing_time VARCHAR(100) DEFAULT NULL,
                status ENUM('active','inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_name (service_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Barangay applications table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS barangay_applications (
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
                FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_type (application_type),
                INDEX idx_reference (reference_number),
                INDEX idx_submitted_at (submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Application documents table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS application_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                document_type VARCHAR(100) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                file_size INT DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES barangay_applications (id) ON DELETE CASCADE,
                INDEX idx_application (application_id),
                INDEX idx_type (document_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Case records table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS case_records (
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
                FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_case_number (case_number),
                INDEX idx_status (status),
                INDEX idx_type (case_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Health services table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS health_services (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_name VARCHAR(255) NOT NULL,
                description TEXT,
                requirements TEXT,
                fee_amount DECIMAL(10,2) DEFAULT 0.00,
                service_type ENUM('consultation','vaccination','laboratory','dental','pharmacy','other') NOT NULL,
                status ENUM('active','inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_type (service_type),
                INDEX idx_name (service_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Appointments table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS appointments (
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
                FOREIGN KEY (service_id) REFERENCES health_services (id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_date (appointment_date),
                INDEX idx_status (status),
                INDEX idx_service (service_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Medical records table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS medical_records (
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
                FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_date (consultation_date),
                INDEX idx_appointment (appointment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Community concerns table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS community_concerns (
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
                FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_type (concern_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Admin messages table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS admin_messages (
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
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_type (type),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // System settings table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Activity logs table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default admin user if not exists
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->connection->prepare("
                INSERT INTO users (username, email, password, role) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute(['admin', 'admin@barangay172.com', $admin_password, 'admin']);
            
            // Insert admin profile
            $stmt = $this->connection->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, phone, occupation) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([1, 'System', 'Administrator', '09123456789', 'System Administrator']);
        }
        
        // Insert sample barangay services
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM barangay_services");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $services = [
                ['Barangay Clearance', 'Official clearance for various purposes', 'Valid ID, Proof of Residency', 50.00, '3-5 working days'],
                ['Business Permit', 'Permit to operate business in barangay', 'Business Plan, Valid ID, Proof of Residency', 200.00, '5-7 working days'],
                ['Indigency Certificate', 'Certificate for indigent residents', 'Valid ID, Proof of Income', 25.00, '2-3 working days'],
                ['Certificate of Residency', 'Official certificate proving residency in the barangay', 'Valid ID, Proof of Residency', 0.00, '1-2 working days']
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO barangay_services (service_name, description, requirements, fee_amount, processing_time) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($services as $service) {
                $stmt->execute($service);
            }
        }
        
        // Insert sample health services
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM health_services");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $health_services = [
                ['Medical Consultation', 'General medical consultation', 'Valid ID, Medical History', 0.00, 'consultation'],
                ['Vaccination', 'Various vaccination services', 'Valid ID, Vaccination Card', 0.00, 'vaccination'],
                ['Health Education', 'Health awareness programs', 'Valid ID', 0.00, 'consultation'],
                ['Prenatal Care', 'Prenatal checkup and care', 'Valid ID, Medical History', 0.00, 'consultation'],
                ['Dental Checkup', 'Dental examination and cleaning', 'Valid ID', 0.00, 'dental'],
                ['Laboratory Tests', 'Blood tests and other laboratory services', 'Valid ID, Doctor\'s Request', 0.00, 'laboratory']
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO health_services (service_name, description, requirements, fee_amount, service_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($health_services as $service) {
                $stmt->execute($service);
            }
        }
        
        // Insert system settings
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM system_settings");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $settings = [
                ['site_name', 'Barangay 172 Urduja Management System', 'Website name'],
                ['site_description', 'Official management system for Barangay 172 Urduja, Caloocan City', 'Website description'],
                ['admin_email', 'admin@barangay172.com', 'Administrator email address'],
                ['max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)'],
                ['allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx', 'Allowed file types for uploads'],
                ['session_timeout', '3600', 'Session timeout in seconds'],
                ['items_per_page', '10', 'Number of items per page in listings'],
                ['maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'],
                ['email_notifications', '1', 'Enable email notifications (0=off, 1=on)'],
                ['registration_enabled', '1', 'Enable user registration (0=off, 1=on)']
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
        }
    }
    
    public function close() {
        $this->connection = null;
    }
    
    // Helper methods for common operations
    
    public function getUserWithProfile($userId) {
        $stmt = $this->connection->prepare("
            SELECT u.*, up.*, a.house_no, a.street, a.barangay, a.city, a.province
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN addresses a ON u.id = a.user_id AND a.is_primary = 1
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function getUsersByRole($role) {
        $stmt = $this->connection->prepare("
            SELECT u.*, up.first_name, up.last_name, up.full_name, up.phone
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE u.role = ? AND u.is_active = 1
            ORDER BY up.last_name, up.first_name
        ");
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }
    
    public function getApplicationWithDetails($applicationId) {
        $stmt = $this->connection->prepare("
            SELECT ba.*, bs.service_name, u.username, up.full_name
            FROM barangay_applications ba
            JOIN barangay_services bs ON ba.service_id = bs.id
            JOIN users u ON ba.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE ba.id = ?
        ");
        $stmt->execute([$applicationId]);
        return $stmt->fetch();
    }
    
    public function getAppointmentWithDetails($appointmentId) {
        $stmt = $this->connection->prepare("
            SELECT a.*, hs.service_name, hs.service_type, u.username, up.full_name
            FROM appointments a
            JOIN health_services hs ON a.service_id = hs.id
            JOIN users u ON a.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        return $stmt->fetch();
    }
}
?>
