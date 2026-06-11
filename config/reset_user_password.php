<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

if ($argc !== 3) {
    echo "Usage: php config/reset_user_password.php username newPassword" . PHP_EOL;
    exit(1);
}

[$script, $username, $newPassword] = $argv;

if (trim($username) === '' || $newPassword === '') {
    echo "Username and new password are required." . PHP_EOL;
    exit(1);
}

$pdo = getDatabaseConnection();
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$pdo->beginTransaction();

$statement = $pdo->prepare('UPDATE users SET password = :password WHERE username = :username');
$statement->execute([
    'password' => $passwordHash,
    'username' => $username,
]);

if ($statement->rowCount() === 0) {
    $pdo->rollBack();
    echo "No user found with username: {$username}" . PHP_EOL;
    exit(1);
}

$completeRequest = $pdo->prepare(
    'UPDATE password_reset_requests pr
     INNER JOIN users u ON u.id = pr.user_id
     SET pr.status = "completed", pr.completed_at = NOW()
     WHERE u.username = :username AND pr.status = "pending"'
);
$completeRequest->execute(['username' => $username]);

$pdo->commit();

echo "Password reset successfully for: {$username}" . PHP_EOL;
