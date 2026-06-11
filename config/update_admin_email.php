<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$email = $argv[1] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Please provide a valid email address." . PHP_EOL;
    exit(1);
}

$pdo = getDatabaseConnection();

$statement = $pdo->prepare('UPDATE users SET email = :email WHERE username = "admin" AND role = "admin"');
$statement->execute(['email' => $email]);

if ($statement->rowCount() === 0) {
    echo "No admin account with username admin was updated." . PHP_EOL;
    exit(1);
}

echo "Admin email updated successfully." . PHP_EOL;
