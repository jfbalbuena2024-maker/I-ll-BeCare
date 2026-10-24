<?php
session_start();
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/booking_status.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'provider'
) {
    header('Location: ../index.html');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit('Database connection is unavailable.');
}

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");
$stmt->bind_param('i', $userId);
$stmt->execute();

$unreadCount = (int) $stmt->get_result()->fetch_row()[0];

$stmt->close();

$stmt = $conn->prepare("
    SELECT provider_id
    FROM providers
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$provider = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$provider) {
    exit('Provider profile not found.');
}

$providerId = (int) $provider['provider_id'];
complete_ended_bookings($conn);

$page = $_GET['page'] ?? 'bookings';

$allowedPages = [
    'bookings',
    'services',
    'reviews',
    'notifications',
    'profile'
];

if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    exit('Page not found.');
}

$title = [
    'bookings' => 'My Bookings',
    'services' => 'My Services',
    'reviews' => 'Client Reviews',
    'notifications' => 'Notifications',
    'profile' => 'My Profile'
][$page];


function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'profile') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile_number'] ?? '');
        $municipality = trim($_POST['municipality'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $validMunicipalities = [
            'Enrique Villanueva',
            'Larena',
            'Lazi',
            'Maria',
            'San Juan',
            'Siquijor',
        ];
        $location = $barangay . ', ' . $municipality . ', Siquijor';
        $skills = trim($_POST['skills'] ?? '');
        $experience = filter_var(
            $_POST['experience_years'] ?? '',
            FILTER_VALIDATE_INT
        );
        $qualifications = trim($_POST['qualifications'] ?? '');

        if (
            $firstName === '' ||
            $lastName === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            $experience === false ||
            $experience < 0 ||
            $experience > 80 ||
            !in_array($municipality, $validMunicipalities, true) ||
            $barangay === '' ||
            mb_strlen($barangay) > 100 ||
            !preg_match('/^[\p{L}\p{M}\s.\'-]+$/u', $barangay)
        ) {
            $message = 'Please enter valid information in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("
                    UPDATE users
                    SET first_name = ?,
                        middle_name = NULLIF(?, ''),
                        last_name = ?,
                        email = ?,
                        mobile_number = ?
                    WHERE user_id = ?
                      AND role = 'provider'
                ");

                $stmt->bind_param(
                    'sssssi',
                    $firstName,
                    $middleName,
                    $lastName,
                    $email,
                    $mobile,
                    $userId
                );

                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    UPDATE providers
                    SET location = ?,
                        skills = ?,
                        experience_years = ?,
                        qualifications = ?
                    WHERE provider_id = ?
                      AND user_id = ?
                ");

                $stmt->bind_param(
                    'ssisii',
                    $location,
                    $skills,
                    $experience,
                    $qualifications,
                    $providerId,
                    $userId
                );

                $stmt->execute();
                $stmt->close();

                $conn->commit();

                // Synchronize session values
                $_SESSION['first_name'] = $firstName;
                $_SESSION['full_name'] = trim(
                    implode(
                        ' ',
                        array_filter(
                            [
                                $firstName,
                                $middleName,
                                $lastName
                            ],
                            fn($v) => $v !== null && $v !== ''
                        )
                    )
                );
                $_SESSION['email'] = $email;

                header('Location: provider_page.php?page=profile&saved=1');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();

                error_log('Provider profile update failed: ' . $exception->getMessage());

                if ((int) $exception->getCode() === 1062) {
                    $message = 'That email address is already being used by another account.';
                } else {
                    $message = 'Unable to update your profile. Please try again.';
                }

                $messageType = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'services') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please refresh the page and try again.';
        $messageType = 'error';
    } else {
        $serviceId = filter_var($_POST['provider_service_id'] ?? '', FILTER_VALIDATE_INT);
        $rateRaw = trim((string) ($_POST['hourly_rate'] ?? ''));

        if (
            $serviceId === false ||
            !preg_match('/^\d{1,6}(\.\d{1,2})?$/', $rateRaw) ||
            (float) $rateRaw <= 0
        ) {
            $message = 'Enter a valid rate greater than 0 (numbers only, up to 2 decimals).';
            $messageType = 'error';
        } else {
            $rate = round((float) $rateRaw, 2);

            try {
                // Make sure this service belongs to the logged-in provider.
                $stmt = $conn->prepare("
                    SELECT 1 FROM provider_services
                    WHERE provider_service_id = ? AND provider_id = ?
                    LIMIT 1
                ");
                $stmt->bind_param('ii', $serviceId, $providerId);
                $stmt->execute();
                $owns = (bool) $stmt->get_result()->fetch_row();
                $stmt->close();

                if (!$owns) {
                    $message = 'Service not found.';
                    $messageType = 'error';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE provider_services
                        SET hourly_rate = ?
                        WHERE provider_service_id = ? AND provider_id = ?
                    ");
                    $stmt->bind_param('dii', $rate, $serviceId, $providerId);
                    $stmt->execute();
                    $stmt->close();

                    header('Location: provider_page.php?page=services&saved=rate');
                    exit;
                }
            } catch (mysqli_sql_exception $exception) {
                error_log('Service rate update failed: ' . $exception->getMessage());
                $message = 'Unable to update the rate. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'notifications') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please refresh the page and try again.';
        $messageType = 'error';
    } elseif (($_POST['action'] ?? '') === 'mark_all_read') {
        try {
            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            header('Location: provider_page.php?page=notifications&saved=read');
            exit;
        } catch (mysqli_sql_exception $exception) {
            error_log('Mark notifications read failed: ' . $exception->getMessage());
            $message = 'Unable to mark notifications as read. Please try again.';
            $messageType = 'error';
        }
    }
}

if (isset($_GET['saved'])) {
    if ($page === 'profile' && $_GET['saved'] === '1') {
        $message = 'Your profile has been updated successfully.';
        $messageType = 'success';
    } elseif ($page === 'services' && $_GET['saved'] === 'rate') {
        $message = 'Your service rate has been updated.';
        $messageType = 'success';
    } elseif ($page === 'notifications' && $_GET['saved'] === 'read') {
        $message = 'All notifications marked as read.';
        $messageType = 'success';
    }
}


if (isset($_GET['error'])) {
    $errorMessages = [
        'booking_not_found' =>
        'Booking not found or it does not belong to your account.',
        'booking_already_processed' =>
        'This booking has already been processed.',
        'booking_not_confirmed' =>
        'Only confirmed bookings can be cancelled.',
        'cancellation_too_late' =>
        'You can only cancel a confirmed booking at least 24 hours before its start time.',
        'client_account_not_found' =>
        'The client account could not be found. No changes were saved.',
        'action_failed' =>
        'The action failed. Check the PHP error log and database schema.',
        'provider_not_approved' =>
        'Your provider account must be active and approved.',
        'invalid_booking' =>
        'Invalid booking ID.',
        'invalid_action' =>
        'Invalid booking action.'
    ];

    $errorCode = (string) $_GET['error'];
    $message = $errorMessages[$errorCode]
        ?? 'Something went wrong. Please try again.';
    $messageType = 'error';
}

if (isset($_GET['success'])) {
    $successMessages = [
        'cancel' => 'Booking cancelled successfully.',
        'confirm' => 'Booking accepted successfully.',
        'decline' => 'Booking declined successfully.'
    ];

    $successAction = (string) $_GET['success'];

    if (isset($successMessages[$successAction])) {
        $message = $successMessages[$successAction];
        $messageType = 'success';
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | I'll BeCare</title>
    <link rel="stylesheet" href="../Assets/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Assets/provider_dashboard.css?v=terms-format-20261024">
</head>

<body>
    <div class="dashboard-layout">

        <aside class="sidebar">

            <div class="brand">
                <img src="../Assets/Logo.png" alt="I'll BeCare Logo">
                <span>I'll BeCare</span>
            </div>



            <a class="nav-link" href="provider_dashboard.php">
                Dashboard
            </a>

            <a class="nav-link <?= $page === 'bookings' ? 'active' : '' ?>" href="provider_page.php?page=bookings">
                My Bookings
            </a>

            <a class="nav-link <?= $page === 'services' ? 'active' : '' ?>" href="provider_page.php?page=services">
                My Services
            </a>

            <a class="nav-link <?= $page === 'reviews' ? 'active' : '' ?>" href="provider_page.php?page=reviews">
                Client Reviews
            </a>

            <a class="nav-link <?= $page === 'notifications' ? 'active' : '' ?>" href="provider_page.php?page=notifications">
                Notifications

                <?php if ($unreadCount > 0): ?>
                    <span class="notification-count">
                        <?= $unreadCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a class="nav-link <?= $page === 'profile' ? 'active' : '' ?>" href="provider_page.php?page=profile">
                My Profile
            </a>

            <a class="nav-link logout" href="../views/logout.php">
                Log Out
            </a>
        </aside>


        <main class="main-content container-fluid">
            <header class="topbar">
                <div>
                    <p class="eyebrow">PROVIDER PORTAL</p>
                    <h1><?= e($title) ?></h1>
                    <p class="muted">Manage your provider account.</p>
                </div>
            </header>

            <section class="content-panel">
                <?php if ($page === 'bookings'): ?>

                    <?php
                    $stmt = $conn->prepare("
                    SELECT b.booking_id, TIMESTAMPDIFF(SECOND, NOW(), b.start_datetime) AS seconds_until_start, b.start_datetime,
                        b.end_datetime, b.total_cost, b.status,
                           b.service_address,
                           u.first_name, u.last_name
                    FROM bookings b
                    LEFT JOIN clients c ON c.client_id = b.client_id
                    LEFT JOIN users u ON u.user_id = c.user_id
                    WHERE b.provider_id = ?
                    ORDER BY b.created_at DESC
                ");
                    $stmt->bind_param('i', $providerId);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    ?>

                    <h2>All Bookings</h2>

                    <?php if ($message !== ''): ?>
                        <p id="flash-message" class="<?= e($messageType) ?>" role="status">
                            <?= e($message) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!$rows): ?>
                        <p class="empty-state">You have no bookings yet.</p>
                    <?php else: ?>
                        <div class="table-wrap table-responsive">
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
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['booking_id'] ?></td>

                                            <td>
                                                <?= e(
                                                    trim(($row['first_name'] ?? '') . ' ' .
                                                        ($row['last_name'] ?? '')) ?: 'Client'
                                                ) ?>
                                            </td>

                                            <td data-label="Service Address"><?= e($row['service_address'] ?: '—') ?></td>
                                            <td><?= e($row['start_datetime']) ?></td>
                                            <td><?= e($row['end_datetime']) ?></td>

                                            <td>₱<?= number_format((float) $row['total_cost'], 2) ?></td>

                                            <td>
                                                <span class="status status-<?= e($row['status']) ?>">
                                                    <?= e(ucfirst($row['status'])) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <a class="button button-small"
                                                    href="booking_details.php?id=<?= (int) $row['booking_id'] ?>">
                                                    <?= $row['status'] === 'pending' ? 'Review request' : 'View details' ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        </div>
<?php endif; ?>

<?php elseif ($page === 'services'): ?>

    <?php
                    $stmt = $conn->prepare("
                        SELECT ps.provider_service_id, ps.category_id, ps.hourly_rate,
                            ps.is_active, sc.name AS category_name
                        FROM provider_services ps
                        JOIN service_categories sc ON sc.category_id = ps.category_id
                        WHERE ps.provider_id = ?
                        ORDER BY sc.name
                    ");

                    $stmt->bind_param('i', $providerId);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
    ?>

    <h2>My Services</h2>
    <p class="muted">Update your hourly rate for each service. New bookings use the updated rate.</p>

    <?php if ($message !== ''): ?>
        <p id="flash-message" class="<?= e($messageType) ?>" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <p class="empty-state">You haven't registered any services.</p>
    <?php else: ?>
        <div class="service-grid">
            <?php foreach ($rows as $row): ?>
                <article class="service-card">
                    <h3><?= e($row['category_name']) ?></h3>
                    <p>
                        <span class="status <?= $row['is_active'] ? 'status-approved' : 'status-suspended' ?>">
                            <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>

                    <form method="POST" action="provider_page.php?page=services" class="rate-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="provider_service_id" value="<?= (int) $row['provider_service_id'] ?>">

                        <label for="rate_<?= (int) $row['provider_service_id'] ?>">Hourly rate (₱)</label>
                        <input id="rate_<?= (int) $row['provider_service_id'] ?>"
                            type="number" name="hourly_rate"
                            min="0.01" max="999999.99" step="0.01" required
                            class="form-control"
                            value="<?= e(number_format((float) $row['hourly_rate'], 2, '.', '')) ?>">

                        <button type="submit" class="button button-small">Save rate</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'reviews'): ?>

    <?php
                    $stmt = $conn->prepare("
                    SELECT rating, comment, created_at
                    FROM reviews
                    WHERE provider_id = ? AND is_hidden = 0
                    ORDER BY created_at DESC
                ");
                    $stmt->bind_param('i', $providerId);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
    ?>

    <h2>Client Reviews</h2>

    <?php if ($message !== ''): ?>
        <p id="flash-message" class="<?= e($messageType) ?>" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <p class="empty-state">No visible reviews yet.</p>
    <?php else: ?>
        <div class="review-list">
            <?php foreach ($rows as $row): ?>
                <article class="review-card">
                    <strong><?= (int) $row['rating'] ?>/5 ★</strong>
                    <p><?= e($row['comment'] ?: 'No comment provided.') ?></p>
                    <small><?= e($row['created_at']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($page === 'notifications'): ?>

    <?php
                    $stmt = $conn->prepare("
                    SELECT title, message, is_read, created_at
                    FROM notifications
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                ");
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
    ?>

    <div class="section-heading">
        <h2>My Notifications</h2>

        <form id="mark-read-form" method="POST" action="provider_page.php?page=notifications">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="button button-outline button-small" <?= $unreadCount > 0 ? '' : 'disabled' ?>>
                Mark all as read
            </button>
        </form>
    </div>

    <?php if ($message !== ''): ?>
        <p id="flash-message" class="<?= e($messageType) ?>" role="status"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <p class="empty-state">You have no notifications yet.</p>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($rows as $row): ?>
                <article class="notification-item <?= !$row['is_read'] ? 'unread' : '' ?>">
                    <div>
                        <strong><?= e($row['title']) ?></strong>
                        <p><?= e($row['message']) ?></p>
                        <small><?= e($row['created_at']) ?></small>
                    </div>
                    <?php if (!$row['is_read']): ?>
                        <span class="unread-label">New</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


<?php elseif ($page === 'profile'): ?>

    <?php
                    $stmt = $conn->prepare("
        SELECT first_name, middle_name, last_name,
               email, mobile_number
        FROM users
        WHERE user_id = ?
          AND role = 'provider'
        LIMIT 1
    ");
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $profile = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $stmt = $conn->prepare("
        SELECT location, skills, experience_years, qualifications
        FROM providers
        WHERE provider_id = ?
          AND user_id = ?
        LIMIT 1
    ");
                    $stmt->bind_param('ii', $providerId, $userId);
                    $stmt->execute();
                    $details = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $locationParts = array_map(
                        'trim',
                        explode(',', (string)($details['location'] ?? ''))
                    );
                    $profileBarangay = '';
                    $profileMunicipality = '';
                    if (
                        count($locationParts) === 3 &&
                        $locationParts[2] === 'Siquijor'
                    ) {
                        $profileBarangay = $locationParts[0];
                        $profileMunicipality = $locationParts[1];
                    }
    ?>

    <h2>Edit My Profile</h2>
    <p>Update your personal and provider information below.</p>

    <?php if ($message !== ''): ?>
        <p id="flash-message" class="<?= e($messageType) ?>" role="status">
            <?= e($message) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="provider_page.php?page=profile"
        class="profile-form">

        <input type="hidden" name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>">

        <h3>Personal Information</h3>

        <label for="first_name">First Name *</label>
        <input id="first_name" type="text" name="first_name"
            value="<?= e($profile['first_name'] ?? '') ?>"
            maxlength="100" class="form-control" required>

        <label for="middle_name">Middle Name</label>
        <input id="middle_name" type="text" name="middle_name"
            value="<?= e($profile['middle_name'] ?? '') ?>"
            maxlength="100" class="form-control">

        <label for="last_name">Last Name *</label>
        <input id="last_name" type="text" name="last_name"
            value="<?= e($profile['last_name'] ?? '') ?>"
            maxlength="100" class="form-control" required>

        <label for="email">Email Address *</label>
        <input id="email" type="email" name="email"
            value="<?= e($profile['email'] ?? '') ?>"
            maxlength="255" class="form-control" required>

        <label for="mobile_number">Mobile Number</label>
        <input id="mobile_number" type="tel" name="mobile_number"
            value="<?= e($profile['mobile_number'] ?? '') ?>"
            maxlength="30" class="form-control">

        <h3>Provider Information</h3>

        <div class="profile-location-grid">
            <div class="profile-location-field">
                <label for="provider_province">Province</label>
                <input id="provider_province" type="text" value="Siquijor"
                    class="form-control" readonly>
            </div>
            <div class="profile-location-field">
                <label for="provider_municipality">Municipality *</label>
                <select id="provider_municipality" name="municipality"
                    data-selected="<?= e($profileMunicipality) ?>"
                    class="form-control" required>
                    <option value="">Select municipality</option>
                </select>
            </div>
            <div class="profile-location-field">
                <label for="provider_barangay">Barangay *</label>
                <select id="provider_barangay" name="barangay"
                    data-selected="<?= e($profileBarangay) ?>"
                    class="form-control" required disabled>
                    <option value="">Select municipality first</option>
                </select>
            </div>
        </div>

        <p class="muted">Your public location will show as barangay and municipality only.</p>

        <label for="skills">Skills</label>
        <textarea id="skills" name="skills" class="form-control"
            rows="3"><?= e($details['skills'] ?? '') ?></textarea>

        <label for="experience_years">Years of Experience *</label>
        <input id="experience_years" type="number"
            name="experience_years"
            min="0" max="80" class="form-control" required
            value="<?= (int) ($details['experience_years'] ?? 0) ?>">

        <label for="qualifications">Qualifications</label>
        <textarea id="qualifications" name="qualifications" class="form-control"
            rows="4"><?= e($details['qualifications'] ?? '') ?></textarea>

        <button type="submit" class="button">
            Save Changes
        </button>

        <a href="provider_page.php?page=profile"
            class="button button-danger">
            Cancel
        </a>
    </form>

    <hr>

    <button type="button" class="terms-link" onclick="openTerms()">
        Terms and Conditions
    </button>

    <div id="termsModal" class="terms-overlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="termsTitle">

        <div class="terms-modal">
            <button type="button"
                class="terms-close"
                onclick="closeTerms()"
                aria-label="Close Terms and Conditions">
                &times;
            </button>

            <h2 id="termsTitle">Terms and Conditions</h2>

            <div class="terms-content">
                <h3>I'll BeCare Terms and Conditions</h3>
                <p><strong>Last updated: October 24, 2026</strong></p>

                <h3>1. About I'll BeCare</h3>
                <p>I'll BeCare is an academic prototype developed by BSIT III students of Siquijor State College for a school requirement. It is a web platform that helps households in Siquijor Island (Clients) find and book approved care and household support Providers for short-term, on-demand services. It is not a commercial service, it does not collect fees, and it is not a recruitment or employment agency.</p>

                <h3>2. Who may use the system</h3>
                <p class="terms-clause"><strong>2.1</strong> You must be at least 18 years old to register as a Client or a Provider.</p>
                <p class="terms-clause"><strong>2.2</strong> You must give true, complete, and current information, and keep your password private.</p>
                <p class="terms-clause"><strong>2.3</strong> The Administrator may confirm, block, or suspend any account that breaks these terms.</p>

                <h3>3. Nature of the services</h3>
                <p class="terms-clause"><strong>3.1</strong> Services are short-term and on-demand (hourly, half-day, full-day, custom, or same-day). The system does not support long-term or live-in arrangements.</p>
                <p class="terms-clause"><strong>3.2</strong> Providers are not employees of I'll BeCare or of the group. A booking is a one-time arrangement between a Client and a Provider and does not create a continuing employment relationship.</p>
                <p class="terms-clause"><strong>3.3</strong> A booking may not exceed 8 hours of service per day, and a meal break must be allowed for longer bookings. Arrangements must not go below the minimum labor standards under the Labor Code and other labor laws.</p>

                <h3>4. Provider requirements</h3>
                <p class="terms-clause"><strong>4.1</strong> Providers must submit a valid government ID and a Barangay Clearance for review. An NBI Clearance is recommended.</p>
                <p class="terms-clause"><strong>4.2</strong> Only Providers approved by the Administrator are shown to Clients. The Administrator may reject or suspend a Provider whose documents are unclear, invalid, or false.</p>
                <p class="terms-clause"><strong>4.3</strong> Providers set their own hourly rates and may change them. The rate shown at booking is the rate that applies to that booking.</p>
                <p class="terms-clause"><strong>4.4</strong> Providers may accept or decline any booking request.</p>

                <h3>5. Bookings, cost, and payment</h3>
                <p class="terms-clause"><strong>5.1</strong> A Client sends a booking request. The booking is confirmed only when the Provider accepts it.</p>
                <p class="terms-clause"><strong>5.2</strong> The system shows a cost summary (the Provider's hourly rate multiplied by the booked hours) before the request is sent.</p>
                <p class="terms-clause"><strong>5.3</strong> Payment is made face-to-face between the Client and the Provider after the service. The system does not process payments and does not charge any fee, service charge, or commission.</p>
                <p class="terms-clause"><strong>5.4</strong> Clients and Providers may cancel a booking through the system. Please cancel as early as possible so the other party can make other plans.</p>

                <h3>6. Safety and conduct</h3>
                <p class="terms-clause"><strong>6.1</strong> Clients must give accurate care details, including emergency contact information, so the Provider can respond properly.</p>
                <p class="terms-clause"><strong>6.2</strong> Users must treat each other with respect and must not harass, abuse, or put anyone at risk.</p>
                <p class="terms-clause"><strong>6.3</strong> The system does not independently investigate or authenticate documents beyond the Administrator's review. Clients should still use their own judgment.</p>
                <p class="terms-clause"><strong>6.4</strong> Ratings and reviews must be honest. The Administrator may remove reviews that break these terms.</p>

                <h3>7. Complaints</h3>
                <p>Clients and Providers may file a complaint or safety concern through the system. The Administrator will review and resolve it within the platform. The system does not mediate or enforce legal action, and serious matters should be reported to the proper authorities.</p>

                <h3>8. Privacy</h3>
                <p>Your personal data, including IDs, clearances, and emergency contact details, is collected only for verification and booking and handled under the Data Privacy Act of 2012 (RA 10173), as explained in the Privacy Notice. Submitted documents are seen only by authorized Administrators.</p>

                <h3>9. Prototype limits</h3>
                <p class="terms-clause"><strong>9.1</strong> The system is for academic use. Use test data only and do not rely on it for emergencies.</p>
                <p class="terms-clause"><strong>9.2</strong> The group makes no promise that the system will always be available or error-free.</p>
                <p class="terms-clause"><strong>9.3</strong> Any commercial use would first require the appropriate DOLE license and updated terms.</p>

                <h3>10. Changes</h3>
                <p>The group may update these terms. Continued use means you accept the updated terms. By ticking the box at registration, you confirm that you have read and agree to these Terms and Conditions and the Privacy Notice.</p>
            </div>
        </div>
    </div>


<?php endif; ?>


</section>
</main>
</div>

<script src="../JS/siquijor_barangay.js"></script>
<script>
    (function() {
        var municipalitySelect = document.getElementById('provider_municipality');
        var barangaySelect = document.getElementById('provider_barangay');
        var locations = window.SIQUIJOR_BARANGAYS;
        if (!municipalitySelect || !barangaySelect || !locations) return;

        var savedMunicipality = municipalitySelect.dataset.selected || '';
        var savedBarangay = barangaySelect.dataset.selected || '';
        var municipalityNames = Object.keys(locations);

        if (savedMunicipality && municipalityNames.indexOf(savedMunicipality) !== -1) {
            municipalityNames.splice(municipalityNames.indexOf(savedMunicipality), 1);
            municipalitySelect.add(new Option(savedMunicipality + ' (current)', savedMunicipality));
        }

        municipalityNames.forEach(function(name) {
            municipalitySelect.add(new Option(name, name));
        });

        function loadBarangays(selectedBarangay, showCurrentFirst) {
            var municipality = municipalitySelect.value;
            barangaySelect.innerHTML = '<option value="">Select barangay</option>';
            if (!municipality || !locations[municipality]) {
                barangaySelect.disabled = true;
                return;
            }

            if (
                showCurrentFirst &&
                selectedBarangay &&
                locations[municipality].indexOf(selectedBarangay) !== -1
            ) {
                barangaySelect.add(new Option(selectedBarangay + ' (current)', selectedBarangay));
            }

            locations[municipality].forEach(function(name) {
                if (showCurrentFirst && name === selectedBarangay) return;
                barangaySelect.add(new Option(name, name));
            });
            barangaySelect.disabled = false;
            if (selectedBarangay && locations[municipality].indexOf(selectedBarangay) !== -1) {
                barangaySelect.value = selectedBarangay;
            }
        }

        municipalitySelect.value = savedMunicipality;
        loadBarangays(savedBarangay, true);
        municipalitySelect.addEventListener('change', function() {
            loadBarangays('', false);
        });
    })();

    // Mark notifications as read 3 seconds after the Provider opens this page
    (function() {
        var form = document.getElementById('mark-read-form');
        var badge = document.querySelector('.notification-count');
        if (!form || !badge) return;

        setTimeout(function() {
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            }).then(function() {
                badge.remove();
            });
        }, 3000);
    })();

    function openTerms() {
        const modal = document.getElementById('termsModal');

        if (modal) {
            modal.classList.add('is-open');
        }
    }

    // Hide flash message after 3 seconds
    (function() {
        const msg = document.getElementById('flash-message');
        if (!msg) return;

        setTimeout(function() {
            msg.style.transition = 'opacity 0.4s';
            msg.style.opacity = '0';
            setTimeout(function() {
                msg.remove();
            }, 400);
        }, 3000);

        // Remove ?success= / ?error= / ?saved= so a refresh doesn't show it again
        if (window.history.replaceState) {
            const url = new URL(window.location);
            ['success', 'error', 'saved'].forEach(function(k) {
                url.searchParams.delete(k);
            });
            window.history.replaceState({}, '', url);
        }
    })();

    function closeTerms() {
        const modal = document.getElementById('termsModal');

        if (modal) {
            modal.classList.remove('is-open');
        }
    }
</script>

</body>
</html>
