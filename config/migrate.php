<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$pdo = getDatabaseConnection();

function migrationColumnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = :table
             AND COLUMN_NAME = :column'
    );
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

if (!migrationColumnExists($pdo, 'users', 'email')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER username');
    $pdo->exec("UPDATE users SET email = CONCAT(username, '@example.com') WHERE email IS NULL OR email = ''");
    $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(150) NOT NULL');
    $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');

    echo "Added email column to users table." . PHP_EOL;
} else {
    echo "Email column already exists." . PHP_EOL;
}

if (!migrationColumnExists($pdo, 'users', 'mobile')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN mobile VARCHAR(30) NULL AFTER email');
    echo "Added mobile column to users table." . PHP_EOL;
} else {
    echo "Mobile column already exists." . PHP_EOL;
}

if (!migrationColumnExists($pdo, 'users', 'profile_photo')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER mobile');
    echo "Added profile_photo column to users table." . PHP_EOL;
} else {
    echo "Profile photo column already exists." . PHP_EOL;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_reset_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NULL,
        status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending',
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_password_reset_token_hash (token_hash),
        CONSTRAINT fk_password_reset_user
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Password reset requests table is ready." . PHP_EOL;

if (!migrationColumnExists($pdo, 'password_reset_requests', 'token_hash')) {
    $pdo->exec('ALTER TABLE password_reset_requests ADD COLUMN token_hash CHAR(64) NULL AFTER user_id');
    $pdo->exec('ALTER TABLE password_reset_requests ADD INDEX idx_password_reset_token_hash (token_hash)');
    echo "Added token_hash column to password reset requests." . PHP_EOL;
}

if (!migrationColumnExists($pdo, 'password_reset_requests', 'expires_at')) {
    $pdo->exec('ALTER TABLE password_reset_requests ADD COLUMN expires_at DATETIME NULL AFTER requested_at');
    echo "Added expires_at column to password reset requests." . PHP_EOL;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS patients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_no VARCHAR(30) NOT NULL UNIQUE,
        fullname VARCHAR(150) NOT NULL,
        birthdate DATE NOT NULL,
        age INT UNSIGNED NOT NULL DEFAULT 0,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        address TEXT NULL,
        contact_number VARCHAR(30) NOT NULL,
        email VARCHAR(150) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        archived_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_patients_fullname (fullname),
        INDEX idx_patients_contact_number (contact_number),
        INDEX idx_patients_archived_at (archived_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Patients table is ready." . PHP_EOL;

if (!migrationColumnExists($pdo, 'patients', 'archived_at')) {
    $pdo->exec('ALTER TABLE patients ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
    $pdo->exec('ALTER TABLE patients ADD INDEX idx_patients_archived_at (archived_at)');
    echo "Added archived_at column to patients table." . PHP_EOL;
}

$patientExtraColumns = [
    'first_name' => "ALTER TABLE patients ADD COLUMN first_name VARCHAR(80) NULL AFTER fullname",
    'middle_name' => "ALTER TABLE patients ADD COLUMN middle_name VARCHAR(80) NULL AFTER first_name",
    'last_name' => "ALTER TABLE patients ADD COLUMN last_name VARCHAR(80) NULL AFTER middle_name",
    'suffix' => "ALTER TABLE patients ADD COLUMN suffix VARCHAR(30) NULL AFTER last_name",
    'patient_photo' => "ALTER TABLE patients ADD COLUMN patient_photo VARCHAR(255) NULL AFTER suffix",
    'emergency_contact' => "ALTER TABLE patients ADD COLUMN emergency_contact VARCHAR(150) NULL AFTER email",
    'emergency_contact_number' => "ALTER TABLE patients ADD COLUMN emergency_contact_number VARCHAR(30) NULL AFTER emergency_contact",
    'has_hmo' => "ALTER TABLE patients ADD COLUMN has_hmo ENUM('Yes', 'No') NOT NULL DEFAULT 'No' AFTER emergency_contact_number",
    'hmo_provider' => "ALTER TABLE patients ADD COLUMN hmo_provider VARCHAR(150) NULL AFTER has_hmo",
    'hmo_card_number' => "ALTER TABLE patients ADD COLUMN hmo_card_number VARCHAR(80) NULL AFTER hmo_provider",
    'hmo_type' => "ALTER TABLE patients ADD COLUMN hmo_type ENUM('Principal', 'Dependent') NULL AFTER hmo_card_number",
    'hmo_expiration_date' => "ALTER TABLE patients ADD COLUMN hmo_expiration_date DATE NULL AFTER hmo_type",
    'hmo_card_file' => "ALTER TABLE patients ADD COLUMN hmo_card_file VARCHAR(255) NULL AFTER hmo_expiration_date",
    'allergies' => "ALTER TABLE patients ADD COLUMN allergies TEXT NULL AFTER hmo_card_file",
    'medical_conditions' => "ALTER TABLE patients ADD COLUMN medical_conditions TEXT NULL AFTER allergies",
    'current_medications' => "ALTER TABLE patients ADD COLUMN current_medications TEXT NULL AFTER medical_conditions",
    'medical_notes' => "ALTER TABLE patients ADD COLUMN medical_notes TEXT NULL AFTER current_medications",
];

foreach ($patientExtraColumns as $column => $sql) {
    if (!migrationColumnExists($pdo, 'patients', $column)) {
        $pdo->exec($sql);
        echo "Added {$column} column to patients table." . PHP_EOL;
    }
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS appointments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_id INT UNSIGNED NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        service VARCHAR(120) NOT NULL,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_appointments_date (appointment_date),
        INDEX idx_appointments_status (status),
        CONSTRAINT fk_appointments_patient
            FOREIGN KEY (patient_id) REFERENCES patients(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Appointments table is ready." . PHP_EOL;

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS services (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        service_name VARCHAR(150) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        description TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_services_name (service_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Services table is ready." . PHP_EOL;

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS bills (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_id INT UNSIGNED NOT NULL,
        service_id INT UNSIGNED NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_status ENUM('Paid', 'Unpaid', 'Partial') NOT NULL DEFAULT 'Unpaid',
        payment_date DATE NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bills_patient_id (patient_id),
        INDEX idx_bills_service_id (service_id),
        INDEX idx_bills_payment_status (payment_status),
        CONSTRAINT fk_bills_patient
            FOREIGN KEY (patient_id) REFERENCES patients(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_bills_service
            FOREIGN KEY (service_id) REFERENCES services(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Bills table is ready." . PHP_EOL;

if (!migrationColumnExists($pdo, 'appointments', 'service')) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN service VARCHAR(120) NOT NULL DEFAULT '' AFTER appointment_time");
    if (migrationColumnExists($pdo, 'appointments', 'service_type')) {
        $pdo->exec("UPDATE appointments SET service = service_type WHERE service = ''");
    }
    echo "Added service column to appointments table." . PHP_EOL;
}

if (!migrationColumnExists($pdo, 'appointments', 'appointment_source')) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN appointment_source ENUM('Walk-In', 'Facebook Page', 'Phone Call') NOT NULL DEFAULT 'Walk-In' AFTER appointment_time");
    echo "Added appointment_source column to appointments table." . PHP_EOL;
}

if (!migrationColumnExists($pdo, 'appointments', 'dentist')) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN dentist VARCHAR(120) NULL AFTER appointment_source");
    echo "Added dentist column to appointments table." . PHP_EOL;
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS dental_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_id INT UNSIGNED NOT NULL,
        diagnosis VARCHAR(255) NOT NULL,
        treatment VARCHAR(255) NOT NULL,
        notes TEXT NULL,
        date_recorded DATE NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dental_records_patient_id (patient_id),
        INDEX idx_dental_records_date_recorded (date_recorded),
        CONSTRAINT fk_dental_records_patient
            FOREIGN KEY (patient_id) REFERENCES patients(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Dental records table is ready." . PHP_EOL;

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        type VARCHAR(80) NOT NULL,
        message TEXT NOT NULL,
        meta JSON NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notifications_user_id (user_id),
        INDEX idx_notifications_is_read (is_read),
        CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Notifications table is ready." . PHP_EOL;

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(191) NOT NULL,
        meta JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_user_id (user_id),
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "Audit logs table is ready." . PHP_EOL;

echo "Migration complete." . PHP_EOL;
