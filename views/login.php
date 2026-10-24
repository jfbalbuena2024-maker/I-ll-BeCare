<?php

declare(strict_types=1);


header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| ROLE REDIRECTS
|--------------------------------------------------------------------------
*/
const REDIRECTS = [
    'client'   => 'client/client_dashboard.php',
    'provider' => 'provider/provider_dashboard.php',
    'admin'    => 'admin/admin_dashboard.php',
];

/*
|--------------------------------------------------------------------------
| LOGIN SECURITY SETTINGS
|--------------------------------------------------------------------------
*/
const MAX_FAILS_PER_EMAIL = 10;
const MAX_FAILS_PER_IP = 20;
const LOCK_WINDOW_MINUTES = 1;

/*
|--------------------------------------------------------------------------
| DUMMY PASSWORD HASH
|--------------------------------------------------------------------------
|
| Used when the email does not exist.
| This helps reduce timing differences between:
|   - existing account
|   - non-existing account
|--------------------------------------------------------------------------
*/
const DUMMY_HASH =
    '$2y$10$Ot.B0s78YyfWEpoDrNUZqOuOC7KZ5k8n1XoXiikD7pxOJX5Hl1Cuq';

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/
function respond(int $code, array $body): never
{
    http_response_code($code);

    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| ONLY POST REQUESTS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
require __DIR__ . '/../config/connection.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    respond(500, [
        'ok' => false,
        'message' => 'Database connection is not available.'
    ]);
}

/*
|--------------------------------------------------------------------------
| SESSION SECURITY
|--------------------------------------------------------------------------
*/
$https = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

