<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    redirectByRole((string) $_SESSION['role']);
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$request = $token !== '' ? findPasswordResetByToken($token) : null;
$error = getFlashError();
$success = getFlashSuccess();
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashError('Your session expired. Please try again.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($request === null) {
        setFlashError('Password reset link is invalid or expired.');
        redirect('forgot_password.php');
    }

    if (strlen($password) < 8) {
        setFlashError('Password must be at least 8 characters.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    if ($password !== $confirmPassword) {
        setFlashError('Passwords do not match.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    if (resetPasswordByToken($token, $password)) {
        setFlashSuccess('Password reset successfully. You can now log in.');
        redirect('reset_password.php');
    }

    setFlashError('Password reset link is invalid or expired.');
    redirect('forgot_password.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Clinic Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="login-page">
        <section class="login-card" aria-labelledby="reset-title">
            <div class="brand-mark" aria-hidden="true">
                <span>+</span>
            </div>

            <div class="login-heading">
                <p class="eyebrow">Account Recovery</p>
                <h1 id="reset-title">Reset Password</h1>
                <p>Create a new password for your clinic account.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success) ?></div>
                <a class="button" href="login.php">Back to Login</a>
            <?php elseif ($request === null): ?>
                <div class="alert" role="alert">Password reset link is invalid or expired.</div>
                <a class="button" href="forgot_password.php">Request New Link</a>
            <?php else: ?>
                <form class="login-form" action="reset_password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" minlength="8" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>

                    <button class="button" type="submit">Update Password</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
