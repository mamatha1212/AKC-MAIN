<?php
include 'includes/db_connect.php';
include 'includes/auth_check.php';

// Strict Security Guard: Only active Super Admin or the active Admin assigned to Testings can delete reports/files
if (($_SESSION['role'] ?? '') === 'admin') {
    $adminPerms = $_SESSION['permissions'] ?? [];
    if (!in_array('testings', $adminPerms)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: You are not assigned to Testings. Action denied.']);
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $file_id = $_POST['file_id'] ?? '';

    if (!$file_id) {
        echo json_encode(['success' => false, 'message' => 'Missing file ID']);
        exit();
    }

    try {
        // 1. Get file details to delete from disk
        $stmt = $conn->prepare("SELECT file_path FROM booking_files WHERE id = :id");
        $stmt->execute(['id' => $file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            $full_path = $file['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }

            // 2. Delete record from database
            $delete = $conn->prepare("DELETE FROM booking_files WHERE id = :id");
            $delete->execute(['id' => $file_id]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found in database']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
