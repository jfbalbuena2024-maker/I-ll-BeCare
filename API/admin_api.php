<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/connection.php';   // must define $conn (mysqli)
require_once __DIR__ . '/../config/booking_status.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

const CLEARANCE_CATEGORIES = [1, 2];   // Childcare, Senior companionship (ids from your SQL)

/* ---------- helpers ---------- */
function out(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $m, int $code = 400): never
{
    out(['ok' => false, 'error' => $m], $code);
}
function run(string $sql, string $t = '', array $p = []): mysqli_stmt
{
    global $conn;
    $s = $conn->prepare($sql);
    if ($t !== '') $s->bind_param($t, ...$p);
    $s->execute();
    return $s;
}
function rows(string $sql, string $t = '', array $p = []): array
{
    return run($sql, $t, $p)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function one(string $sql, string $t = '', array $p = []): ?array
{
    $r = rows($sql, $t, $p);
    return $r[0] ?? null;
}
function sv(array $a, string $k, int $max = 255): string
{
    return mb_substr(trim((string)($a[$k] ?? '')), 0, $max);
}
function like(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}
function logAct(int $uid, string $action, string $details = '', string $outcome = 'success'): void
{
    run(
        'INSERT INTO activity_logs (user_id,action,details,ip_address,outcome) VALUES (?,?,?,?,?)',
        'issss',
        [$uid, $action, mb_substr($details, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null, $outcome]
    );
}
function notify(int $uid, string $type, string $title, string $msg): void
{
    run('INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)', 'isss', [$uid, $type, $title, mb_substr($msg, 0, 255)]);
}
function addEq(array &$w, string &$t, array &$p, string $col, string $val, array $ok): void
{
    if (in_array($val, $ok, true)) {
        $w[] = "$col=?";
        $t .= 's';
        $p[] = $val;
    }
}
function addLike(array &$w, string &$t, array &$p, string $q, array $cols): void
{
    if ($q === '') return;
    $w[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols)) . ')';
    foreach ($cols as $_) {
        $t .= 's';
        $p[] = like($q);
    }
}
function listing(string $select, string $from, array $w, string $order, string $t, array $p, int $page, int $per = 15): array
{
    $where = 'WHERE ' . implode(' AND ', $w);
    $total = (int)one("SELECT COUNT(*) n $from $where", $t, $p)['n'];
    $off = max(0, ($page - 1) * $per);
    return ['items' => rows("$select $from $where ORDER BY $order LIMIT $per OFFSET $off", $t, $p), 'total' => $total, 'page' => $page, 'per' => $per];
}
function providerDetail(int $pid): ?array
{
    $p = one('SELECT p.provider_id,p.user_id,p.location,p.experience_years,p.skills,p.qualifications,p.verification_status,
                     p.verification_remarks,p.verified_at,p.avg_rating,p.total_reviews,
                     CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) full_name,u.email,u.mobile_number,u.created_at
              FROM providers p JOIN users u ON u.user_id=p.user_id WHERE p.provider_id=?', 'i', [$pid]);
    if (!$p) return null;
    $p['documents'] = rows('SELECT document_id,doc_type,uploaded_at FROM provider_documents WHERE provider_id=? ORDER BY uploaded_at', 'i', [$pid]);
    $p['services']  = rows('SELECT sc.category_id,sc.name,ps.hourly_rate,ps.is_active FROM provider_services ps
                            JOIN service_categories sc ON sc.category_id=ps.category_id WHERE ps.provider_id=?', 'i', [$pid]);
    $types = array_column($p['documents'], 'doc_type');
    $needs = false;
    foreach ($p['services'] as $s) if ($s['is_active'] && in_array((int)$s['category_id'], CLEARANCE_CATEGORIES, true)) $needs = true;
    $p['requirements'] = [
        'has_id'          => in_array('government_id', $types, true),
        'needs_clearance' => $needs,
        'has_clearance'   => in_array('nbi_clearance', $types, true) || in_array('police_clearance', $types, true),
    ];
    return $p;
}

/* ---------- auth, timeout, CSRF ---------- */
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') fail('Not authorized.', 401);
if (time() - ($_SESSION['last_activity'] ?? time()) > 1800) {
    session_unset();
    session_destroy();
    fail('Session expired.', 401);
}
$_SESSION['last_activity'] = time();
$me  = (int)$_SESSION['user_id'];
$adm = one('SELECT first_name,last_name FROM users WHERE user_id=? AND role="admin" AND status="active"', 'i', [$me]);
if (!$adm) fail('Not authorized.', 401);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$action = (string)($_GET['action'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$in     = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Invalid request token. Reload the page.', 403);
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
}

/* ---------- routes ---------- */
try {
    if (in_array($action, ['stats', 'bookings_list'], true)) {
        complete_ended_bookings($conn);
    }

    switch ($action) {

        case 'init':
            out(['ok' => true, 'csrf' => $_SESSION['csrf'], 'name' => $adm['first_name'] . ' ' . $adm['last_name']]);

        case 'logout':
            logAct($me, 'logout', 'Administrator signed out');
            $_SESSION = [];
            session_destroy();
            out(['ok' => true]);

        case 'stats':
            $u  = one('SELECT COUNT(*) total, COALESCE(SUM(created_at >= DATE_FORMAT(NOW(),"%Y-%m-01")),0) month FROM users WHERE role<>"admin"');
            $pv = one('SELECT COUNT(*) total, COALESCE(SUM(verification_status="pending"),0) pending FROM providers');
            $by = array_fill_keys(['pending', 'confirmed', 'declined', 'cancelled', 'expired', 'completed'], 0);
            foreach (rows('SELECT status, COUNT(*) n FROM bookings GROUP BY status') as $r) $by[$r['status']] = (int)$r['n'];
            out([
                'ok' => true,
                'users_total' => (int)$u['total'],
                'users_month' => (int)$u['month'],
                'providers_total' => (int)$pv['total'],
                'providers_pending' => (int)$pv['pending'],
                'bookings_total' => array_sum($by),
                'by_status' => $by,
                'bookings_today' => (int)one('SELECT COUNT(*) n FROM bookings WHERE status="confirmed" AND DATE(start_datetime)=CURDATE()')['n'],
                'reports_open' => (int)one('SELECT COUNT(*) n FROM reports WHERE status IN ("open","investigating")')['n'],
                'recent' => rows('SELECT a.action,a.details,a.outcome,a.created_at,CONCAT_WS(" ",u.first_name,u.last_name) who
                              FROM activity_logs a LEFT JOIN users u ON u.user_id=a.user_id ORDER BY a.log_id DESC LIMIT 5'),
            ]);

        case 'users_list':
            $w = ['u.role<>"admin"'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['CONCAT_WS(" ",u.first_name,u.last_name)', 'u.email']);
            addEq($w, $t, $p, 'u.role', (string)($_GET['role'] ?? ''), ['client', 'provider']);
            addEq($w, $t, $p, 'u.status', (string)($_GET['status'] ?? ''), ['active', 'blocked']);
            out(['ok' => true] + listing(
                'SELECT u.user_id,CONCAT_WS(" ",u.first_name,u.middle_name,u.last_name) full_name,u.email,u.mobile_number,u.role,u.status,u.last_login,u.created_at',
                'FROM users u',
                $w,
                'u.created_at DESC',
                $t,
                $p,
                $page
            ));

        case 'user_status':
            $id = (int)($in['user_id'] ?? 0);
            $st = (string)($in['status'] ?? '');
            if (!in_array($st, ['active', 'blocked'], true)) fail('Invalid status.');
            $tg = one('SELECT role FROM users WHERE user_id=?', 'i', [$id]);
            if (!$tg) fail('User not found.', 404);
            if ($tg['role'] === 'admin') fail('Administrator accounts cannot be changed here.');
            run('UPDATE users SET status=? WHERE user_id=?', 'si', [$st, $id]);
            logAct($me, 'user_' . $st, 'User #' . $id);
            out(['ok' => true, 'message' => 'Account ' . ($st === 'blocked' ? 'blocked.' : 'unblocked.')]);

        case 'providers_list':
            $w = ['1=1'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['CONCAT_WS(" ",u.first_name,u.last_name)', 'u.email', 'p.location']);
            addEq($w, $t, $p, 'p.verification_status', (string)($_GET['status'] ?? ''), ['pending', 'approved', 'rejected', 'suspended']);
            out(['ok' => true] + listing(
                'SELECT p.provider_id,p.location,p.experience_years,p.verification_status,p.avg_rating,p.total_reviews,
                CONCAT_WS(" ",u.first_name,u.last_name) full_name,u.email,u.created_at,
                (SELECT GROUP_CONCAT(sc.name SEPARATOR ", ") FROM provider_services ps JOIN service_categories sc ON sc.category_id=ps.category_id
                  WHERE ps.provider_id=p.provider_id AND ps.is_active=1) services,
                (SELECT COUNT(*) FROM provider_documents d WHERE d.provider_id=p.provider_id) docs',
                'FROM providers p JOIN users u ON u.user_id=p.user_id',
                $w,
                '(p.verification_status="pending") DESC, u.created_at DESC',
                $t,
                $p,
                $page
            ));

        case 'provider_detail':
            $pd = providerDetail((int)($_GET['id'] ?? 0));
            if (!$pd) fail('Provider not found.', 404);
            out(['ok' => true, 'provider' => $pd]);

        case 'provider_decide':
            $pid = (int)($in['provider_id'] ?? 0);
            $dec = (string)($in['decision'] ?? '');
            $rem = sv($in, 'remarks', 150);
            $map = [
                'approve' => ['approved', ['pending', 'rejected']],
                'reinstate' => ['approved', ['suspended']],
                'reject' => ['rejected', ['pending']],
                'suspend' => ['suspended', ['approved']]
            ];
            if (!isset($map[$dec])) fail('Invalid decision.');
            [$new, $from] = $map[$dec];
            if (in_array($dec, ['reject', 'suspend'], true) && mb_strlen($rem) < 5) fail('Give a reason of at least 5 characters. The Provider will see it.');
            $conn->begin_transaction();
            $cur = one('SELECT verification_status,user_id FROM providers WHERE provider_id=? FOR UPDATE', 'i', [$pid]);
            if (!$cur) {
                $conn->rollback();
                fail('Provider not found.', 404);
            }
            if (!in_array($cur['verification_status'], $from, true)) {
                $conn->rollback();
                fail('This provider is already ' . $cur['verification_status'] . '.');
            }
            if ($new === 'approved') {
                $rq = providerDetail($pid)['requirements'];
                $miss = [];
                if (!$rq['has_id']) $miss[] = 'government ID';
                if ($rq['needs_clearance'] && !$rq['has_clearance']) $miss[] = 'NBI or police clearance';
                if ($miss) {
                    $conn->rollback();
                    fail('Cannot approve: missing ' . implode(' and ', $miss) . '.');
                }
            }
            run(
                'UPDATE providers SET verification_status=?, verification_remarks=?, verified_by=?, verified_at=NOW() WHERE provider_id=?',
                'ssii',
                [$new, $rem !== '' ? $rem : null, $me, $pid]
            );
            $msg = [
                'approved' => 'Your documents were verified. Clients can now find and book you.',
                'rejected' => 'Your application was not approved.',
                'suspended' => 'Your provider account has been suspended.'
            ][$new];
            if ($rem !== '') $msg .= ' Note: ' . $rem;
            notify((int)$cur['user_id'], 'verification_result', 'Verification update', $msg);
            logAct($me, 'provider_' . $dec, 'Provider #' . $pid);
            $conn->commit();
            $aff = $dec === 'suspend' ? (int)one('SELECT COUNT(*) n FROM bookings WHERE provider_id=? AND status IN ("pending","confirmed")', 'i', [$pid])['n'] : 0;
            out(['ok' => true, 'message' => 'Provider ' . $new . '.' . ($aff ? " $aff active booking(s) still exist. Review them." : '')]);

        case 'bookings_list':
            $w = ['1=1'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['CONCAT_WS(" ",uc.first_name,uc.last_name)', 'CONCAT_WS(" ",up.first_name,up.last_name)']);
            addEq($w, $t, $p, 'b.status', (string)($_GET['status'] ?? ''), ['pending', 'confirmed', 'declined', 'cancelled', 'expired', 'completed']);
            out(['ok' => true] + listing(
                'SELECT b.booking_id,b.booking_type,b.start_datetime,b.end_datetime,b.total_cost,b.status,b.cancelled_by,sc.name service,
                CONCAT_WS(" ",uc.first_name,uc.last_name) client,CONCAT_WS(" ",up.first_name,up.last_name) provider',
                'FROM bookings b JOIN clients c ON c.client_id=b.client_id JOIN users uc ON uc.user_id=c.user_id
             JOIN providers pr ON pr.provider_id=b.provider_id JOIN users up ON up.user_id=pr.user_id
             JOIN provider_services ps ON ps.provider_service_id=b.provider_service_id JOIN service_categories sc ON sc.category_id=ps.category_id',
                $w,
                'b.created_at DESC',
                $t,
                $p,
                $page
            ));

        case 'services_list':
            out(['ok' => true] + listing(
                'SELECT sc.category_id,sc.name,sc.description,sc.is_active,
                (SELECT COUNT(*) FROM provider_services ps WHERE ps.category_id=sc.category_id AND ps.is_active=1) providers',
                'FROM service_categories sc',
                ['1=1'],
                'sc.category_id',
                '',
                [],
                1,
                100
            ));

        case 'service_save':
            $id = (int)($in['category_id'] ?? 0);
            $name = sv($in, 'name', 80);
            $desc = sv($in, 'description');
            if (mb_strlen($name) < 2) fail('Service name is required.');
            if ($id) run('UPDATE service_categories SET name=?,description=? WHERE category_id=?', 'ssi', [$name, $desc !== '' ? $desc : null, $id]);
            else     run('INSERT INTO service_categories (name,description) VALUES (?,?)', 'ss', [$name, $desc !== '' ? $desc : null]);
            logAct($me, $id ? 'service_edit' : 'service_add', $name);
            out(['ok' => true, 'message' => 'Service saved.']);

        case 'service_toggle':
            $id = (int)($in['category_id'] ?? 0);
            run('UPDATE service_categories SET is_active = 1 - is_active WHERE category_id=?', 'i', [$id]);
            logAct($me, 'service_toggle', 'Category #' . $id);
            out(['ok' => true, 'message' => 'Service updated.']);

        case 'reports_list':
            $w = ['1=1'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['r.description', 'CONCAT_WS(" ",ur.first_name,ur.last_name)', 'CONCAT_WS(" ",ud.first_name,ud.last_name)']);
            addEq($w, $t, $p, 'r.status', (string)($_GET['status'] ?? ''), ['open', 'investigating', 'resolved', 'dismissed']);
            addEq($w, $t, $p, 'r.report_type', (string)($_GET['type'] ?? ''), ['complaint', 'safety_concern', 'other']);
            out(['ok' => true] + listing(
                'SELECT r.report_id,r.report_type,r.description,r.status,r.admin_notes,r.booking_id,r.created_at,r.resolved_at,
                CONCAT_WS(" ",ur.first_name,ur.last_name) reporter,ur.role reporter_role,
                CONCAT_WS(" ",ud.first_name,ud.last_name) reported,ud.role reported_role',
                'FROM reports r JOIN users ur ON ur.user_id=r.reporter_id LEFT JOIN users ud ON ud.user_id=r.reported_user_id',
                $w,
                '(r.status IN ("open","investigating")) DESC, r.created_at DESC',
                $t,
                $p,
                $page
            ));

        case 'report_update':
            $id = (int)($in['report_id'] ?? 0);
            $st = (string)($in['status'] ?? '');
            $nt = sv($in, 'notes', 2000);
            if (!in_array($st, ['open', 'investigating', 'resolved', 'dismissed'], true)) fail('Invalid status.');
            $closed = in_array($st, ['resolved', 'dismissed'], true);
            if ($closed && mb_strlen($nt) < 5) fail('Add a short resolution note.');
            run(
                'UPDATE reports SET status=?,admin_notes=?,resolved_by=?,resolved_at=? WHERE report_id=?',
                'ssisi',
                [$st, $nt !== '' ? $nt : null, $closed ? $me : null, $closed ? date('Y-m-d H:i:s') : null, $id]
            );
            logAct($me, 'report_' . $st, 'Report #' . $id);
            out(['ok' => true, 'message' => 'Report updated.']);

        case 'reviews_list':
            $w = ['1=1'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['rv.comment', 'CONCAT_WS(" ",uc.first_name,uc.last_name)', 'CONCAT_WS(" ",up.first_name,up.last_name)']);
            $vis = (string)($_GET['vis'] ?? '');
            if ($vis === 'visible') $w[] = 'rv.is_hidden=0';
            elseif ($vis === 'hidden') $w[] = 'rv.is_hidden=1';
            out(['ok' => true] + listing(
                'SELECT rv.review_id,rv.rating,rv.comment,rv.is_hidden,rv.created_at,
                CONCAT_WS(" ",uc.first_name,uc.last_name) client,CONCAT_WS(" ",up.first_name,up.last_name) provider',
                'FROM reviews rv JOIN clients c ON c.client_id=rv.client_id JOIN users uc ON uc.user_id=c.user_id
             JOIN providers pr ON pr.provider_id=rv.provider_id JOIN users up ON up.user_id=pr.user_id',
                $w,
                'rv.created_at DESC',
                $t,
                $p,
                $page
            ));

        case 'review_visibility':
            $id = (int)($in['review_id'] ?? 0);
            $hid = empty($in['hidden']) ? 0 : 1;
            $conn->begin_transaction();
            $rv = one('SELECT provider_id FROM reviews WHERE review_id=? FOR UPDATE', 'i', [$id]);
            if (!$rv) {
                $conn->rollback();
                fail('Review not found.', 404);
            }
            run('UPDATE reviews SET is_hidden=? WHERE review_id=?', 'ii', [$hid, $id]);
            run('UPDATE providers p SET
               avg_rating = COALESCE((SELECT ROUND(AVG(rating),2) FROM reviews WHERE provider_id=p.provider_id AND is_hidden=0),0),
               total_reviews = (SELECT COUNT(*) FROM reviews WHERE provider_id=p.provider_id AND is_hidden=0)
             WHERE p.provider_id=?', 'i', [(int)$rv['provider_id']]);
            logAct($me, $hid ? 'review_hide' : 'review_show', 'Review #' . $id);
            $conn->commit();
            out(['ok' => true, 'message' => $hid ? 'Review hidden.' : 'Review visible again.']);

        case 'logs_list':
            $w = ['1=1'];
            $t = '';
            $p = [];
            addLike($w, $t, $p, sv($_GET, 'q', 60), ['a.action', 'a.details', 'CONCAT_WS(" ",u.first_name,u.last_name)']);
            addEq($w, $t, $p, 'a.outcome', (string)($_GET['outcome'] ?? ''), ['success', 'failed']);
            out(['ok' => true] + listing(
                'SELECT a.log_id,a.action,a.details,a.ip_address,a.outcome,a.created_at,CONCAT_WS(" ",u.first_name,u.last_name) who',
                'FROM activity_logs a LEFT JOIN users u ON u.user_id=a.user_id',
                $w,
                'a.log_id DESC',
                $t,
                $p,
                $page,
                20
            ));

        case 'change_password':
            $cur = (string)($in['current'] ?? '');
            $new = (string)($in['new'] ?? '');
            if (strlen($new) < 8) fail('New password must be at least 8 characters.');
            $row = one('SELECT password_hash FROM users WHERE user_id=?', 'i', [$me]);
            if (!password_verify($cur, $row['password_hash'])) {
                logAct($me, 'password_change', 'Wrong current password', 'failed');
                fail('Current password is incorrect.');
            }
            run('UPDATE users SET password_hash=? WHERE user_id=?', 'si', [password_hash($new, PASSWORD_DEFAULT), $me]);
            session_regenerate_id(true);
            logAct($me, 'password_change', 'Administrator password changed');
            out(['ok' => true, 'message' => 'Password changed.']);

        case 'announce':
            $roles = ['all' => ['client', 'provider'], 'client' => ['client', 'client'], 'provider' => ['provider', 'provider']][(string)($in['audience'] ?? '')] ?? null;
            $title = sv($in, 'title', 120);
            $msg = sv($in, 'message');
            if (!$roles) fail('Choose an audience.');
            if ($title === '' || $msg === '') fail('Title and message are required.');
            $s = run(
                'INSERT INTO notifications (user_id,type,title,message) SELECT user_id,"announcement",?,? FROM users WHERE role IN (?,?) AND status="active"',
                'ssss',
                [$title, $msg, $roles[0], $roles[1]]
            );
            logAct($me, 'announcement', $title);
            out(['ok' => true, 'message' => 'Sent to ' . $s->affected_rows . ' user(s).']);

        default:
            fail('Unknown action.', 404);
    }
} catch (mysqli_sql_exception $e) {
    try {
        $conn->rollback();
    } catch (Throwable $x) {
    }
    if ($e->getCode() === 1062) fail('That value already exists.', 409);
    error_log('[admin_api] ' . $e->getMessage());
    fail('Database error. Check the PHP error log.', 500);
}
