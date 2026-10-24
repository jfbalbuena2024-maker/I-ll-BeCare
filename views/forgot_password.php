<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/connection.php';   // gives $conn (mysqli)
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../email/phpmailer/src/Exception.php';
require_once __DIR__ . '/../email/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../email/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const OTP_MINUTES  = 10;
const MAX_ATTEMPTS = 5;
const COOLDOWN_SEC = 60;

function out($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $extra));
    exit;
}

function log_activity($conn, $uid, $action, $details, $outcome = 'success') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $st = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, outcome) VALUES (?,?,?,?,?)");
    $st->bind_param('issss', $uid, $action, $details, $ip, $outcome);
    $st->execute();
}

function send_otp_mail($to, $name, $otp) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = str_replace(' ', '', SMTP_PASS);
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress($to, $name);
    $mail->isHTML(true);
    $mail->Subject = "Your I'll BeCare password reset code";
    $mail->Body    = "<p>Hi " . htmlspecialchars($name) . ",</p>"
                   . "<p>Your password reset code is:</p>"
                   . "<h2 style='letter-spacing:6px'>$otp</h2>"
                   . "<p>It expires in " . OTP_MINUTES . " minutes. If you didn't request this, ignore this email.</p>";
    $mail->AltBody = "Your I'll BeCare reset code is $otp. It expires in " . OTP_MINUTES . " minutes.";
    $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'Invalid request.');
$action = $_POST['action'] ?? '';

/* ---------- STEP 1: send OTP ---------- */
if ($action === 'send') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Enter a valid email address.');

    $generic = 'If that email is registered, a 6-digit code has been sent.';
    unset($_SESSION['reset_pending_uid'], $_SESSION['reset_verified_uid'], $_SESSION['reset_verified_until']);

    $st = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ? AND status = 'active' AND role <> 'admin' LIMIT 1");
    $st->bind_param('s', $email);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    if (!$user) out(true, $generic);   // same answer whether the email exists or not

    $uid = (int)$user['user_id'];

    // cooldown: block rapid re-sends
    $st = $conn->prepare("SELECT 1 FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL " . COOLDOWN_SEC . " SECOND) LIMIT 1");
    $st->bind_param('i', $uid);
    $st->execute();
    if ($st->get_result()->num_rows > 0) {
        $_SESSION['reset_pending_uid'] = $uid;
        out(true, $generic);
    }

    // invalidate older codes
    $st = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $st->bind_param('i', $uid);
    $st->execute();

    $otp  = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($otp, PASSWORD_DEFAULT);

        $st = $conn->prepare("INSERT INTO password_resets (user_id, otp_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL " . OTP_MINUTES . " MINUTE)");
    $st->bind_param('is', $uid, $hash);
    $st->execute();
    $resetId = $conn->insert_id;

    try {
        send_otp_mail($email, $user['first_name'], $otp);
    } catch (Exception $e) {
        error_log('OTP mail failed: ' . $e->getMessage());
        $del = $conn->prepare("DELETE FROM password_resets WHERE reset_id = ?");
        $del->bind_param('i', $resetId);
        $del->execute();
        log_activity($conn, $uid, 'password_reset_request', 'Email sending failed', 'failed');
        out(false, 'We could not send the email right now. Please try again later.');
    }

    $_SESSION['reset_pending_uid'] = $uid;
    log_activity($conn, $uid, 'password_reset_request', 'OTP sent');
    out(true, $generic);
}

/* ---------- STEP 2: verify OTP ---------- */
if ($action === 'verify') {
    $otp = trim($_POST['otp'] ?? '');
    $uid = $_SESSION['reset_pending_uid'] ?? null;
    if (!$uid || !preg_match('/^\d{6}$/', $otp)) out(false, 'Invalid or expired code.');

    $st = $conn->prepare("SELECT reset_id, otp_hash, attempts FROM password_resets
                          WHERE user_id = ? AND used = 0 AND expires_at > NOW()
                          ORDER BY reset_id DESC LIMIT 1");
    $st->bind_param('i', $uid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) out(false, 'Invalid or expired code. Request a new one.');

    if ($row['attempts'] >= MAX_ATTEMPTS) {
        $conn->query("UPDATE password_resets SET used = 1 WHERE reset_id = " . (int)$row['reset_id']);
        log_activity($conn, $uid, 'password_reset_verify', 'Too many attempts', 'failed');
        out(false, 'Too many wrong attempts. Request a new code.');
    }

    if (!password_verify($otp, $row['otp_hash'])) {
        $conn->query("UPDATE password_resets SET attempts = attempts + 1 WHERE reset_id = " . (int)$row['reset_id']);
        log_activity($conn, $uid, 'password_reset_verify', 'Wrong code', 'failed');
        out(false, 'Incorrect code.');
    }

    $conn->query("UPDATE password_resets SET used = 1 WHERE reset_id = " . (int)$row['reset_id']);
    session_regenerate_id(true);
    $_SESSION['reset_verified_uid']   = $uid;
    $_SESSION['reset_verified_until'] = time() + 600;
    unset($_SESSION['reset_pending_uid']);
    out(true, 'Code verified. Set your new password.');
}

/* ---------- STEP 3: set new password ---------- */
if ($action === 'reset') {
    $uid = $_SESSION['reset_verified_uid'] ?? null;
    $exp = $_SESSION['reset_verified_until'] ?? 0;
    if (!$uid || time() > $exp) out(false, 'Session expired. Please start again.');

    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm'] ?? '';
    if (strlen($pw) < 8)  out(false, 'Password must be at least 8 characters.');
    if ($pw !== $pw2)     out(false, 'Passwords do not match.');

    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $st = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND role <> 'admin'");
    $st->bind_param('si', $hash, $uid);
    $st->execute();

    log_activity($conn, (int)$uid, 'password_reset_done', 'Password changed via OTP');
    unset($_SESSION['reset_verified_uid'], $_SESSION['reset_verified_until']);
    out(true, 'Password updated. You can now log in.');
}

out(false, 'Invalid request.');