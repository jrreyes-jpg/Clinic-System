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
    <title>Login | AC Ave. Dental Clinic</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/login.js" defer></script>
</head>
<body class="brand-page login-intro-active">
    <div class="intro-screen" aria-hidden="true">
        <div class="intro-logo">
            <img src="assets/img/ac-ave-logo.jpg" alt="">
        </div>
        <div class="intro-text">
            <strong>AC Ave. Dental Clinic</strong>
            <span>Clinic Management System</span>
        </div>
    </div>

    <main class="login-page portal-login">
        <section class="portal-brand-panel" aria-label="AC Ave. Dental Clinic system welcome">
            <div class="portal-pattern" aria-hidden="true"></div>
            <div class="clinic-logo portal-logo" aria-hidden="true">
                <img src="assets/img/ac-ave-logo.jpg" alt="">
            </div>
            <p class="eyebrow">AC Ave. Dental Clinic</p>
            <h1>Clinic Management System</h1>
        </section>

        <section class="login-card glass-login-card" aria-labelledby="login-title">
            <div class="login-brand-row">
                <div class="brand-mark dental-mark" aria-hidden="true">
                    <img src="assets/img/ac-ave-logo.jpg" alt="">
                </div>
                <div>
                    <strong>AC Ave. Dental Clinic</strong>
                </div>
            </div>

            <div class="login-heading">
                <h2 id="login-title">Welcome Back</h2>
                <p>Sign in to continue to your clinic dashboard.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="login-form" action="authenticate.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="form-group floating-field">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        autocomplete="username"
                        placeholder=" "
                        required
                    >
                    <label for="username">Username</label>
                    <small class="field-error" data-error-for="username"></small>
                </div>

                <div class="form-group floating-field">
                    <div class="password-control">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder=" "
                            required
                        >
                        <button type="button" class="toggle-password" aria-controls="password" aria-label="Show password">
                            <span class="eye-icon" aria-hidden="true"></span>
                        </button>
                        <label for="password">Password</label>
                    </div>
                    <small class="field-error" data-error-for="password"></small>
                </div>

                <div class="form-actions-row">
                    <a href="forgot_password.php">Forgot password?</a>
                </div>

                <button class="button login-submit" type="submit">
                    <span class="button-text">Login</span>
                    <span class="button-loader" aria-hidden="true"></span>
                </button>
            </form>
        </section>
    </main>
</body>
</html>
