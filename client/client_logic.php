<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/booking_status.php';

const LOGIN_URL  = '../index.html';
const SELF_URL   = 'client_dashboard.php';
const PH_MOBILE  = '/^(09\d{9}|\+639\d{9})$/';

/* Booking length rules (hours). Adjust here if your adviser wants different limits. */
$BOOKING_RULES = [
    'hourly'   => ['label' => 'Hourly',            'min' => 1, 'max' => 8, 'deduct' => 'none'],
    'full_day' => ['label' => 'Daily',             'min' => 9, 'max' => 9, 'deduct' => 'always'],
    'urgent'   => ['label' => 'Urgent (same day)', 'min' => 1, 'max' => 9, 'deduct' => 'at9'],
];

if ($conn === null) {
    http_response_code(500);
    exit('The database is unavailable. Please try again later.');
}

/* ============================== HELPERS ============================== */

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function money($n): string
{
    return '₱' . number_format((float)$n, 2);
}
function fmt_dt($s): string
{
    return date('M j, Y · g:i A', strtotime($s));
}
function fmt_hours($h): string
{
    return rtrim(rtrim(number_format((float)$h, 2, '.', ''), '0'), '.');
}

function billed_hours(DateTime $s, DateTime $e, string $mode): float
{
    $secs = $e->getTimestamp() - $s->getTimestamp();
    if ($mode === 'always') {
        $secs -= 3600;                       // Daily: always 1 hour off
    } elseif ($mode === 'at9' && $secs === 9 * 3600) {
        $secs -= 3600;                       // Urgent: 1 hour off only for an exact 9-hour window
    }
    return round($secs / 3600, 2);
}

function q(mysqli $c, string $sql, string $types = '', array $args = []): array
{
    $st = $c->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$args);
    }
    $st->execute();
    $res  = $st->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
}
function q1(mysqli $c, string $sql, string $types = '', array $args = []): ?array
{
    $r = q($c, $sql, $types, $args);
    return $r[0] ?? null;
}
function run(mysqli $c, string $sql, string $types = '', array $args = []): int
{
    $st = $c->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$args);
    }
    $st->execute();
    $n = $st->affected_rows;
    $st->close();
    return $n;
}
function res(bool $ok, string $msg, string $to, string $q = ''): array
{
    return ['ok' => $ok, 'msg' => $msg, 'to' => $to, 'q' => $q];
}
function log_activity(mysqli $c, int $uid, string $action, string $details, string $outcome = 'success'): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    run(
        $c,
        "INSERT INTO activity_logs (user_id, action, details, ip_address, outcome) VALUES (?,?,?,?,?)",
        'issss',
        [$uid, $action, mb_substr($details, 0, 255), $ip, $outcome]
    );
}
function notify(mysqli $c, int $uid, ?int $bookingId, string $type, string $title, string $msg): void
{
    run(
        $c,
        "INSERT INTO notifications (user_id, booking_id, type, title, message) VALUES (?,?,?,?,?)",
        'iisss',
        [$uid, $bookingId, $type, mb_substr($title, 0, 120), mb_substr($msg, 0, 255)]
    );
}

/* A service a Client is allowed to book: active, approved Provider, active category. */
function bookable_service(mysqli $c, int $psId): ?array
{
    return q1(
        $c,
        "SELECT ps.provider_service_id, ps.provider_id, ps.hourly_rate,
                p.user_id AS provider_uid, u.first_name, u.last_name, sc.name AS category
           FROM provider_services ps
           JOIN providers p ON p.provider_id = ps.provider_id
           JOIN users u ON u.user_id = p.user_id
           JOIN service_categories sc ON sc.category_id = ps.category_id
          WHERE ps.provider_service_id = ? AND ps.is_active = 1 AND sc.is_active = 1
            AND p.verification_status = 'approved' AND u.status = 'active'",
        'i',
        [$psId]
    );
}

/* ============================== AUTH ============================== */

$userId = (int)($_SESSION['user_id'] ?? 0);   // MATCH LOGIN: session key for the user id
$role   = (string)($_SESSION['role'] ?? '');  // MATCH LOGIN: session key for the role

if ($userId <= 0 || $role !== 'client') {
    header('Location: ' . LOGIN_URL);
    exit;
}

