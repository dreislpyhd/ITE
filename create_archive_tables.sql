-- Create deleted_patients table for archived patient registrations
CREATE TABLE IF NOT EXISTS deleted_patients (
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
    deleted_by INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Create deleted_appointments table for archived appointments
CREATE TABLE IF NOT EXISTS deleted_appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    service_type TEXT NOT NULL,
    appointment_date TEXT NOT NULL,
    notes TEXT,
    status TEXT DEFAULT 'scheduled',
    created_at TEXT DEFAULT (datetime('now')),
    deleted_at TEXT DEFAULT (datetime('now')),
    deleted_by INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (deleted_by) REFERENCES users(id)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_deleted_patients_user_id ON deleted_patients(user_id);
CREATE INDEX IF NOT EXISTS idx_deleted_patients_deleted_at ON deleted_patients(deleted_at);
CREATE INDEX IF NOT EXISTS idx_deleted_appointments_user_id ON deleted_appointments(user_id);
CREATE INDEX IF NOT EXISTS idx_deleted_appointments_deleted_at ON deleted_appointments(deleted_at);
