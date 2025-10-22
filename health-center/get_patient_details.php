<?php
require_once '../includes/config.php';
session_start();

// Set content type to JSON and disable error display
header('Content-Type: application/json');
ini_set('display_errors', 0);

// Check if user is logged in and is health_staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'health_staff') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

$registration_id = $_GET['id'] ?? '';

if (empty($registration_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Registration ID is required']);
    exit();
}

try {
    // Get patient registration details with user information
    $stmt = $conn->prepare("
        SELECT pr.*, u.full_name, u.email, u.phone, u.address, u.birthday
        FROM patient_registrations pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.id = ?
    ");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        http_response_code(404);
        echo json_encode(['error' => 'Registration not found']);
        exit();
    }
    
    $response = [
        'success' => true,
        'patient' => [
            'full_name' => $registration['full_name'] ?? '',
            'email' => $registration['email'] ?? '',
            'phone' => $registration['phone'] ?? null,
            'address' => $registration['address'] ?? null,
            'birthday' => $registration['birthday'] ?? null
        ],
        'registration' => [
            'blood_type' => $registration['blood_type'] ?? '',
            'emergency_contact' => $registration['emergency_contact'] ?? '',
            'medical_history' => $registration['medical_history'] ?? null,
            'current_medications' => $registration['current_medications'] ?? null,
            'insurance_info' => $registration['insurance_info'] ?? null,
            'status' => $registration['status'] ?? 'pending',
            'staff_notes' => $registration['staff_notes'] ?? null,
            'created_at' => $registration['created_at'] ?? null,
            'approved_at' => $registration['approved_at'] ?? null
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log("Patient details error: " . $e->getMessage());
}
?>