$me = q1(
    $conn,
    "SELECT u.user_id, u.first_name, u.middle_name, u.last_name, u.email, u.mobile_number, u.status,
            c.client_id, c.address, c.emergency_contact_name, c.emergency_contact_number, c.photo
       FROM users u JOIN clients c ON c.user_id = u.user_id
      WHERE u.user_id = ? LIMIT 1",
    'i',
    [$userId]
);

if (!$me || $me['status'] !== 'active') {
    session_unset();
    session_destroy();
    header('Location: ' . LOGIN_URL);
    exit;
}
complete_ended_bookings($conn);

$clientId = (int)$me['client_id'];
$now      = date('Y-m-d H:i:s');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

$flash = $_SESSION['flash'] ?? null;
$old   = $_SESSION['old'] ?? [];
unset($_SESSION['flash'], $_SESSION['old']);
function old_raw(string $k, $d = '')
{
    global $old;
    return $old[$k] ?? $d;
}
function old(string $k, $d = ''): string
{
    return e(old_raw($k, $d));
}

/* ============================== BOOKING EXPIRY (24h rule) ============================== */

function expire_stale_bookings(mysqli $c, string $now): void
{
    $c->begin_transaction();
    try {
        $rows = q(
            $c,
            "SELECT b.booking_id, b.booking_type, b.start_datetime, b.end_datetime,
            cu.user_id AS client_uid, p.user_id AS provider_uid,
                    pu.first_name AS pfirst, pu.last_name AS plast, sc.name AS category
            FROM bookings b
            JOIN clients cl ON cl.client_id = b.client_id
            JOIN users cu ON cu.user_id = cl.user_id
            JOIN providers p ON p.provider_id = b.provider_id
            JOIN users pu ON pu.user_id = p.user_id
            JOIN provider_services ps ON ps.provider_service_id = b.provider_service_id
            JOIN service_categories sc ON sc.category_id = ps.category_id
            WHERE b.status = 'pending' AND b.expires_at <= ?",
            's',
            [$now]
        );
        foreach ($rows as $r) {
            $n = run($c, "UPDATE bookings SET status='expired' WHERE booking_id=? AND status='pending'", 'i', [$r['booking_id']]);
            if ($n === 1) {
                $typeLabels = ['hourly' => 'Hourly', 'full_day' => 'Daily', 'urgent' => 'Urgent'];
                $typeLabel  = $typeLabels[$r['booking_type']] ?? ucfirst(str_replace('_', ' ', $r['booking_type']));
                $when       = fmt_dt($r['start_datetime']) . ' – ' . date('g:i A', strtotime($r['end_datetime']));
                $pname      = trim($r['pfirst'] . ' ' . $r['plast']);

                notify(
                    $c,
                    (int)$r['client_uid'],
                    (int)$r['booking_id'],
                    'booking_expired',
                    'Booking request expired',
                    "$pname did not respond to your $typeLabel {$r['category']} booking request on $when. The request expired and the time slot was released."
                );

                notify(
                    $c,
                    (int)$r['provider_uid'],
                    (int)$r['booking_id'],
                    'booking_expired',
                    'Booking request expired',
                    "A $typeLabel {$r['category']} booking request on $when expired because it was not answered within 24 hours."
                );
            }
        }
        $c->commit();
    } catch (Throwable $t) {
        $c->rollback();
        error_log('expire_stale_bookings: ' . $t->getMessage());
    }
}
expire_stale_bookings($conn, $now);

/* ============================== ACTIONS (POST) ============================== */

