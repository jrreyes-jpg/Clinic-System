<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

$patientId = (int) ($_GET['id'] ?? 0);
$patient = $patientId > 0 ? findPatientById($patientId) : null;

if ($patient === null) {
    setFlashError('Patient record not found.');
    redirect('index.php');
}

$csrfToken = generateCsrfToken();
$error = getFlashError();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page brand-page">
    <main class="dashboard-shell">
        <section class="dashboard-panel">
            <div class="page-header">
                <div>
                    <p class="eyebrow"><?= e($patient['patient_no']) ?></p>
                    <h1>Edit Patient</h1>
                    <p class="muted"><?= e($patient['fullname']) ?></p>
                </div>
                <a class="button button-light" href="view.php?id=<?= e((string) $patient['id']) ?>">Back</a>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form class="admin-form" action="update.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= e((string) $patient['id']) ?>">
                <?php require __DIR__ . '/form.php'; ?>
                <button class="button" type="submit">Update Patient</button>
            </form>
        </section>
    </main>
</body>
</html>
