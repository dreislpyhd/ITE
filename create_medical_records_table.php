<?php
/**
 * Migration script to create medical_records table in SQLite database
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "Creating medical_records table...\n";
    
    // Drop table if exists (for clean migration)
    $conn->exec("DROP TABLE IF EXISTS medical_records");
    
    // Create medical_records table (SQLite compatible)
    $sql = "CREATE TABLE medical_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        appointment_id INTEGER DEFAULT NULL,
        record_date DATE NOT NULL,
        consultation_date DATE DEFAULT NULL,
        symptoms TEXT,
        diagnosis TEXT,
        treatment TEXT,
        prescription TEXT,
        doctor_name VARCHAR(255),
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
    )";
    
    $conn->exec($sql);
    
    // Create indexes for better performance
    $conn->exec("CREATE INDEX idx_medical_user_id ON medical_records(user_id)");
    $conn->exec("CREATE INDEX idx_medical_record_date ON medical_records(record_date)");
    $conn->exec("CREATE INDEX idx_medical_appointment ON medical_records(appointment_id)");
    
    echo "✓ medical_records table created successfully!\n";
    echo "✓ Indexes created successfully!\n";
    
    // Verify table was created
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='medical_records'");
    if ($result->fetch()) {
        echo "\n✓ Verification: medical_records table exists in database.\n";
        
        // Show table structure
        echo "\nTable structure:\n";
        $pragma = $conn->query("PRAGMA table_info(medical_records)");
        while ($column = $pragma->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$column['name']} ({$column['type']})\n";
        }
    } else {
        echo "\n✗ Error: Table was not created properly.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration completed successfully!\n";
?>
