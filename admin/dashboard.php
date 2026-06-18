<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$user = currentUser();
$csrfToken = generateCsrfToken();
$profilePhoto = profilePhotoUrl($user, '../');
$initial = strtoupper(substr((string) ($user['fullname'] ?? 'A'), 0, 1));
$sections = [
    'dashboard' => ['Dashboard', 'Loading clinic overview...'],
    'patients' => ['Patients', 'Search, add, edit, and archive patient records.'],
    'appointments' => ['Appointments', 'Book, reschedule, cancel, and monitor patient visits.'],
    'billing' => ['Billing', 'Generate bills, record payments, and print receipts.'],
    'services' => ['Services', 'Create, update, and price clinic treatments.'],
    'records' => ['Dental Records', 'All dental record entries for your patients.'],
    'users' => ['Users', 'Manage receptionist accounts and password access.'],
    'reports' => ['Reports', 'High-level clinic summaries.'],
    'settings' => ['Settings', 'System preferences and account information.'],
];
$requestedSection = (string) ($_GET['section'] ?? 'dashboard');
$initialSection = array_key_exists($requestedSection, $sections) ? $requestedSection : 'dashboard';
$initialTitle = $sections[$initialSection][0];
$initialSubtitle = $sections[$initialSection][1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AC Ave. Dental Clinic</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= e((string) filemtime(__DIR__ . '/../assets/css/style.css')) ?>">
    <script>
        (() => {
            const validSections = <?= json_encode(array_keys($sections)) ?>;
            const url = new URL(window.location.href);
            const hasSection = validSections.includes(url.searchParams.get('section'));
            const hashSection = window.location.hash ? decodeURIComponent(window.location.hash.slice(1)) : '';
            const storedSection = localStorage.getItem('adminActiveSection') || '';
            const fallbackSection = validSections.includes(hashSection) ? hashSection : storedSection;

            if (!hasSection && validSections.includes(fallbackSection) && fallbackSection !== 'dashboard') {
                url.searchParams.set('section', fallbackSection);
                url.hash = '';
                window.location.replace(url.toString());
            }
        })();

        window.dashboardConfig = {
            csrfToken: '<?= e($csrfToken) ?>',
            defaultSection: '<?= e($initialSection) ?>'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    <script src="../assets/js/dashboard.js" defer></script>
</head>
<body class="admin-dashboard-body">
    <div class="admin-layout spa-layout">
        <aside class="admin-sidebar fixed-sidebar" aria-label="Admin navigation">
            <div class="sidebar-brand">
                <button class="sidebar-home" type="button" data-dashboard-home>
                    <img src="<?= e(clinicLogoUrl('../')) ?>" alt="AC Ave. Dental Clinic" data-clinic-logo>
                    <span>
                        <strong>AC Ave.</strong>
                        <span>Dental Clinic</span>
                    </span>
                </button>
                <button class="sidebar-toggle sidebar-brand-toggle" type="button" aria-label="Close sidebar" title="Close sidebar" data-sidebar-toggle>
                    <i class="fa-solid fa-table-columns" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="sidebar-nav" data-dashboard-nav>
                <button class="<?= $initialSection === 'dashboard' ? 'active' : '' ?>" type="button" data-section="dashboard">
                    <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </button>
                <button class="<?= $initialSection === 'patients' ? 'active' : '' ?>" type="button" data-section="patients">
                    <i class="fa-solid fa-hospital-user" aria-hidden="true"></i>
                    <span>Patients</span>
                </button>
                <button class="<?= $initialSection === 'appointments' ? 'active' : '' ?>" type="button" data-section="appointments">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    <span>Appointments</span>
                </button>
                <button class="<?= $initialSection === 'billing' ? 'active' : '' ?>" type="button" data-section="billing">
                    <i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>
                    <span>Billing</span>
                </button>
                <button class="<?= $initialSection === 'services' ? 'active' : '' ?>" type="button" data-section="services">
                    <i class="fa-solid fa-tooth" aria-hidden="true"></i>
                    <span>Services</span>
                </button>
                <button class="<?= $initialSection === 'records' ? 'active' : '' ?>" type="button" data-section="records">
                    <i class="fa-solid fa-notes-medical" aria-hidden="true"></i>
                    <span>Dental Records</span>
                </button>
                <button class="<?= $initialSection === 'users' ? 'active' : '' ?>" type="button" data-section="users">
                    <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                    <span>Users</span>
                </button>
                <button class="<?= $initialSection === 'reports' ? 'active' : '' ?>" type="button" data-section="reports">
                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                    <span>Reports</span>
                </button>
                <button class="<?= $initialSection === 'settings' ? 'active' : '' ?>" type="button" data-section="settings">
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
                <div class="topbar-title-row">
                    <div>
                        <p class="eyebrow">AC Ave. Dental Clinic</p>
                        <h1 id="sectionTitle"><?= e($initialTitle) ?></h1>
                        <p class="muted" id="sectionSubtitle"><?= e($initialSubtitle) ?></p>
                    </div>
                </div>

                <div class="topbar-actions">
                    <div class="live-clock" aria-live="polite">
                        <strong data-live-time>--:--</strong>
                        <span data-live-date>Loading date...</span>
                    </div>

                    <div class="notifications-wrap">
                        <button class="icon-button" type="button" aria-label="Notifications" aria-haspopup="menu" aria-expanded="false" id="notificationsToggle">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
                            <span class="notification-badge" id="notificationCount" hidden>0</span>
                        </button>
                        <div class="notifications-dropdown" id="notificationsDropdown" hidden>
                            <div class="notifications-header">
                                <strong>Notifications</strong>
                                <button class="link-button" type="button" id="markAllRead">Mark all read</button>
                            </div>
                            <div class="notifications-list" id="notificationsList">
                                <p class="muted">Loading...</p>
                            </div>
                            <div class="notifications-footer">
                                <a href="#" id="viewAllNotifications">View all</a>
                            </div>
                        </div>
                    </div>

                    <div class="profile-menu-wrap">
                        <button class="admin-profile-button" type="button" aria-label="Open profile menu" aria-expanded="false" data-profile-toggle>
                            <span class="profile-avatar profile-avatar-round" data-profile-avatar>
                                <?php if ($profilePhoto !== ''): ?>
                                    <img src="<?= e($profilePhoto) ?>" alt="<?= e($user['fullname'] ?? 'Admin') ?>">
                                <?php else: ?>
                                    <?= e($initial) ?>
                                <?php endif; ?>
                            </span>
                            <span class="profile-caret"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
                        </button>

                        <div class="profile-dropdown" data-profile-dropdown hidden>
                            <div class="profile-dropdown-head">
                                <span class="profile-avatar profile-avatar-round" data-profile-avatar>
                                    <?php if ($profilePhoto !== ''): ?>
                                        <img src="<?= e($profilePhoto) ?>" alt="<?= e($user['fullname'] ?? 'Admin') ?>">
                                    <?php else: ?>
                                        <?= e($initial) ?>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <strong data-profile-name><?= e($user['fullname'] ?? 'Admin') ?></strong>
                                    <span data-profile-email><?= e($user['email'] ?? 'Administrator') ?></span>
                                </div>
                            </div>
                            <button type="button" data-profile-modal-open><i class="fa-solid fa-user-pen" aria-hidden="true"></i> Edit Profile</button>
                            <button type="button" data-section="settings"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</button>
                            <a href="../logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <section class="spa-content is-loading" id="dashboardContent" aria-live="polite">
                <div class="section-loader">
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    <span>Loading section...</span>
                </div>
            </section>
        </main>

        <div class="profile-modal-backdrop" data-profile-modal hidden>
            <section class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
                <div class="profile-modal-header">
                    <div>
                        <p class="eyebrow">Account</p>
                        <h2 id="profileModalTitle">Edit Profile</h2>
                    </div>
                    <button class="icon-button" type="button" aria-label="Close profile modal" data-profile-modal-close>
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <form class="admin-form profile-form" data-profile-form enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="profile-photo-field">
                        <span class="profile-avatar profile-avatar-large" data-profile-preview>
                            <?php if ($profilePhoto !== ''): ?>
                                <img src="<?= e($profilePhoto) ?>" alt="<?= e($user['fullname'] ?? 'Admin') ?>">
                            <?php else: ?>
                                <?= e($initial) ?>
                            <?php endif; ?>
                        </span>
                        <div>
                            <label for="profile_photo">Profile Picture</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
                            <p class="muted">JPG, PNG, or WEBP only.</p>
                        </div>
                    </div>

                    <div class="avatar-cropper" data-avatar-cropper hidden>
                        <div class="avatar-crop-stage" data-avatar-crop-stage>
                            <img alt="Profile crop preview" data-avatar-crop-image>
                            <span class="avatar-crop-mask" aria-hidden="true"></span>
                        </div>
                        <div class="avatar-crop-controls">
                            <label for="profile_photo_zoom">Zoom</label>
                            <input type="range" id="profile_photo_zoom" min="1" max="3" step="0.01" value="1" data-avatar-crop-zoom>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label for="profile_fullname">Full Name</label><input id="profile_fullname" name="fullname" value="<?= e($user['fullname'] ?? '') ?>" required></div>
                        <div class="form-group"><label for="profile_email">Email</label><input type="email" id="profile_email" name="email" value="<?= e($user['email'] ?? '') ?>" required></div>
                        <div class="form-group"><label for="profile_mobile">Mobile</label><input id="profile_mobile" name="mobile" value="<?= e($user['mobile'] ?? '') ?>"></div>
                    </div>

                    <button class="button" type="submit">Save Profile</button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
