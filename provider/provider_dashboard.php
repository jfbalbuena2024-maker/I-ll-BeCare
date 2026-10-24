<?php
session_start();

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/booking_status.php';

// Protect the dashboard.
if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'provider'
) {
    header('Location: ../index.html');
    exit;
}

// ADD THE CSRF TOKEN HERE.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Continue with the rest of your existing dashboard code...
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable. Check config/connection.php.');
}

$userId = (int) $_SESSION['user_id'];

// Retrieve the provider's own account and profile.
$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.email,
        u.mobile_number,
        u.status AS account_status,
        p.provider_id,
        p.location,
        p.photo,
        p.skills,
        p.experience_years,
        p.qualifications,
        p.verification_status,
        p.verification_remarks,
        p.avg_rating,
        p.total_reviews
    FROM users u
    INNER JOIN providers p ON p.user_id = u.user_id
    WHERE u.user_id = ?
      AND u.role = 'provider'
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    http_response_code(403);
    exit('Your provider profile was not found. ' .
        'Please contact the administrator.');
}

if ($provider['account_status'] !== 'active') {
    session_destroy();
    header('Location: ../index.html');
    exit;
}

$providerId = (int) $provider['provider_id'];
complete_ended_bookings($conn);

$fullName = trim(
    implode(
        ' ',
        array_filter(
            [
                $provider['first_name'],
                $provider['middle_name'] ?? null,
                $provider['last_name']
            ],
            fn($v) => $v !== null && $v !== ''
        )
    )
);

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getCount(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): int {
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_row();
    $stmt->close();

    return (int) ($result[0] ?? 0);
}

// Dashboard statistics.
$totalBookings = getCount(
    $conn,
    "SELECT COUNT(*) FROM bookings WHERE provider_id = ?",
    'i',
    [$providerId]
);

$pendingBookings = getCount(
    $conn,
    "SELECT COUNT(*) FROM bookings
     WHERE provider_id = ? AND status = 'pending'",
    'i',
    [$providerId]
);

$confirmedBookings = getCount(
    $conn,
    "SELECT COUNT(*) FROM bookings
     WHERE provider_id = ? AND status = 'confirmed'",
    'i',
    [$providerId]
);

$completedBookings = getCount(
    $conn,
    "SELECT COUNT(*) FROM bookings
     WHERE provider_id = ? AND status = 'completed'",
    'i',
    [$providerId]
);

$activeServices = getCount(
    $conn,
    "SELECT COUNT(*) FROM provider_services
     WHERE provider_id = ? AND is_active = 1",
    'i',
    [$providerId]
);

$unreadNotifications = getCount(
    $conn,
    "SELECT COUNT(*) FROM notifications
     WHERE user_id = ? AND is_read = 0",
    'i',
    [$userId]
);


