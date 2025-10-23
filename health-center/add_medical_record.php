<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is health_staff or health_center
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['health_staff', 'health_center'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: medical-records.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get form data
$patient_id = $_POST['patient_id'] ?? '';
$record_date = $_POST['record_date'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$treatment = $_POST['treatment'] ?? '';
$notes = $_POST['notes'] ?? '';

// Validate required fields
if (empty($patient_id) || empty($record_date) || empty($diagnosis) || empty($treatment)) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: medical-records.php');
    exit();
}

// Validate patient exists and is a resident
try {
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'resident'");
    $check_stmt->execute([$patient_id]);
    if (!$check_stmt->fetch()) {
        $_SESSION['error'] = 'Invalid patient selected.';
        header('Location: medical-records.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error validating patient: ' . $e->getMessage();
    header('Location: medical-records.php');
    exit();
}

// Insert medical record
try {
    $stmt = $conn->prepare("INSERT INTO medical_records (user_id, record_date, diagnosis, treatment, notes) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$patient_id, $record_date, $diagnosis, $treatment, $notes]);
    
    $_SESSION['success'] = 'Medical record added successfully!';
    header('Location: medical-records.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = 'Error adding medical record: ' . $e->getMessage();
    header('Location: medical-records.php');
    exit();
}
?>
