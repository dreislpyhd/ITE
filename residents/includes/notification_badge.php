<?php
// This file should be included in all resident pages to show notification badges
// It calculates notification counts without marking them as read

function getResidentNotificationCounts($user_id, $conn) {
    $counts = [
        'applications' => 0,
        'patient_registrations' => 0
    ];
    
    try {
        // Count application status updates
        $stmt = $conn->prepare("SELECT last_viewed_applications FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_viewed = $stmt->fetch();
        $last_viewed = $user_viewed['last_viewed_applications'] ?? null;
        
        if ($last_viewed) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM applications 
                WHERE user_id = ? 
                AND status IN ('processing', 'approved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
                AND (processed_date > ? OR updated_at > ?)
            ");
            $stmt->execute([$user_id, $last_viewed, $last_viewed]);
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM applications 
                WHERE user_id = ? 
                AND status IN ('processing', 'approved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
            ");
            $stmt->execute([$user_id]);
        }
        
        $result = $stmt->fetch();
        $counts['applications'] = $result['update_count'] ?? 0;
        
        // Count unread patient registration notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) as notification_count 
            FROM patient_registration_notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $notification_result = $stmt->fetch();
        $counts['patient_registrations'] = $notification_result['notification_count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Error getting notification counts: " . $e->getMessage());
    }
    
    return $counts;
}

// Get notification counts for current user
$notification_counts = [
    'applications' => 0,
    'patient_registrations' => 0
];

if (isset($_SESSION['user_id']) && isset($conn)) {
    try {
        $notification_counts = getResidentNotificationCounts($_SESSION['user_id'], $conn);
    } catch (Exception $e) {
        error_log("Error loading notification counts: " . $e->getMessage());
        // Keep default values if there's an error
    }
}
?>