function act_update_profile(mysqli $c, int $uid, int $cid, ?string $currentPhoto): array
{
    $f   = trim($_POST['first_name'] ?? '');
    $m   = trim($_POST['middle_name'] ?? '');
    $l   = trim($_POST['last_name'] ?? '');
    $mob = preg_replace('/[\s-]/', '', trim($_POST['mobile_number'] ?? ''));
    $municipality = trim((string)($_POST['municipality'] ?? ''));
    $barangay = trim((string)($_POST['barangay'] ?? ''));
    $validMunicipalities = [
        'Enrique Villanueva',
        'Larena',
        'Lazi',
        'Maria',
        'San Juan',
        'Siquijor',
    ];
    $adr = $barangay . ', ' . $municipality . ', Siquijor';
    $ecn = trim($_POST['emergency_contact_name'] ?? '');
    $ecp = preg_replace('/[\s-]/', '', trim($_POST['emergency_contact_number'] ?? ''));

    $nameOk = function (string $s, bool $optional = false): bool {
        if ($s === '') return $optional;
        return mb_strlen($s) <= 50 && preg_match("/^[\p{L}][\p{L}\s.'-]*$/u", $s) === 1;
    };
    if (!$nameOk($f) || !$nameOk($l) || !$nameOk($m, true)) return res(false, 'Please enter a valid first, middle and last name.', 'profile');
    if (!preg_match(PH_MOBILE, $mob))                         return res(false, 'Enter a valid mobile number, e.g. 09123456789.', 'profile');
    if (
        !in_array($municipality, $validMunicipalities, true) ||
        $barangay === '' ||
        mb_strlen($barangay) > 100 ||
        !preg_match('/^[\p{L}\p{M}\s.\'-]+$/u', $barangay) ||
        mb_strlen($adr) > 255
    ) {
        return res(false, 'Choose a valid municipality and barangay for your home address.', 'profile');
    }
    if (mb_strlen($ecn) > 120)                                return res(false, 'Emergency contact name must be 120 characters or fewer.', 'profile');
    if ($ecp !== '' && !preg_match(PH_MOBILE, $ecp))          return res(false, 'Enter a valid emergency contact number, e.g. 09123456789.', 'profile');

    $newPhotoPath = null;
    $newPhotoAbsolutePath = null;
    $photoFile = $_FILES['profile_photo'] ?? null;
    if (is_array($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return res(false, 'The profile picture could not be uploaded. Please try again.', 'profile');
        }
        if (($photoFile['size'] ?? 0) > 5 * 1024 * 1024) {
            return res(false, 'The profile picture must be 5 MB or smaller.', 'profile');
        }
        if (!isset($photoFile['tmp_name']) || !is_uploaded_file($photoFile['tmp_name'])) {
            return res(false, 'The profile picture upload was rejected.', 'profile');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photoFile['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            return res(false, 'The profile picture must be a JPG, PNG, or WebP image.', 'profile');
        }

        $uploadDirectory = __DIR__ . '/../uploads/client_photos';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            return res(false, 'The profile picture could not be saved. Please try again later.', 'profile');
        }

        $filename = 'client_' . $cid . '_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $newPhotoAbsolutePath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($photoFile['tmp_name'], $newPhotoAbsolutePath)) {
            return res(false, 'The profile picture could not be saved. Please try again.', 'profile');
        }
        $newPhotoPath = 'uploads/client_photos/' . $filename;
    }

    $c->begin_transaction();
    try {
        run($c, "UPDATE users SET first_name=?, middle_name=?, last_name=?, mobile_number=? WHERE user_id=?", 'ssssi', [$f, $m, $l, $mob, $uid]);
        run(
            $c,
            "UPDATE clients SET address=?, emergency_contact_name=?, emergency_contact_number=?, photo=? WHERE client_id=?",
            'ssssi',
            [$adr, $ecn === '' ? null : $ecn, $ecp === '' ? null : $ecp, $newPhotoPath ?? $currentPhoto, $cid]
        );
        $c->commit();
    } catch (Throwable $t) {
        $c->rollback();
        if ($newPhotoAbsolutePath !== null && is_file($newPhotoAbsolutePath) && !unlink($newPhotoAbsolutePath)) {
            error_log('Could not remove an uncommitted client profile photo: ' . $newPhotoAbsolutePath);
        }
        throw $t;
    }

    if ($newPhotoPath !== null && $currentPhoto !== null &&
        preg_match('#\Auploads/client_photos/client_[0-9]+_[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $currentPhoto) === 1
    ) {
        $oldPhotoAbsolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $currentPhoto);
        if (is_file($oldPhotoAbsolutePath) && !unlink($oldPhotoAbsolutePath)) {
            error_log('Could not remove a replaced client profile photo: ' . $oldPhotoAbsolutePath);
        }
    }

    log_activity($c, $uid, 'profile_update', 'Client updated profile');
    return res(true, 'Your profile was updated.', 'profile');
}

