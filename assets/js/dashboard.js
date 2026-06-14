const content = document.querySelector('#dashboardContent');
const title = document.querySelector('#sectionTitle');
const subtitle = document.querySelector('#sectionSubtitle');
const nav = document.querySelector('[data-dashboard-nav]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const dashboardHome = document.querySelector('[data-dashboard-home]');
const profileToggle = document.querySelector('[data-profile-toggle]');
const profileDropdown = document.querySelector('[data-profile-dropdown]');
const profileModal = document.querySelector('[data-profile-modal]');
const profileForm = document.querySelector('[data-profile-form]');
const liveTime = document.querySelector('[data-live-time]');
const liveDate = document.querySelector('[data-live-date]');

const savedSection = localStorage.getItem('adminActiveSection');
let activeSection = window.dashboardConfig?.defaultSection || savedSection || 'dashboard';

if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.body.classList.add('sidebar-collapsed');
}

async function loadSection(section, params = {}) {
    activeSection = section;
    content.classList.add('is-loading');
    content.innerHTML = '<div class="section-loader"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Loading section...</span></div>';

    const query = new URLSearchParams({ section, ...params });
    const response = await fetch(`sections.php?${query.toString()}`, {
        headers: { 'X-Requested-With': 'fetch' }
    });
    const html = await response.text();

    content.innerHTML = html;
    const meta = content.querySelector('.section-meta');

    if (meta) {
        title.textContent = meta.dataset.title || 'Dashboard';
        subtitle.textContent = meta.dataset.subtitle || '';
        meta.remove();
    }

    document.querySelectorAll('[data-section]').forEach((item) => {
        item.classList.toggle('active', item.dataset.section === section);
    });

    localStorage.setItem('adminActiveSection', section);
    window.setTimeout(() => content.classList.remove('is-loading'), 60);
}

dashboardHome?.addEventListener('click', () => {
    loadSection('dashboard');
});

async function postAction(formData) {
    const response = await fetch('actions.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'fetch' }
    });

    return response.json();
}

nav?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-section]');

    if (!button) {
        return;
    }

    loadSection(button.dataset.section);
});

document.addEventListener('click', (event) => {
    const profileButton = event.target.closest('[data-profile-toggle]');
    const modalOpenButton = event.target.closest('[data-profile-modal-open]');
    const modalCloseButton = event.target.closest('[data-profile-modal-close]');
    const menuSectionButton = event.target.closest('.profile-dropdown [data-section]');

    if (profileButton) {
        const isOpen = profileDropdown?.hidden === false;
        if (profileDropdown) {
            profileDropdown.hidden = isOpen;
            profileToggle?.setAttribute('aria-expanded', String(!isOpen));
        }
        return;
    }

    if (modalOpenButton) {
        if (profileDropdown) profileDropdown.hidden = true;
        if (profileModal) profileModal.hidden = false;
        return;
    }

    if (modalCloseButton || event.target === profileModal) {
        if (profileModal) profileModal.hidden = true;
        return;
    }

    if (menuSectionButton) {
        if (profileDropdown) profileDropdown.hidden = true;
        loadSection(menuSectionButton.dataset.section);
        return;
    }

    if (profileDropdown && !profileDropdown.hidden && !event.target.closest('.profile-menu-wrap')) {
        profileDropdown.hidden = true;
        profileToggle?.setAttribute('aria-expanded', 'false');
    }
});

content.addEventListener('change', (event) => {
    const serviceSelect = event.target.closest && event.target.closest('#billingService');
    const amountInput = document.querySelector('#billingAmount');

    if (serviceSelect && amountInput) {
        amountInput.value = serviceSelect.selectedOptions?.[0]?.dataset.price || '';
    }
});

