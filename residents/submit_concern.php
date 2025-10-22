<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in and is resident
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $concern_type = trim($_POST['concern_type'] ?? '');
        $specific_issue = trim($_POST['specific_issue'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['message'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        $user_id = $_SESSION['user_id'];
        
        // Validate required fields
        if (empty($concern_type) || empty($specific_issue) || empty($location) || empty($description)) {
            throw new Exception('All required fields must be filled out.');
        }
        
        // Handle file uploads
        $photo_paths = [];
        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            $upload_dir = '../uploads/concerns/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            for ($i = 0; $i < count($_FILES['photos']['name']); $i++) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_type = $_FILES['photos']['type'][$i];
                    $file_size = $_FILES['photos']['size'][$i];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, and GIF files are allowed.');
                    }
                    
                    if ($file_size > $max_size) {
                        throw new Exception('File size too large. Maximum size is 5MB per file.');
                    }
                    
                    $file_extension = pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $file_path)) {
                        $photo_paths[] = $filename;
                    }
                }
            }
        }
        
        // Convert priority to proper case
        $priority_level = ucfirst($priority);
        
        // Insert into database
        $stmt = $conn->prepare("
            INSERT INTO community_concerns 
            (user_id, concern_type, specific_issue, location, description, priority_level, status, photos, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, datetime('now'))
        ");
        
        $photos_json = !empty($photo_paths) ? json_encode($photo_paths) : null;
        
        if ($stmt->execute([$user_id, $concern_type, $specific_issue, $location, $description, $priority_level, $photos_json])) {
            $response['success'] = true;
            $response['message'] = 'Your concern has been submitted successfully. The barangay will review and address it accordingly.';
        } else {
            throw new Exception('Failed to submit concern. Please try again.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
