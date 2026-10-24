<?php
session_start();

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/booking_status.php';

const CANCEL_SECONDS = 24 * 60 * 60; // provider must cancel 24h+ before start

function back(string $key, string $value): void
{
    header('Location: provider_page.php?page=bookings&' . $key . '=' . rawurlencode($value));
    exit;
}

// Only logged-in providers can perform booking actions.
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
    header('Location: ../index.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: provider_page.php?page=bookings');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable.');
}

// CSRF check.
$sessionToken   = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) || !is_string($submittedToken) ||
    $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

complete_ended_bookings($conn);

$userId    = (int) $_SESSION['user_id'];
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$action    = $_POST['action'] ?? '';

if (!$bookingId || $bookingId < 1) {
    back('error', 'invalid_booking');
}

if (!in_array($action, ['confirm', 'decline', 'cancel'], true)) {
    back('error', 'invalid_action');
}

// Resolve the authenticated, approved provider.
$stmt = $conn->prepare("
    SELECT p.provider_id, u.first_name, u.last_name
    FROM providers p
    INNER JOIN users u ON u.user_id = p.user_id
    WHERE p.user_id = ?
      AND u.role = 'provider'
      AND u.status = 'active'
      AND p.verification_status = 'approved'
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    back('error', 'provider_not_approved');
}

$providerId = (int) $provider['provider_id'];
$providerName = trim($provider['first_name'] . ' ' . $provider['last_name']);

// Everything that depends on the action, defined once.
$map = [
    'confirm' => [
        'status' => 'confirmed',
        'type'   => 'booking_confirmed',
        'title'  => 'Booking accepted',
        'msg'    => '%1$s accepted your %2$s %3$s booking on %4$s.',
        'log'    => 'booking_confirmed',
    ],
    'decline' => [
        'status' => 'declined',
        'type'   => 'booking_declined',
        'title'  => 'Booking declined',
        'msg'    => '%1$s declined your %2$s %3$s booking request on %4$s.',
        'log'    => 'booking_declined',
    ],
    'cancel' => [
        'status' => 'cancelled',
        'type'   => 'booking_cancelled',
        'title'  => 'Booking cancelled by provider',
        'msg'    => '%1$s cancelled your confirmed %2$s %3$s booking on %4$s. '
            . 'The time slot is free again, and you may book another provider.',
        'log'    => 'booking_cancelled_by_provider',
    ],
];
$cfg = $map[$action];

$conn->begin_transaction();

try {
    // Lock the booking; get the client's USER id (for the notification)
    // and the seconds left until start, computed by MySQL itself.
    $stmt = $conn->prepare("
    SELECT b.status, b.booking_type, b.start_datetime, b.end_datetime,
           c.user_id AS client_user_id,
           (SELECT sc.name
              FROM provider_services ps
              JOIN service_categories sc ON sc.category_id = ps.category_id
             WHERE ps.provider_service_id = b.provider_service_id) AS category,
           TIMESTAMPDIFF(SECOND, NOW(), b.start_datetime) AS seconds_until_start
    FROM bookings b
    LEFT JOIN clients c ON c.client_id = b.client_id
    WHERE b.booking_id = ?
      AND b.provider_id = ?
    FOR UPDATE
    ");

    $stmt->bind_param('ii', $bookingId, $providerId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        throw new RuntimeException('booking_not_found');
    }

    if ($action === 'cancel') {
        if ($booking['status'] !== 'confirmed') {
            throw new RuntimeException('booking_not_confirmed');
        }
        if ((int) $booking['seconds_until_start'] < CANCEL_SECONDS) {
            throw new RuntimeException('cancellation_too_late');
        }
    } elseif ($booking['status'] !== 'pending') {
        throw new RuntimeException('booking_already_processed');
    }

    $clientUserId = (int) ($booking['client_user_id'] ?? 0);
    if ($clientUserId < 1) {
        throw new RuntimeException('client_account_not_found');
    }
    
    // ONE update, chosen by action. The WHERE repeats the rules as a guard.
    $newStatus = $cfg['status'];

    if ($action === 'cancel') {
        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = ?,
                cancelled_by = 'provider',
                responded_at = NOW()
            WHERE booking_id = ?
              AND provider_id = ?
              AND status = 'confirmed'
        ");
    } else {
        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = ?,
                responded_at = NOW()
            WHERE booking_id = ?
              AND provider_id = ?
              AND status = 'pending'
        ");
    }
    $stmt->bind_param('sii', $newStatus, $bookingId, $providerId);

    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();

    if ($updated !== 1) {
        throw new RuntimeException(
            $action === 'cancel' ? 'cancellation_too_late' : 'booking_already_processed'
        );
    }

    // Notify the client.
    $title   = $cfg['title'];
    $typeLabels = ['hourly' => 'Hourly', 'full_day' => 'Daily', 'urgent' => 'Urgent'];
    $typeLabel  = $typeLabels[$booking['booking_type'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string) ($booking['booking_type'] ?? '')));
    $startTs    = strtotime($booking['start_datetime']);
    $endTs      = strtotime($booking['end_datetime']);
    $when       = date('M j, Y, g:i A', $startTs) . ' – ' . date('g:i A', $endTs);

    $message = mb_substr(
        sprintf($cfg['msg'], $providerName, $typeLabel, $booking['category'] ?? 'care', $when),
        0,
        255
    );
    $type    = $cfg['type'];

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, booking_id, type, title, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iisss', $clientUserId, $bookingId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();

    // Activity log.
    $logAction = $cfg['log'];
    $details   = 'Provider set booking #' . $bookingId . ' to ' . $cfg['status'] . '.';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, details, ip_address, outcome)
        VALUES (?, ?, ?, ?, 'success')
    ");
    $stmt->bind_param('isss', $userId, $logAction, $details, $ip);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    back('success', $action);
} catch (Throwable $e) {
    $conn->rollback();

    error_log(
        'Provider action failed: ' . get_class($e) . ' | ' . $e->getMessage()
            . ' | ' . $e->getFile() . ':' . $e->getLine()
    );

    $allowed = [
        'booking_not_found',
        'booking_already_processed',
        'booking_not_confirmed',
        'cancellation_too_late',
        'client_account_not_found',
    ];

    back('error', in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'action_failed');
}