function act_change_password(mysqli $c, int $uid): array
{
    $cur = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $con = (string)($_POST['confirm_password'] ?? '');

    $row = q1($c, "SELECT password_hash FROM users WHERE user_id=?", 'i', [$uid]);
    if (!$row || !password_verify($cur, $row['password_hash'])) {
        log_activity($c, $uid, 'password_change', 'Wrong current password', 'failed');
        return res(false, 'Your current password is incorrect.', 'profile');
    }
    if (strlen($new) < 8 || strlen($new) > 72) return res(false, 'The new password must be 8 to 72 characters.', 'profile');
    if ($new !== $con)                          return res(false, 'The new passwords do not match.', 'profile');
    if ($new === $cur)                          return res(false, 'Choose a password different from the current one.', 'profile');

    run($c, "UPDATE users SET password_hash=? WHERE user_id=?", 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
    log_activity($c, $uid, 'password_change', 'Password changed from dashboard');
    session_regenerate_id(true);
    return res(true, 'Your password was updated.', 'profile');
}

function act_create_booking(mysqli $c, int $uid, int $cid, string $now, array $rules, array $me): array
{
    $psId = (int)($_POST['provider_service_id'] ?? 0);
    $back = $psId > 0 ? '?service=' . $psId : '';

    $svc = bookable_service($c, $psId);
    if (!$svc) return res(false, 'That Provider or service is no longer available. Please pick another from Find Providers.', 'find');

    $type = (string)($_POST['booking_type'] ?? '');
    if (!isset($rules[$type])) return res(false, 'Choose a valid booking type.', 'bookings', $back);
    $r = $rules[$type];

    $dateStr = (string)($_POST['booking_date'] ?? '');
    $date = DateTime::createFromFormat('!Y-m-d', $dateStr);
    if (!$date || $date->format('Y-m-d') !== $dateStr)
        return res(false, 'Choose a valid date.', 'bookings', $back);

    $tS = (string)($_POST['start_time'] ?? '');
    $tE = (string)($_POST['end_time'] ?? '');
    $okTime = fn(string $t): bool => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
    if (!$okTime($tS) || !$okTime($tE))
        return res(false, 'Enter a valid start and end time.', 'bookings', $back);
    if ($tE <= $tS)
        return res(false, 'The end time must be after the start time and on the same day.', 'bookings', $back);

    $start = new DateTime($date->format('Y-m-d') . " $tS");
    $end   = new DateTime($date->format('Y-m-d') . " $tE");

    if ($start->getTimestamp() < time() - 60)
        return res(false, 'The start time cannot be in the past.', 'bookings', $back);

    if ($type !== 'urgent' && $start->format('Y-m-d') <= date('Y-m-d'))
        return res(false, 'Choose a date from tomorrow onward, or use Urgent for same-day help.', 'bookings', $back);

    if ($type === 'urgent' && $start->format('Y-m-d') !== date('Y-m-d'))
        return res(false, 'An urgent booking must start today.', 'bookings', $back);

    $span = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

    if ($span < $r['min'] || $span > $r['max'])
        return res(false, $r['min'] === $r['max']
            ? "A {$r['label']} booking must be exactly {$r['min']} hours long."
            : "A {$r['label']} booking must be {$r['min']} to {$r['max']} hours long.", 'bookings', $back);

    $hours = billed_hours($start, $end, $r['deduct']);

    $addr = trim($_POST['service_address'] ?? '');
    $ecn  = trim($_POST['emergency_contact_name'] ?? '');
    $ecp  = preg_replace('/[\s-]/', '', trim($_POST['emergency_contact_number'] ?? ''));
    $kids = trim($_POST['children_info'] ?? '');
    $pets = trim($_POST['pet_info'] ?? '');
    $note = trim($_POST['special_instructions'] ?? '');

    if ($addr === '' || mb_strlen($addr) > 255) return res(false, 'Service address is required (max 255 characters).', 'bookings', $back);
    if ($ecn === '' || mb_strlen($ecn) > 120)   return res(false, 'Emergency contact name is required.', 'bookings', $back);
    if (!preg_match(PH_MOBILE, $ecp))           return res(false, 'Enter a valid emergency contact number.', 'bookings', $back);
    if (mb_strlen($kids) > 255 || mb_strlen($pets) > 255 || mb_strlen($note) > 2000)
        return res(false, 'One of the optional fields is too long.', 'bookings', $back);

    $startStr = $start->format('Y-m-d H:i:s');
    $endStr   = $end->format('Y-m-d H:i:s');
    $rate     = (float)$svc['hourly_rate'];
    $total    = round($rate * $hours, 2);
    $expires  = date('Y-m-d H:i:s', time() + 24 * 3600);

    $c->begin_transaction();
    try {
        /* Lock the Provider row so two Clients cannot grab the same slot at the same time (NFR-9). */
        q1($c, "SELECT provider_id FROM providers WHERE provider_id=? FOR UPDATE", 'i', [$svc['provider_id']]);

        $clash = q1(
            $c,
            "SELECT booking_id FROM bookings
              WHERE provider_id = ?
                AND (status = 'confirmed' OR (status = 'pending' AND expires_at > ?))
                AND start_datetime < ? AND end_datetime > ? LIMIT 1",
            'isss',
            [$svc['provider_id'], $now, $endStr, $startStr]
        );
        if ($clash) {
            $c->rollback();
            return res(false, 'This Provider already has a booking that overlaps your chosen time. Please pick another time.', 'bookings', $back);
        }

        run(
            $c,
            "INSERT INTO bookings (client_id, provider_id, provider_service_id, booking_type, start_datetime, end_datetime,
                                   duration_hours, hourly_rate, total_cost, service_address, emergency_contact_name,
                                   emergency_contact_number, special_instructions, children_info, pet_info, status, expires_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?)",
            'iiisssdddsssssss',
            [
                $cid,
                $svc['provider_id'],
                $svc['provider_service_id'],
                $type,
                $startStr,
                $endStr,
                $hours,
                $rate,
                $total,
                $addr,
                $ecn,
                $ecp,
                $note === '' ? null : $note,
                $kids === '' ? null : $kids,
                $pets === '' ? null : $pets,
                $expires
            ]
        );
        $bookingId = (int)$c->insert_id;

        notify(
            $c,
            (int)$svc['provider_uid'],
            $bookingId,
            'booking_request',
            'New booking request',
            $me['first_name'] . ' requested ' . $svc['category'] . ' on ' . fmt_dt($startStr) . '.'
        );
        $c->commit();
    } catch (Throwable $t) {
        $c->rollback();
        throw $t;
    }

    log_activity($c, $uid, 'booking_request', "Booking #$bookingId requested");
    return res(true, 'Booking request sent. It stays Pending until the Provider accepts (expires in 24 hours).', 'bookings');
}

