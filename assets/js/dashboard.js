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
const avatarCropper = document.querySelector('[data-avatar-cropper]');
const avatarCropStage = document.querySelector('[data-avatar-crop-stage]');
const avatarCropImage = document.querySelector('[data-avatar-crop-image]');
const avatarCropZoom = document.querySelector('[data-avatar-crop-zoom]');

const avatarCrop = {
    file: null,
    url: '',
    naturalWidth: 0,
    naturalHeight: 0,
    baseScale: 1,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    dragging: false,
    startX: 0,
    startY: 0,
    startOffsetX: 0,
    startOffsetY: 0,
};

function clampAvatarCrop() {
    if (!avatarCropStage || !avatarCropImage) {
        return;
    }

    const size = avatarCropStage.getBoundingClientRect().width;
    const scale = avatarCrop.baseScale * avatarCrop.zoom;
    const scaledWidth = avatarCrop.naturalWidth * scale;
    const scaledHeight = avatarCrop.naturalHeight * scale;
    const maxX = Math.max(0, (scaledWidth - size) / 2);
    const maxY = Math.max(0, (scaledHeight - size) / 2);

    avatarCrop.offsetX = Math.min(maxX, Math.max(-maxX, avatarCrop.offsetX));
    avatarCrop.offsetY = Math.min(maxY, Math.max(-maxY, avatarCrop.offsetY));
}

function renderAvatarCrop() {
    if (!avatarCropStage || !avatarCropImage) {
        return;
    }

    const scale = avatarCrop.baseScale * avatarCrop.zoom;
    avatarCropImage.style.width = `${avatarCrop.naturalWidth * scale}px`;
    avatarCropImage.style.height = `${avatarCrop.naturalHeight * scale}px`;
    avatarCropImage.style.transform = `translate(-50%, -50%) translate(${avatarCrop.offsetX}px, ${avatarCrop.offsetY}px)`;
    renderAvatarCropPreview();
}

function renderAvatarCropPreview() {
    const preview = document.querySelector('[data-profile-preview]');
    const image = preview?.querySelector('[data-avatar-crop-preview-image]');

    if (!preview || !image || !avatarCropStage) {
        return;
    }

    const previewSize = preview.getBoundingClientRect().width;
    const stageSize = avatarCropStage.getBoundingClientRect().width;
    const previewRatio = previewSize / stageSize;
    const scale = avatarCrop.baseScale * avatarCrop.zoom * previewRatio;

    image.style.position = 'absolute';
    image.style.left = '50%';
    image.style.top = '50%';
    image.style.width = `${avatarCrop.naturalWidth * scale}px`;
    image.style.height = `${avatarCrop.naturalHeight * scale}px`;
    image.style.objectFit = 'initial';
    image.style.transform = `translate(-50%, -50%) translate(${avatarCrop.offsetX * previewRatio}px, ${avatarCrop.offsetY * previewRatio}px)`;
}

function resetAvatarCropPosition() {
    if (!avatarCropStage) {
        return;
    }

    const size = avatarCropStage.getBoundingClientRect().width;
    avatarCrop.baseScale = size / Math.min(avatarCrop.naturalWidth, avatarCrop.naturalHeight);
    avatarCrop.zoom = 1;
    avatarCrop.offsetX = 0;
    avatarCrop.offsetY = 0;

    if (avatarCropZoom) {
        avatarCropZoom.value = '1';
    }

    renderAvatarCrop();
}

function buildCroppedAvatarBlob() {
    if (!avatarCrop.file || !avatarCropStage || !avatarCropImage || !avatarCrop.naturalWidth || !avatarCrop.naturalHeight) {
        return Promise.resolve(null);
    }

    const canvas = document.createElement('canvas');
    const outputSize = 512;
    const stageSize = avatarCropStage.getBoundingClientRect().width;
    const ratio = outputSize / stageSize;
    const scale = avatarCrop.baseScale * avatarCrop.zoom;
    const drawWidth = avatarCrop.naturalWidth * scale * ratio;
    const drawHeight = avatarCrop.naturalHeight * scale * ratio;
    const drawX = (outputSize / 2) - (drawWidth / 2) + (avatarCrop.offsetX * ratio);
    const drawY = (outputSize / 2) - (drawHeight / 2) + (avatarCrop.offsetY * ratio);
    const context = canvas.getContext('2d');

    canvas.width = outputSize;
    canvas.height = outputSize;
    context.fillStyle = '#f3f4f6';
    context.fillRect(0, 0, outputSize, outputSize);
    context.drawImage(avatarCropImage, drawX, drawY, drawWidth, drawHeight);

    return new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', 0.92);
    });
}

