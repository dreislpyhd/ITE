-- SQLite Database Dump
-- Generated: 2025-10-23 14:32:44


-- Table: activity_logs
CREATE TABLE activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action_type TEXT NOT NULL,
            action_description TEXT NOT NULL,
            target_type TEXT NOT NULL,
            target_id INTEGER,
            target_name TEXT,
            performed_by INTEGER NOT NULL,
            performed_by_name TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (performed_by) REFERENCES users(id)
        );

INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('1', 'patient_rejected', 'Rejected patient registration for Rebecca S Billaro', 'patient_registration', '18', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:00:41');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('2', 'patient_approved', 'Approved patient registration for ', 'patient_registration', '19', NULL, '16', 'Kei Satoru', '2025-10-23 11:01:14');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('3', 'appointment_cancelled', 'Cancelled appointment for Rebecca S Billaro - medical_consultation', 'appointment', '15', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:01:39');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('4', 'appointment_confirmed', 'Confirmed appointment for Rebecca S Billaro - medical_consultation', 'appointment', '17', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:04:44');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('5', 'appointment_confirmed', 'Confirmed appointment for Rebecca S Billaro - medical_consultation', 'appointment', '19', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:14:33');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('6', 'appointment_confirmed', 'Confirmed appointment for Rebecca S Billaro - medical_consultation', 'appointment', '18', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:14:41');
INSERT INTO activity_logs (id, action_type, action_description, target_type, target_id, target_name, performed_by, performed_by_name, created_at) VALUES ('7', 'appointment_confirmed', 'Confirmed appointment for Rebecca S Billaro - medical_consultation', 'appointment', '20', 'Rebecca S Billaro', '16', 'Kei Satoru', '2025-10-23 11:18:39');


-- Table: admin_messages
CREATE TABLE admin_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                admin_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE CASCADE
            );


-- Table: application_notifications
CREATE TABLE application_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                application_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE
            );

INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('1', '1', '6', 'New application for Business Permit from Nagumo  Yoichi', '0', '2025-10-22 18:04:13');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('2', '7', '6', 'New application for Business Permit from Nagumo  Yoichi', '0', '2025-10-22 18:04:13');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('3', '8', '6', 'New application for Business Permit from Nagumo  Yoichi', '0', '2025-10-22 18:04:13');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('4', '15', '6', 'New application for Business Permit from Nagumo  Yoichi', '1', '2025-10-22 18:04:13');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('5', '17', '6', 'New application for Business Permit from Nagumo  Yoichi', '0', '2025-10-22 18:04:13');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('6', '1', '7', 'New application for Barangay Clearance from Rebecca S Billaro', '0', '2025-10-23 19:25:15');
INSERT INTO application_notifications (id, user_id, application_id, message, is_read, created_at) VALUES ('7', '1', '8', 'New application for Barangay Clearance from Ej B Velasquez', '0', '2025-10-23 19:26:22');


-- Table: applications
CREATE TABLE applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                service_type TEXT NOT NULL,
                service_id INTEGER NOT NULL,
                status TEXT DEFAULT 'pending',
                application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                processed_date DATETIME,
                processed_by INTEGER,
                notes TEXT, purpose TEXT, requirements_files TEXT, remarks TEXT,
                FOREIGN KEY (user_id) REFERENCES users (id),
                FOREIGN KEY (processed_by) REFERENCES users (id)
            );

INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('1', '19', 'barangay', '1', 'approved', '2025-10-22 09:32:20', NULL, NULL, NULL, 'Bank Transaction', '["68f8a4a4ead3a_19.jpg","68f8a4a4eb327_19.jpg","68f8a4a4eb9da_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('2', '19', 'barangay', '2', 'approved', '2025-10-22 17:49:24', NULL, NULL, NULL, 'Commercial Activities', '["68f8a8a49cb97_19.jpg","68f8a8a49d233_19.jpg","68f8a8a49d9af_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('3', '19', 'barangay', '4', 'approved', '2025-10-22 17:54:12', NULL, NULL, NULL, 'Financial Assistance', '["68f8a9c41c5c5_19.jpg","68f8a9c41cfa3_19.jpg","68f8a9c41d61d_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('4', '19', 'barangay', '3', 'approved', '2025-10-22 17:54:52', NULL, NULL, NULL, 'Government Transaction', '["68f8a9ec0bc98_19.jpg","68f8a9ec0c1ad_19.jpg","68f8a9ec0c7b7_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('5', '19', 'barangay', '1', 'approved', '2025-10-22 18:00:05', NULL, NULL, NULL, 'Employment', '["68f8ab25394e3_19.jpg","68f8ab2539943_19.jpg","68f8ab2539d7c_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('6', '19', 'barangay', '2', 'approved', '2025-10-22 18:04:13', NULL, NULL, NULL, 'Community-Oriented Activities', '["68f8ac1d5a8ff_19.jpg","68f8ac1d5adca_19.jpg","68f8ac1d5c37b_19.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('7', '29', 'barangay', '1', 'pending', '2025-10-23 19:25:15', NULL, NULL, NULL, 'Community Events', '["68fa109b1fd0b_29.jpg","68fa109b20356_29.jpg"]', NULL);
INSERT INTO applications (id, user_id, service_type, service_id, status, application_date, processed_date, processed_by, notes, purpose, requirements_files, remarks) VALUES ('8', '28', 'barangay', '1', 'approved', '2025-10-23 19:26:22', NULL, NULL, NULL, 'Financial Assistance', '["68fa10de2b321_28.jpg","68fa10de2bcdf_28.jpg"]', NULL);


-- Table: appointments
CREATE TABLE appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                service_type VARCHAR(100),
                appointment_date TIMESTAMP,
                status VARCHAR(20) DEFAULT 'scheduled',
                notes TEXT,
                confirmed_by INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL
            );

INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('1', '19', 'medical_consultation', '2025-10-25 13:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-22 10:09:17', '2025-10-22 10:09:36');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('2', '19', 'medical_consultation', '2025-10-26 14:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-22 10:09:45', '2025-10-22 10:09:59');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('3', '20', 'medical_consultation', '2025-10-23 16:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-22 10:55:38', '2025-10-22 10:55:55');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('4', '20', 'medical_consultation', '2025-10-23 09:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-22 10:56:06', '2025-10-22 11:03:41');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('15', '29', 'medical_consultation', '2025-10-24 11:01:30', 'cancelled', 'Online appointment request', NULL, '2025-10-23 11:01:30', '2025-10-23 11:01:39');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('17', '29', 'medical_consultation', '2025-10-24 09:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-23 11:04:40', '2025-10-23 11:04:44');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('18', '29', 'medical_consultation', '2025-10-24 11:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-23 11:12:54', '2025-10-23 11:14:41');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('19', '29', 'medical_consultation', '2025-10-24 10:00:00', 'confirmed', 'Online appointment request', '16', '2025-10-23 11:14:20', '2025-10-23 11:14:33');
INSERT INTO appointments (id, user_id, service_type, appointment_date, status, notes, confirmed_by, created_at, updated_at) VALUES ('20', '29', 'medical_consultation', '2025-10-24 10:30:00', 'confirmed', 'Online appointment request', '16', '2025-10-23 11:18:25', '2025-10-23 11:18:39');


-- Table: barangay_services
CREATE TABLE barangay_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_name TEXT NOT NULL,
                description TEXT,
                requirements TEXT,
                fee DECIMAL(10,2) DEFAULT 0.00,
                processing_time TEXT,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

INSERT INTO barangay_services (id, service_name, description, requirements, fee, processing_time, status, created_at) VALUES ('1', 'Barangay Clearance', 'Official clearance for various purposes', 'Valid ID, Proof of Residency', '50', '3-5 working days', 'active', '2025-08-27 00:34:51');
INSERT INTO barangay_services (id, service_name, description, requirements, fee, processing_time, status, created_at) VALUES ('3', 'Indigency Certificate', 'Certificate for indigent residents', 'Valid ID, Proof of Income', '25', '2-3 working days', 'active', '2025-08-27 00:34:51');
INSERT INTO barangay_services (id, service_name, description, requirements, fee, processing_time, status, created_at) VALUES ('4', 'Community Program Registration', 'Registration for barangay programs', 'Valid ID, Proof of Residency', '0', '1-2 working days', 'active', '2025-08-27 00:34:51');
INSERT INTO barangay_services (id, service_name, description, requirements, fee, processing_time, status, created_at) VALUES ('6', 'Barangay Permit', 'Permit for various activities and events in the barangay', 'Valid ID, Proof of Residency', '0', NULL, 'active', '2025-10-22 11:27:46');


-- Table: community_concerns
CREATE TABLE community_concerns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                concern_type VARCHAR(255) NOT NULL,
                specific_issue VARCHAR(255) NOT NULL,
                location VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                priority_level VARCHAR(20) DEFAULT 'Medium',
                status VARCHAR(20) DEFAULT 'Pending',
                photos TEXT,
                admin_response TEXT,
                admin_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
            );

INSERT INTO community_concerns (id, user_id, concern_type, specific_issue, location, description, priority_level, status, photos, admin_response, admin_id, created_at, updated_at) VALUES ('1', '19', 'Infrastructure Concerns', 'Damaged roads / sidewalks', 'Sampaloc, Bagumbong', 'OCA MUKHANG BURAT', 'Low', 'Resolved', '["68f8aaba09f97_1761127098.jpg"]', 'OKAY NA PUTANGINAMO TEAM ORENS', '15', '2025-10-22 09:58:18', '2025-10-22 09:59:11');


-- Table: deleted_appointments
CREATE TABLE deleted_appointments (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            user_id INTEGER NOT NULL,
                            service_type TEXT NOT NULL,
                            appointment_date TEXT NOT NULL,
                            notes TEXT,
                            status TEXT DEFAULT 'scheduled',
                            created_at TEXT DEFAULT (datetime('now')),
                            deleted_at TEXT DEFAULT (datetime('now')),
                            deleted_by INTEGER
                        );

INSERT INTO deleted_appointments (id, user_id, service_type, appointment_date, notes, status, created_at, deleted_at, deleted_by) VALUES ('2', '29', 'medical_consultation', '2025-10-24 09:00:00', 'Online appointment request', 'confirmed', '2025-10-23 10:45:19', '2025-10-23 10:53:39', '16');
INSERT INTO deleted_appointments (id, user_id, service_type, appointment_date, notes, status, created_at, deleted_at, deleted_by) VALUES ('3', '29', 'medical_consultation', '2025-10-24 10:53:51', 'Online appointment request', 'cancelled', '2025-10-23 10:53:51', '2025-10-23 10:56:02', '16');
INSERT INTO deleted_appointments (id, user_id, service_type, appointment_date, notes, status, created_at, deleted_at, deleted_by) VALUES ('4', '29', 'medical_consultation', '2025-10-24 09:00:00', 'Online appointment request', 'confirmed', '2025-10-23 10:48:06', '2025-10-23 10:56:05', '16');
INSERT INTO deleted_appointments (id, user_id, service_type, appointment_date, notes, status, created_at, deleted_at, deleted_by) VALUES ('5', '29', 'medical_consultation', '2025-10-24 09:00:00', 'Online appointment request', 'confirmed', '2025-10-23 11:01:55', '2025-10-23 11:05:03', '16');


-- Table: deleted_patients
CREATE TABLE deleted_patients (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            user_id INTEGER NOT NULL,
                            blood_type TEXT,
                            emergency_contact TEXT,
                            medical_history TEXT,
                            current_medications TEXT,
                            insurance_info TEXT,
                            status TEXT DEFAULT 'pending',
                            created_at TEXT,
                            deleted_at TEXT DEFAULT (datetime('now')),
                            deleted_by INTEGER
                        );

INSERT INTO deleted_patients (id, user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, created_at, deleted_at, deleted_by) VALUES ('2', '29', 'A+', '09814639929', 'asthma', 'nebulizer', 'Philhealth', 'approved', '2025-10-23 10:44:24', '2025-10-23 10:56:09', '16');


-- Table: health_records
CREATE TABLE health_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                resident_id INTEGER NOT NULL,
                consultation_date DATE NOT NULL,
                symptoms TEXT,
                diagnosis TEXT,
                treatment TEXT,
                prescription TEXT,
                doctor_name TEXT,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (resident_id) REFERENCES residents (id)
            );


-- Table: health_services
CREATE TABLE health_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_name TEXT NOT NULL,
                description TEXT,
                requirements TEXT,
                fee DECIMAL(10,2) DEFAULT 0.00,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

INSERT INTO health_services (id, service_name, description, requirements, fee, status, created_at) VALUES ('1', 'Medical Consultation', 'General medical consultation', 'Valid ID, Medical History', '0', 'active', '2025-08-27 00:34:51');
INSERT INTO health_services (id, service_name, description, requirements, fee, status, created_at) VALUES ('2', 'Vaccination', 'Various vaccination services', 'Valid ID, Vaccination Card', '0', 'active', '2025-08-27 00:34:51');
INSERT INTO health_services (id, service_name, description, requirements, fee, status, created_at) VALUES ('3', 'Health Education', 'Health awareness programs', 'Valid ID', '0', 'active', '2025-08-27 00:34:51');
INSERT INTO health_services (id, service_name, description, requirements, fee, status, created_at) VALUES ('4', 'Prenatal Care', 'Prenatal checkup and care', 'Valid ID, Medical History', '0', 'active', '2025-08-27 00:34:51');


-- Table: medical_records
CREATE TABLE medical_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        appointment_id INTEGER DEFAULT NULL,
        record_date DATE NOT NULL,
        consultation_date DATE DEFAULT NULL,
        symptoms TEXT,
        diagnosis TEXT,
        treatment TEXT,
        prescription TEXT,
        doctor_name VARCHAR(255),
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
    );

