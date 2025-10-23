<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>User Cleanup Script</h2>";
    echo "<p>This script will remove all users except the main admin user.</p>";
    
    // First, let's see what users exist
    $stmt = $conn->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "<h3>Current Users:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']} - Username: {$user['username']} - Name: {$user['full_name']} - Role: {$user['role']} - Created: {$user['created_at']}</li>";
    }
    echo "</ul>";
    
    // Count total users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    echo "<p><strong>Total users before cleanup: {$total_users}</strong></p>";
    
    // Delete all users except admin (assuming admin has role 'admin' and username 'admin')
    $stmt = $conn->prepare("DELETE FROM users WHERE username != 'admin' OR role != 'admin'");
    $result = $stmt->execute();
    
    if ($result) {
        $deleted_count = $stmt->rowCount();
        echo "<p style='color: green;'><strong>Successfully deleted {$deleted_count} users!</strong></p>";
        
        // Show remaining users
        $stmt = $conn->query("SELECT id, username, full_name, role, created_at FROM users ORDER BY id");
        $remaining_users = $stmt->fetchAll();
        
        echo "<h3>Remaining Users:</h3>";
        echo "<ul>";
        foreach ($remaining_users as $user) {
            echo "<li>ID: {$user['id']} - Username: {$user['username']} - Name: {$user['full_name']} - Role: {$user['role']} - Created: {$user['created_at']}</li>";
        }
        echo "</ul>";
        
        echo "<p style='color: green;'><strong>Cleanup completed successfully! Only admin user remains.</strong></p>";
        
    } else {
        echo "<p style='color: red;'><strong>Error occurred during cleanup.</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error: " . $e->getMessage() . "</strong></p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
ul { background: #f5f5f5; padding: 10px; border-radius: 5px; }
li { margin: 5px 0; }
</style>
