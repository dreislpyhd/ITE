<?php
/**
 * Database Migration Script
 * Migrates from current database structure to normalized structure
 * 
 * Usage: Run this script to migrate existing data to the new normalized structure
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

class DatabaseMigration {
    private $conn;
    private $errors = [];
    private $success = [];
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    
    public function migrate() {
        echo "Starting database migration...\n";
        
        try {
            // Step 1: Backup existing data
            $this->backupExistingData();
            
            // Step 2: Create new normalized structure
            $this->createNormalizedStructure();
            
            // Step 3: Migrate user data
            $this->migrateUserData();
            
            // Step 4: Migrate profile data
            $this->migrateProfileData();
            
            // Step 5: Migrate address data
            $this->migrateAddressData();
            
            // Step 6: Migrate document data
            $this->migrateDocumentData();
            
            // Step 7: Migrate application data
            $this->migrateApplicationData();
            
            // Step 8: Migrate health data
            $this->migrateHealthData();
            
            // Step 9: Migrate system data
            $this->migrateSystemData();
            
            echo "Migration completed successfully!\n";
            $this->printSummary();
            
        } catch (Exception $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
            $this->printErrors();
        }
    }
    
    private function backupExistingData() {
        echo "Backing up existing data...\n";
        
        // Create backup tables
        $backupTables = [
            'users_backup' => 'users',
            'profiles_backup' => 'profiles',
            'applications_backup' => 'applications',
            'appointments_backup' => 'appointments',
            'concerns_backup' => 'concerns',
            'case_records_backup' => 'case_records',
            'admin_messages_backup' => 'admin_messages',
            'system_settings_backup' => 'system_settings',
            'activity_logs_backup' => 'activity_logs'
        ];
        
        foreach ($backupTables as $backupTable => $originalTable) {
            try {
                $this->conn->exec("CREATE TABLE IF NOT EXISTS {$backupTable} LIKE {$originalTable}");
                $this->conn->exec("INSERT INTO {$backupTable} SELECT * FROM {$originalTable}");
                $this->success[] = "Backed up {$originalTable} to {$backupTable}";
            } catch (Exception $e) {
                $this->errors[] = "Failed to backup {$originalTable}: " . $e->getMessage();
            }
        }
    }
    
    private function createNormalizedStructure() {
        echo "Creating normalized database structure...\n";
        
        // Read and execute the normalized database SQL
        $sqlFile = 'normalized_database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        $this->conn->exec($statement);
                    } catch (Exception $e) {
                        // Ignore errors for existing tables/objects
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $this->errors[] = "SQL Error: " . $e->getMessage();
                        }
                    }
                }
            }
            $this->success[] = "Normalized database structure created";
        } else {
            throw new Exception("Normalized database SQL file not found");
        }
    }
    
    private function migrateUserData() {
        echo "Migrating user data...\n";
        
        // Map old roles to new roles
        $roleMapping = [
            'staff' => 'barangay_staff',
            'barangay_staff' => 'barangay_staff',
            'barangay_hall' => 'barangay_hall',
            'health_center_staff' => 'health_staff',
            'health_center' => 'health_center',
            'resident' => 'resident',
            'admin' => 'admin'
        ];
        
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, email, password, role, is_active, email_verified, created_at, updated_at)
            SELECT 
                username, 
                email, 
                password, 
                CASE 
                    WHEN role = 'staff' THEN 'barangay_staff'
                    WHEN role = 'health_center_staff' THEN 'health_staff'
                    ELSE role
                END as role,
                is_active,
                email_verified,
                created_at,
                updated_at
            FROM users_backup
            WHERE username NOT IN (SELECT username FROM users)
        ");
        
        $stmt->execute();
        $this->success[] = "User data migrated";
    }
    
    private function migrateProfileData() {
        echo "Migrating profile data...\n";
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, middle_name, phone, birth_date, gender, civil_status, occupation, nationality, emergency_contact, emergency_phone, emergency_relationship, profile_picture, account_verified, created_at, updated_at)
            SELECT 
                u.id,
                p.first_name,
                p.last_name,
                p.middle_name,
                p.phone,
                p.birth_date,
                p.gender,
                p.civil_status,
                p.occupation,
                p.nationality,
                p.emergency_contact,
                p.emergency_phone,
                p.emergency_relationship,
                p.profile_picture,
                p.account_verified,
                p.created_at,
                p.updated_at
            FROM profiles_backup p
            JOIN users u ON p.user_id = u.id
            WHERE u.id NOT IN (SELECT user_id FROM user_profiles)
        ");
        
        $stmt->execute();
        $this->success[] = "Profile data migrated";
    }
    
    private function migrateAddressData() {
        echo "Migrating address data...\n";
        
        // Migrate from users table address fields
        $stmt = $this->conn->prepare("
            INSERT INTO addresses (user_id, house_no, street, barangay, city, province, postal_code, is_primary, created_at, updated_at)
            SELECT 
                u.id,
                u.house_no,
                COALESCE(u.street, 'Unknown'),
                COALESCE(u.barangay, 'Barangay 172 Urduja'),
                COALESCE(u.city, 'Caloocan City'),
                COALESCE(u.province, 'Metro Manila'),
                u.postal_code,
                1,
                u.created_at,
                u.updated_at
            FROM users_backup u
            WHERE u.id NOT IN (SELECT user_id FROM addresses WHERE is_primary = 1)
            AND (u.house_no IS NOT NULL OR u.street IS NOT NULL)
        ");
        
        $stmt->execute();
        $this->success[] = "Address data migrated";
    }
    
    private function migrateDocumentData() {
        echo "Migrating document data...\n";
        
        // Migrate purok endorsement documents
        $stmt = $this->conn->prepare("
            INSERT INTO user_documents (user_id, document_type, filename, original_filename, uploaded_at)
            SELECT 
                u.id,
                'purok_endorsement',
                u.purok_endorsement,
                'purok_endorsement_' || u.id || '.jpg',
                u.created_at
            FROM users_backup u
            WHERE u.purok_endorsement IS NOT NULL
            AND u.id NOT IN (SELECT user_id FROM user_documents WHERE document_type = 'purok_endorsement')
        ");
        
        $stmt->execute();
        
        // Migrate valid ID documents
        $stmt = $this->conn->prepare("
            INSERT INTO user_documents (user_id, document_type, filename, original_filename, uploaded_at)
            SELECT 
                u.id,
                'valid_id',
                u.valid_id,
                'valid_id_' || u.id || '.jpg',
                u.created_at
            FROM users_backup u
            WHERE u.valid_id IS NOT NULL
            AND u.id NOT IN (SELECT user_id FROM user_documents WHERE document_type = 'valid_id')
        ");
        
        $stmt->execute();
        $this->success[] = "Document data migrated";
    }
    
    private function migrateApplicationData() {
        echo "Migrating application data...\n";
        
        // Migrate barangay applications
        $stmt = $this->conn->prepare("
            INSERT INTO barangay_applications (user_id, service_id, application_type, status, description, reference_number, fee_amount, fee_paid, payment_date, admin_notes, submitted_at, processed_at, processed_by, created_at, updated_at)
            SELECT 
                a.user_id,
                COALESCE(bs.id, 1) as service_id,
                a.application_type,
                a.status,
                a.description,
                a.reference_number,
                a.fee_amount,
                a.fee_paid,
                a.payment_date,
                a.admin_notes,
                a.submitted_at,
                a.processed_at,
                a.processed_by,
                a.created_at,
                a.updated_at
            FROM applications_backup a
            LEFT JOIN barangay_services bs ON bs.service_name LIKE CONCAT('%', a.application_type, '%')
            WHERE a.user_id IN (SELECT id FROM users)
        ");
        
        $stmt->execute();
        $this->success[] = "Application data migrated";
    }
    
    private function migrateHealthData() {
        echo "Migrating health data...\n";
        
        // Migrate appointments
        $stmt = $this->conn->prepare("
            INSERT INTO appointments (user_id, service_id, appointment_date, status, notes, staff_notes, created_at, updated_at)
            SELECT 
                a.user_id,
                COALESCE(hs.id, 1) as service_id,
                a.appointment_date,
                a.status,
                a.notes,
                a.staff_notes,
                a.created_at,
                a.updated_at
            FROM appointments_backup a
            LEFT JOIN health_services hs ON hs.service_name LIKE CONCAT('%', a.service_type, '%')
            WHERE a.user_id IN (SELECT id FROM users)
        ");
        
        $stmt->execute();
        $this->success[] = "Health data migrated";
    }
    
    private function migrateSystemData() {
        echo "Migrating system data...\n";
        
        // Migrate admin messages
        $stmt = $this->conn->prepare("
            INSERT INTO admin_messages (user_id, subject, message, type, status, sent_at, read_at, created_at, updated_at)
            SELECT 
                am.user_id,
                'System Message',
                am.message,
                'notification',
                'unread',
                am.created_at,
                NULL,
                am.created_at,
                am.created_at
            FROM admin_messages_backup am
            WHERE am.user_id IN (SELECT id FROM users)
        ");
        
        $stmt->execute();
        
        // Migrate system settings
        $stmt = $this->conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at)
            SELECT 
                ss.setting_key,
                ss.setting_value,
                ss.description,
                ss.created_at,
                ss.updated_at
            FROM system_settings_backup ss
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                description = VALUES(description),
                updated_at = NOW()
        ");
        
        $stmt->execute();
        
        // Migrate activity logs
        $stmt = $this->conn->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            SELECT 
                al.user_id,
                al.action,
                al.description,
                al.ip_address,
                al.user_agent,
                al.created_at
            FROM activity_logs_backup al
            WHERE al.user_id IN (SELECT id FROM users)
        ");
        
        $stmt->execute();
        $this->success[] = "System data migrated";
    }
    
    private function printSummary() {
        echo "\n=== Migration Summary ===\n";
        echo "Successful operations: " . count($this->success) . "\n";
        echo "Errors: " . count($this->errors) . "\n";
        
        if (!empty($this->success)) {
            echo "\nSuccessful operations:\n";
            foreach ($this->success as $success) {
                echo "✓ " . $success . "\n";
            }
        }
    }
    
    private function printErrors() {
        if (!empty($this->errors)) {
            echo "\nErrors encountered:\n";
            foreach ($this->errors as $error) {
                echo "✗ " . $error . "\n";
            }
        }
    }
}

// Run migration if script is executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $migration = new DatabaseMigration();
    $migration->migrate();
} else {
    echo "Database Migration Script\n";
    echo "Run this script from command line or add ?run=1 to URL\n";
    echo "This will migrate your existing database to the normalized structure.\n";
    echo "Make sure to backup your database before running this script!\n";
}
?>
