<?php
// This file should be included in all health center pages to show notification badges
// It calculates notification counts without marking them as read

function getHealthCenterNotificationCounts($user_id, $conn) {
    $counts = [
        'patient_registrations' => 0,
        'appointments' => 0
    ];
    
    try {
        // Count unread patient registration notifications (for health center staff)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as notification_count 
            FROM patient_registration_notifications 
            WHERE is_read = 0
        ");
        $stmt->execute();
        $notification_result = $stmt->fetch();
        $counts['patient_registrations'] = $notification_result['notification_count'] ?? 0;
        
        // Count new appointments (scheduled status)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as appointment_count 
            FROM appointments 
            WHERE status = 'scheduled'
        ");
        $stmt->execute();
        $appointment_result = $stmt->fetch();
        $counts['appointments'] = $appointment_result['appointment_count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Error getting health center notification counts: " . $e->getMessage());
    }
    
    return $counts;
}

// Get notification counts for current user
$notification_counts = [];
if (isset($_SESSION['user_id'])) {
    $notification_counts = getHealthCenterNotificationCounts($_SESSION['user_id'], $conn);
}
?>
