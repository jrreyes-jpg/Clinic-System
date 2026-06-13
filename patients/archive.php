<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['admin', 'receptionist']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashError('Your session expired. Please try again.');
    redirect('index.php');
}

$patientId = (int) ($_POST['id'] ?? 0);

if ($patientId <= 0 || findPatientById($patientId) === null) {
    setFlashError('Patient record not found.');
    redirect('index.php');
}

archivePatient($patientId);
setFlashSuccess('Patient record archived successfully.');
redirect('index.php');
