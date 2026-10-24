<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/connection.php'; // provides $conn (mysqli)
require_once __DIR__ . '/../config/account_welcome.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

const MAX_FILE_BYTES = 5 * 1024 * 1024;
const MAX_CERTS      = 5;
const CLEARANCE_SERVICES = ['Childcare', 'Senior companionship']; // names from service_categories

function respond(bool $ok, string $message, int $code = 200, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

function field(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

/** Validates one uploaded file. Returns null if optional and empty. */
function checkUpload(array $f, bool $required, string $label): ?array
{
    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            respond(false, "$label is required.", 422);
        }
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        respond(false, "$label could not be uploaded (error code {$f['error']}).", 422);
    }
    if ($f['size'] > MAX_FILE_BYTES) {
        respond(false, "$label must be 5 MB or smaller.", 422);
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        respond(false, "$label upload was rejected.", 422);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($allowed[$mime])) {
        respond(false, "$label must be a JPG, PNG or PDF file.", 422);
    }
    return ['tmp' => $f['tmp_name'], 'ext' => $allowed[$mime]];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', 405);
}

if (($_POST['agree_terms'] ?? '') !== '1') {
    respond(
        false,
        'You must agree to the Terms and Conditions before creating an account.',
        422
    );
}

/* ---------- 1. Read and validate text fields ---------- */
$first  = field('first_name');
$middle = field('middle_name');
$last   = field('last_name');
$email  = strtolower(field('email'));
$mobile = field('mobile_number');
$municipality = field('municipality');
$barangay = field('barangay');
$validMunicipalities = [
    'Enrique Villanueva',
    'Larena',
    'Lazi',
    'Maria',
    'San Juan',
    'Siquijor',
];
$loc = $barangay . ', ' . $municipality . ', Siquijor';
$pw     = (string)($_POST['password'] ?? '');
$pw2    = (string)($_POST['confirm_password'] ?? '');

$nameRx = "/^[\p{L}\p{M}\s.'\-]{1,50}$/u";
if (!preg_match($nameRx, $first) || !preg_match($nameRx, $last)) {
    respond(false, 'Enter a valid first and last name.', 422);
}
if ($middle !== '' && !preg_match($nameRx, $middle)) {
    respond(false, 'Enter a valid middle name or leave it blank.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    respond(false, 'Enter a valid email address.', 422);
}
if (!preg_match('/^(09|\+639)\d{9}$/', $mobile)) {
    respond(false, 'Enter a valid mobile number (e.g. 09123456789).', 422);
}
if (
    !in_array($municipality, $validMunicipalities, true) ||
    $barangay === '' ||
    mb_strlen($barangay) > 100 ||
    !preg_match('/^[\p{L}\p{M}\s.\'-]+$/u', $barangay) ||
    mb_strlen($loc) > 150
) {
    respond(false, 'Select a valid municipality and barangay.', 422);
}
if (strlen($pw) < 8 || strlen($pw) > 72) {
    respond(false, 'Password must be 8 to 72 characters.', 422);
}
if ($pw !== $pw2) {
    respond(false, 'Passwords do not match.', 422);
}

/* ---------- 2. Services and rates (validated against the database) ---------- */
$cats = [];
$res = $conn->query("SELECT category_id, name FROM service_categories WHERE is_active = 1");
while ($row = $res->fetch_assoc()) {
    $cats[(int)$row['category_id']] = $row['name'];
}

$picked = isset($_POST['services']) && is_array($_POST['services'])
    ? array_values(array_unique(array_map('intval', $_POST['services'])))
    : [];
if (!$picked) {
    respond(false, 'Select at least one service you offer.', 422);
}

$rates = [];
$needsClearance = false;
foreach ($picked as $cid) {
    if (!isset($cats[$cid])) {
        respond(false, 'One of the selected services is not available.', 422);
    }
    $rate = $_POST['rate'][$cid] ?? '';
    if (!is_numeric($rate) || (float)$rate <= 0 || (float)$rate > 99999.99) {
        respond(false, 'Enter a valid hourly rate for ' . $cats[$cid] . '.', 422);
    }
    $rates[$cid] = round((float)$rate, 2);
    if (in_array($cats[$cid], CLEARANCE_SERVICES, true)) {
        $needsClearance = true;
    }
}

/* ---------- 3. Files (server-side rule: clearance required for childcare/senior care) ---------- */
$clearanceType = field('clearance_type');
$govId = checkUpload($_FILES['government_id'] ?? [], true, 'Government ID');

$clearance = checkUpload($_FILES['clearance_file'] ?? [], $needsClearance, 'Clearance file');
if ($needsClearance && !in_array($clearanceType, ['nbi_clearance', 'police_clearance'], true)) {
    respond(false, 'Select NBI or police clearance for the clearance you are uploading.', 422);
}
if ($clearance && !in_array($clearanceType, ['nbi_clearance', 'police_clearance'], true)) {
    respond(false, 'Select the clearance type for the uploaded clearance.', 422);
}

$certs = [];
if (isset($_FILES['certifications']['name']) && is_array($_FILES['certifications']['name'])) {
    $count = count($_FILES['certifications']['name']);
    for ($i = 0; $i < $count; $i++) {
        $one = [
            'name'     => $_FILES['certifications']['name'][$i],
            'tmp_name' => $_FILES['certifications']['tmp_name'][$i],
            'error'    => $_FILES['certifications']['error'][$i],
            'size'     => $_FILES['certifications']['size'][$i],
        ];
        $checked = checkUpload($one, false, 'Certification');
        if ($checked) {
            $certs[] = $checked;
        }
    }
    if (count($certs) > MAX_CERTS) {
        respond(false, 'You can upload up to 5 certifications.', 422);
    }
}

/* ---------- 4. Email must be unique ---------- */
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    respond(false, 'That email is already registered.', 409);
}
$stmt->close();

