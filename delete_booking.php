<?php
include 'includes/auth_check.php';
include 'includes/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $booking_id = $_POST['id'] ?? '';

    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing ID']);
        exit();
    }

    try {
        // 1. Get record data before deletion for activity logging
        $stmt = $conn->prepare("SELECT * FROM test_bookings WHERE id = :id");
        $stmt->execute(['id' => $booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        $fileStmt = $conn->prepare("SELECT file_name FROM booking_files WHERE booking_id = :id");
        $fileStmt->execute(['id' => $booking_id]);
        $files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Delete the record (booking_files will be deleted automatically due to ON DELETE CASCADE)
        $deleteStmt = $conn->prepare("DELETE FROM test_bookings WHERE id = :id");
        if ($deleteStmt->execute(['id' => $booking_id])) {
            // Log DELETE activity in admin_activity_logs
            if (isset($_SESSION['user_id']) && $booking) {
                try {
                    $logData = $booking;
                    unset($logData['sample_photo']); // remove image to keep log compact
                    $stmtLog = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, module, record_id, old_data, new_data, created_at) VALUES (:admin_id, 'DELETE', 'BOOKINGS', :rec_id, :old_data, NULL, datetime('now'))");
                    $stmtLog->execute([
                        'admin_id' => $_SESSION['user_id'],
                        'rec_id' => $booking_id,
                        'old_data' => json_encode($logData)
                    ]);
                } catch (Throwable $e) {}
            }
            
            // 3. Physically delete the files
            if ($booking && $booking['sample_photo']) {
                @unlink("assets/uploads/" . $booking['sample_photo']);
            }
            foreach ($files as $file) {
                @unlink("assets/uploads/reports/" . $file['file_name']);
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete from database']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
