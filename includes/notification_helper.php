<?php
require_once 'database.php';

class NotificationHelper {
    private $conn;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    
    /**
     * Mark a module as viewed by the current user
     */
    public function markAsViewed($user_id, $module) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notification_views (user_id, module, last_viewed) 
                VALUES (?, ?, datetime('now')) 
                ON DUPLICATE KEY UPDATE last_viewed = datetime('now')
            ");
            return $stmt->execute([$user_id, $module]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get notification counts for a user
     */
    public function getNotificationCounts($user_id, $user_role = null) {
        try {
            // Get last viewed timestamps for each module
            $stmt = $this->conn->prepare("SELECT module, last_viewed FROM notification_views WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $viewed_modules = [];
            while ($row = $stmt->fetch()) {
                $viewed_modules[$row['module']] = $row['last_viewed'];
            }
            
            if ($user_role === 'resident') {
                // For residents, show different notifications
                
                // New community concerns responses (admin responses or status changes after last viewed)
                $concerns_query = "SELECT COUNT(*) FROM community_concerns WHERE user_id = ? AND (admin_response IS NOT NULL AND admin_response != '' OR status != 'Pending')";
                if (isset($viewed_modules['community_concerns'])) {
                    $concerns_query .= " AND updated_at > '" . $viewed_modules['community_concerns'] . "'";
                }
                $stmt = $this->conn->prepare($concerns_query);
                $stmt->execute([$user_id]);
                $new_concerns = $stmt->fetchColumn();
                
                // New application status updates (status changes after last viewed)
                $applications_query = "SELECT COUNT(*) FROM applications WHERE user_id = ? AND status IN ('processing', 'approved', 'ready_for_pickup')";
                if (isset($viewed_modules['applications'])) {
                    $applications_query .= " AND updated_at > '" . $viewed_modules['applications'] . "'";
                }
                $stmt = $this->conn->prepare($applications_query);
                $stmt->execute([$user_id]);
                $new_applications = $stmt->fetchColumn();
                
                // For residents, we don't show resident account notifications
                $new_residents = 0;
                
            } elseif ($user_role === 'health_staff') {
                // For health staff, show health-related notifications
                
                // New patient registrations (pending status, created after last viewed)
                $patients_query = "SELECT COUNT(*) FROM patient_registrations WHERE status = 'pending'";
                if (isset($viewed_modules['residents'])) {
                    $patients_query .= " AND created_at > '" . $viewed_modules['residents'] . "'";
                }
                $new_residents = $this->conn->query($patients_query)->fetchColumn();
                
                // New appointments (scheduled status, created after last viewed)
                $appointments_query = "SELECT COUNT(*) FROM appointments WHERE status = 'scheduled'";
                if (isset($viewed_modules['appointments'])) {
                    $appointments_query .= " AND created_at > '" . $viewed_modules['appointments'] . "'";
                }
                $new_appointments = $this->conn->query($appointments_query)->fetchColumn();
                
                // For health staff, we don't show concerns or applications
                $new_concerns = 0;
                $new_applications = 0;
                
            } else {
                // For barangay staff, show admin notifications
                
                // New community concerns (pending status, created after last viewed)
                $concerns_query = "SELECT COUNT(*) FROM community_concerns WHERE status = 'Pending'";
                if (isset($viewed_modules['community_concerns'])) {
                    $concerns_query .= " AND created_at > '" . $viewed_modules['community_concerns'] . "'";
                }
                $new_concerns = $this->conn->query($concerns_query)->fetchColumn();
                
                // New applications (pending status, created after last viewed)
                $applications_query = "SELECT COUNT(*) FROM applications WHERE status = 'pending'";
                if (isset($viewed_modules['applications'])) {
                    $applications_query .= " AND created_at > '" . $viewed_modules['applications'] . "'";
                }
                $new_applications = $this->conn->query($applications_query)->fetchColumn();
                
                // New resident accounts (not verified, created after last viewed)
                $residents_query = "SELECT COUNT(*) FROM users WHERE role = 'resident' AND (account_verified = 0 OR account_verified IS NULL)";
                if (isset($viewed_modules['residents'])) {
                    $residents_query .= " AND created_at > '" . $viewed_modules['residents'] . "'";
                }
                $new_residents = $this->conn->query($residents_query)->fetchColumn();
            }
            
            $return_data = [
                'concerns' => $new_concerns,
                'applications' => $new_applications,
                'residents' => $new_residents,
                'total' => $new_concerns + $new_applications + $new_residents
            ];
            
            // Add appointments for health staff
            if ($user_role === 'health_staff') {
                $return_data['appointments'] = $new_appointments ?? 0;
                $return_data['total'] += $return_data['appointments'];
            }
            
            return $return_data;
        } catch (Exception $e) {
            return [
                'concerns' => 0,
                'applications' => 0,
                'residents' => 0,
                'total' => 0
            ];
        }
    }
}
?>
