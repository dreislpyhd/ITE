<?php
require_once 'config_render.php';

class Database {
    private $connection;
    
    public function __construct() {
        $this->connect();
        $this->createTables();
    }
    
    private function connect() {
        try {
            $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';options=--client_encoding=utf8';
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
        // Users table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'resident',
                address TEXT,
                phone VARCHAR(20),
                house_no VARCHAR(20),
                street VARCHAR(100),
                purok_endorsement VARCHAR(255),
                valid_id VARCHAR(255),
                account_verified BOOLEAN DEFAULT FALSE,
                verified_by INTEGER,
                verified_at TIMESTAMP NULL,
                birthday DATE NULL,
                gender VARCHAR(10) NULL,
                civil_status VARCHAR(20) NULL,
                year_started_living INTEGER NULL,
                last_viewed_applications TIMESTAMP NULL,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Barangay Services table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS barangay_services (
                id SERIAL PRIMARY KEY,
                service_name VARCHAR(255) NOT NULL,
                description TEXT,
                requirements TEXT,
                fee DECIMAL(10,2) DEFAULT 0.00,
                processing_time VARCHAR(100),
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Health Services table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS health_services (
                id SERIAL PRIMARY KEY,
                service_name VARCHAR(255) NOT NULL,
                description TEXT,
                requirements TEXT,
                fee DECIMAL(10,2) DEFAULT 0.00,
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Applications table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS applications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                service_type VARCHAR(100) NOT NULL,
                service_id INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_date TIMESTAMP NULL,
                processed_by INTEGER NULL,
                purpose TEXT,
                requirements_files TEXT,
                remarks TEXT,
                notes TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL
            )
        ");
        
        // Patient Registrations table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS patient_registrations (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                blood_type VARCHAR(10),
                emergency_contact TEXT,
                medical_history TEXT,
                current_medications TEXT,
                insurance_info TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                approved_at TIMESTAMP NULL,
                approved_by INTEGER NULL,
                staff_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ");
        
        // Patient Registration Notifications table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS patient_registration_notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                registration_id INTEGER,
                status VARCHAR(50),
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (registration_id) REFERENCES patient_registrations (id) ON DELETE CASCADE
            )
        ");
        
        // Appointments table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS appointments (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                service_type VARCHAR(100),
                appointment_date TIMESTAMP,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                confirmed_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL
            )
        ");
        
        // Community Concerns table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS community_concerns (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                concern_type VARCHAR(255) NOT NULL,
                specific_issue VARCHAR(255) NOT NULL,
                location VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                priority_level VARCHAR(20) DEFAULT 'Medium',
                status VARCHAR(20) DEFAULT 'Pending',
                photos TEXT,
                admin_response TEXT,
                admin_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
            )
        ");
        
        // Residents table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS residents (
                id SERIAL PRIMARY KEY,
                user_id INTEGER UNIQUE,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100),
                birth_date DATE,
                gender VARCHAR(10),
                civil_status VARCHAR(20),
                address TEXT,
                contact_number VARCHAR(20),
                emergency_contact VARCHAR(255),
                emergency_contact_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ");
        
        // Health Records table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS health_records (
                id SERIAL PRIMARY KEY,
                resident_id INTEGER NOT NULL,
                consultation_date DATE NOT NULL,
                symptoms TEXT,
                diagnosis TEXT,
                treatment TEXT,
                prescription TEXT,
                doctor_name VARCHAR(255),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE CASCADE
            )
        ");
        
        // Admin Messages table
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS admin_messages (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                admin_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ");
        
        // Insert default admin user if not exists
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->connection->prepare("
                INSERT INTO users (full_name, username, email, password, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute(['System Administrator', 'admin', 'admin@barangay172.com', $admin_password, 'admin']);
        }
        
        // Insert default health staff user if not exists
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE username = 'health_staff' OR email = 'health@barangay172.com'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $health_staff_password = password_hash('health123', PASSWORD_DEFAULT);
                $stmt = $this->connection->prepare("
                    INSERT INTO users (full_name, username, email, password, role, account_verified) 
                    VALUES (?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute(['Health Center Staff', 'health_staff', 'health@barangay172.com', $health_staff_password, 'health_staff']);
            }
        } catch (PDOException $e) {
            error_log("Health staff user creation skipped: " . $e->getMessage());
        }
        
        // Insert default barangay staff user if not exists  
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE username = 'barangay_staff' OR email = 'barangay@barangay172.com'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $barangay_staff_password = password_hash('barangay123', PASSWORD_DEFAULT);
                $stmt = $this->connection->prepare("
                    INSERT INTO users (full_name, username, email, password, role, account_verified) 
                    VALUES (?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute(['Barangay Hall Staff', 'barangay_staff', 'barangay@barangay172.com', $barangay_staff_password, 'barangay_staff']);
            }
        } catch (PDOException $e) {
            error_log("Barangay staff user creation skipped: " . $e->getMessage());
        }
        
        // Insert default resident users if not exists
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE username = 'resident1' OR email = 'resident1@barangay172.com'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $resident1_password = password_hash('resident123', PASSWORD_DEFAULT);
                $stmt = $this->connection->prepare("
                    INSERT INTO users (full_name, username, email, password, role, phone, address, account_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute(['Juan Dela Cruz', 'resident1', 'resident1@barangay172.com', $resident1_password, 'resident', '09123456789', '123 Main St., Brgy. 172 Urduja, Caloocan City']);
            }
        } catch (PDOException $e) {
            error_log("Resident1 user creation skipped: " . $e->getMessage());
        }
        
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE username = 'resident2' OR email = 'resident2@barangay172.com'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $resident2_password = password_hash('resident123', PASSWORD_DEFAULT);
                $stmt = $this->connection->prepare("
                    INSERT INTO users (full_name, username, email, password, role, phone, address, account_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute(['Maria Santos', 'resident2', 'resident2@barangay172.com', $resident2_password, 'resident', '09234567890', '456 Side St., Brgy. 172 Urduja, Caloocan City']);
            }
        } catch (PDOException $e) {
            error_log("Resident2 user creation skipped: " . $e->getMessage());
        }
        
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM users WHERE username = 'resident3' OR email = 'resident3@barangay172.com'");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $resident3_password = password_hash('resident123', PASSWORD_DEFAULT);
                $stmt = $this->connection->prepare("
                    INSERT INTO users (full_name, username, email, password, role, phone, address, account_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute(['Ana Garcia', 'resident3', 'resident3@barangay172.com', $resident3_password, 'resident', '09345678901', '789 Corner St., Brgy. 172 Urduja, Caloocan City']);
            }
        } catch (PDOException $e) {
            error_log("Resident3 user creation skipped: " . $e->getMessage());
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
                INSERT INTO barangay_services (service_name, description, requirements, fee, processing_time) 
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
                ['Medical Consultation', 'General medical consultation', 'Valid ID, Medical History', 0.00],
                ['Vaccination', 'Various vaccination services', 'Valid ID, Vaccination Card', 0.00],
                ['Health Education', 'Health awareness programs', 'Valid ID', 0.00],
                ['Prenatal Care', 'Prenatal checkup and care', 'Valid ID, Medical History', 0.00]
            ];
            
            $stmt = $this->connection->prepare("
                INSERT INTO health_services (service_name, description, requirements, fee) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($health_services as $service) {
                $stmt->execute($service);
            }
        }
    }
    
    public function close() {
        $this->connection = null;
    }
}
?>
