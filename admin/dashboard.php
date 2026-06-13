<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$user = currentUser();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AC Ave. Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        window.dashboardConfig = {
            csrfToken: '<?= e($csrfToken) ?>',
            defaultSection: 'dashboard'
        };
    </script>
    <script src="../assets/js/dashboard.js" defer></script>
</head>
<body class="admin-dashboard-body">
    <div class="admin-layout spa-layout">
        <aside class="admin-sidebar fixed-sidebar" aria-label="Admin navigation">
            <div class="sidebar-brand">
                <img src="../assets/img/ac-ave-logo.jpg" alt="AC Ave. Dental Clinic">
                <div>
                    <strong>AC Ave.</strong>
                    <span>Dental Clinic</span>
                </div>
            </div>

            <nav class="sidebar-nav" data-dashboard-nav>
                <button class="active" type="button" data-section="dashboard">
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </button>
                <button type="button" data-section="patients">
                    <i class="fa-solid fa-hospital-user" aria-hidden="true"></i>
                    <span>Patients</span>
                </button>
                <button type="button" data-section="appointments">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    <span>Appointments</span>
                </button>
                <button type="button" data-section="users">
                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                    <span>Users</span>
                </button>
                <button type="button" data-section="reports">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    <span>Reports</span>
                </button>
                <button type="button" data-section="settings">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    <span>Settings</span>
                </button>
            </nav>

            <a class="sidebar-logout" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Logout</span>
            </a>
        </aside>

        <main class="admin-main spa-main">
            <header class="admin-topbar">
                <div>
                    <p class="eyebrow">AC Ave. Dental Clinic</p>
                    <h1 id="sectionTitle">Dashboard</h1>
                    <p class="muted" id="sectionSubtitle">Loading clinic overview...</p>
                </div>

                <section class="admin-profile" aria-label="Admin profile">
                    <div class="profile-avatar" aria-hidden="true">
                        <?= e(strtoupper(substr((string) ($user['fullname'] ?? 'A'), 0, 1))) ?>
                    </div>
                    <div>
                        <strong><?= e($user['fullname'] ?? 'Admin') ?></strong>
                        <span><?= e($user['email'] ?? 'Administrator') ?></span>
                    </div>
                </section>
            </header>

            <section class="spa-content is-loading" id="dashboardContent" aria-live="polite">
                <div class="section-loader">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    <span>Loading section...</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
