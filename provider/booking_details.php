<?php
session_start();
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/booking_status.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'provider') {
    header('Location: ../index.html');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$userId    = (int) $_SESSION['user_id'];
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId < 1) {
    header('Location: provider_page.php?page=bookings&error=invalid_booking');
    exit;
}

// The logged-in provider.
$stmt = $conn->prepare("SELECT provider_id FROM providers WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    exit('Provider profile not found.');
}

$providerId = (int) $provider['provider_id'];
complete_ended_bookings($conn);

// The booking, only if it belongs to this provider.
$stmt = $conn->prepare("
    SELECT b.*,
           TIMESTAMPDIFF(SECOND, NOW(), b.start_datetime) AS seconds_until_start,
           (b.expires_at <= NOW()) AS is_expired,
           sc.name AS service_name,
           u.first_name, u.last_name, u.mobile_number AS client_mobile
    FROM bookings b
    LEFT JOIN provider_services ps ON ps.provider_service_id = b.provider_service_id
    LEFT JOIN service_categories sc ON sc.category_id = ps.category_id
    LEFT JOIN clients c ON c.client_id = b.client_id
    LEFT JOIN users u ON u.user_id = c.user_id
    WHERE b.booking_id = ?
      AND b.provider_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $providerId);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$b) {
    header('Location: provider_page.php?page=bookings&error=booking_not_found');
    exit;
}

// Contact numbers are shown only after the provider has accepted.
$revealContacts = in_array($b['status'], ['confirmed', 'completed'], true);
$hidden = 'Shown after you accept';

$clientName = trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?: 'Client';

function fmt_dt(?string $value): string
{
    $time = $value ? strtotime($value) : false;
    return $time ? date('D, M j, Y \a\t g:i A', $time) : '—';
}

function detail_rows(array $rows): void
{
    foreach ($rows as $label => $value) {
        echo '<div class="detail-row"><dt>' . e($label) . '</dt><dd>' . e($value) . '</dd></div>';
    }
}

$typeLabels = [
    'hourly'   => 'Hourly',
    'half_day' => 'Half-day',
    'full_day' => 'Full-day',
    'custom'   => 'Custom',
    'urgent'   => 'Urgent',
];


$status      = $b['status'];
$secondsLeft = (int) $b['seconds_until_start'];
$isExpired   = (bool) $b['is_expired'];

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $userId);
$stmt->execute();
$unreadCount = (int) $stmt->get_result()->fetch_row()[0];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking #<?= (int) $b['booking_id'] ?> | I'll BeCare</title>
    <link rel="stylesheet" href="../Assets/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Assets/provider_dashboard.css">
</head>

