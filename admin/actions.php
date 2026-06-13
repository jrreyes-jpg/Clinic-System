<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
header('Content-Type: application/json');

function jsonResponse(bool $ok, string $message): never
{
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    jsonResponse(false, 'Your session expired. Please refresh and try again.');
}

$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'create_patient') {
        $data = [
            'fullname' => trim((string) ($_POST['fullname'] ?? '')),
            'birthdate' => trim((string) ($_POST['birthdate'] ?? '')),
            'gender' => (string) ($_POST['gender'] ?? ''),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ];
        $errors = validatePatientData($data);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors));
        }
        createPatient($data);
        jsonResponse(true, 'Patient created successfully.');
    }

    if ($action === 'archive_patient') {
        archivePatient((int) ($_POST['id'] ?? 0));
        jsonResponse(true, 'Patient archived successfully.');
    }

    if ($action === 'update_patient') {
        $patientId = (int) ($_POST['id'] ?? 0);
        $data = [
            'fullname' => trim((string) ($_POST['fullname'] ?? '')),
            'birthdate' => trim((string) ($_POST['birthdate'] ?? '')),
            'gender' => (string) ($_POST['gender'] ?? ''),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'contact_number' => trim((string) ($_POST['contact_number'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
        ];
        $errors = validatePatientData($data);
        if ($patientId <= 0 || $errors !== []) {
            jsonResponse(false, $errors === [] ? 'Invalid patient record.' : implode(' ', $errors));
        }
        updatePatient($patientId, $data);
        jsonResponse(true, 'Patient updated successfully.');
    }

    if ($action === 'create_appointment') {
        $data = [
            'patient_id' => (int) ($_POST['patient_id'] ?? 0),
            'appointment_date' => trim((string) ($_POST['appointment_date'] ?? '')),
            'appointment_time' => trim((string) ($_POST['appointment_time'] ?? '')),
            'service_type' => trim((string) ($_POST['service_type'] ?? '')),
            'status' => (string) ($_POST['status'] ?? 'pending'),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
        $errors = validateAppointmentData($data);
        if ($errors !== []) {
            jsonResponse(false, implode(' ', $errors));
        }
        createAppointment($data);
        jsonResponse(true, 'Appointment scheduled successfully.');
    }

    if ($action === 'complete_appointment') {
        updateAppointmentStatus((int) ($_POST['id'] ?? 0), 'completed');
        jsonResponse(true, 'Appointment marked as completed.');
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

        createUser($fullname, $username, $email, $password, 'receptionist', $mobile);
        jsonResponse(true, 'Receptionist account created successfully.');
    }
} catch (PDOException $exception) {
    jsonResponse(false, 'Database action failed. Please check duplicate records or required data.');
}

jsonResponse(false, 'Unknown action.');