INSERT INTO medical_records (id, user_id, appointment_id, record_date, consultation_date, symptoms, diagnosis, treatment, prescription, doctor_name, notes, created_at, updated_at) VALUES ('1', '19', NULL, '2025-10-22', NULL, NULL, 'Fever', 'Paracetamol/Biogesic', NULL, NULL, 'Take 2-3 times a day', '2025-10-22 10:22:10', '2025-10-22 10:22:10');


-- Table: patient_registration_notifications
CREATE TABLE patient_registration_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                registration_id INTEGER,
                status VARCHAR(50),
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (registration_id) REFERENCES patient_registrations (id) ON DELETE CASCADE
            );

INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('1', '19', '1', 'pending', 'New patient registration submitted', '1', '2025-10-22 10:08:46');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('2', '19', '1', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-22 10:09:02');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('3', '20', '2', 'pending', 'New patient registration submitted', '1', '2025-10-22 10:49:37');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('4', '20', '3', 'pending', 'New patient registration submitted', '1', '2025-10-22 10:54:03');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('5', '20', '3', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-22 10:54:22');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('6', '28', '4', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:10:58');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('7', '28', '5', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:11:56');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('8', '28', '6', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:13:58');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('9', '28', '7', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:15:20');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('10', '28', '8', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:17:15');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('11', '28', '9', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:19:41');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('12', '28', '10', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:21:26');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('13', '28', '10', 'rejected', 'Your patient registration has been rejected. Please review the staff notes for more information.', '1', '2025-10-23 09:25:21');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('14', '28', '11', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:34:28');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('15', '28', '11', 'rejected', 'Your patient registration has been rejected. Please review the staff notes for more information.', '1', '2025-10-23 09:35:08');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('16', '28', '12', 'pending', 'New patient registration submitted', '1', '2025-10-23 09:36:41');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('17', '28', '12', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-23 09:36:50');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('18', '29', '13', 'pending', 'New patient registration submitted', '1', '2025-10-23 10:00:46');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('19', '29', '13', 'rejected', 'Your patient registration has been rejected. Please review the staff notes for more information.', '1', '2025-10-23 10:01:08');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('20', '29', '14', 'pending', 'New patient registration submitted', '1', '2025-10-23 10:04:33');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('21', '29', '14', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-23 10:04:58');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('22', '29', '15', 'pending', 'New patient registration submitted', '1', '2025-10-23 10:43:06');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('23', '29', '15', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-23 10:43:15');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('24', '29', '16', 'pending', 'New patient registration submitted', '1', '2025-10-23 10:44:24');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('25', '29', '16', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-23 10:44:32');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('26', '29', '18', 'pending', 'New patient registration submitted', '1', '2025-10-23 11:00:21');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('27', '29', '18', 'rejected', 'Your patient registration has been rejected. Please review the staff notes for more information.', '1', '2025-10-23 11:00:41');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('28', '29', '19', 'pending', 'New patient registration submitted', '1', '2025-10-23 11:01:06');
INSERT INTO patient_registration_notifications (id, user_id, registration_id, status, message, is_read, created_at) VALUES ('29', '29', '19', 'approved', 'Your patient registration has been approved. You can now access health services.', '1', '2025-10-23 11:01:14');