content.addEventListener('click', async (event) => {
    const sectionButton = event.target.closest('[data-section]');
    const toggleButton = event.target.closest('[data-toggle-panel]');
    const archiveButton = event.target.closest('[data-archive-patient]');
    const completeButton = event.target.closest('[data-complete-appointment]');
    const cancelButton = event.target.closest('[data-cancel-appointment]');
    const viewButton = event.target.closest('[data-view-patient]');
    const editButton = event.target.closest('[data-edit-patient]');
    const editAppointmentButton = event.target.closest('[data-edit-appointment]');

    if (sectionButton) {
        loadSection(sectionButton.dataset.section);
    }

    if (toggleButton) {
        const panel = document.querySelector(`#${toggleButton.dataset.togglePanel}`);
        if (panel) {
            panel.hidden = !panel.hidden;
        }
    }

    if (archiveButton && confirm('Archive this patient record?')) {
        const data = new FormData();
        data.append('csrf_token', window.dashboardConfig.csrfToken);
        data.append('action', 'archive_patient');
        data.append('id', archiveButton.dataset.archivePatient);
        const result = await postAction(data);
        alert(result.message);
        if (result.ok) loadSection('patients');
    }

    if (completeButton) {
        const data = new FormData();
        data.append('csrf_token', window.dashboardConfig.csrfToken);
        data.append('action', 'complete_appointment');
        data.append('id', completeButton.dataset.completeAppointment);
        const result = await postAction(data);
        alert(result.message);
        if (result.ok) loadSection('appointments');
    }

    const printBillButton = event.target.closest('[data-print-bill]');

    if (printBillButton) {
        const billId = printBillButton.dataset.printBill;
        window.open(`print_receipt.php?bill_id=${encodeURIComponent(billId)}`, '_blank');
    }

    if (cancelButton && confirm('Cancel this appointment?')) {
        const data = new FormData();
        data.append('csrf_token', window.dashboardConfig.csrfToken);
        data.append('action', 'cancel_appointment');
        data.append('id', cancelButton.dataset.cancelAppointment);
        const result = await postAction(data);
        alert(result.message);
        if (result.ok) loadSection('appointments');
    }

    if (viewButton) {
        const patient = JSON.parse(viewButton.dataset.viewPatient);
        alert(`${patient.patient_no}\n${patient.fullname}\nAge: ${patient.age}\nContact: ${patient.contact_number}\nEmail: ${patient.email || ''}\nAddress: ${patient.address || ''}`);
    }

    if (editButton) {
        const patient = JSON.parse(editButton.dataset.editPatient);
        const panel = document.querySelector('#patientEditPanel');
        document.querySelector('#editPatientId').value = patient.id || '';
        document.querySelector('#editPatientFullname').value = patient.fullname || '';
        document.querySelector('#editPatientBirthdate').value = patient.birthdate || '';
        document.querySelector('#editPatientGender').value = patient.gender || 'Male';
        document.querySelector('#editPatientContact').value = patient.contact_number || '';
        document.querySelector('#editPatientEmail').value = patient.email || '';
        document.querySelector('#editPatientAddress').value = patient.address || '';
        if (panel) {
            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    const editServiceButton = event.target.closest('[data-edit-service]');

    if (editServiceButton) {
        const service = JSON.parse(editServiceButton.dataset.editService);
        const panel = document.querySelector('#serviceEditPanel');

        document.querySelector('#editServiceId').value = service.id || '';
        document.querySelector('#editServiceName').value = service.service_name || '';
        document.querySelector('#editServicePrice').value = service.price || '';
        document.querySelector('#editServiceDescription').value = service.description || '';

        if (panel) {
            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (editAppointmentButton) {
        const appointment = JSON.parse(editAppointmentButton.dataset.editAppointment);
        document.querySelector('#editAppointmentId').value = appointment.id || '';
        document.querySelector('#editAppointmentPatient').value = appointment.patient_id || '';
        document.querySelector('#editAppointmentService').value = appointment.service_type || '';
        document.querySelector('#editAppointmentDate').value = appointment.appointment_date || '';
        document.querySelector('#editAppointmentTime').value = (appointment.appointment_time || '').slice(0, 5);
        document.querySelector('#editAppointmentStatus').value = appointment.status || 'pending';
        document.querySelector('#editAppointmentNotes').value = appointment.notes || '';
        document.querySelector('#editAppointmentId')?.closest('.dashboard-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

content.addEventListener('submit', async (event) => {
    const ajaxForm = event.target.closest('.ajax-form');
    const searchForm = event.target.closest('[data-section-search]');

    if (searchForm) {
        event.preventDefault();
        const data = new FormData(searchForm);
        loadSection(searchForm.dataset.sectionSearch, Object.fromEntries(data.entries()));
        return;
    }

    if (!ajaxForm) {
        return;
    }

    event.preventDefault();
    const button = ajaxForm.querySelector('button[type="submit"]');
    const data = new FormData(ajaxForm);
    data.append('action', ajaxForm.dataset.action);

    if (button) {
        button.disabled = true;
    }

    const result = await postAction(data);
    alert(result.message);

    if (button) {
        button.disabled = false;
    }

    if (result.ok) {
        if (result.logoUrl) {
            document.querySelectorAll('[data-clinic-logo], [data-clinic-logo-preview]').forEach((image) => {
                image.src = result.logoUrl;
            });
        }

        ajaxForm.reset();
        loadSection(activeSection);
    }
});

profileForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = profileForm.querySelector('button[type="submit"]');
    const data = new FormData(profileForm);
    data.append('action', 'update_profile');

    if (button) button.disabled = true;
    const result = await postAction(data);
    alert(result.message);
    if (button) button.disabled = false;

    if (!result.ok) {
        return;
    }

    document.querySelectorAll('[data-profile-name]').forEach((item) => {
        item.textContent = result.fullname || '';
    });
    document.querySelectorAll('[data-profile-email]').forEach((item) => {
        item.textContent = result.email || '';
    });

    document.querySelectorAll('[data-profile-avatar], [data-profile-preview]').forEach((avatar) => {
        avatar.textContent = '';

        if (result.profilePhotoUrl) {
            const image = document.createElement('img');
            image.src = result.profilePhotoUrl;
            image.alt = result.fullname || 'Profile';
            avatar.appendChild(image);
        } else {
            avatar.textContent = result.initial || 'A';
        }
    });

    if (profileModal) profileModal.hidden = true;
});

profileForm?.querySelector('input[name="profile_photo"]')?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    const preview = document.querySelector('[data-profile-preview]');

    if (!file || !preview) {
        return;
    }

    const reader = new FileReader();
    reader.addEventListener('load', () => {
        preview.textContent = '';
        const image = document.createElement('img');
        image.src = String(reader.result);
        image.alt = 'Profile preview';
        preview.appendChild(image);
    });
    reader.readAsDataURL(file);
});

function updateLiveClock() {
    const now = new Date();

    if (liveTime) {
        liveTime.textContent = now.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    if (liveDate) {
        liveDate.textContent = now.toLocaleDateString('en-PH', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }
}

updateLiveClock();
window.setInterval(updateLiveClock, 1000);

loadSection(activeSection);

sidebarToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const collapsed = document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', String(collapsed));
});

if (liveTime) {
    liveTime.classList.add('live-time-motion');
}
