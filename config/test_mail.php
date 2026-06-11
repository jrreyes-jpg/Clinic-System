<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/mailer.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$toEmail = $argv[1] ?? MAIL_FROM_EMAIL;

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo "Please provide a valid recipient email." . PHP_EOL;
    exit(1);
}

$sent = smtpSendMail(
    $toEmail,
    'Clinic Admin',
    'Clinic SMTP Test',
    '<p>This is a test email from your Clinic Management System SMTP setup.</p>'
);

if ($sent) {
    echo "Test email sent successfully to {$toEmail}." . PHP_EOL;
    exit(0);
}

echo "Test email failed: " . smtpLastError() . PHP_EOL;
exit(1);
