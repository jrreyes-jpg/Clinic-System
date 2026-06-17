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

function patientNameParts(array $patient): array
{
    $first = (string) ($patient['first_name'] ?? '');
    $middle = (string) ($patient['middle_name'] ?? '');
    $last = (string) ($patient['last_name'] ?? '');
    $suffix = (string) ($patient['suffix'] ?? '');

    if ($first === '' && $last === '' && !empty($patient['fullname'])) {
        $parts = preg_split('/\s+/', trim((string) $patient['fullname'])) ?: [];
        $first = array_shift($parts) ?? '';
        $last = implode(' ', $parts);
    }

    return [$first, $middle, $last, $suffix];
}

function renderPatientTabletForm(array $patient = [], string $prefix = ''): void
{
    [$firstName, $middleName, $lastName, $suffix] = patientNameParts($patient);
    $hasHmo = ((string) ($patient['has_hmo'] ?? 'No')) === 'Yes';
    $idPrefix = $prefix === '' ? 'patient' : $prefix;
    $today = date('Y-m-d');
    ?>
    <input type="hidden" name="fullname" data-fullname-target value="<?= e($patient['fullname'] ?? '') ?>">
    <div class="tablet-tabs" data-tabs>
        <div class="tab-strip" role="tablist">
            <button class="active" type="button" data-tab-target="personal">Personal <span class="field-info" data-tip="Basic identity details and patient photo.">i</span></button>
            <button type="button" data-tab-target="contact">Contact <span class="field-info" data-tip="Phone, email, address, and emergency contact.">i</span></button>
            <button type="button" data-tab-target="hmo">HMO <span class="field-info" data-tip="Insurance details. HMO fields only show when Yes is selected.">i</span></button>
            <button type="button" data-tab-target="medical">Medical <span class="field-info" data-tip="Clinical notes used for safer patient handling.">i</span></button>
        </div>

        <div class="tab-panel active" data-tab-panel="personal">
            <div class="patient-photo-field">
                <span class="patient-photo-preview" data-patient-photo-preview>
                    <?php if (!empty($patient['patient_photo'])): ?><img src="../<?= e($patient['patient_photo']) ?>" alt="<?= e($patient['fullname'] ?? 'Patient') ?>"><?php else: ?><i class="fa-solid fa-user" aria-hidden="true"></i><?php endif; ?>
                </span>
                <div class="form-group">
                    <label>Patient Photo <span class="field-info" data-tip="Upload a patient photo or use tablet camera when available.">i</span></label>
                    <div class="patient-photo-actions">
                        <input type="file" name="patient_photo_upload" accept="image/jpeg,image/png,image/webp" capture="environment" data-patient-photo-input>
                        <button class="button button-small button-light" type="button" data-camera-start><i class="fa-solid fa-camera" aria-hidden="true"></i> Take Photo</button>
                    </div>
                    <div class="patient-camera" data-patient-camera hidden>
                        <video data-patient-camera-video playsinline autoplay muted></video>
                        <canvas data-patient-camera-canvas hidden></canvas>
                        <div class="patient-camera-actions">
                            <button class="button button-small" type="button" data-camera-capture>Capture</button>
                            <button class="button button-small button-light" type="button" data-camera-close>Close Camera</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tablet-form-grid">
                <div class="form-group"><label>First Name <span class="field-info" data-tip="Patient given name.">i</span></label><input name="first_name" data-name-part value="<?= e($firstName) ?>" required></div>
                <div class="form-group"><label>Middle Name <span class="field-info" data-tip="Optional middle name.">i</span></label><input name="middle_name" data-name-part value="<?= e($middleName) ?>"></div>
                <div class="form-group"><label>Last Name <span class="field-info" data-tip="Patient family name.">i</span></label><input name="last_name" data-name-part value="<?= e($lastName) ?>" required></div>
                <div class="form-group"><label>Suffix <span class="field-info" data-tip="Optional suffix like Jr., Sr., III.">i</span></label><input name="suffix" data-name-part value="<?= e($suffix) ?>" placeholder="Jr., III"></div>
                <div class="form-group"><label>Birthdate <span class="field-info" data-tip="Future dates are blocked. Age is calculated automatically.">i</span></label><input type="date" name="birthdate" data-birthdate max="<?= e($today) ?>" value="<?= e($patient['birthdate'] ?? '') ?>" required></div>
                <div class="form-group"><label>Age <span class="field-info" data-tip="Auto-calculated from birthdate.">i</span></label><input name="age_display" data-age-output value="<?= e((string) ($patient['age'] ?? '')) ?>" readonly></div>
                <div class="form-group"><label>Sex <span class="field-info" data-tip="Required patient sex field.">i</span></label><select name="gender" required><?php $gender = $patient['gender'] ?? ''; ?><option value="">Select</option><option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option><option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option><option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option></select></div>
            </div>
        </div>

        <div class="tab-panel" data-tab-panel="contact">
            <div class="tablet-form-grid">
                <div class="form-group"><label>Contact Number <span class="field-info" data-tip="Primary number for reminders and calls.">i</span></label><input name="contact_number" value="<?= e($patient['contact_number'] ?? '') ?>" required></div>
                <div class="form-group"><label>Email <span class="field-info" data-tip="Optional email for clinic communication.">i</span></label><input type="email" name="email" value="<?= e($patient['email'] ?? '') ?>"></div>
                <div class="form-group form-group-wide"><label>Address <span class="field-info" data-tip="Patient home or mailing address.">i</span></label><input name="address" value="<?= e($patient['address'] ?? '') ?>"></div>
                <div class="form-group"><label>Emergency Contact <span class="field-info" data-tip="Person to contact during urgent situations.">i</span></label><input name="emergency_contact" value="<?= e($patient['emergency_contact'] ?? '') ?>"></div>
                <div class="form-group"><label>Emergency Contact Number <span class="field-info" data-tip="Phone number of emergency contact.">i</span></label><input name="emergency_contact_number" value="<?= e($patient['emergency_contact_number'] ?? '') ?>"></div>
            </div>
        </div>

        <div class="tab-panel" data-tab-panel="hmo">
            <div class="segmented-field" data-hmo-toggle>
                <span>Has HMO? <span class="field-info" data-tip="Choose Yes only when patient will use HMO details for billing/coverage.">i</span></span>
                <label><input type="radio" name="has_hmo" value="Yes" <?= $hasHmo ? 'checked' : '' ?>><span>Yes</span></label>
                <label><input type="radio" name="has_hmo" value="No" <?= !$hasHmo ? 'checked' : '' ?>><span>No</span></label>
            </div>
            <div class="tablet-form-grid hmo-fields" data-hmo-fields <?= $hasHmo ? '' : 'hidden' ?>>
                <div class="form-group"><label>HMO Provider <span class="field-info" data-tip="Name of HMO provider.">i</span></label><input name="hmo_provider" value="<?= e($patient['hmo_provider'] ?? '') ?>"></div>
                <div class="form-group"><label>HMO Card Number <span class="field-info" data-tip="Card/member number printed on HMO card.">i</span></label><input name="hmo_card_number" value="<?= e($patient['hmo_card_number'] ?? '') ?>"></div>
                <div class="form-group"><label>Principal / Dependent <span class="field-info" data-tip="Whether patient is main member or dependent.">i</span></label><select name="hmo_type"><option value="">Select</option><?php $hmoType = $patient['hmo_type'] ?? ''; ?><option value="Principal" <?= $hmoType === 'Principal' ? 'selected' : '' ?>>Principal</option><option value="Dependent" <?= $hmoType === 'Dependent' ? 'selected' : '' ?>>Dependent</option></select></div>
                <div class="form-group"><label>Expiration Date <span class="field-info" data-tip="HMO validity expiration.">i</span></label><input type="date" name="hmo_expiration_date" value="<?= e($patient['hmo_expiration_date'] ?? '') ?>"></div>
                <div class="form-group form-group-wide"><label>HMO Card Upload <span class="field-info" data-tip="Photo or PDF copy of HMO card.">i</span></label><input type="file" name="hmo_card_upload" accept="image/jpeg,image/png,image/webp,application/pdf"><small class="muted">JPG, PNG, WEBP, or PDF.</small></div>
            </div>
        </div>

        <div class="tab-panel" data-tab-panel="medical">
            <div class="tablet-form-grid">
                <div class="form-group"><label>Allergies <span class="field-info" data-tip="Known allergies relevant to care.">i</span></label><textarea name="allergies" rows="2"><?= e($patient['allergies'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Medical Conditions <span class="field-info" data-tip="Existing conditions the dentist should know.">i</span></label><textarea name="medical_conditions" rows="2"><?= e($patient['medical_conditions'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Current Medications <span class="field-info" data-tip="Current medicines or supplements.">i</span></label><textarea name="current_medications" rows="2"><?= e($patient['current_medications'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Notes <span class="field-info" data-tip="Other medical reminders for the clinic team.">i</span></label><textarea name="medical_notes" rows="2"><?= e($patient['medical_notes'] ?? '') ?></textarea></div>
            </div>
        </div>
    </div>
    <?php
}

if ($section === 'dashboard') {
    $totalPatients = countActivePatients();
    $appointmentsToday = countAppointmentsToday();
    $pendingAppointments = countAppointmentsByStatus('pending');
    $confirmedAppointments = countAppointmentsByStatus('confirmed');
    $completedAppointments = countAppointmentsByStatus('completed');
    $noShowAppointments = countNoShowAppointments();
    $recentAppointments = recentAppointments(7);
    sectionHeader('Dashboard', 'Compact reception overview for today.');
    ?>
    <section class="dashboard-stat-grid dashboard-stat-grid-five">
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><span>Today's Appointments</span><strong><?= e((string) $appointmentsToday) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div><span>Pending Appointments</span><strong><?= e((string) $pendingAppointments) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div><div><span>Confirmed</span><strong><?= e((string) $confirmedAppointments) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><span>Completed Appointments</span><strong><?= e((string) $completedAppointments) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div><div><span>No Shows</span><strong><?= e((string) $noShowAppointments) ?></strong></div></article>
    </section>

    <section class="dashboard-card">
        <div class="card-header"><div><h2>Recent Appointments</h2><p class="muted">Fast scan list for receptionist follow-through.</p></div><i class="fa-solid fa-list-check"></i></div>
        <div class="tablet-list">
            <?php if ($recentAppointments === []): ?><p class="muted">No appointments yet.</p><?php endif; ?>
            <?php foreach ($recentAppointments as $appointment): ?>
                <article class="tablet-list-row">
                    <div>
                        <strong><?= e($appointment['patient_name']) ?></strong>
                        <span><?= e($appointment['patient_no']) ?> · <?= e($appointment['contact_number']) ?></span>
                    </div>
                    <div>
                        <strong><?= e(date('M d', strtotime((string) $appointment['appointment_date']))) ?> <?= e(substr((string) $appointment['appointment_time'], 0, 5)) ?></strong>
                        <span><?= e($appointment['service_type']) ?><?= $appointment['dentist'] ? ' · Dr. ' . e($appointment['dentist']) : '' ?></span>
                    </div>
                    <span class="status-badge status-<?= e($appointment['status']) ?>"><?= e(ucfirst($appointment['status'])) ?></span>
                </article>
                <?php endforeach; ?>
        </div>
    </section>
    <?php
    exit;
}

if ($section === 'patients') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $hmoFilter = trim((string) ($_GET['hmo'] ?? ''));
    $patients = listPatients($search);
    if ($hmoFilter !== '') {
        $patients = array_values(array_filter($patients, static function (array $patient) use ($hmoFilter): bool {
            $hasHmo = ((string) ($patient['has_hmo'] ?? 'No')) === 'Yes' ? 'Yes' : 'No';
            return $hasHmo === $hmoFilter;
        }));
    }
    sectionHeader('Patients', 'Search, add, edit, and archive patient records.');
    ?>
    <section class="dashboard-card receptionist-workspace patient-workspace">
        <div class="card-header">
            <div><h2>Patient Registration</h2><p class="muted"><?= count($patients) ?> matching patient<?= count($patients) === 1 ? '' : 's' ?></p></div>
            <button class="button button-small touch-button" type="button" data-toggle-panel="patientCreatePanel"><i class="fa-solid fa-plus"></i> New Patient</button>
        </div>

        <form class="search-bar spa-search patient-filter-bar" data-section-search="patients">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search ID, name, contact, or email">
            <select name="hmo">
                <option value="">All HMO</option>
                <option value="Yes" <?= $hmoFilter === 'Yes' ? 'selected' : '' ?>>With HMO</option>
                <option value="No" <?= $hmoFilter === 'No' ? 'selected' : '' ?>>No HMO</option>
            </select>
            <button class="button button-small touch-button" type="submit">Filter</button>
        </form>

        <div class="inline-panel tablet-form-panel" id="patientCreatePanel" hidden>
            <form class="admin-form ajax-form" data-action="create_patient">
                <div class="tablet-form-header">
                    <div><h3>New Patient</h3><p class="muted">Complete each tab, then save.</p></div>
                    <button class="icon-button" type="button" aria-label="Close patient form" data-panel-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <?php renderPatientTabletForm(); ?>
                <div class="form-actions"><button class="button touch-button" type="submit">Save Patient</button></div>
            </form>
        </div>

        <div class="inline-panel tablet-form-panel" id="patientEditPanel" hidden>
            <form class="admin-form ajax-form" data-action="update_patient">
                <div class="tablet-form-header">
                    <div><h3>Edit Patient</h3><p class="muted">Update only the details that changed.</p></div>
                    <button class="icon-button" type="button" aria-label="Close patient form" data-panel-close><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="editPatientId">
                <?php renderPatientTabletForm([], 'editPatient'); ?>
                <div class="form-actions"><button class="button touch-button" type="submit">Update Patient</button></div>
            </form>
        </div>

        <div class="patient-profile-panel" data-patient-profile hidden></div>

        <div class="table-wrap">
            <table class="compact-table tablet-table">
                <thead><tr><th>Patient ID</th><th>Name</th><th>Contact Number</th><th>HMO Status</th><th>No Show Count</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($patients === []): ?><tr><td colspan="6">No patient records found.</td></tr><?php endif; ?>
                    <?php foreach ($patients as $patient): ?>
                        <?php
                        $hasHmo = ((string) ($patient['has_hmo'] ?? 'No')) === 'Yes';
                        $patient['no_show_count'] = countPatientNoShows((int) $patient['id']);
                        ?>
                        <tr>
                            <td><?= e($patient['patient_no']) ?></td>
                            <td><strong><?= e($patient['fullname']) ?></strong><br><span class="muted"><?= e((string) $patient['age']) ?> · <?= e($patient['gender']) ?></span></td>
                            <td><?= e($patient['contact_number']) ?></td>
                            <td><span class="status-badge <?= $hasHmo ? 'status-completed' : 'status-pending' ?>"><?= $hasHmo ? 'With HMO' : 'No HMO' ?></span></td>
                            <td><?= e((string) $patient['no_show_count']) ?></td>
                            <td><div class="row-actions"><button class="button button-small button-light" type="button" data-view-patient='<?= e(json_encode($patient)) ?>'>View</button><button class="button button-small" type="button" data-edit-patient='<?= e(json_encode($patient)) ?>'>Edit</button><button class="button button-small button-secondary" type="button" data-schedule-patient='<?= e(json_encode($patient)) ?>'>Schedule</button></div></td>
                        </tr>
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
    $services = listServices();
    $selectedPatient = (int) ($_GET['patient_id'] ?? 0);
    $selectedDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
    $selectedStatus = trim((string) ($_GET['status'] ?? ''));
    $calendarMonth = trim((string) ($_GET['month'] ?? date('Y-m')));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }

    if (!preg_match('/^\d{4}-\d{2}$/', $calendarMonth)) {
        $calendarMonth = date('Y-m');
    }

    $appointments = listAppointments($selectedDate, $selectedStatus);
    $calendarAppointments = listAppointmentsForMonth($calendarMonth);
    $appointmentsByDate = [];

    foreach ($calendarAppointments as $calendarAppointment) {
        $appointmentsByDate[$calendarAppointment['appointment_date']][] = $calendarAppointment;
    }

    $monthStart = new DateTimeImmutable($calendarMonth . '-01');
    $calendarStart = $monthStart->modify('-' . ((int) $monthStart->format('N') - 1) . ' days');
    $monthLabel = $monthStart->format('F Y');

    sectionHeader('Appointments', 'Book, reschedule, cancel, and monitor patient visits.');
    ?>
    <section class="appointment-toolbar dashboard-card">
        <form class="search-bar spa-search" data-section-search="appointments">
            <input type="date" name="date" value="<?= e($selectedDate) ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $selectedStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="completed" <?= $selectedStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $selectedStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            <input type="month" name="month" value="<?= e($calendarMonth) ?>">
            <button class="button button-small" type="submit">Filter</button>
        </form>
    </section>

    <section class="appointment-layout tablet-appointment-layout">
        <article class="dashboard-card appointment-entry-card">
            <div class="card-header"><div><h2>Book Appointment</h2><p class="muted">Compact tablet flow for fast scheduling.</p></div><i class="fa-solid fa-calendar-plus"></i></div>
            <form class="admin-form ajax-form" data-action="create_appointment">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="tablet-form-grid">
                    <div class="form-group form-group-wide"><label>Patient</label><select name="patient_id" id="appointmentPatient" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?= e((string) $patient['id']) ?>" <?= $selectedPatient === (int) $patient['id'] ? 'selected' : '' ?>><?= e($patient['patient_no'] . ' - ' . $patient['fullname']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group form-group-wide">
                        <label>Appointment Source</label>
                        <div class="segmented-control">
                            <label><input type="radio" name="appointment_source" value="Walk-In" checked> Walk-In</label>
                            <label><input type="radio" name="appointment_source" value="Facebook Page"> Facebook Page</label>
                            <label><input type="radio" name="appointment_source" value="Phone Call"> Phone Call</label>
                        </div>
                    </div>
                    <div class="form-group"><label>Date</label><input type="date" name="appointment_date" required></div>
                    <div class="form-group"><label>Time</label><input type="time" name="appointment_time" required></div>
                    <div class="form-group"><label>Dentist</label><input name="dentist" placeholder="Dentist on duty"></div>
                    <div class="form-group"><label>Service</label><select name="service_type" required><option value="">Select service</option><?php foreach ($services as $service): ?><option value="<?= e($service['service_name']) ?>"><?= e($service['service_name']) ?></option><?php endforeach; ?><option value="Consultation">Consultation</option></select></div>
                    <input type="hidden" name="status" value="pending">
                    <div class="form-group form-group-wide"><label>Notes</label><input name="notes" placeholder="Optional notes"></div>
                </div>
                <button class="button touch-button" type="submit">Save Appointment</button>
            </form>
        </article>

        <article class="dashboard-card appointment-entry-card">
            <div class="card-header"><div><h2>Reschedule Appointment</h2><p class="muted">Choose an appointment from the daily list to edit.</p></div><i class="fa-solid fa-clock"></i></div>
            <form class="admin-form ajax-form" data-action="update_appointment">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="editAppointmentId">
                <div class="tablet-form-grid">
                    <div class="form-group"><label>Patient</label><select name="patient_id" id="editAppointmentPatient" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?= e((string) $patient['id']) ?>"><?= e($patient['patient_no'] . ' - ' . $patient['fullname']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Source</label><select name="appointment_source" id="editAppointmentSource"><option>Walk-In</option><option>Facebook Page</option><option>Phone Call</option></select></div>
                    <div class="form-group"><label>Date</label><input type="date" name="appointment_date" id="editAppointmentDate" required></div>
                    <div class="form-group"><label>Time</label><input type="time" name="appointment_time" id="editAppointmentTime" required></div>
                    <div class="form-group"><label>Dentist</label><input name="dentist" id="editAppointmentDentist"></div>
                    <div class="form-group"><label>Service</label><input name="service_type" id="editAppointmentService" required></div>
                    <div class="form-group"><label>Status</label><select name="status" id="editAppointmentStatus"><option value="pending">Pending</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                    <div class="form-group form-group-wide"><label>Notes</label><input name="notes" id="editAppointmentNotes"></div>
                </div>
                <button class="button touch-button" type="submit">Update Appointment</button>
            </form>
        </article>
    </section>

    <section class="appointment-layout">
        <article class="dashboard-card">
            <div class="card-header"><div><h2>Daily Appointment List</h2><p class="muted"><?= e(date('M d, Y', strtotime($selectedDate))) ?> - <?= count($appointments) ?> record<?= count($appointments) === 1 ? '' : 's' ?></p></div><i class="fa-solid fa-list-check"></i></div>
            <div class="table-wrap">
                <table class="compact-table appointment-table">
                    <thead><tr><th>Time</th><th>Patient</th><th>Source</th><th>Dentist</th><th>Service</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($appointments === []): ?><tr><td colspan="7">No appointments for this filter.</td></tr><?php endif; ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= e(substr((string) $appointment['appointment_time'], 0, 5)) ?></td>
                                <td><strong><?= e($appointment['patient_name']) ?></strong><br><span class="muted"><?= e($appointment['patient_no']) ?> · <?= e($appointment['contact_number']) ?></span></td>
                                <td><?= e($appointment['appointment_source'] ?? 'Walk-In') ?></td>
                                <td><?= e($appointment['dentist'] ?? '') ?></td>
                                <td><?= e($appointment['service_type']) ?></td>
                                <td><span class="status-badge status-<?= e($appointment['status']) ?>"><?= e(ucfirst($appointment['status'])) ?></span></td>
                                <td>
                                    <div class="row-actions">
                                        <button class="button button-small" type="button" data-edit-appointment='<?= e(json_encode($appointment)) ?>'>Reschedule</button>
                                        <?php if ($appointment['status'] !== 'completed'): ?><button class="button button-small button-light" type="button" data-complete-appointment="<?= e((string) $appointment['id']) ?>">Complete</button><?php endif; ?>
                                        <?php if ($appointment['status'] !== 'cancelled'): ?><button class="button button-small button-secondary" type="button" data-cancel-appointment="<?= e((string) $appointment['id']) ?>">Cancel</button><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="dashboard-card">
            <div class="card-header"><div><h2>Calendar View</h2><p class="muted"><?= e($monthLabel) ?></p></div><i class="fa-solid fa-calendar-days"></i></div>
            <div class="calendar-grid">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName): ?>
                    <div class="calendar-day-name"><?= e($dayName) ?></div>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < 42; $i++): ?>
                    <?php
                    $day = $calendarStart->modify('+' . $i . ' days');
                    $dayKey = $day->format('Y-m-d');
                    $isMuted = $day->format('Y-m') !== $calendarMonth;
                    $dayAppointments = $appointmentsByDate[$dayKey] ?? [];
                    ?>
                    <div class="calendar-cell <?= $isMuted ? 'is-muted' : '' ?>">
                        <strong><?= e($day->format('j')) ?></strong>
                        <?php foreach (array_slice($dayAppointments, 0, 2) as $calendarAppointment): ?>
                            <span class="calendar-pill status-<?= e($calendarAppointment['status']) ?>"><?= e(substr((string) $calendarAppointment['appointment_time'], 0, 5)) ?> <?= e($calendarAppointment['patient_name']) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($dayAppointments) > 2): ?><small>+<?= e((string) (count($dayAppointments) - 2)) ?> more</small><?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </article>
    </section>
    <?php
    exit;
}

if ($section === 'services') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $services = listServices($search);
    sectionHeader('Services', 'Create, update, and price clinic treatments.');
    ?>
    <section class="dashboard-card">
        <div class="card-header">
            <div><h2>Services</h2><p class="muted"><?= count($services) ?> service<?= count($services) === 1 ? '' : 's' ?></p></div>
            <button class="button button-small" type="button" data-toggle-panel="serviceCreatePanel"><i class="fa-solid fa-plus"></i> Add Service</button>
        </div>
        <form class="search-bar spa-search" data-section-search="services">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search services or descriptions">
            <button class="button button-small" type="submit">Search</button>
        </form>
        <div class="inline-panel" id="serviceCreatePanel" hidden>
            <form class="admin-form ajax-form" data-action="create_service">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Service Name</label><input name="service_name" required></div>
                    <div class="form-group"><label>Price</label><input type="number" name="price" step="0.01" min="0" required></div>
                    <div class="form-group form-group-wide"><label>Description</label><textarea name="description" rows="3"></textarea></div>
                </div>
                <button class="button" type="submit">Save Service</button>
            </form>
        </div>
        <div class="inline-panel" id="serviceEditPanel" hidden>
            <form class="admin-form ajax-form" data-action="update_service">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="editServiceId">
                <div class="form-grid">
                    <div class="form-group"><label>Service Name</label><input name="service_name" id="editServiceName" required></div>
                    <div class="form-group"><label>Price</label><input type="number" name="price" id="editServicePrice" step="0.01" min="0" required></div>
                    <div class="form-group form-group-wide"><label>Description</label><textarea name="description" id="editServiceDescription" rows="3"></textarea></div>
                </div>
                <button class="button" type="submit">Update Service</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="compact-table">
                <thead><tr><th>Service</th><th>Price</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($services === []): ?><tr><td colspan="4">No services found.</td></tr><?php endif; ?>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= e($service['service_name']) ?></td>
                            <td><?= e(number_format((float) $service['price'], 2)) ?></td>
                            <td><?= e($service['description'] ?? '') ?></td>
                            <td><div class="row-actions"><button class="button button-small" type="button" data-edit-service='<?= e(json_encode($service)) ?>'>Edit</button></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    exit;
}

