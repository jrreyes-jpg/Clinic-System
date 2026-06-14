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

function clinicLogoUrl(string $prefix = ''): string
{
    $logoFiles = ['clinic-logo.jpg', 'clinic-logo.png', 'clinic-logo.webp', 'ac-ave-logo.jpg'];

    foreach ($logoFiles as $file) {
        $path = __DIR__ . '/../assets/img/' . $file;

        if (is_file($path)) {
            return $prefix . 'assets/img/' . $file . '?v=' . filemtime($path);
        }
    }

    return $prefix . 'assets/img/ac-ave-logo.jpg';
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
        'mobile' => $_SESSION['mobile'] ?? '',
        'profile_photo' => $_SESSION['profile_photo'] ?? '',
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

function usersProfilePhotoSelect(): string
{
    return columnExists('users', 'profile_photo') ? 'profile_photo' : '"" AS profile_photo';
}

function findUserByUsername(string $username): ?array
{
    $pdo = getDatabaseConnection();
    $emailSelect = usersEmailSelect();
    $mobileSelect = usersMobileSelect();
    $profilePhotoSelect = usersProfilePhotoSelect();

    $statement = $pdo->prepare(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, {$profilePhotoSelect}, password, role FROM users WHERE username = :username LIMIT 1"
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
    $mobileSelect = usersMobileSelect();
    $profilePhotoSelect = usersProfilePhotoSelect();
    $whereClause = $emailExists ? 'username = :username OR email = :email' : 'username = :username';

    $statement = $pdo->prepare(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, {$profilePhotoSelect}, password, role
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
    $profilePhotoSelect = usersProfilePhotoSelect();

    $statement = $pdo->query(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, {$profilePhotoSelect}, role, created_at
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
    $profilePhotoSelect = usersProfilePhotoSelect();

    $statement = $pdo->query(
        "SELECT id, fullname, username, {$emailSelect}, {$mobileSelect}, {$profilePhotoSelect}, role, created_at
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
): int
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

    return (int) $pdo->lastInsertId();
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

function profilePhotoUrl(?array $user, string $prefix = ''): string
{
    $photo = trim((string) ($user['profile_photo'] ?? ''));

    if ($photo !== '' && is_file(__DIR__ . '/../' . $photo)) {
        return $prefix . $photo . '?v=' . filemtime(__DIR__ . '/../' . $photo);
    }

    return '';
}

function updateCurrentUserProfile(int $userId, string $fullname, string $email, string $mobile, string $profilePhoto = ''): void
{
    $pdo = getDatabaseConnection();
    $hasMobile = columnExists('users', 'mobile');
    $hasProfilePhoto = columnExists('users', 'profile_photo');
    $sets = ['fullname = :fullname', 'email = :email'];
    $parameters = [
        'fullname' => $fullname,
        'email' => $email,
        'id' => $userId,
    ];

    if ($hasMobile) {
        $sets[] = 'mobile = :mobile';
        $parameters['mobile'] = $mobile;
    }

    if ($hasProfilePhoto && $profilePhoto !== '') {
        $sets[] = 'profile_photo = :profile_photo';
        $parameters['profile_photo'] = $profilePhoto;
    }

    $statement = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $statement->execute($parameters);

    $_SESSION['fullname'] = $fullname;
    $_SESSION['email'] = $email;
    $_SESSION['mobile'] = $mobile;

    if ($profilePhoto !== '') {
        $_SESSION['profile_photo'] = $profilePhoto;
    }
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
    $_SESSION['mobile'] = $user['mobile'] ?? '';
    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
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

function listServices(string $search = ''): array
{
    if (!tableExists('services')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $where = '';
    $parameters = [];

    if ($search !== '') {
        $where = 'WHERE service_name LIKE :search OR description LIKE :search';
        $parameters['search'] = '%' . $search . '%';
    }

    $statement = $pdo->prepare(
        "SELECT id, service_name, price, description, created_at
         FROM services
         {$where}
         ORDER BY service_name ASC"
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

/* Notifications helper functions */
function createNotification(?int $userId, string $type, string $message, ?array $meta = null): void
{
    if (!tableExists('notifications')) {
        return;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, message, meta) VALUES (:user_id, :type, :message, :meta)'
    );
    $statement->execute([
        'user_id' => $userId,
        'type' => $type,
        'message' => $message,
        'meta' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function getNotificationsForUser(int $userId, int $limit = 20): array
{
    if (!tableExists('notifications')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT id, user_id, type, message, meta, is_read, created_at
         FROM notifications
         WHERE user_id = :user_id OR user_id IS NULL
         ORDER BY created_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll();

    foreach ($rows as &$r) {
        $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : null;
        $r['is_read'] = (bool) $r['is_read'];
    }

    return $rows;
}

function countUnreadNotifications(int $userId): int
{
    if (!tableExists('notifications')) {
        return 0;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE (user_id = :user_id OR user_id IS NULL) AND is_read = 0'
    );
    $statement->execute(['user_id' => $userId]);

    return (int) $statement->fetchColumn();
}

function markNotificationRead(int $notificationId): void
{
    if (!tableExists('notifications')) {
        return;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id');
    $statement->execute(['id' => $notificationId]);
}

function findServiceById(int $serviceId): ?array
{
    if (!tableExists('services')) {
        return null;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT id, service_name, price, description, created_at FROM services WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $serviceId]);

    $service = $statement->fetch();

    return $service ?: null;
}

function createService(array $data): int
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'INSERT INTO services (service_name, price, description)
         VALUES (:service_name, :price, :description)'
    );
    $statement->execute([
        'service_name' => $data['service_name'],
        'price' => number_format((float) $data['price'], 2, '.', ''),
        'description' => $data['description'],
    ]);

    return (int) $pdo->lastInsertId();
}

function updateService(int $serviceId, array $data): void
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'UPDATE services
         SET service_name = :service_name,
             price = :price,
             description = :description
         WHERE id = :id'
    );
    $statement->execute([
        'service_name' => $data['service_name'],
        'price' => number_format((float) $data['price'], 2, '.', ''),
        'description' => $data['description'],
        'id' => $serviceId,
    ]);
}

function validateServiceData(array $data): array
{
    $errors = [];

    if (trim((string) ($data['service_name'] ?? '')) === '') {
        $errors[] = 'Service name is required.';
    }

    $price = trim((string) ($data['price'] ?? ''));
    if ($price === '' || !is_numeric($price) || (float) $price < 0) {
        $errors[] = 'Please enter a valid service price.';
    }

    return $errors;
}

function listBills(string $search = ''): array
{
    if (!tableExists('bills')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $where = '';
    $parameters = [];

    if ($search !== '') {
        $where = 'WHERE p.fullname LIKE :search OR s.service_name LIKE :search OR b.payment_status LIKE :search';
        $parameters['search'] = '%' . $search . '%';
    }

    $statement = $pdo->prepare(
        "SELECT b.id, b.patient_id, b.service_id, b.amount, b.payment_status, b.payment_date, b.created_at,
                p.fullname AS patient_name, p.patient_no, s.service_name
         FROM bills b
         INNER JOIN patients p ON p.id = b.patient_id
         INNER JOIN services s ON s.id = b.service_id
         {$where}
         ORDER BY b.created_at DESC"
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function getBillById(int $billId): ?array
{
    if (!tableExists('bills')) {
        return null;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT b.id, b.patient_id, b.service_id, b.amount, b.payment_status, b.payment_date, b.created_at,
                p.fullname AS patient_name, p.patient_no, p.contact_number, p.email, s.service_name, s.price AS service_price, s.description AS service_description
         FROM bills b
         INNER JOIN patients p ON p.id = b.patient_id
         INNER JOIN services s ON s.id = b.service_id
         WHERE b.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $billId]);

    $bill = $statement->fetch();
    return $bill ?: null;
}

function createBill(array $data): int
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'INSERT INTO bills (patient_id, service_id, amount, payment_status, payment_date)
         VALUES (:patient_id, :service_id, :amount, :payment_status, :payment_date)'
    );
    $statement->execute([
        'patient_id' => $data['patient_id'],
        'service_id' => $data['service_id'],
        'amount' => number_format((float) $data['amount'], 2, '.', ''),
        'payment_status' => $data['payment_status'],
        'payment_date' => $data['payment_date'] !== '' ? $data['payment_date'] : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function recordPayment(int $billId, array $data): void
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'UPDATE bills
         SET payment_status = :payment_status,
             payment_date = :payment_date
         WHERE id = :id'
    );
    $statement->execute([
        'payment_status' => $data['payment_status'],
        'payment_date' => $data['payment_date'] !== '' ? $data['payment_date'] : null,
        'id' => $billId,
    ]);
}

function validateBillData(array $data): array
{
    $errors = [];
    $validStatuses = ['Paid', 'Unpaid', 'Partial'];

    if ($data['patient_id'] <= 0) {
        $errors[] = 'Please select a patient.';
    }

    if ($data['service_id'] <= 0) {
        $errors[] = 'Please select a service.';
    }

    $amount = trim((string) ($data['amount'] ?? ''));
    if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
        $errors[] = 'Please enter a valid bill amount.';
    }

    if (!in_array($data['payment_status'], $validStatuses, true)) {
        $errors[] = 'Please select a valid payment status.';
    }

    $paymentDate = trim((string) ($data['payment_date'] ?? ''));
    if ($paymentDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $errors[] = 'Please enter a valid payment date.';
    }

    return $errors;
}

function validatePaymentData(array $data): array
{
    $errors = [];
    $validStatuses = ['Paid', 'Unpaid', 'Partial'];

    if (!in_array($data['payment_status'], $validStatuses, true)) {
        $errors[] = 'Please select a valid payment status.';
    }

    $paymentDate = trim((string) ($data['payment_date'] ?? ''));
    if ($paymentDate === '') {
        $errors[] = 'Please enter the payment date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $errors[] = 'Please enter a valid payment date.';
    }

    return $errors;
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

function listDentalRecords(int $patientId = 0): array
{
    if (!tableExists('dental_records')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $where = '';
    $parameters = [];

    if ($patientId > 0) {
        $where = 'WHERE dr.patient_id = :patient_id';
        $parameters['patient_id'] = $patientId;
    }

    $statement = $pdo->prepare(
        "SELECT dr.id, dr.patient_id, dr.diagnosis, dr.treatment, dr.notes, dr.date_recorded, dr.created_at,
                p.patient_no, p.fullname AS patient_name, p.contact_number
         FROM dental_records dr
         INNER JOIN patients p ON p.id = dr.patient_id
         {$where}
         ORDER BY dr.date_recorded DESC, dr.id DESC"
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function createDentalRecord(array $data): void
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'INSERT INTO dental_records (patient_id, diagnosis, treatment, notes, date_recorded)
         VALUES (:patient_id, :diagnosis, :treatment, :notes, :date_recorded)'
    );
    $statement->execute([
        'patient_id' => (int) $data['patient_id'],
        'diagnosis' => $data['diagnosis'],
        'treatment' => $data['treatment'],
        'notes' => $data['notes'],
        'date_recorded' => $data['date_recorded'],
    ]);
}

function validateDentalRecordData(array $data): array
{
    $errors = [];

    if ((int) ($data['patient_id'] ?? 0) <= 0) {
        $errors[] = 'Please select a patient.';
    }

    if (trim((string) ($data['diagnosis'] ?? '')) === '') {
        $errors[] = 'Diagnosis is required.';
    }

    if (trim((string) ($data['treatment'] ?? '')) === '') {
        $errors[] = 'Treatment is required.';
    }

    if (trim((string) ($data['date_recorded'] ?? '')) === '') {
        $errors[] = 'Date recorded is required.';
    }

    return $errors;
}

function listPreviousVisits(int $patientId): array
{
    if (!tableExists('appointments') || $patientId <= 0) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $serviceColumn = appointmentServiceColumn();
    $statement = $pdo->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, a.{$serviceColumn} AS service_type, a.status, a.notes
         FROM appointments a
         WHERE a.patient_id = :patient_id
             AND (a.status IN ('completed', 'cancelled') OR a.appointment_date < CURDATE())
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $statement->execute(['patient_id' => $patientId]);

    return $statement->fetchAll();
}

function countDentalRecords(): int
{
    if (!tableExists('dental_records')) {
        return 0;
    }

    $pdo = getDatabaseConnection();

    return (int) $pdo->query('SELECT COUNT(*) FROM dental_records')->fetchColumn();
}

function countActivePatients(): int
{
    if (!tableExists('patients')) {
        return 0;
    }

    $pdo = getDatabaseConnection();
    $where = columnExists('patients', 'archived_at') ? 'WHERE archived_at IS NULL' : '';

    return (int) $pdo->query("SELECT COUNT(*) FROM patients {$where}")->fetchColumn();
}

function countActiveUsers(): int
{
    if (!tableExists('users')) {
        return 0;
    }

    $pdo = getDatabaseConnection();

    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function countAppointmentsToday(): int
{
    if (!tableExists('appointments')) {
        return 0;
    }

    $pdo = getDatabaseConnection();

    if (columnExists('appointments', 'appointment_date')) {
        return (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()')->fetchColumn();
    }

    if (columnExists('appointments', 'scheduled_at')) {
        return (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_at) = CURDATE()')->fetchColumn();
    }

    return 0;
}

function countAppointmentsByStatus(string $status): int
{
    if (!tableExists('appointments')) {
        return 0;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE status = :status');
    $statement->execute(['status' => $status]);

    return (int) $statement->fetchColumn();
}

function getDailyAppointmentCounts(int $days = 7): array
{
    if (!tableExists('appointments')) {
        return [];
    }

    $days = max(1, $days);
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT appointment_date AS period, COUNT(*) AS count
         FROM appointments
         WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL :days_minus1 DAY) AND CURDATE()
         GROUP BY appointment_date
         ORDER BY appointment_date ASC'
    );
    $statement->execute(['days_minus1' => $days - 1]);
    $rows = $statement->fetchAll();

    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['period']] = (int) $row['count'];
    }

    $result = [];
    $date = new DateTimeImmutable('-' . ($days - 1) . ' days');
    for ($i = 0; $i < $days; $i++, $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $result[] = [
            'label' => $date->format('M d'),
            'count' => $counts[$key] ?? 0,
        ];
    }

    return $result;
}

function getMonthlyPatientCounts(int $months = 6): array
{
    if (!tableExists('patients')) {
        return [];
    }

    $months = max(1, $months);
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS count
         FROM patients
         WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL :months_minus1 MONTH), '%Y-%m-01')
         GROUP BY period
         ORDER BY period ASC"
    );
    $statement->execute(['months_minus1' => $months - 1]);
    $rows = $statement->fetchAll();

    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['period']] = (int) $row['count'];
    }

    $result = [];
    $date = new DateTimeImmutable('first day of -' . ($months - 1) . ' months');
    for ($i = 0; $i < $months; $i++, $date = $date->modify('+1 month')) {
        $key = $date->format('Y-m');
        $result[] = [
            'label' => $date->format('M Y'),
            'count' => $counts[$key] ?? 0,
        ];
    }

    return $result;
}

function getMonthlyRevenue(int $months = 6): array
{
    if (!tableExists('bills')) {
        return [];
    }

    $months = max(1, $months);
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS period, SUM(amount) AS revenue
         FROM bills
         WHERE payment_date IS NOT NULL
             AND payment_status IN ('Paid', 'Partial')
             AND payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL :months_minus1 MONTH), '%Y-%m-01')
         GROUP BY period
         ORDER BY period ASC"
    );
    $statement->execute(['months_minus1' => $months - 1]);
    $rows = $statement->fetchAll();

    $revenue = [];
    foreach ($rows as $row) {
        $revenue[$row['period']] = (float) $row['revenue'];
    }

    $result = [];
    $date = new DateTimeImmutable('first day of -' . ($months - 1) . ' months');
    for ($i = 0; $i < $months; $i++, $date = $date->modify('+1 month')) {
        $key = $date->format('Y-m');
        $result[] = [
            'label' => $date->format('M Y'),
            'revenue' => $revenue[$key] ?? 0.0,
        ];
    }

    return $result;
}

function getMostRequestedServices(int $limit = 5): array
{
    if (!tableExists('bills')) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        "SELECT s.service_name, COUNT(*) AS request_count, SUM(b.amount) AS total_amount
         FROM bills b
         INNER JOIN services s ON s.id = b.service_id
         GROUP BY b.service_id
         ORDER BY request_count DESC
         LIMIT {$limit}"
    );
    $statement->execute();

    return $statement->fetchAll();
}