-- Table: patient_registrations
CREATE TABLE patient_registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                blood_type VARCHAR(10),
                emergency_contact TEXT,
                medical_history TEXT,
                current_medications TEXT,
                insurance_info TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                approved_at TIMESTAMP NULL,
                approved_by INTEGER NULL,
                staff_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );

INSERT INTO patient_registrations (id, user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, approved_at, approved_by, staff_notes, created_at, updated_at) VALUES ('1', '19', 'B+', '09814639929', 'may hika', '', 'philhealth na ninakaw ng corrupt', 'approved', '2025-10-22 10:09:02', '16', '', '2025-10-22 10:08:46', '2025-10-22 10:09:02');
INSERT INTO patient_registrations (id, user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, approved_at, approved_by, staff_notes, created_at, updated_at) VALUES ('3', '20', 'O-', '09814639929', 'Asthma', '', 'Philhealth', 'approved', '2025-10-22 10:54:22', '16', '', '2025-10-22 10:54:03', '2025-10-22 10:54:22');
INSERT INTO patient_registrations (id, user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, approved_at, approved_by, staff_notes, created_at, updated_at) VALUES ('18', '29', 'A+', '09814639929', 'asthma', 'nebulizer', 'Philhealth', 'rejected', '2025-10-23 11:00:41', '16', 'ulit', '2025-10-23 11:00:21', '2025-10-23 11:00:41');
INSERT INTO patient_registrations (id, user_id, blood_type, emergency_contact, medical_history, current_medications, insurance_info, status, approved_at, approved_by, staff_notes, created_at, updated_at) VALUES ('19', '29', 'A-', '09814639929', 'asthma', 'nebulizer', 'Philhealth', 'approved', '2025-10-23 11:01:14', '16', '', '2025-10-23 11:01:06', '2025-10-23 11:01:14');