if ($section === 'billing') {
    $search = trim((string) ($_GET['search'] ?? ''));
    $patients = listPatients();
    $services = listServices();
    $bills = listBills($search);
    $statuses = ['Unpaid', 'Partial', 'Paid'];
    sectionHeader('Billing', 'Generate bills, record payments, and print receipts.');
    ?>
    <section class="dashboard-card">
        <div class="card-header">
            <div><h2>Billing</h2><p class="muted"><?= count($bills) ?> bill<?= count($bills) === 1 ? '' : 's' ?></p></div>
        </div>
        <form class="search-bar spa-search" data-section-search="billing">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search patient, service, or payment status">
            <button class="button button-small" type="submit">Search</button>
        </form>
        <div class="dashboard-grid">
            <article class="dashboard-card">
                <div class="card-header"><div><h3>Generate Bill</h3><p class="muted">Create a new billing record for a patient.</p></div></div>
                <form class="admin-form ajax-form" data-action="create_bill">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Patient</label><select name="patient_id" id="billingPatient" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?= e((string) $patient['id']) ?>"><?= e($patient['patient_no'] . ' - ' . $patient['fullname']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Service</label><select name="service_id" id="billingService" required><option value="">Select service</option><?php foreach ($services as $service): ?><option value="<?= e((string) $service['id']) ?>" data-price="<?= e(number_format((float) $service['price'], 2, '.', '')) ?>"><?= e($service['service_name']) ?> (<?= e(number_format((float) $service['price'], 2)) ?>)</option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Amount</label><input type="number" step="0.01" min="0" name="amount" id="billingAmount" required></div>
                        <div class="form-group"><label>Status</label><select name="payment_status" required><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $status === 'Unpaid' ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>"></div>
                    </div>
                    <button class="button" type="submit">Generate Bill</button>
                </form>
            </article>

            <article class="dashboard-card">
                <div class="card-header"><div><h3>Record Payment</h3><p class="muted">Update payment status for an existing bill.</p></div></div>
                <form class="admin-form ajax-form" data-action="record_payment">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Bill</label><select name="bill_id" required><option value="">Select bill</option><?php foreach ($bills as $bill): ?><option value="<?= e((string) $bill['id']) ?>">#<?= e($bill['id']) ?> - <?= e($bill['patient_name']) ?> / <?= e($bill['service_name']) ?> (<?= e($bill['payment_status']) ?>)</option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Status</label><select name="payment_status" required><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>"></div>
                    </div>
                    <button class="button" type="submit">Record Payment</button>
                </form>
            </article>
        </div>

        <div class="table-wrap">
            <table class="compact-table">
                <thead><tr><th>Bill</th><th>Patient</th><th>Service</th><th>Amount</th><th>Status</th><th>Payment Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($bills === []): ?><tr><td colspan="7">No billing records found.</td></tr><?php endif; ?>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td>#<?= e((string) $bill['id']) ?></td>
                            <td><?= e($bill['patient_name']) ?></td>
                            <td><?= e($bill['service_name']) ?></td>
                            <td><?= e(number_format((float) $bill['amount'], 2)) ?></td>
                            <td><?= e($bill['payment_status']) ?></td>
                            <td><?= e($bill['payment_date'] ? date('Y-m-d', strtotime((string) $bill['payment_date'])) : '—') ?></td>
                            <td><div class="row-actions"><button class="button button-small button-light" type="button" data-print-bill="<?= e((string) $bill['id']) ?>">Print</button></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
    $reports = getReportsData(7, 6, 5);
    $monthlyPatients = $reports['monthlyPatients'];
    $monthlyRevenue = $reports['monthlyRevenue'];
    $topServices = $reports['topServices'];
    sectionHeader('Reports', 'High-level clinic summaries.');
    ?>
    <section class="dashboard-stat-grid">
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div><div><span>Today's Appointments</span><strong><?= e((string) countAppointmentsToday()) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-user-plus"></i></div><div><span>New Patients (6 mo)</span><strong><?= e((string) array_sum(array_column($monthlyPatients, 'count'))) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div><div><span>Revenue (6 mo)</span><strong><?= e(number_format(array_sum(array_column($monthlyRevenue, 'revenue')), 2)) ?></strong></div></article>
        <article class="dashboard-stat-card"><div class="stat-icon"><i class="fa-solid fa-chart-column"></i></div><div><span>Top Service</span><strong><?= e($topServices[0]['service_name'] ?? 'N/A') ?></strong></div></article>
    </section>
    <section class="dashboard-card reports-grid">
        <div class="report-panel">
            <div class="card-header"><div><h2>Monthly Patients</h2><p class="muted">New patient registrations.</p></div></div>
            <div class="report-chart" style="height:200px; min-height:200px;">
                <canvas id="monthlyPatientsChart" style="width:100%; height:100%;"></canvas>
            </div>
        </div>
        <div class="report-panel">
            <div class="card-header"><div><h2>Monthly Revenue</h2><p class="muted">Collected revenue over time.</p></div></div>
            <div class="report-chart" style="height:200px; min-height:200px;">
                <canvas id="monthlyRevenueChart" style="width:100%; height:100%;"></canvas>
            </div>
        </div>
        <div class="report-panel">
            <div class="card-header"><div><h2>Most Requested Services</h2><p class="muted">Top 5 services by billing count.</p></div></div>
            <div class="table-wrap">
                <table class="compact-table">
                    <thead><tr><th>Service</th><th>Requests</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php if ($topServices === []): ?><tr><td colspan="3">No data available.</td></tr><?php endif; ?>
                        <?php foreach ($topServices as $service): ?>
                            <tr>
                                <td><?= e($service['service_name']) ?></td>
                                <td><?= e((string) $service['request_count']) ?></td>
                                <td><?= e(number_format((float) $service['total_amount'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="report-panel">
            <div class="card-header"><div><h2>Recent Audit Logs</h2><p class="muted">Recent system actions for accountability.</p></div></div>
            <div class="table-wrap">
                <table class="compact-table">
                    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Meta</th></tr></thead>
                    <tbody>
                        <?php $recentAudits = $reports['recentAudits'] ?? []; ?>
                        <?php if ($recentAudits === []): ?><tr><td colspan="4">No audit logs yet.</td></tr><?php endif; ?>
                        <?php foreach ($recentAudits as $audit): ?>
                            <tr>
                                <td><?= e(date('M d, Y H:i', strtotime((string) $audit['created_at']))) ?></td>
                                <td><?= e($audit['user_fullname'] ?? ($audit['username'] ?? 'System')) ?></td>
                                <td><?= e($audit['action']) ?></td>
                                <td><?= e($audit['meta'] ? json_encode($audit['meta']) : '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <script type="application/json" id="reports-data">
        <?= json_encode([
            'monthlyPatients' => $monthlyPatients,
            'monthlyRevenue' => $monthlyRevenue,
            'topServices' => $topServices,
            'recentAudits' => $reports['recentAudits'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <?php
    exit;
}

if ($section === 'records') {
    $records = listDentalRecords();
    sectionHeader('Dental Records', 'All dental record entries for your patients.');
    ?>
    <section class="dashboard-card">
        <div class="card-header"><div><h2>Dental Records</h2><p class="muted"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></p></div><i class="fa-solid fa-notes-medical"></i></div>
        <div class="table-wrap">
            <table class="compact-table">
                <thead><tr><th>Date</th><th>Patient</th><th>Diagnosis</th><th>Treatment</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php if ($records === []): ?><tr><td colspan="5">No dental records found.</td></tr><?php endif; ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= e(date('M d, Y', strtotime($record['date_recorded']))) ?></td>
                            <td><strong><?= e($record['patient_name']) ?></strong><br><span class="muted"><?= e($record['patient_no']) ?></span></td>
                            <td><?= e($record['diagnosis']) ?></td>
                            <td><?= e($record['treatment']) ?></td>
                            <td><?= e($record['notes']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
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
