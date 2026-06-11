<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

function smtpSetLastError(string $message): void
{
    $GLOBALS['smtp_last_error'] = $message;
}

function smtpLastError(): string
{
    return (string) ($GLOBALS['smtp_last_error'] ?? '');
}

function smtpRead($socket): string
{
    $response = '';

    while ($line = fgets($socket, 515)) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtpCommand($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtpRead($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed after "' . $command . '": ' . trim($response));
    }

    return $response;
}

function smtpSendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    if (MAIL_USERNAME === 'yourgmail@gmail.com' || MAIL_PASSWORD === 'your-google-app-password') {
        smtpSetLastError('SMTP is not configured. Update config/mail.php.');
        error_log(smtpLastError());
        return false;
    }

    $socket = stream_socket_client(
        'ssl://' . MAIL_HOST . ':' . MAIL_PORT,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        smtpSetLastError("SMTP connection failed: {$errno} {$errstr}");
        error_log(smtpLastError());
        return false;
    }

    try {
        smtpRead($socket);
        smtpCommand($socket, 'EHLO localhost', [250]);
        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode(MAIL_USERNAME), [334]);
        smtpCommand($socket, base64_encode(MAIL_PASSWORD), [235]);
        smtpCommand($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $headers = [
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
            'To: ' . $toName . ' <' . $toEmail . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . $htmlBody
            . "\r\n.";

        smtpCommand($socket, $message, [250]);
        smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
        smtpSetLastError('');

        return true;
    } catch (RuntimeException $exception) {
        smtpSetLastError($exception->getMessage());
        error_log(smtpLastError());
        fclose($socket);

        return false;
    }
}