// Recent bookings for this provider only.
$stmt = $conn->prepare("
    SELECT
        b.booking_id,
        b.start_datetime,
        b.end_datetime,
        b.total_cost,
        b.service_address,
        b.status,
        TIMESTAMPDIFF(SECOND, NOW(), b.start_datetime) AS seconds_until_start,
        u.first_name,
        u.last_name
    FROM bookings b
    LEFT JOIN clients c ON c.client_id = b.client_id
    LEFT JOIN users u ON u.user_id = c.user_id
    WHERE b.provider_id = ?
    ORDER BY b.created_at DESC
    LIMIT 8
");
$stmt->bind_param('i', $providerId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Services belonging to this provider.
$stmt = $conn->prepare("
    SELECT ps.provider_service_id, ps.category_id, ps.hourly_rate, ps.is_active, sc.name AS category_name
    FROM provider_services ps
    JOIN service_categories sc ON sc.category_id = ps.category_id
    WHERE ps.provider_id = ?
    ORDER BY sc.name
");
$stmt->bind_param('i', $providerId);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Visible reviews belonging to this provider.
$stmt = $conn->prepare("
    SELECT rating, comment, created_at, client_id
    FROM reviews
    WHERE provider_id = ? AND is_hidden = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $providerId);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Latest notifications for this logged-in user only.
$stmt = $conn->prepare("
    SELECT notification_id, type, title, message,
           booking_id, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$verification = strtolower((string) ($provider['verification_status'] ?? ''));

$photoSrc = !empty($provider['photo'])
    ? (preg_match('~^(https?://|/)~', $provider['photo']) ? $provider['photo'] : '../' . $provider['photo'])
    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Provider Dashboard | I'll BeCare</title>
    <link rel="stylesheet" href="../Assets/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Assets/provider_dashboard.css">
</head>

<body>

    <div class="dashboard-layout">


        <aside class="sidebar" aria-label="Provider navigation">

            <div class="brand">
                <img src="../Assets/Logo.png" alt="I'll BeCare Logo">
                <span>I'll BeCare</span>
            </div>



            <a class="nav-link active" href="provider_dashboard.php">
                Dashboard
            </a>

            <a class="nav-link"
                href="provider_page.php?page=bookings">
                My Bookings
            </a>

            <a class="nav-link"
                href="provider_page.php?page=services">
                My Services
            </a>

            <a class="nav-link"
                href="provider_page.php?page=reviews">
                Client Reviews
            </a>

            <a class="nav-link"
                href="provider_page.php?page=notifications">
                Notifications
                <?php if ($unreadNotifications > 0): ?>
                    <span class="notification-count">
                        <?= $unreadNotifications ?>
                    </span>
                <?php endif; ?>
            </a>

            <a class="nav-link"
                href="provider_page.php?page=profile">
                My Profile
            </a>

            <a class="nav-link logout"
                href="../views/logout.php">
                Log Out
            </a>
        </aside>


        <main class="main-content container-fluid">
            <header class="topbar">
                <div>
                    <p class="eyebrow">PROVIDER PORTAL</p>
                    <h1>Welcome, <?= e($provider['first_name']) ?>!</h1>
                    <p class="muted">Here's an overview of your provider account.</p>
                </div>

                <div class="account-chip">
                    <strong><?= e($fullName) ?></strong>
                    <span><?= e($provider['email']) ?></span>
                </div>
            </header>

            <?php if ($verification !== 'approved'): ?>
                <section class="notice">
                    <strong>Verification: <?= e(ucfirst($verification)) ?></strong>
                    <p>
                        <?php if ($verification === 'pending'): ?>
                            Your provider profile is awaiting verification.
                        <?php elseif ($verification === 'rejected'): ?>
                            Your verification was rejected.
                        <?php elseif ($verification === 'suspended'): ?>
                            Your provider account is suspended. Contact the administrator.
                        <?php else: ?>
                            Your current verification status is
                            <?= e($verification) ?>.
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($provider['verification_remarks'])): ?>
                        <p><?= e($provider['verification_remarks']) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="profile-panel">
                <div class="profile-photo">
                    <?php if ($photoSrc !== ''): ?>
                        <img src="<?= e($photoSrc) ?>"
                            alt="Provider profile photo">
                    <?php else: ?>
                        <span><?= e(mb_strtoupper(mb_substr($provider['first_name'], 0, 1, 'UTF-8'))) ?></span>
                    <?php endif; ?>
                </div>

                <div class="profile-details">
                    <h2><?= e($fullName) ?></h2>
                    <p><?= e($provider['location']) ?></p>

                    <p>
                        Experience: <?= (int) $provider['experience_years'] ?>
                        <?= (int) $provider['experience_years'] === 1 ? 'year' : 'years' ?>
                    </p>
                    <p>
                        Verification:
                        <span class="status status-<?= e($verification) ?>">
                            <?= e(ucfirst($verification)) ?>
                        </span>
                    </p>
                </div>

                <div class="rating-box">
                    <strong><?= number_format((float) $provider['avg_rating'], 1) ?> / 5</strong>
                    <span><?= (int) $provider['total_reviews'] ?> reviews</span>
                </div>
            </section>

            <section class="stats-grid row g-3">
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Total Bookings</span>
                    <strong><?= $totalBookings ?></strong>
                </article>
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Pending Requests</span>
                    <strong><?= $pendingBookings ?></strong>
                </article>
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Confirmed</span>
                    <strong><?= $confirmedBookings ?></strong>
                </article>
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Completed</span>
                    <strong><?= $completedBookings ?></strong>
                </article>
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Active Services</span>
                    <strong><?= $activeServices ?></strong>
                </article>
                <article class="stat-card col-12 col-sm-6 col-xl-4">
                    <span>Unread Notifications</span>
                    <strong><?= $unreadNotifications ?></strong>
                </article>
            </section>

            <section class="content-panel" id="bookings">
                <div class="section-heading">
                    <div>
                        <h2>Recent Bookings</h2>
                        <p>Booking requests associated with your account.</p>
                    </div>
                    <a class="section-action" href="provider_page.php?page=bookings">
                        View all bookings
                    </a>
                </div>

                <?php if (!$bookings): ?>
                    <p class="empty-state">You don't have any bookings yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Client</th>
                                    <th>Service Address</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td data-label="Booking #">#<?= (int) $booking['booking_id'] ?></td>
                                        <td data-label="Client">
                                            <?= e(trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')) ?: 'Client') ?>
                                        </td>
                                        <td data-label="Service Address"><?= e($booking['service_address'] ?: '—') ?></td>
                                        <td data-label="Start"><?= e($booking['start_datetime']) ?></td>
                                        <td data-label="End"><?= e($booking['end_datetime']) ?></td>
                                        <td data-label="Total">₱<?= number_format((float) $booking['total_cost'], 2) ?></td>
                                        <td data-label="Status">
                                            <span class="status status-<?= e($booking['status']) ?>">
                                                <?= e(ucfirst($booking['status'])) ?>
                                            </span>
                                        </td>
                                        <td data-label="Action">
                                            <a class="button button-small"
                                                href="booking_details.php?id=<?= (int) $booking['booking_id'] ?>">
                                                <?= $booking['status'] === 'pending' ? 'Review request' : 'View details' ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="content-panel" id="services">
                <div class="section-heading">
                    <div>
                        <h2>My Services</h2>
                        <p>Your registered service rates and availability.</p>
                    </div>
                    <a class="section-action" href="provider_page.php?page=services">
                        Manage services
                    </a>
                </div>

                <?php if (!$services): ?>
                    <p class="empty-state">You haven't registered any services yet.</p>
                <?php else: ?>
                    <div class="service-grid">
                        <?php foreach ($services as $service): ?>
                            <article class="service-card">
                                <h3><?= e($service['category_name']) ?></h3>
                                <strong>
                                    ₱<?= number_format((float) $service['hourly_rate'], 2) ?>
                                    <small>/ hour</small>
                                </strong>
                                <p>
                                    <span class="status <?= $service['is_active'] ? 'status-approved' : 'status-suspended' ?>">
                                        <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="content-panel" id="reviews">
                <div class="section-heading">
                    <div>
                        <h2>Client Reviews</h2>
                        <p>Your latest visible reviews.</p>
                    </div>
                    <a class="section-action" href="provider_page.php?page=reviews">
                        View all reviews
                    </a>
                </div>

                <?php if (!$reviews): ?>
                    <p class="empty-state">No visible reviews yet.</p>
                <?php else: ?>
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <article class="review-card">
                                <div class="review-top">
                                    <strong>
                                        <?= str_repeat('★', max(0, min(5, (int) $review['rating']))) ?>
                                        <?= (int) $review['rating'] ?>/5
                                    </strong>
                                    <span><?= e($review['created_at']) ?></span>
                                </div>
                                <p><?= e($review['comment'] ?: 'No comment provided.') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="content-panel" id="notifications">
                <div class="section-heading">
                    <div>
                        <h2>Notifications</h2>
                        <p>Recent updates for your account.</p>
                    </div>
                    <a class="section-action" href="provider_page.php?page=notifications">
                        View all notifications
                    </a>
                </div>

                <?php if (!$notifications): ?>
                    <p class="empty-state">You have no notifications yet.</p>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <article class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>">
                                <div>
                                    <strong><?= e($notification['title']) ?></strong>
                                    <p><?= e($notification['message']) ?></p>
                                    <small><?= e($notification['created_at']) ?></small>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="unread-label">New</span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <footer class="footer">
                I'll BeCare Provider Portal
            </footer>
        </main>
    </div>

</body>

</html>