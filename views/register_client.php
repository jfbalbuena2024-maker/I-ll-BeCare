<?php
header('Content-Type: application/json; charset=utf-8');



require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../config/account_welcome.php';

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($conn) || !$conn) {
    respond([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'success' => false,
        'message' => 'Invalid request method.'
    ], 405);
}

// ========================================
// GET AND CLEAN FORM DATA
// ========================================

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$mobileNumber = preg_replace(
    '/\D/',
    '',
    trim($_POST['mobile_number'] ?? '')
);
$address = trim($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$agreeTerms = $_POST['agree_terms'] ?? '';

// ========================================
// SERVER-SIDE VALIDATION
// ========================================

if ($agreeTerms !== '1') {
    respond([
        'success' => false,
        'message' => 'You must agree to the Terms and Conditions before creating an account.'
    ], 422);
}

// ========================================
// SERVER-SIDE VALIDATION
// ========================================

if (
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $mobileNumber === '' ||
    $address === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    respond([
        'success' => false,
        'message' => 'Please complete all required fields.'
    ], 422);
}



$firstNameLength = preg_match_all('/./us', $firstName);
$lastNameLength = preg_match_all('/./us', $lastName);
$middleNameLength = preg_match_all('/./us', $middleName);

if (
    $firstNameLength === false ||
    $lastNameLength === false ||
    $middleNameLength === false ||
    $firstNameLength > 50 ||
    $lastNameLength > 50 ||
    $middleNameLength > 50
) {
    respond([
        'success' => false,
        'message' => 'Names must not exceed 50 characters.'
    ], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    respond([
        'success' => false,
        'field' => 'email',
        'message' => 'Please enter a valid email address.'
    ], 422);
}

if (strlen($mobileNumber) < 10 || strlen($mobileNumber) > 13) {
    respond([
        'success' => false,
        'message' => 'Mobile number must contain 10 to 13 digits.'
    ], 422);
}

if (strlen($address) > 255) {
    respond([
        'success' => false,
        'message' => 'Home address must not exceed 255 characters.'
    ], 422);
}



if (strlen($password) < 8) {
    respond([
        'success' => false,
        'message' => 'Password must contain at least 8 characters.'
    ], 422);
}

if (
    !preg_match('/[A-Za-z]/', $password) ||
    !preg_match('/[0-9]/', $password)
) {
    respond([
        'success' => false,
        'message' => 'Password must contain at least one letter and one number.'
    ], 422);
}

if ($password !== $confirmPassword) {
    respond([
        'success' => false,
        'message' => 'Passwords do not match.'
    ], 422);
}

// ========================================
// CHECK EMAIL AND CREATE CLIENT ACCOUNT
// ========================================

try {
    // Check if this email is already registered.
    $check = $conn->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1'
    );

    $check->bind_param('s', $email);
    $check->execute();
    $check->store_result();

    $emailExists = $check->num_rows > 0;
    $check->close();

    if ($emailExists) {
        respond([
            'success' => false,
            'field' => 'email',
            'message' => 'This email cannot be used. Please try a different email address, or log in if you already have an account.'
        ], 409);
    }

    // Never save a plain-text password.
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if (!is_string($passwordHash)) {
        throw new RuntimeException('Password hashing failed.');
    }

    // Save the user and client profile as one transaction.
    $conn->begin_transaction();

    try {
        // Create the login account.
        $userStmt = $conn->prepare(
            'INSERT INTO users
                (first_name, middle_name, last_name, email,
                 password_hash, mobile_number, role)
             VALUES (?, ?, ?, ?, ?, ?, "client")'
        );

        $userStmt->bind_param(
            'ssssss',
            $firstName,
            $middleName,
            $lastName,
            $email,
            $passwordHash,
            $mobileNumber
        );

        $userStmt->execute();
        $userId = $conn->insert_id;
        $userStmt->close();

        // Save the client's profile details.
        $clientStmt = $conn->prepare(
            'INSERT INTO clients (user_id, address) VALUES (?, ?)'
        );

        $clientStmt->bind_param('is', $userId, $address);

        $clientStmt->execute();
        $clientStmt->close();

        // Both inserts succeeded.
        $conn->commit();

        $emailSent = sendAccountWelcomeEmail(
            $email,
            $firstName,
            'client'
        );

        if (!$emailSent) {
            error_log(
                "Welcome email could not be sent to Client user ID: "
                    . $userId
            );
        }

        respond([
            'success' => true,
            'message' => $emailSent
                ? 'Your client account was created successfully. A welcome email has been sent.'
                : 'Your client account was created successfully, but the welcome email could not be sent.'
        ], 201);
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
} catch (mysqli_sql_exception $e) {
    error_log('Client registration database error: ' . $e->getMessage());

    // The database UNIQUE constraint protects against duplicate emails
    // even if two registrations happen at nearly the same time.
    if ((int) $e->getCode() === 1062) {
        respond([
            'success' => false,
            'field' => 'email',
            'message' => 'This email is already registered. Please use another email.'
        ], 409);
    }

    respond([
        'success' => false,
        'message' => 'Unable to create your account. Please try again.'
    ], 500);
} catch (Throwable $e) {
    error_log('Client registration failed: ' . $e->getMessage());

    respond([
        'success' => false,
        'message' => 'Something went wrong while creating your account.'
    ], 500);
}
