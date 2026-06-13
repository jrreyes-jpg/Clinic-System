<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

$patientId = (int) ($_GET['id'] ?? 0);
$patient = $patientId > 0 ? findPatientById($patientId) : null;
$success = getFlashSuccess();
$error = getFlashError();

if ($patient === null) {
    setFlashError('Patient record not found.');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page brand-page">
    <main class="dashboard-shell">
        <section class="dashboard-panel">
            <div class="page-header">
                <div>
                    <p class="eyebrow"><?= e($patient['patient_no']) ?></p>
                    <h1><?= e($patient['fullname']) ?></h1>
                    <p class="muted">Patient profile and contact information.</p>
                </div>
                <div class="dashboard-actions">
                    <a class="button" href="edit.php?id=<?= e((string) $patient['id']) ?>">Edit</a>
                    <a class="button button-light" href="index.php">Back</a>
                </div>
            </div>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <article class="stat-card">
                    <span>Birthdate</span>
                    <strong><?= e($patient['birthdate']) ?></strong>
                </article>
                <article class="stat-card">
                    <span>Age</span>
                    <strong><?= e((string) $patient['age']) ?></strong>
                </article>
                <article class="stat-card">
                    <span>Gender</span>
                    <strong><?= e($patient['gender']) ?></strong>
                </article>
                <article class="stat-card">
                    <span>Contact</span>
                    <strong><?= e($patient['contact_number']) ?></strong>
                </article>
                <article class="stat-card">
                    <span>Email</span>
                    <strong><?= e($patient['email'] ?? '') ?></strong>
                </article>
                <article class="stat-card">
                    <span>Created</span>
                    <strong><?= e($patient['created_at']) ?></strong>
                </article>
            </div>

            <article class="detail-panel">
                <span>Address</span>
                <p><?= e($patient['address'] ?? 'No address recorded.') ?></p>
            </article>
        </section>
    </main>
</body>
</html>