function act_cancel_booking(mysqli $c, int $uid, int $cid, string $now): array
{
    $bid = (int)($_POST['booking_id'] ?? 0);
    $b = q1(
        $c,
        "SELECT b.booking_id, b.start_datetime, p.user_id AS provider_uid
           FROM bookings b JOIN providers p ON p.provider_id = b.provider_id
          WHERE b.booking_id = ? AND b.client_id = ?",
        'ii',
        [$bid, $cid]
    );
    if (!$b) return res(false, 'Booking not found.', 'bookings');

    $n = run(
        $c,
        "UPDATE bookings SET status='cancelled', cancelled_by='client'
          WHERE booking_id=? AND client_id=? AND status IN ('pending','confirmed') AND start_datetime > ?",
        'iis',
        [$bid, $cid, $now]
    );
    if ($n !== 1) return res(false, 'This booking can no longer be cancelled.', 'bookings');

    notify(
        $c,
        (int)$b['provider_uid'],
        $bid,
        'booking_cancelled',
        'Booking cancelled',
        'The Client cancelled the booking for ' . fmt_dt($b['start_datetime']) . '. The time slot is free again.'
    );
    log_activity($c, $uid, 'booking_cancel', "Booking #$bid cancelled by client");
    return res(true, 'Booking cancelled. The time slot was released.', 'bookings');
}

