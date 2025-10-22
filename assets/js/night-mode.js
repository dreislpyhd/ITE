// Night Mode Toggle Functionality
class NightMode {
    constructor() {
        this.isNightMode = localStorage.getItem('nightMode') === 'true';
        this.init();
    }

    init() {
        this.applyTheme();
        this.createToggleButton();
        this.bindEvents();
    }

    applyTheme() {
        const body = document.body;
        const html = document.documentElement;
        
        if (this.isNightMode) {
            body.classList.add('night-mode');
            html.classList.add('night-mode');
        } else {
            body.classList.remove('night-mode');
            html.classList.remove('night-mode');
        }
    }

    createToggleButton() {
        // Check if toggle button already exists
        if (document.getElementById('night-mode-toggle')) {
            return;
        }

        // Try to find the navigation container for admin pages first
        let nav = document.querySelector('nav .flex.items-center.space-x-4');
        
        // If not found, try to find the admin header structure
        if (!nav) {
            nav = document.querySelector('nav .flex.justify-between .flex.items-center.space-x-4');
        }
        
        // If still not found, try to find any flex container with items-center
        if (!nav) {
            nav = document.querySelector('nav .flex.items-center');
        }

        if (!nav) return;

        const toggleButton = document.createElement('button');
        toggleButton.id = 'night-mode-toggle';
        toggleButton.className = 'flex items-center justify-center bg-white hover:bg-gray-100 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-white p-2 rounded-lg font-medium transition duration-300 ml-2';
        toggleButton.innerHTML = `
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        `;

        // Insert before the logout button if it exists
        const logoutButton = nav.querySelector('button[onclick*="logout"]');
        if (logoutButton) {
            nav.insertBefore(toggleButton, logoutButton);
        } else {
            nav.appendChild(toggleButton);
        }
    }

    bindEvents() {
        const toggleButton = document.getElementById('night-mode-toggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', () => this.toggle());
        }
    }

    toggle() {
        this.isNightMode = !this.isNightMode;
        localStorage.setItem('nightMode', this.isNightMode);
        this.applyTheme();
        this.updateToggleButton();
    }

    updateToggleButton() {
        // Icon-only toggle, no text to update
        // The icon will change based on the theme state
    }
}

// Initialize night mode when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new NightMode();
});

// Export for potential external use
window.NightMode = NightMode;
