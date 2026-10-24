<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../email/phpmailer/src/Exception.php';
require_once __DIR__ . '/../email/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../email/phpmailer/src/SMTP.php';

// Reuse the email configuration used by your existing email feature.
require_once __DIR__ . '/../config/email_config.php';

function sendAccountWelcomeEmail(
    string $email,
    string $firstName,
    string $role
): bool {
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($email, $firstName);

        $mail->isHTML(true);

        $safeName = htmlspecialchars(
            $firstName,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $mail->Subject =
            "Welcome to I'll BeCare - Account Created Successfully";

        if ($role === 'provider') {
            $message = '
                <p>Your Provider account was created successfully.</p>
                <p>Your submitted documents are pending Administrator
                review. Your profile will be available to Clients
                after approval.</p>
            ';
        } else {
            $message = '
                <p>Your Client account was created successfully.</p>
                <p>You can now log in to I’ll BeCare to explore
                available care and household support services.</p>
            ';
        }

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;line-height:1.6'>
                <h2>Welcome to I'll BeCare, {$safeName}!</h2>
                {$message}
                <p>Thank you for joining our community.</p>
                <p>Best regards,<br>I'll BeCare Team</p>
            </div>
        ";

        $mail->AltBody =
            "Welcome to I'll BeCare, {$firstName}! " .
            "Your " . ucfirst($role) .
            " account was created successfully.";

        $mail->send();

        return true;

    } catch (\Throwable $e) {
        error_log(
            'Welcome email failed: ' . $e->getMessage()
        );

        return false;
    }
}
