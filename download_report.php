<?php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    die('Unauthorized access.');
}

$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($file_id <= 0) {
    http_response_code(400);
    die('Invalid file ID.');
}

$stmt = $conn->prepare("
    SELECT bf.*, tb.email AS booking_email
    FROM booking_files bf
    LEFT JOIN test_bookings tb ON bf.booking_id = tb.id
    WHERE bf.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    die('Report file not found.');
}

// Authorization: clients can only download files belonging to their own bookings
$role = $_SESSION['role'] ?? '';
if ($role === 'client') {
    $user_email = !empty($_SESSION['email']) ? $_SESSION['email'] : ($_SESSION['username'] ?? '');
    if (strtolower(trim($file['booking_email'] ?? '')) !== strtolower(trim($user_email))) {
        http_response_code(403);
        die('Access denied.');
    }
} elseif (!in_array($role, ['admin', 'super_admin'])) {
    http_response_code(403);
    die('Access denied.');
}

$downloadName = basename($file['file_name'] ?: ('report_' . $file_id));
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
$filePath = $file['file_path'] ?? '';

// 1. Serve base64-encoded data URI stored in database (Vercel serverless / read-only FS)
if (strpos($filePath, 'data:') === 0) {
    $commaPos = strpos($filePath, ',');
    if ($commaPos !== false) {
        $meta = substr($filePath, 5, $commaPos - 5);
        $base64Data = substr($filePath, $commaPos + 1);
        $mime = explode(';', $meta)[0] ?: ($file['file_type'] ?: 'application/octet-stream');
        $binary = base64_decode($base64Data);
        if ($binary !== false) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($downloadName) . '"');
            header('Content-Length: ' . strlen($binary));
            header('Cache-Control: private, max-age=3600');
            echo $binary;
            exit();
        }
    }
}

// 2. Fallback: Serve from disk if file exists locally
$candidates = [
    __DIR__ . '/assets/uploads/reports/' . $file['file_name'],
    $filePath,
    __DIR__ . '/' . ltrim($filePath, '/')
];

foreach ($candidates as $candidate) {
    if (!empty($candidate) && file_exists($candidate) && is_file($candidate)) {
        $mime = !empty($file['file_type']) ? $file['file_type'] : (function_exists('mime_content_type') ? mime_content_type($candidate) : 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($downloadName) . '"');
        header('Content-Length: ' . filesize($candidate));
        header('Cache-Control: private, max-age=3600');
        readfile($candidate);
        exit();
    }
}

http_response_code(404);
die('Report file not found on server.');

