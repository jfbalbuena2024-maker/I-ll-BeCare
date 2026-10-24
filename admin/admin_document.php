<?php

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$id = (int) ($_GET['id'] ?? 0);
$st = $conn->prepare('SELECT file_path, doc_type FROM provider_documents WHERE document_id=?');
$st->bind_param('i', $id);
$st->execute();
$d = $st->get_result()->fetch_assoc();
if (!$d) {
    http_response_code(404);
    exit('Not found');
}

$root = realpath(__DIR__ . '/..');
$path = $d['file_path'];
$abs = preg_match('~^([A-Za-z]:[\\\\/]|/)~', $path) ? $path : $root . DIRECTORY_SEPARATOR . $path;
$full = realpath($abs);
if (!$full || !is_file($full) || strpos($full, $root) !== 0) {
    http_response_code(404);
    exit('File missing');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($full);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    http_response_code(415);
    exit('Unsupported file');
}

$log = $conn->prepare('INSERT INTO activity_logs (user_id,action,details,ip_address,outcome) VALUES (?,?,?,?,"success")');
$uid = (int) $_SESSION['user_id'];
$act = 'document_view';
$det = 'Document #' . $id . ' (' . $d['doc_type'] . ')';
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$log->bind_param('isss', $uid, $act, $det, $ip);
$log->execute();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: inline; filename="' . $d['doc_type'] . '-' . $id . '"');
readfile($full);

