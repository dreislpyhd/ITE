<?php
/**
 * Deadline Widget for Residents
 * Shows countdown to document upload deadline
 * Include this in residents/index.php or other resident pages
 */

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    return; // Don't show widget if not a resident
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get user's document status and registration date
    $stmt = $conn->prepare("
        SELECT created_at, purok_endorsement, valid_id, account_verified 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return;
    }
    
    $registration_date = strtotime($user['created_at']);
    $current_time = time();
    $days_since_registration = ($current_time - $registration_date) / (24 * 3600);
    $days_remaining = 30 - $days_since_registration;
    
    // Check if documents are uploaded
    $has_purok_endorsement = !empty($user['purok_endorsement']);
    $has_valid_id = !empty($user['valid_id']);
    $documents_complete = $has_purok_endorsement && $has_valid_id;
    
    // Determine status
    $status = 'safe';
    $status_text = '';
    $status_color = '';
    
    if ($user['account_verified']) {
        $status = 'verified';
        $status_text = 'Account Verified';
        $status_color = 'text-green-600 bg-green-100';
    } elseif ($documents_complete) {
        $status = 'pending_verification';
        $status_text = 'Documents Uploaded - Pending Verification';
        $status_color = 'text-blue-600 bg-blue-100';
    } elseif ($days_remaining <= 0) {
        $status = 'expired';
        $status_text = 'Deadline Expired - Account at Risk';
        $status_color = 'text-red-600 bg-red-100';
    } elseif ($days_remaining <= 5) {
        $status = 'critical';
        $status_text = 'Critical: ' . round($days_remaining) . ' days remaining';
        $status_color = 'text-red-600 bg-red-100';
    } elseif ($days_remaining <= 10) {
        $status = 'warning';
        $status_text = 'Warning: ' . round($days_remaining) . ' days remaining';
        $status_color = 'text-orange-600 bg-orange-100';
    } else {
        $status = 'safe';
        $status_text = round($days_remaining) . ' days remaining';
        $status_color = 'text-green-600 bg-green-100';
    }
    
    // Get warning messages
    $stmt = $conn->prepare("
        SELECT message, created_at 
        FROM admin_messages 
        WHERE user_id = ? AND message_type = 'warning' 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $warnings = $stmt->fetchAll();
    
} catch (Exception $e) {
    return; // Don't show widget if there's an error
}
?>

<!-- Deadline Widget -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 font-eb-garamond">Document Upload Deadline</h3>
        <div class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_color; ?>">
            <?php echo htmlspecialchars($status_text); ?>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="mb-4">
        <div class="flex justify-between text-sm text-gray-600 mb-2">
            <span>Registration Date</span>
            <span>30-Day Deadline</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <?php 
            $progress_percentage = min(100, max(0, ($days_since_registration / 30) * 100));
            $progress_color = $status === 'expired' ? 'bg-red-500' : 
                            ($status === 'critical' ? 'bg-red-400' : 
                            ($status === 'warning' ? 'bg-orange-400' : 'bg-green-500'));
            ?>
            <div class="h-2 rounded-full <?php echo $progress_color; ?> transition-all duration-300" 
                 style="width: <?php echo $progress_percentage; ?>%"></div>
        </div>
        <div class="text-xs text-gray-500 mt-1">
            <?php echo date('M j, Y', $registration_date); ?> → <?php echo date('M j, Y', $registration_date + (30 * 24 * 3600)); ?>
        </div>
    </div>
    
    <!-- Document Status -->
    <div class="grid grid-cols-2 gap-4 mb-4">
        <div class="text-center">
            <div class="text-2xl font-bold <?php echo $has_purok_endorsement ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo $has_purok_endorsement ? '✓' : '✗'; ?>
            </div>
            <div class="text-sm text-gray-600">Purok Endorsement</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold <?php echo $has_valid_id ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo $has_valid_id ? '✓' : '✗'; ?>
            </div>
            <div class="text-sm text-gray-600">Valid ID/Proof of Billing</div>
        </div>
    </div>
    
    <!-- Action Required -->
    <?php if (!$documents_complete && $days_remaining > 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800 font-eb-garamond">Action Required</h4>
                    <p class="text-sm text-yellow-700">
                        You have <strong><?php echo round($days_remaining); ?> day(s)</strong> to upload the required documents. 
                        <a href="profile.php" class="underline font-medium">Upload now</a>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Warning Messages -->
    <?php if (!empty($warnings)): ?>
        <div class="border-t pt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2 font-eb-garamond">Recent Warnings</h4>
            <div class="space-y-2">
                <?php foreach ($warnings as $warning): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="text-sm text-red-800">
                            <?php echo nl2br(htmlspecialchars($warning['message'])); ?>
                        </div>
                        <div class="text-xs text-red-600 mt-1">
                            <?php echo date('M j, Y g:i A', strtotime($warning['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Important Notice -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-4">
        <div class="text-xs text-gray-600">
            <strong>Important:</strong> Accounts without uploaded documents within 30 days will be automatically deleted. 
            Please ensure all required documents are uploaded before the deadline.
        </div>
    </div>
</div>
