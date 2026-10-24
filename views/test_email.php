<?php
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../email/phpmailer/src/Exception.php';
require_once __DIR__ . '/../email/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../email/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/plain; charset=utf-8');

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug   = 2;
    $mail->Debugoutput = 'echo';
    $mail->isSMTP();
    $mail->Host        = SMTP_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = SMTP_USER;
    $mail->Password    = str_replace(' ', '', SMTP_PASS);
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = SMTP_PORT;

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER);
    $mail->Subject = 'SMTP test';
    $mail->Body    = 'It works.';
    $mail->send();
    echo "\n\nSUCCESS: check the inbox.";
} catch (Exception $e) {
    echo "\n\nFAILED: " . $mail->ErrorInfo;
}