<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

startSecureSession();

if (isLoggedIn()) {
    redirectByRole((string) $_SESSION['role']);
}

$error = getFlashError();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Clinic Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="login-page">
        <section class="login-card" aria-labelledby="forgot-title">
            <div class="brand-mark" aria-hidden="true">
                <span>+</span>
            </div>

            <div class="login-heading">
                <p class="eyebrow">Account Recovery</p>
                <h1 id="forgot-title">Forgot Password</h1>
                <p>Submit your username or email to receive a secure password reset link.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="login-form" action="request_password_reset.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        autocomplete="username"
                        placeholder="Enter your username or Gmail"
                        required
                    >
                </div>

                <button class="button" type="submit">Request Password Reset</button>
                <a class="button button-light" href="login.php">Back to Login</a>
            </form>
        </section>
    </main>
</body>
</html>
