<?php
declare(strict_types=1);

require __DIR__ . '/../config/connection.php';

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

/* Record the logout */
if ($uid !== null && isset($conn) && $conn instanceof mysqli) {
    try {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $conn->prepare(
            "INSERT INTO activity_logs (user_id, action, details, ip_address, outcome)
             VALUES (?, 'logout', 'user logged out', ?, 'success')"
        );
        $stmt->bind_param('is', $uid, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('logout log failed: ' . $e->getMessage());
    }
}

/* Destroy the session completely */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
}

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ../index.html');
exit;