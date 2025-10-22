<?php
/**
 * Reusable Logout Confirmation Modal Component
 * 
 * This component provides a consistent logout confirmation modal
 * across the entire system.
 * 
 * Usage:
 * 1. Include this file: <?php include '../includes/logout_modal.php'; ?>
 * 2. Add the logout button with: onclick="showLogoutModal()"
 * 3. The modal and JavaScript functions are automatically included
 */
?>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white font-eb-garamond">Confirm Logout</h3>
                    </div>
                </div>
                <div class="mb-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Are you sure you want to logout? You will need to login again to access the system.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button onclick="hideLogoutModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <a href="<?php 
                        // Determine the correct logout path based on current directory
                        $current_path = $_SERVER['REQUEST_URI'];
                        if (strpos($current_path, '/admin/') !== false) {
                            echo '../auth/logout.php';
                        } elseif (strpos($current_path, '/barangay-hall/') !== false || 
                                  strpos($current_path, '/health-center/') !== false || 
                                  strpos($current_path, '/residents/') !== false) {
                            echo '../auth/logout.php';
                        } else {
                            echo 'auth/logout.php';
                        }
                    ?>" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Logout Modal Functions
function showLogoutModal() {
    document.getElementById('logoutModal').classList.remove('hidden');
}

function hideLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const logoutModal = document.getElementById('logoutModal');
    if (logoutModal) {
        logoutModal.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
                hideLogoutModal();
            }
        });
    }
});
</script>
