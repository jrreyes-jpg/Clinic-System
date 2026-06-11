<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('receptionist');
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard | Clinic Management System</title>
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
                <h1>Front Desk</h1>
                <p class="muted">General Dentistry, Orthodontics, Oral Surgery</p>
            </div>
        </section>

        <section class="dashboard-panel">
            <div>
                <p class="eyebrow">Receptionist</p>
                <h1>Receptionist Dashboard</h1>
                <p class="muted">Welcome, <?= e($user['fullname'] ?? 'Receptionist') ?>.</p>
            </div>

            <div class="dashboard-grid">
                <article class="stat-card">
                    <span>Role</span>
                    <strong>Receptionist</strong>
                </article>
                <article class="stat-card">
                    <span>Access</span>
                    <strong>Front desk</strong>
                </article>
            </div>

            <a class="button button-secondary" href="../logout.php">Logout</a>
        </section>
    </main>
</body>
</html>