function getReportsData(int $days = 7, int $months = 6, int $topServices = 5): array
{
    return [
        'monthlyPatients' => getMonthlyPatientCounts($months),
        'monthlyRevenue' => getMonthlyRevenue($months),
        'topServices' => getMostRequestedServices($topServices),
        'recentAudits' => getRecentAuditLogs(10),
    ];
}

function createAuditLog(?int $userId, string $action, ?array $meta = null): void
{
    if (!tableExists('audit_logs')) {
        return;
    }

    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, meta) VALUES (:user_id, :action, :meta)'
    );
    $statement->execute([
        'user_id' => $userId,
        'action' => $action,
        'meta' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function getRecentAuditLogs(int $limit = 20): array
{
    if (!tableExists('audit_logs')) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT a.id, a.user_id, a.action, a.meta, a.created_at, u.fullname AS user_fullname, u.username
         FROM audit_logs a
         LEFT JOIN users u ON u.id = a.user_id
         ORDER BY a.created_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll();
    foreach ($rows as &$r) {
        $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : null;
    }

    return $rows;
}

function appointmentServiceColumn(): string
{
    return columnExists('appointments', 'service') ? 'service' : 'service_type';
}

function listAppointments(string $date = '', string $status = ''): array
{
    if (!tableExists('appointments')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $conditions = [];
    $parameters = [];

    if ($date !== '') {
        $conditions[] = 'a.appointment_date = :appointment_date';
        $parameters['appointment_date'] = $date;
    }

    if ($status !== '' && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
        $conditions[] = 'a.status = :status';
        $parameters['status'] = $status;
    }

    $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
    $serviceColumn = appointmentServiceColumn();

    $statement = $pdo->prepare(
        "SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.{$serviceColumn} AS service_type, a.status, a.notes, a.created_at,
                p.patient_no, p.fullname AS patient_name, p.contact_number
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         {$where}
         ORDER BY a.appointment_date ASC, a.appointment_time ASC"
    );
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function listAppointmentsForMonth(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || !tableExists('appointments')) {
        return [];
    }

    $pdo = getDatabaseConnection();
    $serviceColumn = appointmentServiceColumn();
    $statement = $pdo->prepare(
        "SELECT a.id, a.appointment_date, a.appointment_time, a.{$serviceColumn} AS service_type, a.status,
                p.fullname AS patient_name
         FROM appointments a
         INNER JOIN patients p ON p.id = a.patient_id
         WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = :month
         ORDER BY a.appointment_date ASC, a.appointment_time ASC"
    );
    $statement->execute(['month' => $month]);

    return $statement->fetchAll();
}

function createAppointment(array $data): int
{
    $pdo = getDatabaseConnection();
    $serviceColumn = appointmentServiceColumn();

    $statement = $pdo->prepare(
        "INSERT INTO appointments (patient_id, appointment_date, appointment_time, {$serviceColumn}, status, notes)
         VALUES (:patient_id, :appointment_date, :appointment_time, :service_type, :status, :notes)"
    );
    $statement->execute([
        'patient_id' => (int) $data['patient_id'],
        'appointment_date' => $data['appointment_date'],
        'appointment_time' => $data['appointment_time'],
        'service_type' => $data['service_type'],
        'status' => $data['status'],
        'notes' => $data['notes'],
    ]);

    return (int) $pdo->lastInsertId();
}

function updateAppointment(int $appointmentId, array $data): void
{
    $pdo = getDatabaseConnection();
    $serviceColumn = appointmentServiceColumn();

    $statement = $pdo->prepare(
        "UPDATE appointments
         SET patient_id = :patient_id,
             appointment_date = :appointment_date,
             appointment_time = :appointment_time,
             {$serviceColumn} = :service_type,
             status = :status,
             notes = :notes
         WHERE id = :id"
    );
    $statement->execute([
        'patient_id' => (int) $data['patient_id'],
        'appointment_date' => $data['appointment_date'],
        'appointment_time' => $data['appointment_time'],
        'service_type' => $data['service_type'],
        'status' => $data['status'],
        'notes' => $data['notes'],
        'id' => $appointmentId,
    ]);
}

function updateAppointmentStatus(int $appointmentId, string $status): void
{
    $pdo = getDatabaseConnection();

    $statement = $pdo->prepare(
        'UPDATE appointments SET status = :status WHERE id = :id'
    );
    $statement->execute([
        'status' => $status,
        'id' => $appointmentId,
    ]);
}

function validateAppointmentData(array $data): array
{
    $errors = [];

    if ((int) ($data['patient_id'] ?? 0) <= 0) {
        $errors[] = 'Please select a patient.';
    }

    if (trim((string) ($data['appointment_date'] ?? '')) === '') {
        $errors[] = 'Appointment date is required.';
    }

    if (trim((string) ($data['appointment_time'] ?? '')) === '') {
        $errors[] = 'Appointment time is required.';
    }

    if (trim((string) ($data['service_type'] ?? '')) === '') {
        $errors[] = 'Service type is required.';
    }

    if (!in_array((string) ($data['status'] ?? ''), ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
        $errors[] = 'Please select a valid status.';
    }

    return $errors;
}

function recentDashboardActivities(int $limit = 5): array
{
    $activities = [];
    $pdo = getDatabaseConnection();

    if (tableExists('patients')) {
        $where = columnExists('patients', 'archived_at') ? 'WHERE archived_at IS NULL' : '';
        $statement = $pdo->query(
            "SELECT fullname AS title, patient_no AS meta, created_at
             FROM patients
             {$where}
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );

        foreach ($statement->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fa-user-injured',
                'label' => 'Patient added',
                'title' => $row['title'],
                'meta' => $row['meta'],
                'created_at' => $row['created_at'],
            ];
        }
    }

    if (tableExists('users')) {
        $statement = $pdo->query(
            "SELECT fullname AS title, role AS meta, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );

        foreach ($statement->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fa-user-shield',
                'label' => 'User account created',
                'title' => $row['title'],
                'meta' => ucfirst((string) $row['meta']),
                'created_at' => $row['created_at'],
            ];
        }
    }

    if (tableExists('appointments')) {
        $serviceColumn = appointmentServiceColumn();
        $statement = $pdo->query(
            "SELECT p.fullname AS title, CONCAT(a.{$serviceColumn}, ' - ', a.status) AS meta, a.created_at
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             ORDER BY a.created_at DESC
             LIMIT {$limit}"
        );

        foreach ($statement->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fa-calendar-check',
                'label' => 'Appointment scheduled',
                'title' => $row['title'],
                'meta' => $row['meta'],
                'created_at' => $row['created_at'],
            ];
        }
    }

    if (tableExists('dental_records')) {
        $statement = $pdo->query(
            "SELECT p.fullname AS title, dr.treatment AS meta, dr.created_at
             FROM dental_records dr
             INNER JOIN patients p ON p.id = dr.patient_id
             ORDER BY dr.created_at DESC
             LIMIT {$limit}"
        );

        foreach ($statement->fetchAll() as $row) {
            $activities[] = [
                'icon' => 'fa-notes-medical',
                'label' => 'Dental record added',
                'title' => $row['title'],
                'meta' => $row['meta'],
                'created_at' => $row['created_at'],
            ];
        }
    }

    usort($activities, static function (array $a, array $b): int {
        return strcmp((string) $b['created_at'], (string) $a['created_at']);
    });

    return array_slice($activities, 0, $limit);
}