function act_submit_review(mysqli $c, int $uid, int $cid): array
{
    $bid    = (int)($_POST['booking_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $text   = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5)   return res(false, 'Choose a rating from 1 to 5.', 'reviews');
    if (mb_strlen($text) > 1000)      return res(false, 'Your review is too long (max 1000 characters).', 'reviews');

    $b = q1($c, "SELECT booking_id, provider_id FROM bookings WHERE booking_id=? AND client_id=? AND status='completed'", 'ii', [$bid, $cid]);
    if (!$b) return res(false, 'You can only review your own completed bookings.', 'reviews');

    $c->begin_transaction();
    try {
        run(
            $c,
            "INSERT INTO reviews (booking_id, client_id, provider_id, rating, comment) VALUES (?,?,?,?,?)",
            'iiiis',
            [$bid, $cid, $b['provider_id'], $rating, $text === '' ? null : $text]
        );
        run(
            $c,
            "UPDATE providers SET
                avg_rating    = (SELECT COALESCE(ROUND(AVG(rating), 2), 0) FROM reviews WHERE provider_id=? AND is_hidden=0),
                total_reviews = (SELECT COUNT(*) FROM reviews WHERE provider_id=? AND is_hidden=0)
              WHERE provider_id=?",
            'iii',
            [$b['provider_id'], $b['provider_id'], $b['provider_id']]
        );
        $c->commit();
    } catch (mysqli_sql_exception $t) {
        $c->rollback();
        if ($t->getCode() === 1062) return res(false, 'You already reviewed this booking.', 'reviews');
        throw $t;
    }
    log_activity($c, $uid, 'review_submit', "Review for booking #$bid");
    return res(true, 'Thank you! Your review was submitted.', 'reviews');
}

function act_submit_report(mysqli $c, int $uid, int $cid): array
{
    $bid  = (int)($_POST['booking_id'] ?? 0);
    $type = (string)($_POST['report_type'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (!in_array($type, ['complaint', 'safety_concern', 'other'], true)) return res(false, 'Choose a valid report type.', 'reports');
    if (mb_strlen($desc) < 10 || mb_strlen($desc) > 2000)                 return res(false, 'Describe the issue in 10 to 2000 characters.', 'reports');

    $reportedUid = null;
    $bookingId = null;
    if ($bid > 0) {
        $b = q1($c, "SELECT b.booking_id, p.user_id AS provider_uid FROM bookings b JOIN providers p ON p.provider_id=b.provider_id
                      WHERE b.booking_id=? AND b.client_id=?", 'ii', [$bid, $cid]);
        if (!$b) return res(false, 'That booking does not belong to your account.', 'reports');
        $bookingId = (int)$b['booking_id'];
        $reportedUid = (int)$b['provider_uid'];
    }
    run(
        $c,
        "INSERT INTO reports (reporter_id, reported_user_id, booking_id, report_type, description) VALUES (?,?,?,?,?)",
        'iiiss',
        [$uid, $reportedUid, $bookingId, $type, $desc]
    );
    log_activity($c, $uid, 'report_submit', "Report type: $type");
    return res(true, 'Your report was sent. The Administrator will review it.', 'reports');
}

function act_mark_all_read(mysqli $c, int $uid): array
{
    run($c, "UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0", 'i', [$uid]);
    return res(true, 'All notifications marked as read.', 'notifications');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
            $r = res(false, 'Your session expired. Please try again.', 'overview');
        } else {
            switch ($_POST['action'] ?? '') {
                case 'update_profile':
                    $r = act_update_profile($conn, $userId, $clientId, $me['photo'] ?? null);
                    break;
                case 'change_password':
                    $r = act_change_password($conn, $userId);
                    break;
                case 'create_booking':
                    $r = act_create_booking($conn, $userId, $clientId, $now, $BOOKING_RULES, $me);
                    break;
                case 'cancel_booking':
                    $r = act_cancel_booking($conn, $userId, $clientId, $now);
                    break;
                case 'submit_review':
                    $r = act_submit_review($conn, $userId, $clientId);
                    break;
                case 'submit_report':
                    $r = act_submit_report($conn, $userId, $clientId);
                    break;
                case 'mark_all_read':
                    $r = act_mark_all_read($conn, $userId);
                    break;
                default:
                    $r = res(false, 'Unknown action.', 'overview');
            }
        }
    } catch (Throwable $t) {
        error_log('client_logic: ' . $t->getMessage());
        $r = res(false, 'Something went wrong. Please try again.', 'overview');
    }

    $_SESSION['flash'] = ['ok' => $r['ok'], 'msg' => $r['msg']];
    if (!$r['ok']) {
        $keep = $_POST;
        unset($keep['current_password'], $keep['new_password'], $keep['confirm_password'], $keep['csrf']);
        $_SESSION['old'] = $keep;
    }
    header('Location: ' . SELF_URL . $r['q'] . '#' . $r['to']);
    exit;
}

/* ============================== DATA FOR THE PAGE (GET) ============================== */

/* ---- Find Providers: service category and provider location filters ---- */
$categories = q($conn, "SELECT category_id, name FROM service_categories WHERE is_active = 1 ORDER BY name");
$catId = (int)($_GET['category_id'] ?? 0);
$catName = '';
foreach ($categories as $cat) {
    if ((int)$cat['category_id'] === $catId) {
        $catName = $cat['name'];
    }
}
if ($catName === '') {
    $catId = 0;
}

$municipalities = [
    'Enrique Villanueva',
    'Larena',
    'Lazi',
    'Maria',
    'San Juan',
    'Siquijor',
];

$municipality = trim((string)($_GET['municipality'] ?? ''));
if (!in_array($municipality, $municipalities, true)) {
    $municipality = '';
}
$barangay = trim((string)($_GET['barangay'] ?? ''));
if ($municipality === '' || !preg_match('/^[\p{L}\p{M}\s.\'-]{1,100}$/u', $barangay)) {
    $barangay = '';
}

$providerFilters = [];
$providerTypes = '';
$providerArgs = [];
if ($catId) {
    $providerFilters[] = "EXISTS (
        SELECT 1
        FROM provider_services ps
        JOIN service_categories sc ON sc.category_id = ps.category_id
        WHERE ps.provider_id = p.provider_id
          AND ps.is_active = 1
          AND sc.is_active = 1
          AND ps.category_id = ?
    )";
    $providerTypes .= 'i';
    $providerArgs[] = $catId;
}
if ($barangay !== '') {
    $providerFilters[] = 'p.location = ?';
    $providerTypes .= 's';
    $providerArgs[] = $barangay . ', ' . $municipality . ', Siquijor';
} elseif ($municipality !== '') {
    $providerFilters[] = 'p.location LIKE ?';
    $providerTypes .= 's';
    $providerArgs[] = '%, ' . $municipality . ', Siquijor';
}

$sql = "SELECT p.provider_id, p.location, p.photo, p.experience_years, p.avg_rating, p.total_reviews,
               u.first_name, u.last_name
          FROM providers p
          JOIN users u ON u.user_id = p.user_id
         WHERE p.verification_status = 'approved'
           AND u.status = 'active'
           AND EXISTS (SELECT 1 FROM provider_services ps
                         JOIN service_categories sc ON sc.category_id = ps.category_id
                        WHERE ps.provider_id = p.provider_id
                          AND ps.is_active = 1 AND sc.is_active = 1)"
    . ($providerFilters ? ' AND ' . implode(' AND ', $providerFilters) : '') . "
         ORDER BY p.avg_rating DESC, p.total_reviews DESC, u.first_name ASC";
$providers = q($conn, $sql, $providerTypes, $providerArgs);

$servicesByProvider = [];
if ($providers) {
    $ids = array_map('intval', array_column($providers, 'provider_id'));
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $args  = $ids;
    $sql2 = "SELECT ps.provider_service_id, ps.provider_id, ps.hourly_rate, sc.name AS category
               FROM provider_services ps JOIN service_categories sc ON sc.category_id = ps.category_id
              WHERE ps.is_active = 1 AND sc.is_active = 1 AND ps.provider_id IN ($ph)";
    if ($catId) {
        $sql2 .= " AND ps.category_id = ?";
        $types .= 'i';
        $args[] = $catId;
    }
    $sql2 .= " ORDER BY sc.name";
    foreach (q($conn, $sql2, $types, $args) as $row) {
        $servicesByProvider[(int)$row['provider_id']][] = $row;
    }
}

/* ---- Provider profile view (?provider=ID) ---- */
$viewProvider = null;
$viewServices = [];
$viewReviews = [];
if (isset($_GET['provider'])) {
    $viewProvider = q1(
        $conn,
        "SELECT p.provider_id, p.location, p.photo, p.skills, p.experience_years, p.qualifications,
                p.avg_rating, p.total_reviews, u.first_name, u.last_name
           FROM providers p JOIN users u ON u.user_id = p.user_id
          WHERE p.provider_id = ? AND p.verification_status='approved' AND u.status='active'",
        'i',
        [(int)$_GET['provider']]
    );
    if ($viewProvider) {
        $viewServices = q(
            $conn,
            "SELECT ps.provider_service_id, ps.hourly_rate, sc.name AS category
               FROM provider_services ps JOIN service_categories sc ON sc.category_id = ps.category_id
              WHERE ps.provider_id=? AND ps.is_active=1 AND sc.is_active=1 ORDER BY sc.name",
            'i',
            [$viewProvider['provider_id']]
        );
        $viewReviews = q(
            $conn,
            "SELECT r.rating, r.comment, r.created_at, u.first_name, u.last_name
               FROM reviews r JOIN clients cl ON cl.client_id = r.client_id JOIN users u ON u.user_id = cl.user_id
              WHERE r.provider_id=? AND r.is_hidden=0 ORDER BY r.created_at DESC LIMIT 10",
            'i',
            [$viewProvider['provider_id']]
        );
    }
}

/* ---- Service selected for the booking form (?service=ID) ---- */
$selSvc = isset($_GET['service']) ? bookable_service($conn, (int)$_GET['service']) : null;

/* ---- Bookings (all of this Client's), then stats are derived from them ---- */
$allBookings = q(
    $conn,
    "SELECT b.*, u.first_name AS pfirst, u.last_name AS plast, sc.name AS category
       FROM bookings b
       JOIN providers p ON p.provider_id = b.provider_id
       JOIN users u ON u.user_id = p.user_id
       JOIN provider_services ps ON ps.provider_service_id = b.provider_service_id
       JOIN service_categories sc ON sc.category_id = ps.category_id
      WHERE b.client_id = ? ORDER BY b.start_datetime DESC",
    'i',
    [$clientId]
);

$statusKeys = ['all', 'pending', 'confirmed', 'completed', 'declined', 'cancelled', 'expired'];
$status = in_array($_GET['status'] ?? 'all', $statusKeys, true) ? ($_GET['status'] ?? 'all') : 'all';

$counts = array_fill_keys($statusKeys, 0);
$counts['all'] = count($allBookings);
$sumPending = $sumConfirmed = $sumCompleted = 0.0;
$upcoming = 0;
$nextBooking = null;
foreach ($allBookings as $b) {
    $counts[$b['status']]++;
    if ($b['status'] === 'pending') {
        $sumPending   += (float)$b['total_cost'];
    }
    if ($b['status'] === 'completed') {
        $sumCompleted += (float)$b['total_cost'];
    }
    if ($b['status'] === 'confirmed' && $b['end_datetime'] >= $now) {
        $sumConfirmed += (float)$b['total_cost'];
        $upcoming++;
        if ($b['start_datetime'] >= $now && ($nextBooking === null || $b['start_datetime'] < $nextBooking['start_datetime'])) {
            $nextBooking = $b;
        }
    }
}
$shownBookings = $status === 'all' ? $allBookings : array_values(array_filter($allBookings, fn($b) => $b['status'] === $status));

/* ---- Reviews ---- */
$myReviews = q(
    $conn,
    "SELECT r.rating, r.comment, r.created_at, r.booking_id, u.first_name, u.last_name
       FROM reviews r JOIN providers p ON p.provider_id = r.provider_id JOIN users u ON u.user_id = p.user_id
      WHERE r.client_id = ? ORDER BY r.created_at DESC",
    'i',
    [$clientId]
);
$reviewedIds = array_map('intval', array_column($myReviews, 'booking_id'));
$reviewable  = array_values(array_filter($allBookings, fn($b) => $b['status'] === 'completed' && !in_array((int)$b['booking_id'], $reviewedIds, true)));

/* ---- Reports ---- */
$myReports = q(
    $conn,
    "SELECT r.report_type, r.description, r.status, r.created_at, u.first_name, u.last_name
       FROM reports r LEFT JOIN users u ON u.user_id = r.reported_user_id
      WHERE r.reporter_id = ? ORDER BY r.created_at DESC",
    'i',
    [$userId]
);

/* ---- Notifications ---- */
$notes  = q($conn, "SELECT notification_id, title, message, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC, notification_id DESC LIMIT 50", 'i', [$userId]);
$unread = (int)(q1($conn, "SELECT COUNT(*) AS n FROM notifications WHERE user_id=? AND is_read=0", 'i', [$userId])['n'] ?? 0);
