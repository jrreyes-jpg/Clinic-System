<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Starts a hardened PHP session for authentication.
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    startSecureSession();

    return isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'fullname' => $_SESSION['fullname'] ?? '',
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'],
    ];
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function rolePath(string $role): string
{
    if ($role === 'admin') {
        return 'admin/dashboard.php';
    }

    if ($role === 'receptionist') {
        return 'receptionist/dashboard.php';
    }

    return 'login.php';
}

function redirectByRole(string $role, string $prefix = ''): never
{
    redirect($prefix . rolePath($role));
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['error'] = 'Please log in to access that page.';
        redirect('../login.php');
    }
}

function requireRole(string $requiredRole): void
{
    requireLogin();

    if (($_SESSION['role'] ?? '') !== $requiredRole) {
        redirectByRole((string) $_SESSION['role'], '../');
    }
}

function tableExists(string $table): bool
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $statement->execute(['table' => $table]);

    return (int) $statement->fetchColumn() > 0;
}

function columnExists(string $table, string $column): bool
{
    $pdo = getDatabaseConnection();

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

function usersEmailSelect(): string
{
    return columnExists('users', 'email') ? 'email' : '"" AS email';
}

function usersMobileSelect(): string
{
    return columnExists('users', 'mobile') ? 'mobile' : '"" AS mobile';
}

function findUserByUsername(string $username): ?array
{
    $pdo = getDatabaseConnection();
    $emailSelect = usersEmailSelect();

    $statement = $pdo->prepare(
        "SELECT id, fullname, username, {$emailSelect}, password, role FROM users WHERE username = :username LIMIT 1"
    );
    $statement->execute(['username' => $username]);

    $user = $statement->fetch();

    return $user ?: null;
}

function findUserByUsernameOrEmail(string $login): ?array
{
    $pdo = getDatabaseConnection();
    $emailExists = columnExists('users', 'email');
    $emailSelect = $emailExists ? 'email' : '"" AS email';
    $whereClause = $emailExists ? 'username = :username OR email = :email' : 'username = :username';

    $statement = $pdo->prepare(
        "SELECT id, fullname, username, {$emailSelect}, password, role
         FROM users
         WHERE {$whereClause}
         LIMIT 1"
    );
    $parameters = ['username' => $login];

    if ($emailExists) {
        $parameters['email'] = $login;
    }

    $statement->execute($parameters);

    $user = $statement->fetch();

    return $user ?: null;
}

function listUsers(): array
{
    $pdo = getDatabaseConnection();
    $emailSelect = usersEmailSelect();
    $mobileSelect = usersMobileSelect();

    $statement = $pdo->query(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, role, created_at
         FROM users
         ORDER BY role ASC, fullname ASC"
    );

    return $statement->fetchAll();
}

function listReceptionists(): array
{
    $pdo = getDatabaseConnection();
    $emailSelect = usersEmailSelect();
    $mobileSelect = usersMobileSelect();

    $statement = $pdo->query(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, role, created_at
         FROM users
         WHERE role = 'receptionist'
         ORDER BY fullname ASC"
    );

    return $statement->fetchAll();
}

function createUser(
    string $fullname,
    string $username,
    string $email,
    string $password,
    string $role,
    string $mobile = ''
): void
{
    $pdo = getDatabaseConnection();
    $hasMobile = columnExists('users', 'mobile');

    $columns = 'fullname, username, email, password, role';
    $values = ':fullname, :username, :email, :password, :role';
    $parameters = [
        'fullname' => $fullname,
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ];

    if ($hasMobile) {
        $columns .= ', mobile';
        $values .= ', :mobile';
        $parameters['mobile'] = $mobile;
    }

    $statement = $pdo->prepare("INSERT INTO users ({$columns}) VALUES ({$values})");
    $statement->execute($parameters);
}

function updateReceptionist(int $userId, string $fullname, string $username, string $email, string $mobile): void
{
    $pdo = getDatabaseConnection();
    $hasMobile = columnExists('users', 'mobile');

    if ($hasMobile) {
        $statement = $pdo->prepare(
            'UPDATE users
             SET fullname = :fullname, username = :username, email = :email, mobile = :mobile
             WHERE id = :id AND role = "receptionist"'
        );
        $statement->execute([
            'fullname' => $fullname,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'id' => $userId,
        ]);

        return;
    }

    $statement = $pdo->prepare(
        'UPDATE users
         SET fullname = :fullname, username = :username, email = :email
         WHERE id = :id AND role = "receptionist"'
    );
    $statement->execute([
        'fullname' => $fullname,
        'username' => $username,
        'email' => $email,
        'id' => $userId,
    ]);
}

function resetUserPassword(int $userId, string $newPassword, bool $completePendingRequests = true): void
{
    $pdo = getDatabaseConnection();
    $hasResetRequests = $completePendingRequests && tableExists('password_reset_requests');

    $pdo->beginTransaction();

    $statement = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
    $statement->execute([
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    if ($hasResetRequests) {
        $completeRequest = $pdo->prepare(
            'UPDATE password_reset_requests
             SET status = "completed", completed_at = NOW()
             WHERE user_id = :user_id AND status = "pending"'
        );
        $completeRequest->execute(['user_id' => $userId]);
    }

    $pdo->commit();
}

function listPendingPasswordResetRequests(): array
{
    if (!tableExists('password_reset_requests')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $emailSelect = columnExists('users', 'email') ? 'u.email' : '"" AS email';

    $statement = $pdo->query(
        "SELECT pr.id, pr.requested_at, u.id AS user_id, u.fullname, u.username, {$emailSelect}, u.role
         FROM password_reset_requests pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.status = 'pending'
         ORDER BY pr.requested_at DESC"
    );

    return $statement->fetchAll();
}

function createPasswordResetRequest(int $userId): ?string
{
    if (!tableExists('password_reset_requests')) {
        return null;
    }

    $pdo = getDatabaseConnection();
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $completeOldRequests = $pdo->prepare(
        'UPDATE password_reset_requests
         SET status = "completed", completed_at = NOW()
         WHERE user_id = :user_id AND status = "pending"'
    );
    $completeOldRequests->execute(['user_id' => $userId]);

    $statement = $pdo->prepare(
        'INSERT INTO password_reset_requests (user_id, token_hash, expires_at, status)
         VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE), "pending")'
    );
    $statement->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
    ]);

    return $token;
}

