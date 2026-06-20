<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
header('Content-Type: application/json');

function jsonResponse(bool $ok, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(false, 'Your session expired. Please refresh and try again.');
}

$action = (string) ($_POST['action'] ?? '');
$currentUser = currentUser();

function saveHmoCardUpload(int $patientId = 0): string
{
    if (!isset($_FILES['hmo_card_upload']) || $_FILES['hmo_card_upload']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES['hmo_card_upload']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'HMO card upload failed. Please choose another file.');
    }

    $tmpPath = (string) $_FILES['hmo_card_upload']['tmp_name'];
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $mimeType = mime_content_type($tmpPath) ?: '';

    if (!isset($allowedTypes[$mimeType])) {
        jsonResponse(false, 'HMO card must be a JPG, PNG, WEBP, or PDF file.');
    }

    $targetDir = __DIR__ . '/../assets/hmo';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        jsonResponse(false, 'HMO card upload folder could not be created.');
    }

    $prefix = $patientId > 0 ? 'patient-' . $patientId : 'patient-new';
    $filename = $prefix . '-hmo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        jsonResponse(false, 'HMO card could not be saved.');
    }

    return 'assets/hmo/' . $filename;
}

function savePatientPhotoUpload(int $patientId = 0): string
{
    if (!isset($_FILES['patient_photo_upload']) || $_FILES['patient_photo_upload']['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES['patient_photo_upload']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'Patient photo upload failed. Please choose another image.');
    }

    $tmpPath = (string) $_FILES['patient_photo_upload']['tmp_name'];
    $imageInfo = getimagesize($tmpPath);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if ($imageInfo === false || !isset($allowedTypes[$imageInfo['mime']])) {
        jsonResponse(false, 'Patient photo must be a JPG, PNG, or WEBP image.');
    }

    $targetDir = __DIR__ . '/../assets/patients';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        jsonResponse(false, 'Patient photo upload folder could not be created.');
    }

    $prefix = $patientId > 0 ? 'patient-' . $patientId : 'patient-new';
    $filename = $prefix . '-photo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$imageInfo['mime']];
    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        jsonResponse(false, 'Patient photo could not be saved.');
    }

    return 'assets/patients/' . $filename;
}