<body>
    <div class="dashboard-layout">

        <aside class="sidebar">
            <div class="brand">
                <img src="../Assets/Logo.png" alt="I'll BeCare Logo">
                <span>I'll BeCare</span>
            </div>

            <a class="nav-link" href="provider_dashboard.php">Dashboard</a>
            <a class="nav-link active" href="provider_page.php?page=bookings">My Bookings</a>
            <a class="nav-link" href="provider_page.php?page=services">My Services</a>
            <a class="nav-link" href="provider_page.php?page=reviews">Client Reviews</a>
            <a class="nav-link" href="provider_page.php?page=notifications">
                Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-count"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link" href="provider_page.php?page=profile">My Profile</a>
            <a class="nav-link logout" href="../views/logout.php">Log Out</a>
        </aside>

        <main class="main-content container-fluid">
            <header class="topbar">
                <div>
                    <p class="eyebrow">PROVIDER PORTAL</p>
                    <h1>Booking #<?= (int) $b['booking_id'] ?></h1>
                    <p class="muted">Review everything before you respond.</p>
                </div>
                <span class="status status-<?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
            </header>

                        <section class="content-panel">

                <!-- Summary -->
                <div class="booking-hero">
                    <div>
                        <p class="eyebrow">
                            <?= e($typeLabels[$b['booking_type']] ?? ucfirst((string) $b['booking_type'])) ?> BOOKING
                        </p>
                        <h2><?= e($b['service_name'] ?: 'Care service') ?></h2>
                        <p class="muted">Requested on <?= e(fmt_dt($b['created_at'])) ?></p>
                    </div>

                    <div class="earnings">
                        <span>Total earnings</span>
                        <strong>₱<?= number_format((float) $b['total_cost'], 2) ?></strong>
                        <small>
                            <?= number_format((float) $b['duration_hours'], 2) ?> h
                            × ₱<?= number_format((float) $b['hourly_rate'], 2) ?>/h
                        </small>
                    </div>
                </div>

                <?php if ($status === 'pending' && !$isExpired): ?>
                    <p class="deadline">
                        Please respond before <strong><?= e(fmt_dt($b['expires_at'])) ?></strong>
                        or this request will expire.
                    </p>
                <?php endif; ?>

                <!-- Schedule -->
                <div class="schedule-bar">
                    <div>
                        <span>Start</span>
                        <strong><?= e(fmt_dt($b['start_datetime'])) ?></strong>
                    </div>
                    <div>
                        <span>End</span>
                        <strong><?= e(fmt_dt($b['end_datetime'])) ?></strong>
                    </div>
                    <div>
                        <span>Billable hours</span>
                        <strong><?= number_format((float) $b['duration_hours'], 2) ?> h</strong>
                    </div>
                </div>

                <!-- Details in cards -->
                <div class="detail-cards row g-3">

                    <article class="detail-card col-12 col-md-6">
                        <h3>Client</h3>
                        <dl>
                            <?php detail_rows([
                                'Name'   => $clientName,
                                'Mobile' => $revealContacts ? ($b['client_mobile'] ?: '—') : $hidden,
                            ]); ?>
                        </dl>
                    </article>

                    <article class="detail-card col-12 col-md-6">
                        <h3>Location and emergency contact</h3>
                        <dl>
                            <?php detail_rows([
                                'Service address'   => $b['service_address'],
                                'Emergency contact' => $b['emergency_contact_name'] ?: '—',
                                'Emergency number'  => $revealContacts ? ($b['emergency_contact_number'] ?: '—') : $hidden,
                            ]); ?>
                        </dl>
                    </article>

                    <article class="detail-card col-12 col-md-6">
                        <h3>Who needs care</h3>
                        <dl>
                            <?php detail_rows([
                                'Children' => $b['children_info'] ?: 'None provided',
                                'Pets'     => $b['pet_info'] ?: 'None provided',
                            ]); ?>
                        </dl>
                    </article>

                    <article class="detail-card col-12 col-md-6">
                        <h3>Booking reference</h3>
                        <dl>
                            <?php detail_rows([
                                'Booking #' => '#' . (int) $b['booking_id'],
                                'Status'    => ucfirst($status),
                            ]); ?>
                        </dl>
                    </article>
                </div>

                <article class="detail-card detail-card-wide">
                    <h3>Special instructions</h3>
                    <p class="instructions"><?= e($b['special_instructions'] ?: 'None provided') ?></p>
                </article>

                <!-- Actions (same logic as before) -->
                <div class="detail-actions">
                    <?php if ($status === 'pending' && !$isExpired): ?>

                        <form method="post" action="provider_action.php" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                            <button class="button" type="submit" name="action" value="confirm"
                                onclick="return confirm('Accept this booking? You will be committed to this schedule.')">
                                Accept booking
                            </button>
                            <button class="button button-danger" type="submit" name="action" value="decline"
                                onclick="return confirm('Decline this booking request?')">
                                Decline
                            </button>
                        </form>

                    <?php elseif ($status === 'pending'): ?>

                        <p class="muted">This request expired and can no longer be accepted.</p>

                    <?php elseif ($status === 'confirmed' && $secondsLeft >= 86400): ?>

                        <form method="post" action="provider_action.php" class="inline-form"
                            onsubmit="return confirm('Cancel this confirmed booking? The client will be notified.');">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int) $b['booking_id'] ?>">
                            <button class="button button-danger" type="submit" name="action" value="cancel">
                                Cancel booking
                            </button>
                        </form>

                    <?php elseif ($status === 'confirmed'): ?>

                        <p class="muted">Cancellation window closed (less than 24 hours before start).</p>

                    <?php endif; ?>

                    <a class="button button-outline" href="provider_page.php?page=bookings">Back to bookings</a>
                </div>
            </section>
        </main>
    </div>
</body>

</html>