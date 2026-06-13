<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$section = (string) ($_GET['section'] ?? 'dashboard');
$csrfToken = generateCsrfToken();

function sectionHeader(string $title, string $subtitle): void
{
    echo '<div class="section-meta" data-title="' . e($title) . '" data-subtitle="' . e($subtitle) . '"></div>';
}

if ($section === 'dashboard') {
    $totalPatients = countActivePatients();
    $appointmentsToday = countAppointmentsToday();
    $activeUsers = countActiveUsers();
    $recentActivities = recentDashboardActivities();
    sectionHeader('Dashboard', 'Clinic overview and daily activity.');
    ?>
    <section class="dashboard-stat-grid">
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div><div><span>Total Patients</span><strong><?= e((string) $totalPatients) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><span>Appointments Today</span><strong><?= e((string) $appointmentsToday) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-users-gear"></i></div><div><span>Active Users</span><strong><?= e((string) $activeUsers) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div><span>Recent Activities</span><strong><?= e((string) count($recentActivities)) ?></strong></div></article>
    </section>
    <section class="admin-content-grid">
        <article class="dashboard-card">
            <div class="card-header"><div><h2>Recent Activities</h2><p class="muted">Latest records and system actions.</p></div><i class="fa-solid fa-bolt"></i></div>
            <div class="activity-list">
                <?php if ($recentActivities === []): ?><p class="muted">No recent activities yet.</p><?php endif; ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item"><div class="activity-icon"><i class="fa-solid <?= e($activity['icon']) ?>"></i></div><div><strong><?= e($activity['label']) ?></strong><span><?= e($activity['title']) ?> - <?= e($activity['meta']) ?></span></div><time><?= e(date('M d', strtotime((string) $activity['created_at']))) ?></time></div>
                <?php endforeach; ?>
            </div>
        </article>
        <article class="dashboard-card quick-actions-card">
            <div class="card-header"><div><h2>Quick Actions</h2><p class="muted">Open common workflows here.</p></div><i class="fa-solid fa-wand-magic-sparkles"></i></div>
            <div class="quick-action-list">
                <button type="button" data-section="patients" data-open-create="patient"><i class="fa-solid fa-user-plus"></i><span>Add Patient</span></button>
                <button type="button" data-section="appointments"><i class="fa-solid fa-calendar-plus"></i><span>Schedule Appointment</span></button>
                <button type="button" data-section="users"><i class="fa-solid fa-user-nurse"></i><span>Manage Users</span></button>
            </div>
        </article>
    </section>
    <?php
    exit;
}

if ($section === 'patients') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $patients = listPatients($search);
    sectionHeader('Patients', 'Search, add, edit, and archive patient records.');
    ?>
    <section class="dashboard-card">
        <div class="card-header">
            <div><h2>Patient Records</h2><p class="muted"><?= count($patients) ?> active record<?= count($patients) === 1 ? '' : 's' ?></p></div>
            <button class="button button-small" type="button" data-toggle-panel="patientCreatePanel"><i class="fa-solid fa-plus"></i> Add Patient</button>
        </div>
        <form class="search-bar spa-search" data-section-search="patients">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search patient no, name, contact, or email">
            <button class="button button-small" type="submit">Search</button>
        </form>
        <div class="inline-panel" id="patientCreatePanel" hidden>
            <form class="admin-form ajax-form" data-action="create_patient">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <?php $patient = []; require __DIR__ . '/../patients/form.php'; ?>
                <button class="button" type="submit">Save Patient</button>
            </form>
        </div>
        <div class="inline-panel" id="patientEditPanel" hidden>
            <form class="admin-form ajax-form" data-action="update_patient">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="editPatientId">
                <div class="form-grid">
                    <div class="form-group"><label>Full Name</label><input name="fullname" id="editPatientFullname" required></div>
                    <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" id="editPatientBirthdate" required></div>
                    <div class="form-group"><label>Gender</label><select name="gender" id="editPatientGender" required><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                    <div class="form-group"><label>Contact Number</label><input name="contact_number" id="editPatientContact" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="editPatientEmail"></div>
                    <div class="form-group form-group-wide"><label>Address</label><input name="address" id="editPatientAddress"></div>
                </div>
                <button class="button" type="submit">Update Patient</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="compact-table">
                <thead><tr><th>No.</th><th>Name</th><th>Age</th><th>Gender</th><th>Contact</th><th>Email</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($patients === []): ?><tr><td colspan="7">No patient records found.</td></tr><?php endif; ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr><td><?= e($patient['patient_no']) ?></td><td><?= e($patient['fullname']) ?></td><td><?= e((string) $patient['age']) ?></td><td><?= e($patient['gender']) ?></td><td><?= e($patient['contact_number']) ?></td><td><?= e($patient['email'] ?? '') ?></td><td><div class="row-actions"><button class="button button-small button-light" type="button" data-view-patient='<?= e(json_encode($patient)) ?>'>View</button><button class="button button-small" type="button" data-edit-patient='<?= e(json_encode($patient)) ?>'>Edit</button><button class="button button-small button-secondary" type="button" data-archive-patient="<?= e((string) $patient['id']) ?>">Archive</button></div></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    exit;
}

