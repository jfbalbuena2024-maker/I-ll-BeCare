<?php
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/../config/connection.php';


function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($conn) || !$conn) {
    respond([
        'success' => false,
        'message' => 'Database connection failed.'
    ], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond([
        'success' => false,
        'message' => 'Invalid request method.'
    ], 405);
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond([
        'success' => false,
        'available' => false,
        'message' => 'Please enter a valid email address.'
    ], 422);
}

try {
    $stmt = $conn->prepare(
        'SELECT user_id FROM users WHERE email = ? LIMIT 1'
    );

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    respond([
        'success' => true,
        'available' => !$exists,
        'message' => $exists
            ? 'This email cannot be used. Please try a different email address, or log in if you already have an account.'
            : 'Email is available.'
    ]);

} catch (Throwable $e) {
    error_log('Email check failed: ' . $e->getMessage());

    respond([
        'success' => false,
        'message' => 'Unable to check email right now.'
    ], 500);
}
