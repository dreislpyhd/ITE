<?php
/**
 * Script to create default resident accounts
 * Run this script to add default resident users to existing databases
 */

require_once 'includes/config.php';

try {
    $conn = new PDO($dsn, $username, $password, $options);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating default resident accounts...\n";
    
    // Default resident accounts
    $residents = [
        [
            'full_name' => 'Juan Dela Cruz',
            'username' => 'resident1',
            'email' => 'resident1@barangay172.com',
            'password' => 'resident123',
            'phone' => '09123456789',
            'address' => '123 Main St., Brgy. 172 Urduja, Caloocan City'
        ],
        [
            'full_name' => 'Maria Santos',
            'username' => 'resident2',
            'email' => 'resident2@barangay172.com',
            'password' => 'resident123',
            'phone' => '09234567890',
            'address' => '456 Side St., Brgy. 172 Urduja, Caloocan City'
        ],
        [
            'full_name' => 'Ana Garcia',
            'username' => 'resident3',
            'email' => 'resident3@barangay172.com',
            'password' => 'resident123',
            'phone' => '09345678901',
            'address' => '789 Corner St., Brgy. 172 Urduja, Caloocan City'
        ]
    ];
    
    foreach ($residents as $resident) {
        // Check if user already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $check_stmt->execute([$resident['username'], $resident['email']]);
        
        if ($check_stmt->fetchColumn() == 0) {
            // Create user
            $hashed_password = password_hash($resident['password'], PASSWORD_DEFAULT);
            $insert_stmt = $conn->prepare("
                INSERT INTO users (full_name, username, email, password, role, phone, address, account_verified, created_at) 
                VALUES (?, ?, ?, ?, 'resident', ?, ?, TRUE, datetime('now'))
            ");
            
            if ($insert_stmt->execute([
                $resident['full_name'],
                $resident['username'],
                $resident['email'],
                $hashed_password,
                $resident['phone'],
                $resident['address']
            ])) {
                echo "✅ Created resident: {$resident['full_name']} ({$resident['username']})\n";
            } else {
                echo "❌ Failed to create resident: {$resident['full_name']}\n";
            }
        } else {
            echo "⚠️  Resident already exists: {$resident['full_name']} ({$resident['username']})\n";
        }
    }
    
    echo "\nDefault resident accounts creation completed!\n";
    echo "\nResident Login Credentials:\n";
    echo "==========================\n";
    echo "Resident 1: resident1 / resident123\n";
    echo "Resident 2: resident2 / resident123\n";
    echo "Resident 3: resident3 / resident123\n";
    echo "\nAll accounts are pre-verified and ready to use.\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
