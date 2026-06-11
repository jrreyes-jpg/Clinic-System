<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

if ($argc !== 6) {
    echo "Usage: php config/create_user.php \"Full Name\" username email password admin|receptionist" . PHP_EOL;
    exit(1);
}

[$script, $fullname, $username, $email, $plainPassword, $role] = $argv;

if (!in_array($role, ['admin', 'receptionist'], true)) {
    echo "Role must be admin or receptionist." . PHP_EOL;
    exit(1);
}

if (trim($fullname) === '' || trim($username) === '' || $plainPassword === '') {
    echo "Full name, username, email, and password are required." . PHP_EOL;
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Please enter a valid email address." . PHP_EOL;
    exit(1);
}

$passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
$pdo = getDatabaseConnection();

$statement = $pdo->prepare(
    'INSERT INTO users (fullname, username, email, password, role) VALUES (:fullname, :username, :email, :password, :role)'
);

$statement->execute([
    'fullname' => $fullname,
    'username' => $username,
    'email' => $email,
    'password' => $passwordHash,
    'role' => $role,
]);

echo "User created successfully: {$username}" . PHP_EOL;