/* ---------- 5. Prepare private upload folder ---------- */
$uploadDir = __DIR__ . '/../uploads/provider_docs';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    respond(false, 'Server could not prepare the upload folder.', 500);
}
$htaccess = $uploadDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Require all denied\n"); // Apache 2.4 (XAMPP)
}

/* ---------- 6. Save everything in one transaction ---------- */
$savedFiles = [];
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $conn->begin_transaction();

    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO users (first_name, middle_name, last_name, email, password_hash, mobile_number, role)
         VALUES (?, ?, ?, ?, ?, ?, 'provider')"
    );
    $stmt->bind_param('ssssss', $first, $middle, $last, $email, $hash, $mobile);
    $stmt->execute();
    $userId = (int)$conn->insert_id;
    $stmt->close();

    // verification_status defaults to 'pending' (not listed to Clients until approved)
    $stmt = $conn->prepare("INSERT INTO providers (user_id, location) VALUES (?, ?)");
    $stmt->bind_param('is', $userId, $loc);
    $stmt->execute();
    $providerId = (int)$conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare(
        "INSERT INTO provider_services (provider_id, category_id, hourly_rate) VALUES (?, ?, ?)"
    );
    foreach ($rates as $cid => $rate) {
        $stmt->bind_param('iid', $providerId, $cid, $rate);
        $stmt->execute();
    }
    $stmt->close();

    $docStmt = $conn->prepare(
        "INSERT INTO provider_documents (provider_id, doc_type, file_path) VALUES (?, ?, ?)"
    );
    $saveDoc = function (array $file, string $docType) use ($uploadDir, $providerId, $docStmt, &$savedFiles): void {
        $name = 'p' . $providerId . '_' . $docType . '_' . bin2hex(random_bytes(8)) . '.' . $file['ext'];
        $dest = $uploadDir . '/' . $name;
        if (!move_uploaded_file($file['tmp'], $dest)) {
            throw new RuntimeException('File save failed.');
        }
        $savedFiles[] = $dest;
        $relative = 'uploads/provider_docs/' . $name;
        $docStmt->bind_param('iss', $providerId, $docType, $relative);
        $docStmt->execute();
    };

    $saveDoc($govId, 'government_id');
    if ($clearance) {
        $saveDoc($clearance, $clearanceType);
    }
    foreach ($certs as $c) {
        $saveDoc($c, 'certification');
    }
    $docStmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($savedFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
        respond(false, 'That email is already registered.', 409);
    }
    error_log('register_provider: ' . $e->getMessage());
    respond(false, 'Registration failed. Please try again.', 500);
}

/* ---------- 7. Activity log (non-critical) ---------- */
try {
    $action = 'register_provider';
    $details = 'Provider account created (pending verification)';
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, action, details, ip_address, outcome)
         VALUES (?, ?, ?, ?, 'success')"
    );
    $stmt->bind_param('isss', $userId, $action, $details, $ip);
    $stmt->execute();
} catch (Throwable $e) { /* ignore logging failure */
}


$emailSent = sendAccountWelcomeEmail(
    $email,
    $first,
    'provider'
);

if (!$emailSent) {
    error_log(
        "Welcome email could not be sent to Provider user ID: "
        . $userId
    );
}

respond(
    true,
    $emailSent
        ? 'Account created successfully! A welcome email has been sent. Your documents are pending Administrator review.'
        : 'Account created successfully, but the welcome email could not be sent. Your documents are pending Administrator review.',
    201,
    [
        'redirect' => '../index.html',
        'email_sent' => $emailSent
    ]
);
