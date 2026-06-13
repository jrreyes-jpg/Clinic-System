<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashError('Your session expired. Please try again.');
    redirect('create.php');
}

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
    setFlashError(implode(' ', $errors));
    redirect('create.php');
}

$patientId = createPatient($data);
setFlashSuccess('Patient record created successfully.');
redirect('view.php?id=' . $patientId);