function hasPendingPasswordResetRequest(int $userId): bool
{
    if (!tableExists('password_reset_requests')) {
        return false;
    }

    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'SELECT id
         FROM password_reset_requests
         WHERE user_id = :user_id
             AND status = "pending"
             AND token_hash IS NOT NULL
             AND expires_at > NOW()
         LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);

    return (bool) $statement->fetch();
}

function findPasswordResetByToken(string $token): ?array
{
    if (!tableExists('password_reset_requests')) {
        return null;
    }

    $pdo = getDatabaseConnection();
    $tokenHash = hash('sha256', $token);
    $emailSelect = columnExists('users', 'email') ? 'u.email' : '"" AS email';

    $statement = $pdo->prepare(
        "SELECT pr.id, pr.user_id, pr.expires_at, u.fullname, u.username, {$emailSelect}, u.role
         FROM password_reset_requests pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = :token_hash
             AND pr.status = 'pending'
             AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $statement->execute(['token_hash' => $tokenHash]);

    $request = $statement->fetch();

    return $request ?: null;
}

function resetPasswordByToken(string $token, string $newPassword): bool
{
    $request = findPasswordResetByToken($token);

    if ($request === null) {
        return false;
    }

    $pdo = getDatabaseConnection();
    $tokenHash = hash('sha256', $token);

    $pdo->beginTransaction();

    $updateUser = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
    $updateUser->execute([
        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => (int) $request['user_id'],
    ]);

    $completeRequest = $pdo->prepare(
        'UPDATE password_reset_requests
         SET status = "completed", completed_at = NOW()
         WHERE token_hash = :token_hash AND status = "pending"'
    );
    $completeRequest->execute(['token_hash' => $tokenHash]);

    $pdo->commit();

    return true;
}

function createLoginSession(array $user): void
{
    startSecureSession();

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['role'] = $user['role'];
}

function setFlashError(string $message): void
{
    startSecureSession();
    $_SESSION['error'] = $message;
}

function setFlashSuccess(string $message): void
{
    startSecureSession();
    $_SESSION['success'] = $message;
}

function getFlashError(): string
{
    startSecureSession();

    $message = $_SESSION['error'] ?? '';
    unset($_SESSION['error']);

    return (string) $message;
}

function getFlashSuccess(): string
{
    startSecureSession();

    $message = $_SESSION['success'] ?? '';
    unset($_SESSION['success']);

    return (string) $message;
}

function generateCsrfToken(): string
{
    startSecureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    startSecureSession();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireAnyRole(array $allowedRoles): void
{
    requireLogin();

    if (!in_array((string) ($_SESSION['role'] ?? ''), $allowedRoles, true)) {
        redirectByRole((string) $_SESSION['role'], '../');
    }
}

function patientBasePath(): string
{
    return '../patients/index.php';
}

function generatePatientNumber(): string
{
    $pdo = getDatabaseConnection();
    $year = date('Y');

    $statement = $pdo->prepare(
        'SELECT patient_no FROM patients WHERE patient_no LIKE :prefix ORDER BY id DESC LIMIT 1'
    );
    $statement->execute(['prefix' => 'P-' . $year . '-%']);
    $latest = $statement->fetchColumn();

    if (!$latest) {
        return 'P-' . $year . '-0001';
    }

    $number = (int) substr((string) $latest, -4);

    return 'P-' . $year . '-' . str_pad((string) ($number + 1), 4, '0', STR_PAD_LEFT);
}

function calculateAgeFromBirthdate(string $birthdate): int
{
    if ($birthdate === '') {
        return 0;
    }

    $birth = new DateTime($birthdate);
    $today = new DateTime();

    return $birth->diff($today)->y;
}

function listPatients(string $search = '', bool $includeArchived = false): array
{
    $pdo = getDatabaseConnection();
    $conditions = [];
    $parameters = [];

    if (!$includeArchived && columnExists('patients', 'archived_at')) {
        $conditions[] = 'archived_at IS NULL';
    }

    if ($search !== '') {
        $conditions[] = '(patient_no LIKE :search OR fullname LIKE :search OR contact_number LIKE :search OR email LIKE :search)';
        $parameters['search'] = '%' . $search . '%';
    }

    $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

    $statement = $pdo->prepare(
        "SELECT id, patient_no, fullname, birthdate, age, gender, address, contact_number, email, created_at,
            " . (columnExists('patients', 'archived_at') ? 'archived_at' : 'NULL AS archived_at') . "
         FROM patients
         {$where}
         ORDER BY created_at DESC, fullname ASC"
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function findPatientById(int $patientId): ?array
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'SELECT id, patient_no, fullname, birthdate, age, gender, address, contact_number, email, created_at,
            ' . (columnExists('patients', 'archived_at') ? 'archived_at' : 'NULL AS archived_at') . '
         FROM patients
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $patientId]);

    $patient = $statement->fetch();

    return $patient ?: null;
}

function createPatient(array $data): int
{
    $pdo = getDatabaseConnection();
    $patientNo = generatePatientNumber();
    $age = calculateAgeFromBirthdate((string) $data['birthdate']);

    $statement = $pdo->prepare(
        'INSERT INTO patients (patient_no, fullname, birthdate, age, gender, address, contact_number, email)
         VALUES (:patient_no, :fullname, :birthdate, :age, :gender, :address, :contact_number, :email)'
    );
    $statement->execute([
        'patient_no' => $patientNo,
        'fullname' => $data['fullname'],
        'birthdate' => $data['birthdate'],
        'age' => $age,
        'gender' => $data['gender'],
        'address' => $data['address'],
        'contact_number' => $data['contact_number'],
        'email' => $data['email'],
    ]);

    return (int) $pdo->lastInsertId();
}

function updatePatient(int $patientId, array $data): void
{
    $pdo = getDatabaseConnection();
    $age = calculateAgeFromBirthdate((string) $data['birthdate']);

    $statement = $pdo->prepare(
        'UPDATE patients
         SET fullname = :fullname,
             birthdate = :birthdate,
             age = :age,
             gender = :gender,
             address = :address,
             contact_number = :contact_number,
             email = :email
         WHERE id = :id'
    );
    $statement->execute([
        'fullname' => $data['fullname'],
        'birthdate' => $data['birthdate'],
        'age' => $age,
        'gender' => $data['gender'],
        'address' => $data['address'],
        'contact_number' => $data['contact_number'],
        'email' => $data['email'],
        'id' => $patientId,
    ]);
}

function archivePatient(int $patientId): void
{
    $pdo = getDatabaseConnection();

    if (columnExists('patients', 'archived_at')) {
        $statement = $pdo->prepare('UPDATE patients SET archived_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $patientId]);
    }
}

function validatePatientData(array $data): array
{
    $errors = [];

    if (trim((string) ($data['fullname'] ?? '')) === '') {
        $errors[] = 'Full name is required.';
    }

    if (trim((string) ($data['birthdate'] ?? '')) === '') {
        $errors[] = 'Birthdate is required.';
    }

    if (!in_array((string) ($data['gender'] ?? ''), ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }

    if (trim((string) ($data['contact_number'] ?? '')) === '') {
        $errors[] = 'Contact number is required.';
    }

    $email = trim((string) ($data['email'] ?? ''));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    return $errors;
}
