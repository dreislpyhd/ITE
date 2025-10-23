<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "Starting user cleanup...\n";
    
    // First, let's see what users exist
    $stmt = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "Current users:\n";
    foreach ($users as $user) {
        echo "- ID: {$user['id']}, Username: {$user['username']}, Name: {$user['full_name']}, Role: {$user['role']}\n";
    }
    
    // Count total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    echo "Total users before cleanup: {$total_users}\n";
    
    // Delete all users except admin (assuming admin has role 'admin' and username 'admin')
    $stmt = $conn->prepare("DELETE FROM users WHERE username != 'admin' OR role != 'admin'");
    $result = $stmt->execute();
    
    if ($result) {
        $deleted_count = $stmt->rowCount();
        echo "Successfully deleted {$deleted_count} users!\n";
        
        // Show remaining users
        $stmt = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id");
        $remaining_users = $stmt->fetchAll();
        
        echo "Remaining users:\n";
        foreach ($remaining_users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Name: {$user['full_name']}, Role: {$user['role']}\n";
        }
        
        echo "Cleanup completed successfully! Only admin user remains.\n";
        
    } else {
        echo "Error occurred during cleanup.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
