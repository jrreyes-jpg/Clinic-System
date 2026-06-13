const content = document.querySelector('#dashboardContent');
const title = document.querySelector('#sectionTitle');
const subtitle = document.querySelector('#sectionSubtitle');
const nav = document.querySelector('[data-dashboard-nav]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

let activeSection = window.dashboardConfig?.defaultSection || 'dashboard';

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

    window.setTimeout(() => content.classList.remove('is-loading'), 60);
}

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

content.addEventListener('click', async (event) => {
    const sectionButton = event.target.closest('[data-section]');
    const toggleButton = event.target.closest('[data-toggle-panel]');
    const archiveButton = event.target.closest('[data-archive-patient]');
    const completeButton = event.target.closest('[data-complete-appointment]');
    const viewButton = event.target.closest('[data-view-patient]');
    const editButton = event.target.closest('[data-edit-patient]');

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

loadSection(activeSection);

sidebarToggle?.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-collapsed');
});
