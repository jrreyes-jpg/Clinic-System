<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

$csrfToken = generateCsrfToken();
$error = getFlashError();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patient | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page brand-page">
    <main class="dashboard-shell">
        <section class="dashboard-panel">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Patient Management</p>
                    <h1>Add Patient</h1>
                    <p class="muted">Create a new patient record.</p>
                </div>
                <a class="button button-light" href="index.php">Back</a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="admin-form" action="store.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <?php require __DIR__ . '/form.php'; ?>
                <button class="button" type="submit">Save Patient</button>
            </form>
        </section>
    </main>
</body>
</html>