function clearAvatarCrop() {
    const photoInput = profileForm?.querySelector('input[name="profile_photo"]');

    if (avatarCrop.url) {
        URL.revokeObjectURL(avatarCrop.url);
    }

    avatarCrop.file = null;
    avatarCrop.url = '';
    avatarCrop.naturalWidth = 0;
    avatarCrop.naturalHeight = 0;
    avatarCrop.offsetX = 0;
    avatarCrop.offsetY = 0;
    avatarCrop.zoom = 1;

    if (avatarCropper) avatarCropper.hidden = true;
    if (avatarCropImage) avatarCropImage.removeAttribute('src');
    if (avatarCropZoom) avatarCropZoom.value = '1';
    if (photoInput) photoInput.value = '';
}

const validDashboardSections = ['dashboard', 'patients', 'appointments', 'billing', 'services', 'records', 'users', 'reports', 'settings'];
const savedSection = localStorage.getItem('adminActiveSection');
const urlSection = new URLSearchParams(window.location.search).get('section') || '';
let activeSection = validDashboardSections.includes(urlSection)
    ? urlSection
    : savedSection || window.dashboardConfig?.defaultSection || 'dashboard';

if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.body.classList.add('sidebar-collapsed');
}

function updateSidebarToggleLabel() {
    if (!sidebarToggle) {
        return;
    }

    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    const label = isCollapsed ? 'Open sidebar' : 'Close sidebar';
    sidebarToggle.setAttribute('aria-label', label);
    sidebarToggle.setAttribute('title', label);
}

updateSidebarToggleLabel();

async function loadSection(section, params = {}) {
    activeSection = section;
    if (!params || Object.keys(params).length === 0) {
        const url = new URL(window.location.href);
        url.searchParams.set('section', section);
        url.hash = '';
        window.history.replaceState(null, '', url.toString());
    }
    // clean up any existing Chart.js instances to avoid leaks and layout issues
    try {
        if (window._clinicCharts) {
            Object.keys(window._clinicCharts).forEach((k) => {
                try { if (window._clinicCharts[k] && typeof window._clinicCharts[k].destroy === 'function') { window._clinicCharts[k].destroy(); } } catch (e) {}
                window._clinicCharts[k] = null;
            });
        }
    } catch (e) {
        // non-fatal
    }
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

    renderReportCharts();

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
    data.delete('profile_photo');

    const croppedAvatar = await buildCroppedAvatarBlob();
    if (croppedAvatar) {
        data.append('profile_photo', croppedAvatar, 'profile-avatar.jpg');
    }

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
    clearAvatarCrop();
});

profileForm?.querySelector('input[name="profile_photo"]')?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    const preview = document.querySelector('[data-profile-preview]');

    if (!file || !preview || !avatarCropper || !avatarCropImage) {
        return;
    }

    avatarCrop.file = file;
    if (avatarCrop.url) {
        URL.revokeObjectURL(avatarCrop.url);
    }

    avatarCrop.url = URL.createObjectURL(file);
    avatarCropImage.addEventListener('load', () => {
        avatarCrop.naturalWidth = avatarCropImage.naturalWidth;
        avatarCrop.naturalHeight = avatarCropImage.naturalHeight;
        avatarCropper.hidden = false;
        resetAvatarCropPosition();

        preview.textContent = '';
        const image = document.createElement('img');
        image.src = avatarCrop.url;
        image.alt = 'Profile preview';
        image.dataset.avatarCropPreviewImage = 'true';
        preview.appendChild(image);
        renderAvatarCrop();
    }, { once: true });
    avatarCropImage.src = avatarCrop.url;
});

avatarCropZoom?.addEventListener('input', () => {
    avatarCrop.zoom = Number(avatarCropZoom.value) || 1;
    clampAvatarCrop();
    renderAvatarCrop();
});

avatarCropStage?.addEventListener('pointerdown', (event) => {
    if (!avatarCrop.file) {
        return;
    }

    avatarCrop.dragging = true;
    avatarCrop.startX = event.clientX;
    avatarCrop.startY = event.clientY;
    avatarCrop.startOffsetX = avatarCrop.offsetX;
    avatarCrop.startOffsetY = avatarCrop.offsetY;
    avatarCropStage.setPointerCapture(event.pointerId);
});

avatarCropStage?.addEventListener('pointermove', (event) => {
    if (!avatarCrop.dragging) {
        return;
    }

    avatarCrop.offsetX = avatarCrop.startOffsetX + event.clientX - avatarCrop.startX;
    avatarCrop.offsetY = avatarCrop.startOffsetY + event.clientY - avatarCrop.startY;
    clampAvatarCrop();
    renderAvatarCrop();
});

avatarCropStage?.addEventListener('pointerup', (event) => {
    avatarCrop.dragging = false;
    avatarCropStage.releasePointerCapture(event.pointerId);
});

avatarCropStage?.addEventListener('pointercancel', () => {
    avatarCrop.dragging = false;
});

