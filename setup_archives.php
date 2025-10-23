<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Archives - Barangay 172</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        h1 { color: #2E8B57; }
        .btn { display: inline-block; padding: 10px 20px; background: #2E8B57; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #236B47; }
    </style>
</head>
<body>
    <h1>🗄️ Archives Setup</h1>
    <p>This will create the necessary database tables for the Archives feature.</p>
    
<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Create deleted_patients table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS deleted_patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            blood_type TEXT,
            emergency_contact TEXT,
            medical_history TEXT,
            current_medications TEXT,
            insurance_info TEXT,
            status TEXT DEFAULT 'pending',
            created_at TEXT,
            deleted_at TEXT DEFAULT (datetime('now')),
            deleted_by INTEGER,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (deleted_by) REFERENCES users(id)
        )
    ");
    echo "✓ Created deleted_patients table<br>";
    
    // Create deleted_appointments table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS deleted_appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            service_type TEXT NOT NULL,
            appointment_date TEXT NOT NULL,
            notes TEXT,
            status TEXT DEFAULT 'scheduled',
            created_at TEXT DEFAULT (datetime('now')),
            deleted_at TEXT DEFAULT (datetime('now')),
            deleted_by INTEGER,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (deleted_by) REFERENCES users(id)
        )
    ");
    echo "✓ Created deleted_appointments table<br>";
    
    // Create indexes
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_deleted_patients_user_id ON deleted_patients(user_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_deleted_patients_deleted_at ON deleted_patients(deleted_at)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_deleted_appointments_user_id ON deleted_appointments(user_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_deleted_appointments_deleted_at ON deleted_appointments(deleted_at)");
    echo "✓ Created indexes<br>";
    
    echo "<br><p class='success'>✓ Archive tables setup completed successfully!</p>";
    echo "<div class='info'>";
    echo "<strong>What's next?</strong><br>";
    echo "• You can now delete patients and appointments<br>";
    echo "• Deleted records will appear in the Archives<br>";
    echo "• You can restore deleted records from Archives<br>";
    echo "</div>";
    echo "<a href='health-center/archives.php' class='btn'>Go to Archives</a> ";
    echo "<a href='health-center/health-staff.php' class='btn'>Go to Patients</a> ";
    echo "<a href='health-center/appointments.php' class='btn'>Go to Appointments</a>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure the database file has write permissions.</p>";
}
?>
</body>
</html>
