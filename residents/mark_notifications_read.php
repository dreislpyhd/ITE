<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'mark_read') {
    try {
        // Mark all unread notifications for this user as read
        $stmt = $conn->prepare("UPDATE patient_registration_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
    } catch (Exception $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating notifications']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
