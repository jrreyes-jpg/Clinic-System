<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashError('Your session expired. Please try again.');
    redirect('login.php');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    setFlashError('Username and password are required.');
    redirect('login.php');
}

$user = findUserByUsername($username);

if ($user === null || !password_verify($password, $user['password'])) {
    setFlashError('Invalid username or password.');
    redirect('login.php');
}

if (!in_array($user['role'], ['admin', 'receptionist'], true)) {
    setFlashError('Your account role is not allowed to access this system.');
    redirect('login.php');
}

createLoginSession($user);
redirectByRole($user['role']);