try {
    if ($action === 'create_patient') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $middleName = trim((string) ($_POST['middle_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $suffix = trim((string) ($_POST['suffix'] ?? ''));
        $fullNameParts = array_filter([$firstName, $middleName, $lastName, $suffix], static fn ($part) => $part !== '');
        $data = [
            'fullname' => trim((string) ($_POST['fullname'] ?? '')) ?: implode(' ', $fullNameParts),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'patient_photo' => '',
            'birthdate' => trim((string) ($_POST['birthdate'] ?? '')),
            'gender' => (string) ($_POST['gender'] ?? ''),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'emergency_contact' => trim((string) ($_POST['emergency_contact'] ?? '')),
            'emergency_contact_number' => trim((string) ($_POST['emergency_contact_number'] ?? '')),
            'has_hmo' => (string) ($_POST['has_hmo'] ?? 'No'),
            'hmo_provider' => trim((string) ($_POST['hmo_provider'] ?? '')),
            'hmo_card_number' => trim((string) ($_POST['hmo_card_number'] ?? '')),
            'hmo_type' => trim((string) ($_POST['hmo_type'] ?? '')),
            'hmo_expiration_date' => trim((string) ($_POST['hmo_expiration_date'] ?? '')) ?: null,
            'hmo_card_file' => '',
            'allergies' => trim((string) ($_POST['allergies'] ?? '')),
            'medical_conditions' => trim((string) ($_POST['medical_conditions'] ?? '')),
            'current_medications' => trim((string) ($_POST['current_medications'] ?? '')),
            'medical_notes' => trim((string) ($_POST['medical_notes'] ?? '')),
        ];
        $fieldErrors = validatePatientDataFields($data);
        $errors = array_values($fieldErrors);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors), ['errors' => $fieldErrors]);
        }
        $data['patient_photo'] = savePatientPhotoUpload();
        $data['hmo_card_file'] = saveHmoCardUpload();
        $patientId = createPatient($data);
        createNotification(null, 'new_patient', 'New patient registered: ' . $data['fullname'], ['patient_id' => $patientId]);
        createAuditLog((int) ($currentUser['id'] ?? null), 'created patient', ['patient_id' => $patientId, 'fullname' => $data['fullname']]);
        jsonResponse(true, 'Patient created successfully.');
    }

    if ($action === 'archive_patient') {
        $pid = (int) ($_POST['id'] ?? 0);
        archivePatient($pid);
        createAuditLog((int) ($currentUser['id'] ?? null), 'archived patient', ['patient_id' => $pid]);
        jsonResponse(true, 'Patient archived successfully.');
    }

    if ($action === 'update_patient') {
        $patientId = (int) ($_POST['id'] ?? 0);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $middleName = trim((string) ($_POST['middle_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $suffix = trim((string) ($_POST['suffix'] ?? ''));
        $fullNameParts = array_filter([$firstName, $middleName, $lastName, $suffix], static fn ($part) => $part !== '');
        $data = [
            'fullname' => trim((string) ($_POST['fullname'] ?? '')) ?: implode(' ', $fullNameParts),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $suffix,
            'patient_photo' => '',
            'birthdate' => trim((string) ($_POST['birthdate'] ?? '')),
            'gender' => (string) ($_POST['gender'] ?? ''),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'emergency_contact' => trim((string) ($_POST['emergency_contact'] ?? '')),
            'emergency_contact_number' => trim((string) ($_POST['emergency_contact_number'] ?? '')),
            'has_hmo' => (string) ($_POST['has_hmo'] ?? 'No'),
            'hmo_provider' => trim((string) ($_POST['hmo_provider'] ?? '')),
            'hmo_card_number' => trim((string) ($_POST['hmo_card_number'] ?? '')),
            'hmo_type' => trim((string) ($_POST['hmo_type'] ?? '')),
            'hmo_expiration_date' => trim((string) ($_POST['hmo_expiration_date'] ?? '')) ?: null,
            'hmo_card_file' => '',
            'allergies' => trim((string) ($_POST['allergies'] ?? '')),
            'medical_conditions' => trim((string) ($_POST['medical_conditions'] ?? '')),
            'current_medications' => trim((string) ($_POST['current_medications'] ?? '')),
            'medical_notes' => trim((string) ($_POST['medical_notes'] ?? '')),
        ];
        $fieldErrors = validatePatientDataFields($data);
        $errors = array_values($fieldErrors);
        if ($patientId <= 0 || $errors !== []) {
            jsonResponse(false, $errors === [] ? 'Invalid patient record.' : implode(' ', $errors), ['errors' => $fieldErrors]);
        }
        $hmoCardFile = saveHmoCardUpload($patientId);
        $patientPhoto = savePatientPhotoUpload($patientId);
        $data['hmo_card_file'] = $hmoCardFile;
        $data['patient_photo'] = $patientPhoto;
        if ($hmoCardFile === '') {
            unset($data['hmo_card_file']);
        }
        if ($patientPhoto === '' && (string) ($_POST['remove_patient_photo'] ?? '0') === '1') {
            $data['patient_photo'] = '';
        } elseif ($patientPhoto === '') {
            unset($data['patient_photo']);
        }
        updatePatient($patientId, $data);
        createAuditLog((int) ($currentUser['id'] ?? null), 'updated patient', ['patient_id' => $patientId]);
        jsonResponse(true, 'Patient updated successfully.');
    }

    if ($action === 'create_appointment') {
        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'appointment_source' => trim((string) ($_POST['appointment_source'] ?? 'Walk-In')),
            'appointment_date' => trim((string) ($_POST['appointment_date'] ?? '')),
            'appointment_time' => trim((string) ($_POST['appointment_time'] ?? '')),
            'dentist' => trim((string) ($_POST['dentist'] ?? '')),
            'service_type' => trim((string) ($_POST['service_type'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'pending'),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
        $errors = validateAppointmentData($data);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors));
        }
        $appointmentId = createAppointment($data);
        createNotification(null, 'appointment_scheduled', 'Appointment scheduled for patient ID ' . $data['patient_id'] . ' on ' . $data['appointment_date'], ['appointment_id' => $appointmentId]);
        createAuditLog((int) ($currentUser['id'] ?? null), 'created appointment', ['appointment_id' => $appointmentId, 'patient_id' => $data['patient_id'], 'date' => $data['appointment_date']]);
        jsonResponse(true, 'Appointment scheduled successfully.');
    }

    if ($action === 'complete_appointment') {
        $id = (int) ($_POST['id'] ?? 0);
        updateAppointmentStatus($id, 'completed');
        createNotification(null, 'appointment_completed', 'Appointment #' . $id . ' marked completed.', ['appointment_id' => $id]);
        createAuditLog((int) ($currentUser['id'] ?? null), 'completed appointment', ['appointment_id' => $id]);
        jsonResponse(true, 'Appointment marked as completed.');
    }

    if ($action === 'cancel_appointment') {
        $id = (int) ($_POST['id'] ?? 0);
        updateAppointmentStatus($id, 'cancelled');
        createNotification(null, 'appointment_cancelled', 'Appointment #' . $id . ' was cancelled.', ['appointment_id' => $id]);
        createAuditLog((int) ($currentUser['id'] ?? null), 'cancelled appointment', ['appointment_id' => $id]);
        jsonResponse(true, 'Appointment cancelled successfully.');
    }

    if ($action === 'update_appointment') {
        $appointmentId = (int) ($_POST['id'] ?? 0);
        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'appointment_source' => trim((string) ($_POST['appointment_source'] ?? 'Walk-In')),
            'appointment_date' => trim((string) ($_POST['appointment_date'] ?? '')),
            'appointment_time' => trim((string) ($_POST['appointment_time'] ?? '')),
            'dentist' => trim((string) ($_POST['dentist'] ?? '')),
            'service_type' => trim((string) ($_POST['service_type'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'pending'),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
        $errors = validateAppointmentData($data);
        if ($appointmentId <= 0 || $errors !== []) {
            jsonResponse(false, $errors === [] ? 'Please select an appointment to update.' : implode(' ', $errors));
        }
        updateAppointment($appointmentId, $data);
        if ($data['status'] === 'confirmed') {
            createNotification(null, 'appointment_confirmed', 'Appointment #' . $appointmentId . ' was confirmed.', ['appointment_id' => $appointmentId]);
        }
        createAuditLog((int) ($currentUser['id'] ?? null), 'updated appointment', ['appointment_id' => $appointmentId, 'status' => $data['status']]);
        
        jsonResponse(true, 'Appointment updated successfully.');
    }

    if ($action === 'create_service') {
        $data = [
            'service_name' => trim((string) ($_POST['service_name'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];

        $errors = validateServiceData($data);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors));
        }

        $serviceId = createService($data);
        createAuditLog((int) ($currentUser['id'] ?? null), 'created service', ['service_id' => $serviceId, 'service_name' => $data['service_name']]);
        jsonResponse(true, 'Service created successfully.');
    }

    if ($action === 'update_service') {
        $serviceId = (int) ($_POST['id'] ?? 0);
        $data = [
            'service_name' => trim((string) ($_POST['service_name'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];

        $errors = validateServiceData($data);
        if ($serviceId <= 0 || $errors !== []) {
            jsonResponse(false, $errors === [] ? 'Please select a service to update.' : implode(' ', $errors));
        }

        updateService($serviceId, $data);
        createAuditLog((int) ($currentUser['id'] ?? null), 'updated service', ['service_id' => $serviceId]);
        jsonResponse(true, 'Service updated successfully.');
    }

    if ($action === 'create_bill') {
        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'service_id' => (int) ($_POST['service_id'] ?? 0),
            'amount' => trim((string) ($_POST['amount'] ?? '')),
            'payment_status' => trim((string) ($_POST['payment_status'] ?? 'Unpaid')),
            'payment_date' => trim((string) ($_POST['payment_date'] ?? '')),
        ];

        $errors = validateBillData($data);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors));
        }

        $billId = createBill($data);
        createAuditLog((int) ($currentUser['id'] ?? null), 'created bill', ['bill_id' => $billId, 'patient_id' => $data['patient_id']]);
        jsonResponse(true, 'Bill generated successfully.');
    }

    if ($action === 'record_payment') {
        $billId = (int) ($_POST['bill_id'] ?? 0);
        $data = [
            'payment_status' => trim((string) ($_POST['payment_status'] ?? '')),
            'payment_date' => trim((string) ($_POST['payment_date'] ?? '')),
        ];

        $errors = validatePaymentData($data);
        if ($billId <= 0 || $errors !== []) {
            jsonResponse(false, $errors === [] ? 'Please select a bill to update.' : implode(' ', $errors));
        }

        recordPayment($billId, $data);
        createAuditLog((int) ($currentUser['id'] ?? null), 'recorded payment', ['bill_id' => $billId, 'payment_status' => $data['payment_status']]);
        jsonResponse(true, 'Payment recorded successfully.');
    }

    if ($action === 'create_user') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($fullname === '' || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            jsonResponse(false, 'Please complete all user fields with a valid email and 8-character password.');
        }

        $newId = createUser($fullname, $username, $email, $password, 'receptionist', $mobile);
        createAuditLog((int) ($currentUser['id'] ?? null), 'created user', ['user_id' => $newId, 'username' => $username]);
        jsonResponse(true, 'Receptionist account created successfully.');
    }

    if ($action === 'update_logo') {
        if (!isset($_FILES['clinic_logo']) || $_FILES['clinic_logo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(false, 'Please choose a valid logo image.');
        }

        $tmpPath = (string) $_FILES['clinic_logo']['tmp_name'];
        $imageInfo = getimagesize($tmpPath);
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if ($imageInfo === false || !isset($allowedTypes[$imageInfo['mime']])) {
            jsonResponse(false, 'Logo must be a JPG, PNG, or WEBP image.');
        }

        $targetDir = __DIR__ . '/../assets/img';

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $oldPath = $targetDir . '/clinic-logo.' . $extension;
            if (is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        $extension = $allowedTypes[$imageInfo['mime']];
        $targetPath = $targetDir . '/clinic-logo.' . $extension;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            jsonResponse(false, 'Logo upload failed. Please try again.');
        }

        jsonResponse(true, 'Clinic logo updated successfully.', [
            'logoUrl' => clinicLogoUrl('../'),
        ]);
    }

    if ($action === 'update_profile') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $profilePhoto = '';

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Please enter your full name and a valid email address.');
        }

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(false, 'Profile photo upload failed. Please choose another image.');
            }

            $tmpPath = (string) $_FILES['profile_photo']['tmp_name'];
            $imageInfo = getimagesize($tmpPath);
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if ($imageInfo === false || !isset($allowedTypes[$imageInfo['mime']])) {
                jsonResponse(false, 'Profile photo must be a JPG, PNG, or WEBP image.');
            }

            $targetDir = __DIR__ . '/../assets/img';
            $extension = $allowedTypes[$imageInfo['mime']];
            $filename = 'profile-' . (int) ($currentUser['id'] ?? 0) . '.' . $extension;
            $targetPath = $targetDir . '/' . $filename;

            foreach (['jpg', 'png', 'webp'] as $oldExtension) {
                $oldPath = $targetDir . '/profile-' . (int) ($currentUser['id'] ?? 0) . '.' . $oldExtension;
                if ($oldPath !== $targetPath && is_file($oldPath)) {
                    unlink($oldPath);
                }
            }

            if (!move_uploaded_file($tmpPath, $targetPath)) {
                jsonResponse(false, 'Profile photo could not be saved.');
            }

            $profilePhoto = 'assets/img/' . $filename;
        }

        updateCurrentUserProfile((int) ($currentUser['id'] ?? 0), $fullname, $email, $mobile, $profilePhoto);
        $updatedUser = currentUser();

        jsonResponse(true, 'Profile updated successfully.', [
            'fullname' => $updatedUser['fullname'] ?? $fullname,
            'email' => $updatedUser['email'] ?? $email,
            'mobile' => $updatedUser['mobile'] ?? $mobile,
            'profilePhotoUrl' => profilePhotoUrl($updatedUser, '../'),
            'initial' => strtoupper(substr($fullname, 0, 1)),
        ]);
    }
} catch (PDOException $exception) {
    http_response_code(503);
    jsonResponse(false, databaseUnavailableMessage());
}

jsonResponse(false, 'Unknown action.');
