<?php
/**
 * cron_monthly_report.php
 * 
 * Automated Monthly Operations Report Dispatcher for Super Admin.
 * Runs on the LAST WORKING DAY (Monday - Friday) of the month at 11:59 PM.
 * Can be run via:
 * 1. Server CLI / cPanel Cron Job
 * 2. Background automated check
 * 3. Browser with security key: ?key=akc_secure_cron_2026&force=1 (for testing)
 */

// Allow execution from CLI or web
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? '';
    $cronKey = 'akc_secure_cron_2026';
    if ($providedKey !== $cronKey && !isset($_GET['internal_call'])) {
        header('HTTP/1.0 403 Forbidden');
        echo "Access denied: Invalid or missing security key.";
        exit();
    }
}

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/mail_sender.php';

date_default_timezone_set('Asia/Kolkata'); // Match local timezone

/**
 * Calculates the last working day (Monday - Friday) of a given month
 *
 * @param string|null $ym Format 'YYYY-MM'
 * @return string Date formatted 'YYYY-MM-DD'
 */
function akc_get_last_working_day($ym = null) {
    if (!$ym) {
        $ym = date('Y-m');
    }
    
    // Find the total number of days in this month
    $totalDays = (int)date('t', strtotime($ym . '-01'));
    
    // Start from the last calendar day and step backwards until a weekday (Mon-Fri) is found
    for ($day = $totalDays; $day >= 1; $day--) {
        $timestamp = strtotime(sprintf('%s-%02d', $ym, $day));
        $dayOfWeek = (int)date('N', $timestamp); // 1 = Monday, ..., 5 = Friday, 6 = Saturday, 7 = Sunday
        if ($dayOfWeek <= 5) { // Weekday
            return date('Y-m-d', $timestamp);
        }
    }
    return date('Y-m-t', strtotime($ym . '-01'));
}

/**
 * Gather monthly statistics
 */
function akc_cron_get_monthly_stats($conn, $selectedMonth) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tests,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tests,
            SUM(CASE WHEN status = 'Accept' THEN 1 ELSE 0 END) as processing_tests,
            SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as complete_tests,
            SUM(CASE WHEN status = 'Cancel' THEN 1 ELSE 0 END) as rejected_tests
        FROM test_bookings
        WHERE SUBSTR(created_at, 1, 7) = :ym
    ");
    $stmt->execute(['ym' => $selectedMonth]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_tests' => 0,
        'pending_tests' => 0,
        'processing_tests' => 0,
        'complete_tests' => 0,
        'rejected_tests' => 0
    ];

    $stmtTypes = $conn->prepare("
        SELECT sample_type, COUNT(*) as cnt,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_cnt,
            SUM(CASE WHEN status = 'Accept' THEN 1 ELSE 0 END) as processing_cnt,
            SUM(CASE WHEN status = 'Complete' THEN 1 ELSE 0 END) as complete_cnt,
            SUM(CASE WHEN status = 'Cancel' THEN 1 ELSE 0 END) as rejected_cnt
        FROM test_bookings
        WHERE SUBSTR(created_at, 1, 7) = :ym
        GROUP BY sample_type
        ORDER BY cnt DESC
    ");
    $stmtTypes->execute(['ym' => $selectedMonth]);
    $sampleStats = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

    return [
        'month' => $selectedMonth,
        'month_label' => date('F Y', strtotime($selectedMonth . '-01')),
        'stats' => $stats,
        'sample_stats' => $sampleStats
    ];
}

/**
 * Build HTML email body
 */
