#!/usr/bin/env python3
"""
Script to update barangay-hall pages to use the standardized logout modal.
This replaces custom logout modal implementations with the reusable component.
"""

import re
import os

def update_logout_modal(file_path):
    """Update a single file to use the standardized logout modal."""
    print(f"Updating {file_path}...")
    
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Pattern to match the entire custom logout modal section
    modal_pattern = r'    <!-- Logout Confirmation Modal -->.*?</script>'
    
    # Replacement with standardized component
    replacement = '''    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 1024) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.add('-translate-x-full');
                    }
                }
            });
        }
    </script>

    <?php include '../includes/logout_modal.php'; ?>'''
    
    # Apply the replacement
    updated_content = re.sub(modal_pattern, replacement, content, flags=re.DOTALL)
    
    # Write back the updated content
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(updated_content)
    
    print(f"✅ Updated {file_path}")

def main():
    """Update all barangay-hall files."""
    files_to_update = [
        'barangay-hall/applications.php',
        'barangay-hall/barangay-staff.php', 
        'barangay-hall/community-concerns.php',
        'barangay-hall/reports.php',
        'barangay-hall/services.php'
    ]
    
    for file_path in files_to_update:
        if os.path.exists(file_path):
            update_logout_modal(file_path)
        else:
            print(f"❌ File not found: {file_path}")

if __name__ == '__main__':
    main()
