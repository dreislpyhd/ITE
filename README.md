# Barangay 172 Urduja Management System

A comprehensive management system for Brgy. 172 Urduja, Caloocan City, featuring subsystems for both Barangay Hall and Health Center operations.

## Features

### 🏛️ Barangay Hall Subsystem
- Business Permits & Licenses
- Barangay Clearance
- Community Programs
- Official Records Management
- Application Processing

### 🏥 Health Center Subsystem
- Medical Consultations
- Health Records
- Vaccination Programs
- Health Education
- Patient Management

### 👥 User Management
- Multi-role system (Admin, Staff, Residents)
- Secure authentication
- User registration and login
- Role-based access control

## Technology Stack

- **Frontend**: HTML5, Tailwind CSS, JavaScript
- **Backend**: PHP 7.4+
- **Database**: SQLite
- **Font**: Poppins
- **Colors**: Orange (#FF6B35) and Green (#2E8B57)

## Installation & Setup

### Prerequisites
- PHP 7.4 or higher
- Web server (Apache/Nginx) or PHP built-in server
- SQLite extension enabled in PHP

### Quick Start

1. **Clone or download the project**
   ```bash
   # If using git
   git clone [repository-url]
   cd barangay-management
   ```

2. **Start PHP built-in server**
   ```bash
   php -S localhost:8000
   ```

3. **Access the system**
   - Open your browser and go to `http://localhost:8000`
   - The system will automatically create the SQLite database and tables

### Default Admin Account
- **Username**: `admin`
- **Password**: `admin123`
- **Role**: System Administrator

⚠️ **Important**: Change the default admin password after first login!

## Project Structure

```
barangay-management/
├── index.html              # Public landing page
├── auth/                   # Authentication files
│   ├── login.php          # User login
│   └── register.php       # User registration
├── includes/               # PHP includes
│   ├── config.php         # Configuration settings
│   └── database.php       # Database connection & setup
├── admin/                  # Admin dashboard (to be implemented)
├── barangay-hall/          # Barangay Hall subsystem (to be implemented)
├── health-center/          # Health Center subsystem (to be implemented)
├── assets/                 # Static assets
│   ├── css/               # Custom CSS files
│   └── js/                # JavaScript files
└── README.md               # This file
```

## Database Schema

The system automatically creates the following tables:

- **users** - User accounts and authentication
- **barangay_services** - Available barangay services
- **health_services** - Available health services
- **applications** - Service applications
- **residents** - Resident information
- **health_records** - Medical records

## Usage

### For Residents
1. Register an account on the public page
2. Login to access services
3. Apply for barangay services
4. Schedule health appointments

### For Staff
1. Login with staff credentials
2. Process applications
3. Manage resident records
4. Update service information

### For Administrators
1. Login with admin credentials
2. Manage all users and roles
3. Configure system settings
4. Generate reports and analytics

## Security Features

- Password hashing using PHP's built-in `password_hash()`
- Prepared statements to prevent SQL injection
- Session management with timeout
- Input validation and sanitization
- Role-based access control

## Customization

### Colors
The system uses custom Tailwind CSS colors:
- `barangay-orange`: #FF6B35
- `barangay-green`: #2E8B57

### Font
Poppins font family is used throughout the system.

### Adding New Services
1. Add service details to the database
2. Update the relevant subsystem interface
3. Modify application processing logic

## Development

### Adding New Features
1. Create the necessary database tables
2. Implement backend logic in PHP
3. Create frontend interface with HTML/Tailwind
4. Add JavaScript functionality as needed

### File Naming Convention
- Use lowercase with underscores for PHP files
- Use kebab-case for HTML/CSS files
- Use camelCase for JavaScript functions

## Troubleshooting

### Common Issues

1. **Database connection error**
   - Ensure SQLite extension is enabled in PHP
   - Check file permissions for database creation

2. **Login not working**
   - Verify database tables are created
   - Check if admin user exists in database

3. **Page not loading**
   - Ensure PHP server is running
   - Check file paths and permissions

### Debug Mode
Set `error_reporting(E_ALL)` and `ini_set('display_errors', 1)` in `config.php` for development.

## Contributing

1. Follow the existing code structure
2. Use consistent formatting and naming conventions
3. Test thoroughly before submitting changes
4. Update documentation as needed

## License

This project is developed for Barangay 172 Urduja, Caloocan City.

## Support

For technical support or questions, contact the development team.

---

**Barangay 172 Urduja Management System** - Streamlining community services for a better barangay.
