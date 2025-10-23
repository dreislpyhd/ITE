<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

try {
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($role_filter) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get all users matching criteria
    $users_stmt = $conn->prepare("SELECT id, username, full_name, email, role, address, phone, status, house_no, street, purok_endorsement, valid_id, created_at, updated_at FROM users WHERE $where_clause ORDER BY created_at DESC");
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper Excel encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    $headers = [
        'User ID',
        'Full Name',
        'Username',
        'Email',
        'Role',
        'Phone',
        'House No.',
        'Street',
        'Complete Address',
        'Status',
        'Purok Endorsement',
        'Valid ID',
        'Created Date',
        'Last Updated'
    ];
    fputcsv($output, $headers);
    
    // Add user data
    foreach ($users as $user) {
        $row = [
            $user['id'],
            $user['full_name'],
            $user['username'],
            $user['email'],
            ucfirst(str_replace('_', ' ', $user['role'])),
            $user['phone'] ?: 'Not provided',
            $user['house_no'] ?: 'Not provided',
            $user['street'] ?: 'Not provided',
            $user['address'] ?: 'Not provided',
            ucfirst($user['status']),
            $user['purok_endorsement'] ? 'Uploaded' : 'Not uploaded',
            $user['valid_id'] ? 'Uploaded' : 'Not uploaded',
            date('Y-m-d H:i:s', strtotime($user['created_at'])),
            date('Y-m-d H:i:s', strtotime($user['updated_at']))
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    // If there's an error, redirect back with error message
    header('Location: users.php?error=' . urlencode('Export failed: ' . $e->getMessage()));
    exit();
}
?>
