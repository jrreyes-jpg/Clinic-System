<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('forgot_password.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashError('Your session expired. Please try again.');
    redirect('forgot_password.php');
}

$username = trim((string) ($_POST['username'] ?? ''));

if ($username !== '') {
    $user = findUserByUsernameOrEmail($username);

    if ($user !== null) {
        $token = createPasswordResetRequest((int) $user['id']);

        if ($token !== null && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $resetLink = APP_URL . '/reset_password.php?token=' . urlencode($token);
            $message = '<p>Hello ' . e($user['fullname']) . ',</p>'
                . '<p>Click the link below to reset your Clinic Management System password. This link expires in 30 minutes.</p>'
                . '<p><a href="' . e($resetLink) . '">Reset Password</a></p>'
                . '<p>If you did not request this, you can ignore this email.</p>';

            smtpSendMail($user['email'], $user['fullname'], 'Clinic Password Reset', $message);
        }
        createNotification(null, 'password_reset_requested', 'Password reset requested for ' . $user['username'], ['user_id' => (int) $user['id']]);
        createAuditLog(null, 'password reset requested', ['target_user_id' => (int) $user['id'], 'username' => $user['username']]);
    }
}

// Generic response prevents attackers from checking which usernames exist.
setFlashError('If that username or email exists, a password reset link has been sent.');
redirect('forgot_password.php');
