<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

try {
    // Check if user is already registered as a patient
    $stmt = $conn->prepare("SELECT id, status FROM patient_registrations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $registration = $stmt->fetch();
    
    $response = [
        'is_registered' => false,
        'has_pending_registration' => false,
        'status' => null
    ];
    
    if ($registration) {
        if ($registration['status'] === 'approved') {
            $response['is_registered'] = true;
            $response['status'] = 'approved';
        } elseif ($registration['status'] === 'pending') {
            $response['has_pending_registration'] = true;
            $response['status'] = 'pending';
        } elseif ($registration['status'] === 'rejected') {
            $response['status'] = 'rejected';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log("Patient status check error: " . $e->getMessage());
}
?>