window.addEventListener('resize', () => {
    if (avatarCrop.file) {
        resetAvatarCropPosition();
    }
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

function renderReportCharts() {
    // only render when reports section is present to avoid accidental runs
    if (!document.querySelector('.reports-grid') && !document.querySelector('#reports-data')) {
        return;
    }

    let reportsData = window.reportsData;
    if (!reportsData) {
        const reportJson = document.querySelector('#reports-data');
        if (reportJson) {
            try {
                reportsData = JSON.parse(reportJson.textContent || '{}');
            } catch (error) {
                console.error('Failed to parse report data', error);
                return;
            }
        }
    }
    if (!reportsData) {
        return;
    }

    const monthlyPatientsCanvas = document.querySelector('#monthlyPatientsChart');
    const monthlyRevenueCanvas = document.querySelector('#monthlyRevenueChart');

    window._clinicCharts = window._clinicCharts || {};

    // destroy any existing charts to avoid duplicates/layout growth
    function destroyChart(key) {
        try {
            if (window._clinicCharts && window._clinicCharts[key]) {
                window._clinicCharts[key].destroy();
                window._clinicCharts[key] = null;
            }
        } catch (err) {
            console.error('Error destroying chart', key, err);
        }
    }

    destroyChart('monthlyPatients');
    destroyChart('monthlyRevenue');

    if (monthlyPatientsCanvas) {
        const labelsM = reportsData.monthlyPatients.map((item) => item.label);
        const countsM = reportsData.monthlyPatients.map((item) => item.count);
        window._clinicCharts.monthlyPatients = new Chart(monthlyPatientsCanvas, {
            type: 'bar',
            data: {
                labels: labelsM,
                datasets: [{ label: 'New Patients', data: countsM, backgroundColor: '#10b981' }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true } },
            },
        });
    }

    if (monthlyRevenueCanvas) {
        const labelsR = reportsData.monthlyRevenue.map((item) => item.label);
        const amounts = reportsData.monthlyRevenue.map((item) => item.revenue);
        window._clinicCharts.monthlyRevenue = new Chart(monthlyRevenueCanvas, {
            type: 'bar',
            data: {
                labels: labelsR,
                datasets: [{ label: 'Revenue', data: amounts, backgroundColor: '#f59e0b' }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true } },
            },
        });
    }
}

updateLiveClock();
window.setInterval(updateLiveClock, 1000);

renderReportCharts();

// No expand/collapse behavior for Reports — sparkline only by default.

loadSection(activeSection);

sidebarToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const collapsed = document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', String(collapsed));
    updateSidebarToggleLabel();
});

// Notifications
const notificationsToggle = document.querySelector('#notificationsToggle');
const notificationsDropdown = document.querySelector('#notificationsDropdown');
const notificationsList = document.querySelector('#notificationsList');
const notificationCount = document.querySelector('#notificationCount');
const markAllReadBtn = document.querySelector('#markAllRead');

async function fetchNotifications() {
    try {
        const res = await fetch('notifications.php', { headers: { 'X-Requested-With': 'fetch' } });
        const data = await res.json();
        if (!data.ok) return;

        const list = data.notifications || [];
        notificationsList.innerHTML = '';

        if (list.length === 0) {
            notificationsList.innerHTML = '<p class="muted">No notifications.</p>';
        } else {
            list.forEach((note) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'notification-item' + (note.is_read ? ' is-read' : '');
                item.dataset.id = note.id;
                item.innerHTML = `<div><strong>${escapeHtml(note.type.replace(/_/g, ' '))}</strong><small class="muted">${new Date(note.created_at).toLocaleString()}</small><div class="muted">${escapeHtml(note.message)}</div></div>`;
                item.addEventListener('click', async () => {
                    await markNotificationRead(note.id);
                    if (note.meta && note.meta.appointment_id) {
                        loadSection('appointments');
                    }
                    fetchNotifications();
                });
                notificationsList.appendChild(item);
            });
        }

        const unread = data.unread || 0;
        if (unread > 0) {
            notificationCount.hidden = false;
            notificationCount.textContent = String(unread);
        } else {
            notificationCount.hidden = true;
        }
    } catch (err) {
        console.error('Failed to load notifications', err);
    }
}

async function markNotificationRead(id) {
    try {
        await fetch('notifications.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: new URLSearchParams({ action: 'mark_read', id: String(id) }),
        });
    } catch (err) {
        console.error('Failed to mark read', err);
    }
}

async function markAllRead() {
    try {
        await fetch('notifications.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: new URLSearchParams({ action: 'mark_all_read' }),
        });
        fetchNotifications();
    } catch (err) {
        console.error('Failed to mark all read', err);
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

notificationsToggle?.addEventListener('click', (e) => {
    const open = notificationsDropdown?.hidden === false;
    if (notificationsDropdown) {
        notificationsDropdown.hidden = open;
    }
    if (!open) {
        fetchNotifications();
    }
});

markAllReadBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    markAllRead();
});

// poll every 30s
setInterval(fetchNotifications, 30000);
fetchNotifications();

if (liveTime) {
    liveTime.classList.add('live-time-motion');
}