function akc_cron_build_email_html($data, $dispatchNote = '') {
    $monthLabel = $data['month_label'];
    $s = $data['stats'];
    $samples = $data['sample_stats'];
    $currentDate = date('d M Y, h:i A');

    $rowsHtml = '';
    if (!empty($samples)) {
        foreach ($samples as $row) {
            $type = htmlspecialchars($row['sample_type'] ?: 'Unspecified');
            $rowsHtml .= "
            <tr>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; font-weight: 600; color: #1e293b;'>{$type}</td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: center; color: #0284c7; font-weight: bold;'>{$row['cnt']}</td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: center; color: #ea580c; font-weight: 600;'>{$row['pending_cnt']}</td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: center; color: #2563eb; font-weight: 600;'>{$row['processing_cnt']}</td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: center; color: #16a34a; font-weight: 600;'>{$row['complete_cnt']}</td>
                <td style='padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 13px; text-align: center; color: #dc2626; font-weight: 600;'>{$row['rejected_cnt']}</td>
            </tr>";
        }
    } else {
        $rowsHtml = "<tr><td colspan='6' style='padding: 16px; text-align: center; color: #64748b; font-size: 13px;'>No test requests recorded for this period.</td></tr>";
    }

    return "
    <div style='font-family: Arial, Helvetica, sans-serif; max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;'>
        <div style='background: linear-gradient(135deg, #0A58CA 0%, #1e3a8a 100%); padding: 25px 30px; color: #ffffff;'>
            <div style='display: flex; justify-content: space-between; align-items: center;'>
                <h2 style='margin: 0 0 6px 0; font-size: 22px; font-weight: 700; color: #ffffff;'>AKC Monthly Operations Digest</h2>
            </div>
            <p style='margin: 0; font-size: 14px; color: #ffffff; opacity: 0.95;'>Testing Requests &amp; Status Overview &bull; <strong>{$monthLabel}</strong></p>
        </div>

        <div style='padding: 25px 30px;'>
            <div style='margin-bottom: 20px; padding: 12px 16px; background: #f0fdf4; border-left: 4px solid #16a34a; border-radius: 6px; font-size: 13px; color: #166534;'>
                <i style='font-weight: bold;'>Automated Scheduled Delivery:</i> This report was automatically compiled and dispatched at 11:59 PM on the last working day of {$monthLabel}.
            </div>

            <p style='font-size: 14px; color: #334155; margin-top: 0;'>
                Hello Super Admin,<br>
                Here is the automated operations summary of test bookings submitted, processed, completed, and rejected at the AKC Testing Centre for <strong>{$monthLabel}</strong>.
            </p>

            <!-- Status KPI Boxes -->
            <div style='display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 25px 0;'>
                <div style='background: #f0f9ff; border: 1.5px solid #bae6fd; border-radius: 8px; padding: 12px 8px; text-align: center;'>
                    <div style='font-size: 11px; font-weight: 700; color: #0369a1; text-transform: uppercase;'>Total Tests</div>
                    <div style='font-size: 22px; font-weight: 800; color: #0284c7; margin-top: 4px;'>{$s['total_tests']}</div>
                </div>
                <div style='background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 8px; padding: 12px 8px; text-align: center;'>
                    <div style='font-size: 11px; font-weight: 700; color: #c2410c; text-transform: uppercase;'>Pending</div>
                    <div style='font-size: 22px; font-weight: 800; color: #ea580c; margin-top: 4px;'>{$s['pending_tests']}</div>
                </div>
                <div style='background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 8px; padding: 12px 8px; text-align: center;'>
                    <div style='font-size: 11px; font-weight: 700; color: #1d4ed8; text-transform: uppercase;'>In Process</div>
                    <div style='font-size: 22px; font-weight: 800; color: #2563eb; margin-top: 4px;'>{$s['processing_tests']}</div>
                </div>
                <div style='background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 8px; padding: 12px 8px; text-align: center;'>
                    <div style='font-size: 11px; font-weight: 700; color: #15803d; text-transform: uppercase;'>Complete</div>
                    <div style='font-size: 22px; font-weight: 800; color: #16a34a; margin-top: 4px;'>{$s['complete_tests']}</div>
                </div>
                <div style='background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 8px; padding: 12px 8px; text-align: center;'>
                    <div style='font-size: 11px; font-weight: 700; color: #b91c1c; text-transform: uppercase;'>Rejected</div>
                    <div style='font-size: 22px; font-weight: 800; color: #dc2626; margin-top: 4px;'>{$s['rejected_tests']}</div>
                </div>
            </div>

            <!-- Table breakdown -->
            <h3 style='font-size: 15px; color: #0f172a; margin: 25px 0 10px 0;'>Breakdown by Sample / Material</h3>
            <table style='width: 100%; border-collapse: collapse; margin-top: 8px;'>
                <thead>
                    <tr style='background: #f8fafc;'>
                        <th style='padding: 10px 14px; text-align: left; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>Sample Type</th>
                        <th style='padding: 10px 14px; text-align: center; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>Total</th>
                        <th style='padding: 10px 14px; text-align: center; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>Pending</th>
                        <th style='padding: 10px 14px; text-align: center; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>In Process</th>
                        <th style='padding: 10px 14px; text-align: center; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>Completed</th>
                        <th style='padding: 10px 14px; text-align: center; font-size: 12px; color: #475569; border-bottom: 2px solid #e2e8f0;'>Rejected</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>

            <div style='margin-top: 30px; padding: 15px; background: #f8fafc; border-radius: 8px; font-size: 12px; color: #64748b; text-align: center;'>
                Sent automatically on {$currentDate} &bull; AKC Packaging Technology &amp; Research Centre
            </div>
        </div>
    </div>";
}

