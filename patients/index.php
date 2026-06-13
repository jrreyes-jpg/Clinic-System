<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

$user = currentUser();
$search = trim((string) ($_GET['search'] ?? ''));
$patients = listPatients($search);
$error = getFlashError();
$success = getFlashSuccess();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page brand-page">
    <main class="dashboard-shell dashboard-shell-wide">
        <section class="clinic-cover">
            <div class="clinic-logo" aria-hidden="true">
                <img src="../assets/img/ac-ave-logo.jpg" alt="">
            </div>
            <div>
                <p class="eyebrow">AC Ave. Dental Clinic</p>
                <h1>Patient Management</h1>
                <p class="muted">Add, search, update, and archive patient records.</p>
            </div>
        </section>

        <section class="dashboard-panel">
            <div class="page-header">
                <div>
                    <p class="eyebrow"><?= e(ucfirst((string) $user['role'])) ?></p>
                    <h1>Patients</h1>
                    <p class="muted"><?= count($patients) ?> active record<?= count($patients) === 1 ? '' : 's' ?></p>
                </div>
                <div class="dashboard-actions">
                    <a class="button" href="create.php">Add Patient</a>
                    <a class="button button-light" href="../<?= e(rolePath((string) $user['role'])) ?>">Dashboard</a>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success) ?></div>
            <?php endif; ?>

            <form class="search-bar" action="index.php" method="GET">
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search patient no, name, contact, or email">
                <button class="button button-small" type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="button button-light button-small" href="index.php">Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Patient No.</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($patients === []): ?>
                            <tr>
                                <td colspan="7">No patient records found.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?= e($patient['patient_no']) ?></td>
                                <td><?= e($patient['fullname']) ?></td>
                                <td><?= e((string) $patient['age']) ?></td>
                                <td><?= e($patient['gender']) ?></td>
                                <td><?= e($patient['contact_number']) ?></td>
                                <td><?= e($patient['email'] ?? '') ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="button button-small button-light" href="view.php?id=<?= e((string) $patient['id']) ?>">View</a>
                                        <a class="button button-small" href="edit.php?id=<?= e((string) $patient['id']) ?>">Edit</a>
                                        <form action="archive.php" method="POST" onsubmit="return confirm('Archive this patient record?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                            <input type="hidden" name="id" value="<?= e((string) $patient['id']) ?>">
                                            <button class="button button-small button-secondary" type="submit">Archive</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
