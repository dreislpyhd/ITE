<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Flowchart - Barangay 172</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js"></script>
    <style>
        .mermaid { text-align: center; }
        .flowchart-container { 
            background: white; 
            border-radius: 8px; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .system-overview {
            background: linear-gradient(135deg, #ff8829 0%, #ff6b35 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .module-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            transition: all 0.3s ease;
        }
        .module-card:hover {
            border-color: #ff8829;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 136, 41, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="system-overview">
            <h1 class="text-4xl font-bold mb-4">Barangay 172 Urduja Management System</h1>
            <p class="text-xl opacity-90">System Architecture & Flow Diagram</p>
            <p class="mt-2 opacity-80">Comprehensive overview of system modules, user flows, and database relationships</p>
        </div>

        <!-- Navigation -->
        <nav class="mb-8">
            <div class="flex flex-wrap gap-2">
                <button onclick="showDiagram('main-flow')" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">Main Flow</button>
                <button onclick="showDiagram('user-flow')" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition">User Flow</button>
                <button onclick="showDiagram('database')" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 transition">Database Schema</button>
                <button onclick="showDiagram('modules')" class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 transition">System Modules</button>
                <button onclick="showDiagram('process')" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">Process Flow</button>
            </div>
        </nav>

        <!-- Main System Flow -->
        <div id="main-flow" class="flowchart-container">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">Main System Flow</h2>
            <div class="mermaid">
                graph TD
                    A[User Access] --> B{User Type?}
                    B -->|Resident| C[Resident Portal]
                    B -->|Admin| D[Admin Portal]
                    B -->|Staff| E[Staff Portal]
                    
                    C --> F[Resident Dashboard]
                    D --> G[Admin Dashboard]
                    E --> H[Staff Dashboard]
                    
                    F --> I[View Services]
                    F --> J[Submit Applications]
                    F --> K[View Appointments]
                    F --> L[Submit Concerns]
                    
                    G --> M[User Management]
                    G --> N[Reports & Analytics]
                    G --> O[System Settings]
                    G --> P[Application Processing]
                    
                    H --> Q[Process Applications]
                    H --> R[Manage Records]
                    H --> S[Health Services]
                    
                    I --> T[Barangay Services]
                    I --> U[Health Services]
                    
                    J --> V[Application Forms]
                    V --> W[Document Upload]
                    W --> X[Submit Application]
                    X --> Y[Admin Review]
                    
                    K --> Z[Schedule Appointment]
                    Z --> AA[Health Center]
                    
                    L --> BB[Community Concerns]
                    BB --> CC[Admin Response]
                    
                    M --> DD[User Accounts]
                    M --> EE[Role Management]
                    
                    N --> FF[Statistical Reports]
                    N --> GG[Export Data]
                    
                    O --> HH[System Configuration]
                    O --> II[Email Settings]
                    
                    P --> JJ[Application Approval]
                    P --> KK[Document Verification]
                    
                    Q --> LL[Process Requests]
                    Q --> MM[Update Status]
                    
                    R --> NN[Case Records]
                    R --> OO[Document Management]
                    
                    S --> PP[Medical Records]
                    S --> QQ[Health Programs]
                    
                    style A fill:#ff8829,stroke:#333,stroke-width:2px,color:#fff
                    style B fill:#e5e7eb,stroke:#333,stroke-width:2px
                    style C fill:#3b82f6,stroke:#333,stroke-width:2px,color:#fff
                    style D fill:#10b981,stroke:#333,stroke-width:2px,color:#fff
                    style E fill:#8b5cf6,stroke:#333,stroke-width:2px,color:#fff
            </div>
        </div>

        <!-- User Flow -->
        <div id="user-flow" class="flowchart-container" style="display: none;">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">User Authentication & Flow</h2>
            <div class="mermaid">
                graph TD
                    A[Landing Page] --> B[Login/Register]
                    B --> C{Action?}
                    C -->|Login| D[Login Form]
                    C -->|Register| E[Registration Form]
                    
                    D --> F[Validate Credentials]
                    E --> G[Create Account]
                    
                    F --> H{Valid?}
                    G --> I[Email Verification]
                    
                    H -->|Yes| J[Check Role]
                    H -->|No| K[Show Error]
                    
                    I --> L[Account Activated]
                    K --> D
                    
                    J --> M{User Role?}
                    L --> N[Login Redirect]
                    
                    M -->|Admin| O[Admin Dashboard]
                    M -->|Resident| P[Resident Dashboard]
                    M -->|Staff| Q[Staff Dashboard]
                    
                    N --> M
                    
                    O --> R[Admin Features]
                    P --> S[Resident Features]
                    Q --> T[Staff Features]
                    
                    R --> U[User Management]
                    R --> V[System Reports]
                    R --> W[Settings]
                    
                    S --> X[Services]
                    S --> Y[Applications]
                    S --> Z[Appointments]
                    
                    T --> AA[Process Applications]
                    T --> BB[Manage Records]
                    T --> CC[Health Services]
                    
                    style A fill:#ff8829,stroke:#333,stroke-width:2px,color:#fff
                    style B fill:#3b82f6,stroke:#333,stroke-width:2px,color:#fff
                    style C fill:#e5e7eb,stroke:#333,stroke-width:2px
                    style D fill:#10b981,stroke:#333,stroke-width:2px,color:#fff
                    style E fill:#8b5cf6,stroke:#333,stroke-width:2px,color:#fff
                    style O fill:#ef4444,stroke:#333,stroke-width:2px,color:#fff
                    style P fill:#06b6d4,stroke:#333,stroke-width:2px,color:#fff
                    style Q fill:#f59e0b,stroke:#333,stroke-width:2px,color:#fff
            </div>
        </div>

        <!-- Database Schema -->
        <div id="database" class="flowchart-container" style="display: none;">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">Database Schema & Relationships</h2>
            <div class="mermaid">
                erDiagram
                    USERS {
                        int id PK
                        string username
                        string email
                        string password
                        string role
                        datetime created_at
                        datetime updated_at
                    }
                    
                    PROFILES {
                        int id PK
                        int user_id FK
                        string first_name
                        string last_name
                        string phone
                        string address
                        string barangay
                        string city
                        string province
                        string postal_code
                        date birth_date
                        string gender
                        string civil_status
                        string occupation
                        string emergency_contact
                        string emergency_phone
                    }
                    
                    APPLICATIONS {
                        int id PK
                        int user_id FK
                        string application_type
                        string status
                        text description
                        string documents
                        datetime submitted_at
                        datetime processed_at
                        string admin_notes
                    }
                    
                    APPOINTMENTS {
                        int id PK
                        int user_id FK
                        string service_type
                        datetime appointment_date
                        string status
                        text notes
                        datetime created_at
                    }
                    
                    CONCERNS {
                        int id PK
                        int user_id FK
                        string concern_type
                        string priority
                        string status
                        text description
                        string location
                        datetime reported_at
                        datetime resolved_at
                        text admin_response
                    }
                    
                    CASE_RECORDS {
                        int id PK
                        int user_id FK
                        string case_number
                        string case_type
                        string status
                        text description
                        string documents
                        datetime filed_at
                        datetime resolved_at
                        text resolution
                    }
                    
                    ADMIN_MESSAGES {
                        int id PK
                        int user_id FK
                        string subject
                        text message
                        string type
                        string status
                        datetime sent_at
                        datetime read_at
                    }
                    
                    USERS ||--|| PROFILES : "has"
                    USERS ||--o{ APPLICATIONS : "submits"
                    USERS ||--o{ APPOINTMENTS : "books"
                    USERS ||--o{ CONCERNS : "reports"
                    USERS ||--o{ CASE_RECORDS : "files"
                    USERS ||--o{ ADMIN_MESSAGES : "receives"
            </div>
        </div>

        <!-- System Modules -->
        <div id="modules" class="flowchart-container" style="display: none;">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">System Modules Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-blue-600 mb-2">Authentication Module</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• User Registration</li>
                        <li>• Login/Logout</li>
                        <li>• Password Reset</li>
                        <li>• Role Management</li>
                        <li>• Session Management</li>
                    </ul>
                </div>
                
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-green-600 mb-2">Resident Portal</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Service Requests</li>
                        <li>• Application Submission</li>
                        <li>• Appointment Booking</li>
                        <li>• Concern Reporting</li>
                        <li>• Profile Management</li>
                    </ul>
                </div>
                
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-purple-600 mb-2">Admin Portal</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• User Management</li>
                        <li>• Application Processing</li>
                        <li>• System Reports</li>
                        <li>• Configuration</li>
                        <li>• Data Export</li>
                    </ul>
                </div>
                
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-orange-600 mb-2">Barangay Hall</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Business Permits</li>
                        <li>• Barangay Clearance</li>
                        <li>• Community Programs</li>
                        <li>• Official Records</li>
                        <li>• Staff Management</li>
                    </ul>
                </div>
                
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-red-600 mb-2">Health Center</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Medical Consultations</li>
                        <li>• Health Records</li>
                        <li>• Vaccination Programs</li>
                        <li>• Health Education</li>
                        <li>• Appointment Management</li>
                    </ul>
                </div>
                
                <div class="module-card">
                    <h3 class="text-lg font-semibold text-indigo-600 mb-2">Communication</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Email Notifications</li>
                        <li>• Admin Messages</li>
                        <li>• Status Updates</li>
                        <li>• Announcements</li>
                        <li>• Feedback System</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Process Flow -->
        <div id="process" class="flowchart-container" style="display: none;">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">Application Processing Flow</h2>
            <div class="mermaid">
                graph LR
                    A[Resident Submits Application] --> B[System Receives Application]
                    B --> C[Email Notification Sent]
                    C --> D[Admin Reviews Application]
                    D --> E{Application Valid?}
                    
                    E -->|Yes| F[Process Application]
                    E -->|No| G[Request Additional Documents]
                    
                    G --> H[Resident Updates Application]
                    H --> D
                    
                    F --> I[Verify Documents]
                    I --> J{All Documents Complete?}
                    
                    J -->|Yes| K[Approve Application]
                    J -->|No| L[Request Missing Documents]
                    
                    L --> M[Resident Submits Missing Docs]
                    M --> I
                    
                    K --> N[Generate Certificate/Permit]
                    N --> O[Update Application Status]
                    O --> P[Send Approval Email]
                    P --> Q[Application Complete]
                    
                    style A fill:#ff8829,stroke:#333,stroke-width:2px,color:#fff
                    style B fill:#3b82f6,stroke:#333,stroke-width:2px,color:#fff
                    style D fill:#10b981,stroke:#333,stroke-width:2px,color:#fff
                    style E fill:#e5e7eb,stroke:#333,stroke-width:2px
                    style F fill:#8b5cf6,stroke:#333,stroke-width:2px,color:#fff
                    style K fill:#06b6d4,stroke:#333,stroke-width:2px,color:#fff
                    style Q fill:#f59e0b,stroke:#333,stroke-width:2px,color:#fff
            </div>
        </div>

        <!-- System Information -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-4 text-gray-800">System Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-700">Technical Stack</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li><strong>Backend:</strong> PHP 8.2</li>
                        <li><strong>Database:</strong> MySQL/MariaDB</li>
                        <li><strong>Frontend:</strong> HTML5, CSS3, JavaScript</li>
                        <li><strong>Framework:</strong> Tailwind CSS</li>
                        <li><strong>Email:</strong> PHPMailer</li>
                        <li><strong>Charts:</strong> Mermaid.js</li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-700">System Features</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li><strong>User Roles:</strong> Admin, Resident, Staff</li>
                        <li><strong>Modules:</strong> 6 Core Modules</li>
                        <li><strong>Services:</strong> 15+ Services</li>
                        <li><strong>Security:</strong> Password Hashing, Session Management</li>
                        <li><strong>Notifications:</strong> Email & In-App Messages</li>
                        <li><strong>File Upload:</strong> Document Management</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Mermaid
        mermaid.initialize({
            startOnLoad: true,
            theme: 'default',
            flowchart: {
                useMaxWidth: true,
                htmlLabels: true,
                curve: 'basis'
            }
        });

        // Show/Hide diagrams
        function showDiagram(diagramId) {
            // Hide all diagrams
            const diagrams = ['main-flow', 'user-flow', 'database', 'modules', 'process'];
            diagrams.forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
            
            // Show selected diagram
            document.getElementById(diagramId).style.display = 'block';
            
            // Re-render mermaid diagrams
            if (diagramId !== 'modules') {
                mermaid.init();
            }
        }

        // Initialize with main flow
        showDiagram('main-flow');
    </script>
</body>
</html>