if ($section === 'appointments') {
    $patients = listPatients();
    $appointments = listAppointments();
    sectionHeader('Appointments', 'Schedule and update patient visits without leaving the dashboard.');
    ?>
    <section class="admin-content-grid">
        <article class="dashboard-card">
            <div class="card-header"><div><h2>Schedule Appointment</h2><p class="muted">Create an appointment for an existing patient.</p></div><i class="fa-solid fa-calendar-plus"></i></div>
            <form class="admin-form ajax-form" data-action="create_appointment">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Patient</label><select name="patient_id" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?= e((string) $patient['id']) ?>"><?= e($patient['patient_no'] . ' - ' . $patient['fullname']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Service</label><input name="service_type" placeholder="Oral Prophylaxis" required></div>
                    <div class="form-group"><label>Date</label><input type="date" name="appointment_date" required></div>
                    <div class="form-group"><label>Time</label><input type="time" name="appointment_time" required></div>
                    <div class="form-group"><label>Status</label><select name="status"><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                    <div class="form-group form-group-wide"><label>Notes</label><input name="notes" placeholder="Optional notes"></div>
                </div>
                <button class="button" type="submit">Save Appointment</button>
            </form>
        </article>
        <article class="dashboard-card">
            <div class="card-header"><div><h2>Appointment List</h2><p class="muted"><?= count($appointments) ?> appointment<?= count($appointments) === 1 ? '' : 's' ?></p></div><i class="fa-solid fa-list-check"></i></div>
            <div class="activity-list">
                <?php if ($appointments === []): ?><p class="muted">No appointments yet.</p><?php endif; ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="activity-item"><div class="activity-icon"><i class="fa-solid fa-tooth"></i></div><div><strong><?= e($appointment['patient_name']) ?></strong><span><?= e($appointment['service_type']) ?> - <?= e($appointment['appointment_date']) ?> <?= e(substr((string) $appointment['appointment_time'], 0, 5)) ?> - <?= e(ucfirst($appointment['status'])) ?></span></div><button class="button button-small button-light" type="button" data-complete-appointment="<?= e((string) $appointment['id']) ?>">Complete</button></div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
    <?php
    exit;
}

if ($section === 'users') {
    $users = listReceptionists();
    sectionHeader('Users', 'Manage receptionist accounts and password access.');
    ?>
    <section class="dashboard-card">
        <div class="card-header"><div><h2>Receptionists</h2><p class="muted"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></p></div><button class="button button-small" type="button" data-toggle-panel="userCreatePanel"><i class="fa-solid fa-plus"></i> Add User</button></div>
        <div class="inline-panel" id="userCreatePanel" hidden>
            <form class="admin-form ajax-form" data-action="create_user">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-grid"><div class="form-group"><label>Full Name</label><input name="fullname" required></div><div class="form-group"><label>Username</label><input name="username" required></div><div class="form-group"><label>Email</label><input type="email" name="email" required></div><div class="form-group"><label>Mobile</label><input name="mobile"></div><div class="form-group"><label>Temporary Password</label><input type="password" name="password" minlength="8" required></div></div>
                <button class="button" type="submit">Create Receptionist</button>
            </form>
        </div>
        <div class="table-wrap"><table class="compact-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Mobile</th></tr></thead><tbody><?php foreach ($users as $account): ?><tr><td><?= e($account['fullname']) ?></td><td><?= e($account['username']) ?></td><td><?= e($account['email']) ?></td><td><?= e($account['mobile'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <?php
    exit;
}

if ($section === 'reports') {
    sectionHeader('Reports', 'High-level clinic summaries.');
    ?>
    <section class="dashboard-stat-grid"><article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div><div><span>Active Patients</span><strong><?= e((string) countActivePatients()) ?></strong></div></article><article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><span>Today</span><strong><?= e((string) countAppointmentsToday()) ?></strong></div></article><article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div><span>Users</span><strong><?= e((string) countActiveUsers()) ?></strong></div></article></section>
    <?php
    exit;
}

sectionHeader('Settings', 'System preferences and account information.');
?>
<section class="dashboard-card">
    <div class="card-header"><div><h2>Settings</h2><p class="muted">More configurable clinic preferences can be added here.</p></div><i class="fa-solid fa-gear"></i></div>
    <div class="settings-grid">
        <div class="detail-panel">
            <span>Clinic</span>
            <p>AC Ave. Dental Clinic</p>
        </div>

        <form class="admin-form ajax-form logo-settings-form" data-action="update_logo" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div class="logo-preview">
                <img src="<?= e(clinicLogoUrl('../')) ?>" alt="Current clinic logo" data-clinic-logo-preview>
            </div>
            <div class="form-group">
                <label for="clinic_logo">Clinic Logo</label>
                <input type="file" id="clinic_logo" name="clinic_logo" accept="image/jpeg,image/png,image/webp" required>
            </div>
            <button class="button" type="submit">Update Logo</button>
        </form>
    </div>
</section>