-- Table: residents
CREATE TABLE residents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                middle_name TEXT,
                birth_date DATE,
                gender TEXT,
                civil_status TEXT,
                address TEXT,
                contact_number TEXT,
                emergency_contact TEXT,
                emergency_contact_number TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            );


-- Table: sqlite_sequence
CREATE TABLE sqlite_sequence(name,seq);

INSERT INTO sqlite_sequence (name, seq) VALUES ('users', '46');
INSERT INTO sqlite_sequence (name, seq) VALUES ('barangay_services', '6');
INSERT INTO sqlite_sequence (name, seq) VALUES ('health_services', '4');
INSERT INTO sqlite_sequence (name, seq) VALUES ('applications', '8');
INSERT INTO sqlite_sequence (name, seq) VALUES ('community_concerns', '1');
INSERT INTO sqlite_sequence (name, seq) VALUES ('application_notifications', '7');
INSERT INTO sqlite_sequence (name, seq) VALUES ('patient_registrations', '19');
INSERT INTO sqlite_sequence (name, seq) VALUES ('patient_registration_notifications', '29');
INSERT INTO sqlite_sequence (name, seq) VALUES ('appointments', '20');
INSERT INTO sqlite_sequence (name, seq) VALUES ('medical_records', '1');
INSERT INTO sqlite_sequence (name, seq) VALUES ('deleted_patients', '2');
INSERT INTO sqlite_sequence (name, seq) VALUES ('deleted_appointments', '5');
INSERT INTO sqlite_sequence (name, seq) VALUES ('activity_logs', '7');


-- Table: system_settings
CREATE TABLE system_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );


-- Table: users
CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'resident',
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            , address TEXT, phone TEXT, house_no VARCHAR(20), street VARCHAR(100), purok_endorsement VARCHAR(255), valid_id VARCHAR(255), account_verified BOOLEAN DEFAULT FALSE, verified_by INTEGER, verified_at TIMESTAMP NULL, birthday DATE NULL, gender VARCHAR(10) NULL, civil_status VARCHAR(20) NULL, year_started_living INTEGER NULL, last_viewed_applications TIMESTAMP NULL, password_changed BOOLEAN DEFAULT FALSE);

INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('1', 'System Administrator', 'admin', 'admin@barangay172.com', '$2y$10$TEpqChJCUwlg9zT9/9uV8.JwacjRY5zzaJYvwWT4HdYBkdTWgUiH.', 'admin', 'active', '2025-08-27 00:34:51', '2025-08-27 00:34:51', NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('16', 'Kei Satoru', 'hc001', 'keisleepyhead@gmail.com', '$2y$10$bPZkqq0CXHsrq/wW9FYuM.HkFmVk6hYvhkOx4.4NOTkqQI8ZoX/7C', 'health_center', 'active', '2025-10-22 08:32:10', '2025-10-22 08:32:10', 'dasdsadasd', '09814639929', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('22', 'Drei Gumangan', 'enc2001', 'gumangankurt@gmail.com', '$2y$10$q7D.juObJLr17UguPwcruOJx4CBhNB.UZBGNI7WN8TW.ARglOij9u', 'encoder2', 'active', '2025-10-23 02:25:45', '2025-10-23 02:25:45', 'Sampaloc', '09123456789', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-23 10:00:09', '0');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('23', 'Kei Andrei', 'enc3001', 'keimortred5@gmail.com', '$2y$10$Vp6p/GyuUG5T/vipVKgKMeAKY5QNRQRdMAL8tR4265Duz/r.dDfpe', 'encoder3', 'active', '2025-10-23 02:27:52', '2025-10-23 02:27:52', 'Bagumbong, Deparo', '0987654321', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-23 04:10:47', '0');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('28', 'Ej B Velasquez', 'RES00004', 'kosukekaede@gmail.com', '$2y$10$Docg2ZY.0IsK23AXSXfxh.55XiGCCQW7OfBcdC1mwkuMBsGIjA8MK', 'resident', 'active', '2025-10-23 02:41:58', '2025-10-23 02:41:58', 'blk 5 lot 5 Jenemiah, Zone 15, Brgy. 172, Caloocan City', NULL, 'blk 5 lot 5', 'Jenemiah', 'purok_endorsement_28_1761210585.jpg', 'valid_id_28_1761210585.jpg', '1', '45', '2025-10-23 09:10:08', NULL, NULL, NULL, NULL, '2025-10-23 09:58:49', '1');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('29', 'Rebecca S Billaro', 'RES00005', 'bontoandrei11@gmail.com', '$2y$10$Mo0CwdhjSBMw9Q.sMvlFVexIx6OCBQuIe3JqLbzpHI7yu3o/LxA6i', 'resident', 'active', '2025-10-23 02:51:43', '2025-10-23 02:51:43', 'blk3 lot9 Paul, Zone 15, Brgy. 172, Caloocan City', NULL, 'blk3 lot9', 'Paul', 'purok_endorsement_29_1761213573.jpg', 'valid_id_29_1761213573.jpg', '1', '22', '2025-10-23 10:00:15', NULL, NULL, NULL, NULL, '2025-10-23 10:47:59', '1');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('30', 'Marichu  Obsanga', 'RES00006', 'ryliebonto@gmail.com', '$2y$10$GXxbeuZ.RmvFLcAY4LT5bu5/U0P51NAHBkRO1obQC3IwEqs1M5DjO', 'resident', 'active', '2025-10-23 03:02:34', '2025-10-23 03:02:34', 'blk7 lot 3 Pearl, Zone 15, Brgy. 172, Caloocan City', NULL, 'blk7 lot 3', 'Pearl', 'purok_endorsement_30_1761190089.jpg', 'valid_id_30_1761190089.jpg', '1', '23', '2025-10-23 03:28:26', NULL, NULL, NULL, NULL, '2025-10-23 04:16:09', '1');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('45', 'John Michael Agbon', 'enc1001', 'onlymainaccnt@gmail.com', '$2y$10$JJGkoYmJ9Lw.8va51GDL0.WGmagFSP0o1r/L6gFWiSFXjwADTD3l6', 'encoder1', 'active', '2025-10-23 04:54:55', '2025-10-23 04:54:55', 'Deparo, Bagumbong', '0987654321', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-23 12:23:38', '0');
INSERT INTO users (id, full_name, username, email, password, role, status, created_at, updated_at, address, phone, house_no, street, purok_endorsement, valid_id, account_verified, verified_by, verified_at, birthday, gender, civil_status, year_started_living, last_viewed_applications, password_changed) VALUES ('46', 'Health Center Staff', 'health_staff', 'health@barangay172.com', '$2y$10$xBOjTMxGf6eMf27PirGZ2e8BfSZt8hAwsNXf1ocmevYCTaX2.GkYG', 'health_staff', 'active', '2025-10-23 05:00:49', '2025-10-23 05:00:49', NULL, NULL, NULL, NULL, NULL, NULL, '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0');

