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

if (!migrationColumnExists($pdo, 'appointments', 'service')) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN service VARCHAR(120) NOT NULL DEFAULT '' AFTER appointment_time");
    if (migrationColumnExists($pdo, 'appointments', 'service_type')) {
        $pdo->exec("UPDATE appointments SET service = service_type WHERE service = ''");
    }
    echo "Added service column to appointments table." . PHP_EOL;
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

echo "Migration complete." . PHP_EOL;