/*
|--------------------------------------------------------------------------
| ACTIVITY LOG
|--------------------------------------------------------------------------
*/
function log_event(
    mysqli $conn,
    ?int $user_id,
    string $action,
    string $details,
    string $outcome
): void {
    try {

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $conn->prepare(
            'INSERT INTO activity_logs
                (user_id, action, details, ip_address, outcome)
             VALUES
                (?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'issss',
            $user_id,
            $action,
            $details,
            $ip,
            $outcome
        );

        $stmt->execute();
        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        /*
         * Logging failure should not prevent login.
         */
        error_log(
            'Login activity log failed: ' .
            $e->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| COUNT RECENT FAILED LOGINS
|--------------------------------------------------------------------------
*/
function recent_fails(
    mysqli $conn,
    string $column,
    string $value
): int {

    /*
     * Only these columns are allowed.
     * This prevents the column name from becoming
     * an SQL injection point.
     */
    $allowedColumns = [
        'details',
        'ip_address'
    ];

    if (!in_array($column, $allowedColumns, true)) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*)
        FROM activity_logs
        WHERE action = 'login_failed'
          AND outcome = 'failed'
          AND {$column} = ?
          AND created_at > (
              NOW() - INTERVAL " .
              LOCK_WINDOW_MINUTES .
              " MINUTE
          )
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param('s', $value);

    $stmt->execute();

    $stmt->bind_result($count);

    $stmt->fetch();

    $stmt->close();

    return (int) $count;
}

/*
|--------------------------------------------------------------------------
| READ FORM INPUT
|--------------------------------------------------------------------------
*/
$email = strtolower(
    trim((string) ($_POST['email'] ?? ''))
);

$password = (string) (
    $_POST['password'] ?? ''
);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

/*
|--------------------------------------------------------------------------
| VALIDATE INPUT
|--------------------------------------------------------------------------
*/
$errors = [];

/*
 * Database email field is VARCHAR(150).
 */
if (
    $email === '' ||
    mb_strlen($email) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    $errors['email'] =
        'Enter a valid email address.';
}

/*
 * bcrypt only safely handles passwords up to 72 bytes.
 */
if (
    $password === '' ||
    strlen($password) > 72
) {
    $errors['password'] =
        'Enter your password.';
}

if ($errors) {

    respond(422, [
        'ok' => false,
        'message' =>
            'Please correct the highlighted fields.',
        'errors' => $errors
    ]);
}

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/
try {

    /*
    |--------------------------------------------------------------------------
    | 1. CHECK FAILED LOGIN LIMITS
    |--------------------------------------------------------------------------
    */

    /*
     * Email is stored in activity_logs.details as:
     *
     * email:example@gmail.com
     */
    $emailKey = 'email:' . $email;

    $emailFailures = recent_fails(
        $conn,
        'details',
        $emailKey
    );

    $ipFailures = 0;

    if ($ip !== '') {
        $ipFailures = recent_fails(
            $conn,
            'ip_address',
            $ip
        );
    }

    if (
        $emailFailures >= MAX_FAILS_PER_EMAIL ||
        $ipFailures >= MAX_FAILS_PER_IP
    ) {

        respond(429, [
            'ok' => false,
            'message' =>
                'Too many failed attempts. Please wait ' .
                LOCK_WINDOW_MINUTES .
                ' minutes and try again.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. FIND USER
    |--------------------------------------------------------------------------
    |
    | The database does NOT have full_name.
    |
    | It has:
    | first_name
    | middle_name
    | last_name
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        'SELECT
            user_id,
            first_name,
            middle_name,
            last_name,
            email,
            password_hash,
            role,
            status
         FROM users
         WHERE email = ?
         LIMIT 1'
    );

    $stmt->bind_param(
        's',
        $email
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | 3. VERIFY PASSWORD
    |--------------------------------------------------------------------------
    |
    | password_verify() still runs when the user does
    | not exist by using DUMMY_HASH.
    |--------------------------------------------------------------------------
    */

    $storedHash =
        $user['password_hash'] ?? DUMMY_HASH;

    $valid = password_verify(
        $password,
        $storedHash
    );

    /*
    |--------------------------------------------------------------------------
    | 4. INVALID LOGIN
    |--------------------------------------------------------------------------
    */

    if (!$user || !$valid) {

        log_event(
            $conn,
            $user
                ? (int) $user['user_id']
                : null,
            'login_failed',
            $emailKey,
            'failed'
        );

        respond(401, [
            'ok' => false,
            'message' =>
                'Incorrect email or password.'
        ]);
    }

    $uid = (int) $user['user_id'];

    /*
    |--------------------------------------------------------------------------
    | 5. CHECK ACCOUNT STATUS
    |--------------------------------------------------------------------------
    |
    | users.status:
    |   active
    |   blocked
    |--------------------------------------------------------------------------
    */

    if ($user['status'] === 'blocked') {

        log_event(
            $conn,
            $uid,
            'login_blocked',
            'blocked account',
            'failed'
        );

        respond(403, [
            'ok' => false,
            'message' =>
                'Your account has been blocked. Please contact the administrator.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. CHECK PROVIDER
    |--------------------------------------------------------------------------
    |
    | Only users whose role is "provider"
    | need a providers record.
    |--------------------------------------------------------------------------
    */

    $providerId = null;
    $verification = null;

    if ($user['role'] === 'provider') {

        $stmt = $conn->prepare(
            'SELECT
                provider_id,
                verification_status
             FROM providers
             WHERE user_id = ?
             LIMIT 1'
        );

        $stmt->bind_param(
            'i',
            $uid
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $provider = $result->fetch_assoc();

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | PROVIDER PROFILE DOES NOT EXIST
        |--------------------------------------------------------------------------
        */

        if (!$provider) {

            log_event(
                $conn,
                $uid,
                'login_provider_missing',
                'provider profile not found',
                'failed'
            );

            respond(403, [
                'ok' => false,
                'message' =>
                    'Provider profile not found. Please contact the administrator.'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STORE PROVIDER ID
        |--------------------------------------------------------------------------
        */

        $providerId =
            (int) $provider['provider_id'];

        $verification =
            $provider['verification_status'];

        /*
        |--------------------------------------------------------------------------
        | SUSPENDED PROVIDER
        |--------------------------------------------------------------------------
        */

        if ($verification === 'suspended') {

            log_event(
                $conn,
                $uid,
                'login_suspended',
                'suspended provider',
                'failed'
            );

            respond(403, [
                'ok' => false,
                'message' =>
                    'Your Provider account has been suspended. Please contact the administrator.'
            ]);
        }

        /*
         * pending and rejected providers are allowed
         * to log in so they can see their verification status.
         */
    }

    /*
    |--------------------------------------------------------------------------
    | 7. REGENERATE SESSION ID
    |--------------------------------------------------------------------------
    |
    | Prevents session fixation.
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    /*
    |--------------------------------------------------------------------------
    | 8. BUILD FULL NAME
    |--------------------------------------------------------------------------
    |
    | middle_name is nullable in the database.
    |--------------------------------------------------------------------------
    */

    $full_name = trim(
        implode(
            ' ',
            array_filter(
                [
                    $user['first_name'],
                    $user['middle_name'],
                    $user['last_name']
                ],
                fn($value) =>
                    $value !== null &&
                    $value !== ''
            )
        )
    );

    /*
    |--------------------------------------------------------------------------
    | 9. CREATE SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION = [
        'user_id' =>
            $uid,

        'provider_id' =>
            $providerId,

        'first_name' =>
            $user['first_name'],

        'full_name' =>
            $full_name,

        'email' =>
            $user['email'],

        'role' =>
            $user['role'],

        'verification' =>
            $verification,

        'logged_in_at' =>
            time(),

        'last_activity' =>
            time(),
    ];

    /*
    |--------------------------------------------------------------------------
    | 10. PASSWORD REHASH
    |--------------------------------------------------------------------------
    */

    if (
        password_needs_rehash(
            $user['password_hash'],
            PASSWORD_DEFAULT
        )
    ) {

        $newHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            'UPDATE users
             SET password_hash = ?
             WHERE user_id = ?'
        );

        $stmt->bind_param(
            'si',
            $newHash,
            $uid
        );

        $stmt->execute();

        $stmt->close();
    }

    /*
    |--------------------------------------------------------------------------
    | 11. UPDATE LAST LOGIN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        'UPDATE users
         SET last_login = NOW()
         WHERE user_id = ?'
    );

    $stmt->bind_param(
        'i',
        $uid
    );

    $stmt->execute();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | 12. LOG SUCCESSFUL LOGIN
    |--------------------------------------------------------------------------
    */

    log_event(
        $conn,
        $uid,
        'login_success',
        'role:' . $user['role'],
        'success'
    );

    /*
    |--------------------------------------------------------------------------
    | 13. REDIRECT
    |--------------------------------------------------------------------------
    */

    $redirect =
        REDIRECTS[$user['role']]
        ?? 'index.html';

    /*
    |--------------------------------------------------------------------------
    | 14. SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    respond(200, [
        'ok' => true,
        'message' =>
            'Login successful. Redirecting...',
        'redirect' =>
            $redirect
    ]);

} catch (mysqli_sql_exception $e) {

    error_log(
        'login: ' .
        $e->getMessage()
    );

    respond(500, [
        'ok' => false,
        'message' =>
            'We could not log you in. Please try again.'
    ]);
}
