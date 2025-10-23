<?php
// This file should be included in all barangay-hall pages to show notification badges
// It calculates notification counts without marking them as read

require_once __DIR__ . '/../../includes/street_assignments.php';

function getBarangayHallNotificationCounts($user_id, $conn) {
    $counts = [
        'applications' => 0,
        'concerns' => 0,
        'residents' => 0
    ];
    
    try {
        // Check if user is an encoder
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $user_role = $user['role'] ?? '';
        
        $is_encoder = in_array($user_role, ['encoder1', 'encoder2', 'encoder3']);
        $assigned_streets = [];
        $street_filter = '';
        
        if ($is_encoder) {
            $assigned_streets = getStreetsForEncoder($user_role);
            $street_placeholders = implode(',', array_fill(0, count($assigned_streets), '?'));
            $street_filter = "AND u.street IN ($street_placeholders)";
        }
        // Count unread application notifications
        $stmt = $conn->prepare("
            SELECT COUNT(*) as notif_count 
            FROM application_notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $notif_result = $stmt->fetch();
        $unread_notifications = $notif_result['notif_count'] ?? 0;
        
        // Count new applications (pending status) and application status updates
        $stmt = $conn->prepare("SELECT last_viewed_applications FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_viewed = $stmt->fetch();
        $last_viewed = $user_viewed['last_viewed_applications'] ?? null;
        
        // Count new pending applications (only those created after last viewed, filtered by streets for encoders)
        if ($is_encoder) {
            if ($last_viewed) {
                $query = "
                    SELECT COUNT(*) as new_count 
                    FROM applications a
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE a.status = 'pending'
                    AND a.application_date > ?
                    $street_filter
                ";
                $params = array_merge([$last_viewed], $assigned_streets);
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
            } else {
                $query = "
                    SELECT COUNT(*) as new_count 
                    FROM applications a
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE a.status = 'pending'
                    $street_filter
                ";
                $stmt = $conn->prepare($query);
                $stmt->execute($assigned_streets);
            }
        } else {
            if ($last_viewed) {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as new_count 
                    FROM applications 
                    WHERE status = 'pending'
                    AND application_date > ?
                ");
                $stmt->execute([$last_viewed]);
            } else {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as new_count 
                    FROM applications 
                    WHERE status = 'pending'
                ");
                $stmt->execute();
            }
        }
        $new_result = $stmt->fetch();
        $new_applications = $new_result['new_count'] ?? 0;
        
        // Total applications count (unread notifications + new applications)
        $counts['applications'] = $unread_notifications + $new_applications;
        
        // Count new community concerns (pending status) and concerns updates
        $stmt = $conn->prepare("SELECT last_viewed_concerns FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_viewed = $stmt->fetch();
        $last_viewed = $user_viewed['last_viewed_concerns'] ?? null;
        
        // Count new pending concerns (only those created after last viewed)
        if ($last_viewed) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as new_count 
                FROM community_concerns 
                WHERE status = 'pending'
                AND created_at > ?
            ");
            $stmt->execute([$last_viewed]);
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as new_count 
                FROM community_concerns 
                WHERE status = 'pending'
            ");
            $stmt->execute();
        }
        $new_result = $stmt->fetch();
        $new_concerns = $new_result['new_count'] ?? 0;
        
        // Count concerns status updates
        if ($last_viewed) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM community_concerns 
                WHERE status IN ('processing', 'resolved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
                AND (processed_date > ? OR updated_at > ?)
            ");
            $stmt->execute([$last_viewed, $last_viewed]);
        } else {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as update_count 
                FROM community_concerns 
                WHERE status IN ('processing', 'resolved') 
                AND (status != 'pending' OR processed_date IS NOT NULL)
            ");
            $stmt->execute();
        }
        
        $result = $stmt->fetch();
        $update_concerns = $result['update_count'] ?? 0;
        
        // Total concerns count (new + updates)
        $counts['concerns'] = $new_concerns + $update_concerns;
        
        // Count residents with uploaded documents waiting for verification
        $stmt = $conn->prepare("SELECT last_viewed_residents FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_viewed = $stmt->fetch();
        $last_viewed_residents = $user_viewed['last_viewed_residents'] ?? null;
        
        if ($is_encoder) {
            // For encoders, filter by assigned streets
            if ($last_viewed_residents) {
                $query = "
                    SELECT COUNT(*) as resident_count 
                    FROM users u
                    WHERE u.role = 'resident' 
                    AND (u.account_verified = 0 OR u.account_verified IS NULL)
                    AND u.purok_endorsement IS NOT NULL 
                    AND u.purok_endorsement != ''
                    AND u.valid_id IS NOT NULL 
                    AND u.valid_id != ''
                    AND u.created_at > ?
                    $street_filter
                ";
                $params = array_merge([$last_viewed_residents], $assigned_streets);
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
            } else {
                $query = "
                    SELECT COUNT(*) as resident_count 
                    FROM users u
                    WHERE u.role = 'resident' 
                    AND (u.account_verified = 0 OR u.account_verified IS NULL)
                    AND u.purok_endorsement IS NOT NULL 
                    AND u.purok_endorsement != ''
                    AND u.valid_id IS NOT NULL 
                    AND u.valid_id != ''
                    $street_filter
                ";
                $stmt = $conn->prepare($query);
                $stmt->execute($assigned_streets);
            }
        } else {
            if ($last_viewed_residents) {
                // Only count residents that were uploaded after the last viewed timestamp
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as resident_count 
                    FROM users 
                    WHERE role = 'resident' 
                    AND (account_verified = 0 OR account_verified IS NULL)
                    AND purok_endorsement IS NOT NULL 
                    AND purok_endorsement != ''
                    AND valid_id IS NOT NULL 
                    AND valid_id != ''
                    AND created_at > ?
                ");
                $stmt->execute([$last_viewed_residents]);
            } else {
                // If never viewed, count all residents with uploaded documents
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as resident_count 
                    FROM users 
                    WHERE role = 'resident' 
                    AND (account_verified = 0 OR account_verified IS NULL)
                    AND purok_endorsement IS NOT NULL 
                    AND purok_endorsement != ''
                    AND valid_id IS NOT NULL 
                    AND valid_id != ''
                ");
                $stmt->execute();
            }
        }
        
        $resident_result = $stmt->fetch();
        $counts['residents'] = $resident_result['resident_count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Error getting barangay hall notification counts: " . $e->getMessage());
    }
    
    return $counts;
}

// Get notification counts for current user
$notification_counts = [];
if (isset($_SESSION['user_id'])) {
    $notification_counts = getBarangayHallNotificationCounts($_SESSION['user_id'], $conn);
}
?>
