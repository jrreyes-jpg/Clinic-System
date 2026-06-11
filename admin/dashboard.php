<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Clinic Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dashboard-page brand-page">
    <main class="dashboard-shell">
        <section class="clinic-cover">
            <div class="clinic-logo" aria-hidden="true">
                <span>AC</span>
            </div>
            <div>
                <p class="eyebrow">AC Ave. Dental Clinic</p>
                <h1>Clinic System</h1>
                <p class="muted">General Dentistry, Orthodontics, Oral Surgery</p>
            </div>
        </section>

        <section class="dashboard-panel">
            <div>
                <p class="eyebrow">Administrator</p>
                <h1>Admin Dashboard</h1>
                <p class="muted">Welcome, <?= e($user['fullname'] ?? 'Admin') ?>.</p>
            </div>

            <div class="dashboard-grid">
                <article class="stat-card">
                    <span>Role</span>
                    <strong>Admin</strong>
                </article>
                <article class="stat-card">
                    <span>Access</span>
                    <strong>Full System</strong>
                </article>
            </div>

            <div class="dashboard-actions">
                <a class="button" href="users.php">Manage Users</a>
                <a class="button button-secondary" href="../logout.php">Logout</a>
            </div>
        </section>
    </main>
</body>
</html>
