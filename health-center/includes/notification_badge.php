<?php
// This file should be included in all health center pages to show notification badges
// It calculates notification counts without marking them as read

function getHealthCenterNotificationCounts($user_id, $conn) {
    $counts = [
        'patients' => 0,
        'appointments' => 0,
        'medical_records' => 0
    ];
    
    try {
        // Get user's last viewed timestamps
        $stmt = $conn->prepare("SELECT last_viewed_patients, last_viewed_appointments FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_viewed = $stmt->fetch();
        $last_viewed_patients = $user_viewed['last_viewed_patients'] ?? null;
        $last_viewed_appointments = $user_viewed['last_viewed_appointments'] ?? null;
        
        // Count new patient registrations (pending status - case insensitive)
        // Always count all pending registrations regardless of last_viewed
        $stmt = $conn->prepare("
            SELECT COUNT(*) as patient_count 
            FROM patient_registrations 
            WHERE LOWER(status) = 'pending'
        ");
        $stmt->execute();
        $patient_result = $stmt->fetch();
        $new_registrations = $patient_result['patient_count'] ?? 0;
        
        // Debug: Log the query result
        error_log("Patient registrations query result: " . print_r($patient_result, true));
        
        // Count unread patient registration notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) as notif_count 
            FROM patient_registration_notifications 
            WHERE is_read = 0
        ");
        $stmt->execute();
        $notif_result = $stmt->fetch();
        $unread_notifications = $notif_result['notif_count'] ?? 0;
        
        // Total patients count (new registrations + unread notifications)
        $counts['patients'] = $new_registrations + $unread_notifications;
        
        // Count new appointments (scheduled status created after last viewed)
        if ($last_viewed_appointments) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as appointment_count 
                FROM appointments 
                WHERE status = 'scheduled'
                AND created_at > ?
            ");
            $stmt->execute([$last_viewed_appointments]);
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as appointment_count 
                FROM appointments 
                WHERE status = 'scheduled'
            ");
            $stmt->execute();
        }
        $appointment_result = $stmt->fetch();
        $counts['appointments'] = $appointment_result['appointment_count'] ?? 0;
        
        // Count medical records that need attention (optional - if you want to track this)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as record_count 
            FROM medical_records 
            WHERE status = 'pending' OR status IS NULL
        ");
        $stmt->execute();
        $record_result = $stmt->fetch();
        $counts['medical_records'] = $record_result['record_count'] ?? 0;
        
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
