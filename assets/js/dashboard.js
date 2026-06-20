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
let patientCameraStream = null;
const patientCropStates = new WeakMap();

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

function createCropState(file = null) {
    return {
        file,
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
    stopPatientCamera();
    document.body.classList.remove('patient-modal-open');
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

content?.addEventListener('click', (event) => {
    const retryButton = event.target.closest('[data-retry-section]');
    if (!retryButton) {
        return;
    }

    loadSection(activeSection);
});

async function postAction(formData) {
    const response = await fetch('actions.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'fetch' }
    });

    return response.json();
}

async function startPatientCamera(form) {
    if (!form) {
        return;
    }

    const camera = form.querySelector('[data-patient-camera]');
    const video = form.querySelector('[data-patient-camera-video]');

    if (!camera || !video || !navigator.mediaDevices?.getUserMedia) {
        alert('Camera is not available in this browser. Use upload instead.');
        return;
    }

    stopPatientCamera();

    try {
        patientCameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        video.srcObject = patientCameraStream;
        camera.hidden = false;
    } catch (error) {
        alert('Camera could not be opened. Please allow camera access or use upload.');
    }
}

function stopPatientCamera(form = null) {
    if (patientCameraStream) {
        patientCameraStream.getTracks().forEach((track) => track.stop());
        patientCameraStream = null;
    }

    const scope = form || document;
    scope.querySelectorAll?.('[data-patient-camera]').forEach((camera) => {
        camera.hidden = true;
        const video = camera.querySelector('[data-patient-camera-video]');
        if (video) video.srcObject = null;
    });
}

function capturePatientPhoto(form) {
    if (!form) {
        return;
    }

    const video = form.querySelector('[data-patient-camera-video]');
    const canvas = form.querySelector('[data-patient-camera-canvas]');
    const input = form.querySelector('[data-patient-photo-input]');

    if (!video || !canvas || !input || !video.videoWidth || !video.videoHeight) {
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob((blob) => {
        if (!blob) {
            return;
        }

        const file = new File([blob], `patient-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        previewPatientPhoto(input);
        stopPatientCamera(form);
    }, 'image/jpeg', 0.9);
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

    const birthdateInput = event.target.closest && event.target.closest('[data-birthdate]');
    if (birthdateInput) {
        updatePatientAge(birthdateInput.closest('form'));
    }

    const hmoInput = event.target.closest && event.target.closest('[name="has_hmo"]');
    if (hmoInput) {
        updateHmoFields(hmoInput.closest('form'));
        clearPatientValidation(hmoInput.closest('form'));
    }

    const patientPhotoInput = event.target.closest && event.target.closest('[data-patient-photo-input]');
    if (patientPhotoInput) {
        previewPatientPhoto(patientPhotoInput);
    }

    const patientField = event.target.closest && event.target.closest('[data-patient-form-view] input, [data-patient-form-view] textarea, [data-patient-form-view] select');
    if (patientField) {
        clearPatientFieldError(patientField.closest('form'), patientField);
    }

    const patientCropZoom = event.target.closest && event.target.closest('[data-patient-crop-zoom]');
    if (patientCropZoom) {
        const form = patientCropZoom.closest('form');
        const state = patientCropStates.get(form);
        if (state) {
            state.zoom = Number(patientCropZoom.value) || 1;
            renderPatientCrop(form);
        }
    }
});

content.addEventListener('click', async (event) => {
    const sectionButton = event.target.closest('[data-section]');
    const toggleButton = event.target.closest('[data-toggle-panel]');
    const patientCreateButton = event.target.closest('[data-patient-show-create]');
    const patientBackButton = event.target.closest('[data-patient-back]');
    const archiveButton = event.target.closest('[data-archive-patient]');
    const completeButton = event.target.closest('[data-complete-appointment]');
    const cancelButton = event.target.closest('[data-cancel-appointment]');
    const viewButton = event.target.closest('[data-view-patient]');
    const editButton = event.target.closest('[data-edit-patient]');
    const scheduleButton = event.target.closest('[data-schedule-patient]');
    const editAppointmentButton = event.target.closest('[data-edit-appointment]');
    const tabButton = event.target.closest('[data-tab-target]');
    const panelCloseButton = event.target.closest('[data-panel-close]');
    const cameraStartButton = event.target.closest('[data-camera-start]');
    const cameraCaptureButton = event.target.closest('[data-camera-capture]');
    const cameraCloseButton = event.target.closest('[data-camera-close]');
    const patientPhotoTrigger = event.target.closest('[data-patient-photo-trigger]');
    const patientModalBackdrop = event.target.matches('[data-patient-modal-backdrop]') ? event.target : null;

    if (sectionButton) {
        loadSection(sectionButton.dataset.section);
    }

    if (tabButton) {
        activateFormTab(tabButton);
        return;
    }

    if (patientCreateButton) {
        const panel = document.querySelector('#patientCreatePanel');
        const form = panel?.querySelector('form');
        form?.reset();
        clearPatientValidation(form);
        clearPatientCrop(form, false);
        resetPatientPhotoPreview(form);
        resetPatientFormTabs(form);
        showPatientFormView(panel);
        return;
    }

    if (patientBackButton) {
        const form = patientBackButton.closest('form');
        stopPatientCamera(form);
        clearPatientCrop(form, false);
        showPatientListView();
        return;
    }

    if (patientModalBackdrop) {
        const form = patientModalBackdrop.querySelector('form');
        stopPatientCamera(form);
        clearPatientCrop(form, false);
        showPatientListView();
        return;
    }

    if (panelCloseButton) {
        const form = panelCloseButton.closest('form');
        stopPatientCamera(form);
        clearPatientCrop(form, false);
        const panel = panelCloseButton.closest('.tablet-form-panel');
        if (panel) panel.hidden = true;
        return;
    }

    if (patientPhotoTrigger) {
        patientPhotoTrigger.closest('form')?.querySelector('[data-patient-photo-input]')?.click();
        return;
    }

    if (toggleButton) {
        const panel = document.querySelector(`#${toggleButton.dataset.togglePanel}`);
        if (panel) {
            document.querySelectorAll('.tablet-form-panel').forEach((item) => {
                if (item !== panel) {
                    stopPatientCamera(item.querySelector('form'));
                    item.hidden = true;
                }
            });
            panel.hidden = !panel.hidden;
            panel.querySelector('[data-birthdate]') && updatePatientAge(panel.querySelector('form'));
            panel.querySelector('[name="has_hmo"]') && updateHmoFields(panel.querySelector('form'));
        }
    }

    if (cameraStartButton) {
        startPatientCamera(cameraStartButton.closest('form'));
        return;
    }

    if (cameraCaptureButton) {
        capturePatientPhoto(cameraCaptureButton.closest('form'));
        return;
    }

    if (cameraCloseButton) {
        stopPatientCamera(cameraCloseButton.closest('form'));
        return;
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
        renderPatientProfile(patient);
    }

    if (editButton) {
        const patient = JSON.parse(editButton.dataset.editPatient);
        const panel = document.querySelector('#patientEditPanel');
        fillPatientForm(panel?.querySelector('form'), patient);
        showPatientFormView(panel);
    }

    if (scheduleButton) {
        const patient = JSON.parse(scheduleButton.dataset.schedulePatient);
        loadSection('appointments', { patient_id: patient.id || '' });
        return;
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
        document.querySelector('#editAppointmentSource').value = appointment.appointment_source || 'Walk-In';
        document.querySelector('#editAppointmentService').value = appointment.service_type || '';
        document.querySelector('#editAppointmentDate').value = appointment.appointment_date || '';
        document.querySelector('#editAppointmentTime').value = (appointment.appointment_time || '').slice(0, 5);
        document.querySelector('#editAppointmentDentist').value = appointment.dentist || '';
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
    syncPatientFullname(ajaxForm);

    if (['create_patient', 'update_patient'].includes(ajaxForm.dataset.action) && !validatePatientForm(ajaxForm)) {
        return;
    }

    const data = new FormData(ajaxForm);
    data.append('action', ajaxForm.dataset.action);

    if (['create_patient', 'update_patient'].includes(ajaxForm.dataset.action)) {
        const croppedPatientPhoto = await buildCroppedPatientPhotoBlob(ajaxForm);
        if (croppedPatientPhoto) {
            data.delete('patient_photo_upload');
            data.append('patient_photo_upload', croppedPatientPhoto, `patient-photo-${Date.now()}.jpg`);
        }
    }

    if (button) {
        button.disabled = true;
    }

    const result = await postAction(data);

    if (button) {
        button.disabled = false;
    }

    if (!result.ok && result.errors && ['create_patient', 'update_patient'].includes(ajaxForm.dataset.action)) {
        applyPatientValidationErrors(ajaxForm, result.errors);
        return;
    }

    alert(result.message);

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

content.addEventListener('input', (event) => {
    const namePart = event.target.closest && event.target.closest('[data-name-part]');
    const birthdateInput = event.target.closest && event.target.closest('[data-birthdate]');
    const patientField = event.target.closest && event.target.closest('[data-patient-form-view] input, [data-patient-form-view] textarea, [data-patient-form-view] select');

    if (namePart) {
        syncPatientFullname(namePart.closest('form'));
    }

    if (birthdateInput) {
        updatePatientAge(birthdateInput.closest('form'));
    }

    if (patientField) {
        clearPatientFieldError(patientField.closest('form'), patientField);
    }
});

content.addEventListener('pointerdown', (event) => {
    const stage = event.target.closest && event.target.closest('[data-patient-crop-stage]');
    const form = stage?.closest('form');
    const state = patientCropStates.get(form);

    if (!stage || !state?.file) {
        return;
    }

    state.dragging = true;
    state.startX = event.clientX;
    state.startY = event.clientY;
    state.startOffsetX = state.offsetX;
    state.startOffsetY = state.offsetY;
    stage.setPointerCapture(event.pointerId);
});

content.addEventListener('pointermove', (event) => {
    const stage = event.target.closest && event.target.closest('[data-patient-crop-stage]');
    const form = stage?.closest('form');
    const state = patientCropStates.get(form);

    if (!stage || !state?.dragging) {
        return;
    }

    state.offsetX = state.startOffsetX + event.clientX - state.startX;
    state.offsetY = state.startOffsetY + event.clientY - state.startY;
    renderPatientCrop(form);
});

content.addEventListener('pointerup', (event) => {
    const stage = event.target.closest && event.target.closest('[data-patient-crop-stage]');
    const form = stage?.closest('form');
    const state = patientCropStates.get(form);

    if (!stage || !state) {
        return;
    }

    state.dragging = false;
    if (stage.hasPointerCapture(event.pointerId)) {
        stage.releasePointerCapture(event.pointerId);
    }
});

content.addEventListener('pointercancel', (event) => {
    const stage = event.target.closest && event.target.closest('[data-patient-crop-stage]');
    const form = stage?.closest('form');
    const state = patientCropStates.get(form);

    if (state) {
        state.dragging = false;
    }
});

function activateFormTab(button) {
    const tabs = button.closest('[data-tabs]');
    if (!tabs) {
        return;
    }

    const target = button.dataset.tabTarget;
    tabs.querySelectorAll('[data-tab-target]').forEach((item) => {
        item.classList.toggle('active', item === button);
    });
    tabs.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.tabPanel === target);
    });
}

function activatePatientTab(form, tabName) {
    const button = form?.querySelector(`[data-tab-target="${tabName}"]`);
    if (button) {
        activateFormTab(button);
    }
}

function clearPatientValidation(form) {
    if (!form) {
        return;
    }

    form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    form.querySelectorAll('.form-group.has-error').forEach((group) => group.classList.remove('has-error'));
    form.querySelectorAll('.field-error-message').forEach((message) => message.remove());
    form.querySelectorAll('[data-tab-target].has-errors').forEach((button) => button.classList.remove('has-errors'));
    form.querySelector('[data-validation-summary]')?.remove();
}

function clearPatientFieldError(form, field) {
    if (!form || !field) {
        return;
    }

    const group = field.closest('.form-group') || field.parentElement;
    field.classList.remove('is-invalid');
    group?.classList.remove('has-error');
    group?.querySelector('.field-error-message')?.remove();

    const tabName = fieldTabName(field);
    const tabPanel = form.querySelector(`[data-tab-panel="${tabName}"]`);
    const hasTabErrors = Boolean(tabPanel?.querySelector('.is-invalid'));
    if (!hasTabErrors) {
        form.querySelector(`[data-tab-target="${tabName}"]`)?.classList.remove('has-errors');
    }

    if (!form.querySelector('.is-invalid')) {
        form.querySelector('[data-validation-summary]')?.remove();
    }
}

function fieldTabName(field) {
    return field?.closest('[data-tab-panel]')?.dataset.tabPanel || 'personal';
}

function patientFieldByName(form, name) {
    return Array.from(form?.elements || []).find((field) => field.name === name) || null;
}

function fieldLabel(field) {
    const label = field?.closest('.form-group')?.querySelector('label');
    return label ? label.textContent.replace(/\s+/g, ' ').replace(/\s+i$/, '').trim() : field?.name || 'This field';
}

function setPatientFieldError(form, field, message) {
    if (!form || !field) {
        return;
    }

    const group = field.closest('.form-group') || field.parentElement;
    field.classList.add('is-invalid');
    group?.classList.add('has-error');

    if (group && !group.querySelector('.field-error-message')) {
        const error = document.createElement('small');
        error.className = 'field-error-message';
        error.textContent = message;
        group.appendChild(error);
    }

    const tabName = fieldTabName(field);
    form.querySelector(`[data-tab-target="${tabName}"]`)?.classList.add('has-errors');
}

function addPatientValidationSummary(form, count) {
    const tabs = form?.querySelector('[data-tabs]');
    if (!tabs || count <= 0) {
        return;
    }

    const summary = document.createElement('div');
    summary.className = 'validation-summary';
    summary.dataset.validationSummary = 'true';
    summary.setAttribute('role', 'alert');
    summary.textContent = `${count} field${count === 1 ? '' : 's'} need attention. Complete the red field${count === 1 ? '' : 's'} before saving.`;
    tabs.prepend(summary);
}

function applyPatientValidationErrors(form, errors) {
    clearPatientValidation(form);

    const entries = Object.entries(errors || {});
    let firstField = null;

    entries.forEach(([name, message]) => {
        const field = patientFieldByName(form, name);
        if (!field) {
            return;
        }

        if (!firstField) {
            firstField = field;
        }

        setPatientFieldError(form, field, String(message));
    });

    addPatientValidationSummary(form, entries.length);

    if (firstField) {
        activatePatientTab(form, fieldTabName(firstField));
        window.setTimeout(() => {
            firstField.focus({ preventScroll: true });
            firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 80);
    }
}

function validatePatientForm(form) {
    clearPatientValidation(form);
    syncPatientFullname(form);

    const errors = {};
    const requiredNames = ['first_name', 'last_name', 'birthdate', 'gender', 'contact_number'];
    const hasHmo = form.querySelector('[name="has_hmo"]:checked')?.value === 'Yes';

    if (hasHmo) {
        requiredNames.push('hmo_provider', 'hmo_card_number', 'hmo_type', 'hmo_expiration_date');
    }

    requiredNames.forEach((name) => {
        const field = patientFieldByName(form, name);
        if (field && String(field.value || '').trim() === '') {
            errors[name] = `${fieldLabel(field)} is required.`;
        }
    });

    const email = form.querySelector('[name="email"]');
    if (email?.value.trim() && !email.validity.valid) {
        errors.email = 'Please enter a valid email address.';
    }

    const birthdate = form.querySelector('[name="birthdate"]');
    if (birthdate?.value) {
        const birth = new Date(`${birthdate.value}T00:00:00`);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (birth > today) {
            errors.birthdate = 'Birthdate cannot be in the future.';
        }
    }

    if (Object.keys(errors).length > 0) {
        applyPatientValidationErrors(form, errors);
        return false;
    }

    return true;
}

function syncPatientFullname(form) {
    if (!form) {
        return;
    }

    const target = form.querySelector('[data-fullname-target]');
    if (!target) {
        return;
    }

    const parts = ['first_name', 'middle_name', 'last_name', 'suffix']
        .map((name) => form.querySelector(`[name="${name}"]`)?.value.trim() || '')
        .filter(Boolean);
    target.value = parts.join(' ');
}

function updatePatientAge(form) {
    if (!form) {
        return;
    }

    const birthdate = form.querySelector('[data-birthdate]')?.value;
    const output = form.querySelector('[data-age-output]');

    if (!birthdate || !output) {
        return;
    }

    const birth = new Date(`${birthdate}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (birth > today) {
        output.value = '';
        form.querySelector('[data-birthdate]')?.setCustomValidity('Birthdate cannot be in the future.');
        return;
    }

    form.querySelector('[data-birthdate]')?.setCustomValidity('');
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age -= 1;
    }

    output.value = Number.isFinite(age) && age >= 0 ? String(age) : '';
}

function previewPatientPhoto(input) {
    const file = input.files?.[0];
    const form = input.closest('form');
    const preview = form?.querySelector('[data-patient-photo-preview]');

    if (!file || !preview) {
        return;
    }

    startPatientPhotoCrop(form, file);
}

function setPatientPhotoPreview(preview, src = '', alt = 'Patient') {
    if (!preview) {
        return;
    }

    preview.textContent = '';
    if (src) {
        const image = document.createElement('img');
        image.src = src;
        image.alt = alt;
        preview.appendChild(image);
    } else {
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-user';
        icon.setAttribute('aria-hidden', 'true');
        preview.appendChild(icon);
    }

    const badge = document.createElement('span');
    badge.className = 'patient-photo-edit-icon';
    badge.innerHTML = '<i class="fa-solid fa-camera" aria-hidden="true"></i>';
    preview.appendChild(badge);
}

function patientPhotoSrc(path) {
    const cleanPath = String(path || '').trim();
    if (!cleanPath) {
        return '';
    }

    if (/^(https?:)?\/\//i.test(cleanPath) || cleanPath.startsWith('data:') || cleanPath.startsWith('../')) {
        return cleanPath;
    }

    return `../${cleanPath}`;
}

function patientCropElements(form) {
    return {
        cropper: form?.querySelector('[data-patient-cropper]'),
        stage: form?.querySelector('[data-patient-crop-stage]'),
        image: form?.querySelector('[data-patient-crop-image]'),
        zoom: form?.querySelector('[data-patient-crop-zoom]'),
        preview: form?.querySelector('[data-patient-photo-preview]'),
    };
}

function clearPatientCrop(form, clearInput = true) {
    if (!form) {
        return;
    }

    const state = patientCropStates.get(form);
    if (state?.url) {
        URL.revokeObjectURL(state.url);
    }

    patientCropStates.delete(form);

    const { cropper, image, zoom, preview } = patientCropElements(form);
    if (cropper) cropper.hidden = true;
    if (image) image.removeAttribute('src');
    if (zoom) zoom.value = '1';
    preview?.querySelector('[data-patient-crop-preview-image]')?.remove();

    if (clearInput) {
        const input = form.querySelector('[data-patient-photo-input]');
        if (input) input.value = '';
    }
}

function clampPatientCrop(form) {
    const state = patientCropStates.get(form);
    const { stage } = patientCropElements(form);

    if (!state || !stage) {
        return;
    }

    const size = stage.getBoundingClientRect().width;
    const scale = state.baseScale * state.zoom;
    const scaledWidth = state.naturalWidth * scale;
    const scaledHeight = state.naturalHeight * scale;
    const maxX = Math.max(0, (scaledWidth - size) / 2);
    const maxY = Math.max(0, (scaledHeight - size) / 2);

    state.offsetX = Math.min(maxX, Math.max(-maxX, state.offsetX));
    state.offsetY = Math.min(maxY, Math.max(-maxY, state.offsetY));
}

function renderPatientCropPreview(form) {
    const state = patientCropStates.get(form);
    const { stage, preview } = patientCropElements(form);
    const image = preview?.querySelector('[data-patient-crop-preview-image]');

    if (!state || !stage || !preview || !image) {
        return;
    }

    const previewSize = preview.getBoundingClientRect().width;
    const stageSize = stage.getBoundingClientRect().width;
    const previewRatio = previewSize / stageSize;
    const scale = state.baseScale * state.zoom * previewRatio;

    image.style.position = 'absolute';
    image.style.left = '50%';
    image.style.top = '50%';
    image.style.width = `${state.naturalWidth * scale}px`;
    image.style.height = `${state.naturalHeight * scale}px`;
    image.style.objectFit = 'initial';
    image.style.transform = `translate(-50%, -50%) translate(${state.offsetX * previewRatio}px, ${state.offsetY * previewRatio}px)`;
}

function renderPatientCrop(form) {
    const state = patientCropStates.get(form);
    const { image } = patientCropElements(form);

    if (!state || !image) {
        return;
    }

    clampPatientCrop(form);
    const scale = state.baseScale * state.zoom;
    image.style.width = `${state.naturalWidth * scale}px`;
    image.style.height = `${state.naturalHeight * scale}px`;
    image.style.transform = `translate(-50%, -50%) translate(${state.offsetX}px, ${state.offsetY}px)`;
    renderPatientCropPreview(form);
}

function resetPatientCropPosition(form) {
    const state = patientCropStates.get(form);
    const { stage, zoom } = patientCropElements(form);

    if (!state || !stage) {
        return;
    }

    const size = stage.getBoundingClientRect().width;
    state.baseScale = size / Math.min(state.naturalWidth, state.naturalHeight);
    state.zoom = 1;
    state.offsetX = 0;
    state.offsetY = 0;

    if (zoom) zoom.value = '1';
    renderPatientCrop(form);
}

function startPatientPhotoCrop(form, file) {
    const { cropper, image, preview } = patientCropElements(form);

    if (!form || !file || !cropper || !image || !preview) {
        return;
    }

    clearPatientCrop(form, false);

    const state = createCropState(file);
    state.url = URL.createObjectURL(file);
    patientCropStates.set(form, state);

    image.addEventListener('load', () => {
        state.naturalWidth = image.naturalWidth;
        state.naturalHeight = image.naturalHeight;
        cropper.hidden = false;
        resetPatientCropPosition(form);
    }, { once: true });

    setPatientPhotoPreview(preview, state.url, 'Patient preview');
    const previewImage = preview.querySelector('img');
    if (previewImage) {
        previewImage.dataset.patientCropPreviewImage = 'true';
    }

    image.src = state.url;
}

function buildCroppedPatientPhotoBlob(form) {
    const state = patientCropStates.get(form);
    const { stage, image } = patientCropElements(form);

    if (!state?.file || !stage || !image || !state.naturalWidth || !state.naturalHeight) {
        return Promise.resolve(null);
    }

    const canvas = document.createElement('canvas');
    const outputSize = 512;
    const stageSize = stage.getBoundingClientRect().width;
    const ratio = outputSize / stageSize;
    const scale = state.baseScale * state.zoom;
    const drawWidth = state.naturalWidth * scale * ratio;
    const drawHeight = state.naturalHeight * scale * ratio;
    const drawX = (outputSize / 2) - (drawWidth / 2) + (state.offsetX * ratio);
    const drawY = (outputSize / 2) - (drawHeight / 2) + (state.offsetY * ratio);
    const context = canvas.getContext('2d');

    canvas.width = outputSize;
    canvas.height = outputSize;
    context.fillStyle = '#f3f4f6';
    context.fillRect(0, 0, outputSize, outputSize);
    context.drawImage(image, drawX, drawY, drawWidth, drawHeight);

    return new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', 0.92);
    });
}

function resetPatientPhotoPreview(form) {
    setPatientPhotoPreview(form?.querySelector('[data-patient-photo-preview]'));
}

function resetPatientFormTabs(form) {
    const tabs = form?.querySelector('[data-tabs]');
    if (!tabs) {
        return;
    }

    tabs.querySelectorAll('[data-tab-target]').forEach((button) => {
        button.classList.toggle('active', button.dataset.tabTarget === 'personal');
    });
    tabs.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.tabPanel === 'personal');
    });
}

function updateHmoFields(form) {
    if (!form) {
        return;
    }

    const enabled = form.querySelector('[name="has_hmo"]:checked')?.value === 'Yes';
    const fields = form.querySelector('[data-hmo-fields]');

    if (fields) {
        fields.hidden = !enabled;
    }
}

function showPatientListView() {
    const workspace = document.querySelector('[data-patient-workspace]');
    const listView = workspace?.querySelector('[data-patient-list-view]');

    if (!workspace || !listView) {
        return;
    }

    workspace.querySelectorAll('[data-patient-form-view]').forEach((panel) => {
        const form = panel.querySelector('form');
        stopPatientCamera(form);
        clearPatientCrop(form, false);
        panel.hidden = true;
    });
    document.body.classList.remove('patient-modal-open');
    listView.hidden = false;
}

function showPatientFormView(panel) {
    const workspace = document.querySelector('[data-patient-workspace]');

    if (!workspace || !panel) {
        return;
    }

    workspace.querySelectorAll('[data-patient-form-view]').forEach((item) => {
        if (item !== panel) {
            const form = item.querySelector('form');
            stopPatientCamera(form);
            clearPatientCrop(form, false);
            item.hidden = true;
        }
    });

    document.body.classList.add('patient-modal-open');
    panel.hidden = false;
    panel.querySelector('[data-birthdate]') && updatePatientAge(panel.querySelector('form'));
    panel.querySelector('[name="has_hmo"]') && updateHmoFields(panel.querySelector('form'));
}

function fillPatientForm(form, patient) {
    if (!form) {
        return;
    }

    clearPatientValidation(form);
    form.querySelector('[name="id"]').value = patient.id || '';
    const fallbackNameParts = String(patient.fullname || '').trim().split(/\s+/).filter(Boolean);
    const fallbackFirstName = patient.first_name || fallbackNameParts.shift() || '';
    const fallbackLastName = patient.last_name || fallbackNameParts.join(' ');
    const values = {
        fullname: patient.fullname || '',
        patient_photo: patient.patient_photo || '',
        first_name: fallbackFirstName,
        middle_name: patient.middle_name || '',
        last_name: fallbackLastName,
        suffix: patient.suffix || '',
        birthdate: patient.birthdate || '',
        gender: patient.gender || 'Male',
        contact_number: patient.contact_number || '',
        email: patient.email || '',
        address: patient.address || '',
        emergency_contact: patient.emergency_contact || '',
        emergency_contact_number: patient.emergency_contact_number || '',
        hmo_provider: patient.hmo_provider || '',
        hmo_card_number: patient.hmo_card_number || '',
        hmo_type: patient.hmo_type || '',
        hmo_expiration_date: patient.hmo_expiration_date || '',
        allergies: patient.allergies || '',
        medical_conditions: patient.medical_conditions || '',
        current_medications: patient.current_medications || '',
        medical_notes: patient.medical_notes || '',
    };

    Object.entries(values).forEach(([name, value]) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (field) {
            field.value = value;
        }
    });

    const hasHmo = (patient.has_hmo || 'No') === 'Yes';
    const hmoRadio = form.querySelector(`[name="has_hmo"][value="${hasHmo ? 'Yes' : 'No'}"]`);
    if (hmoRadio) hmoRadio.checked = true;

    const photoPreview = form.querySelector('[data-patient-photo-preview]');
    if (photoPreview) {
        const photoSrc = patientPhotoSrc(patient.patient_photo);
        if (photoSrc) {
            setPatientPhotoPreview(photoPreview, photoSrc, patient.fullname || 'Patient');
        } else {
            setPatientPhotoPreview(photoPreview);
        }
    }

    syncPatientFullname(form);
    updatePatientAge(form);
    updateHmoFields(form);
}

function detailValue(value) {
    return value ? escapeHtml(value) : '<span class="muted">Not recorded</span>';
}

function renderPatientProfile(patient) {
    const panel = document.querySelector('[data-patient-profile]');
    if (!panel) {
        return;
    }

    const hasHmo = (patient.has_hmo || 'No') === 'Yes';
    panel.hidden = false;
    panel.innerHTML = `
        <div class="card-header">
            <div><h2>${escapeHtml(patient.fullname || 'Patient Profile')}</h2><p class="muted">${escapeHtml(patient.patient_no || '')}</p></div>
            <button class="button button-small button-light" type="button" data-profile-close>Close</button>
        </div>
        <div class="profile-card-grid">
            <article><h3>Personal Information</h3>${patientPhotoSrc(patient.patient_photo) ? `<img class="profile-patient-photo" src="${escapeHtml(patientPhotoSrc(patient.patient_photo))}" alt="${escapeHtml(patient.fullname || 'Patient')}">` : ''}<p>Age: ${detailValue(patient.age)}</p><p>Sex: ${detailValue(patient.gender)}</p><p>Birthdate: ${detailValue(patient.birthdate)}</p></article>
            <article><h3>Contact Information</h3><p>${detailValue(patient.contact_number)}</p><p>${detailValue(patient.email)}</p><p>${detailValue(patient.address)}</p><p>Emergency: ${detailValue(patient.emergency_contact)} ${patient.emergency_contact_number ? `(${escapeHtml(patient.emergency_contact_number)})` : ''}</p></article>
            <article><h3>HMO Information</h3><p>${hasHmo ? 'With HMO' : 'No HMO'}</p><p>${detailValue(patient.hmo_provider)}</p><p>${detailValue(patient.hmo_card_number)}</p><p>${detailValue(patient.hmo_type)}</p></article>
            <article><h3>Medical Information</h3><p>Allergies: ${detailValue(patient.allergies)}</p><p>Conditions: ${detailValue(patient.medical_conditions)}</p><p>Medications: ${detailValue(patient.current_medications)}</p><p>Notes: ${detailValue(patient.medical_notes)}</p></article>
            <article><h3>Appointment History</h3><p class="muted">Open Appointments to review dated records.</p></article>
            <article><h3>No Show History</h3><p><strong>${escapeHtml(patient.no_show_count || 0)}</strong> recorded no-show/cancelled visit${Number(patient.no_show_count || 0) === 1 ? '' : 's'}.</p></article>
        </div>
    `;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

content.addEventListener('click', (event) => {
    if (event.target.closest('[data-profile-close]')) {
        const panel = document.querySelector('[data-patient-profile]');
        if (panel) panel.hidden = true;
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
                    if (notificationsDropdown) notificationsDropdown.hidden = true;
                    notificationsToggle?.setAttribute('aria-expanded', 'false');
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
    e.stopPropagation();
    const open = notificationsDropdown?.hidden === false;
    if (notificationsDropdown) {
        notificationsDropdown.hidden = open;
    }
    notificationsToggle.setAttribute('aria-expanded', String(!open));
    if (!open) {
        fetchNotifications();
    }
});

document.addEventListener('click', (event) => {
    if (!notificationsDropdown || notificationsDropdown.hidden) {
        return;
    }

    if (!event.target.closest('.notifications-wrap')) {
        notificationsDropdown.hidden = true;
        notificationsToggle?.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const patientModal = document.querySelector('[data-patient-form-view]:not([hidden])');
        if (patientModal) {
            stopPatientCamera(patientModal.querySelector('form'));
            showPatientListView();
            return;
        }
    }

    if (event.key === 'Escape' && notificationsDropdown && !notificationsDropdown.hidden) {
        notificationsDropdown.hidden = true;
        notificationsToggle?.setAttribute('aria-expanded', 'false');
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