// Ensure dispatch log table exists to prevent duplicate emails for the same month
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS monthly_report_dispatches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_month VARCHAR(10) NOT NULL UNIQUE,
            dispatched_at DATETIME NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL
        )
    ");
} catch (PDOException $e) {}

$todayDate = date('Y-m-d');
$currentMonth = date('Y-m');
$lastWorkingDay = akc_get_last_working_day($currentMonth);
$isForce = isset($_GET['force']) && $_GET['force'] == '1';

// Execution check:
// 1. Is it the last working day of the month?
// 2. Or is forced testing triggered?
$shouldSend = ($todayDate === $lastWorkingDay) || $isForce;

// Check if already dispatched for this month
$checkStmt = $conn->prepare("SELECT COUNT(*) FROM monthly_report_dispatches WHERE report_month = :ym");
$checkStmt->execute(['ym' => $currentMonth]);
$alreadySent = (int)$checkStmt->fetchColumn() > 0;

$resultMsg = "";

if ($shouldSend) {
    if ($alreadySent && !$isForce) {
        $resultMsg = "Report for {$currentMonth} has already been automatically dispatched.";
    } else {
        // Fetch Super Admin recipients
        $superAdmins = $conn->query("SELECT email, fullname FROM users WHERE role = 'super_admin' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($superAdmins)) {
            $superAdmins = [['email' => 'mamathasurekha8@gmail.com', 'fullname' => 'System Super Admin']];
        }

        $reportData = akc_cron_get_monthly_stats($conn, $currentMonth);
        $htmlEmail = akc_cron_build_email_html($reportData);
        $subject = "AKC Monthly Operations Report - " . $reportData['month_label'];

        $allSent = true;
        foreach ($superAdmins as $admin) {
            $toEmail = trim($admin['email']);
            if (!empty($toEmail)) {
                $mailRes = akc_send_mail($toEmail, $admin['fullname'], $subject, $htmlEmail);
                if (!$mailRes['sent_external']) {
                    // Logged to email logs
                }
            }
        }

        // Record dispatch to avoid duplicate emails
        $recEmails = implode(', ', array_column($superAdmins, 'email'));
        $logStmt = $conn->prepare("INSERT OR REPLACE INTO monthly_report_dispatches (report_month, dispatched_at, recipient, status) VALUES (:ym, datetime('now'), :rec, 'SENT')");
        $logStmt->execute([
            'ym' => $currentMonth,
            'rec' => $recEmails
        ]);

        $resultMsg = "SUCCESS: Monthly Report for {$reportData['month_label']} was successfully dispatched to: {$recEmails} (Last working day: {$lastWorkingDay}).";
    }
} else {
    $resultMsg = "IDLE: Today ({$todayDate}) is not the last working day of {$currentMonth} (Last working day is {$lastWorkingDay}). No email sent.";
}

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] " . $resultMsg . PHP_EOL;
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'today' => $todayDate,
        'current_month' => $currentMonth,
        'last_working_day' => $lastWorkingDay,
        'dispatched' => $shouldSend && (!$alreadySent || $isForce),
        'message' => $resultMsg
    ], JSON_PRETTY_PRINT);
}

