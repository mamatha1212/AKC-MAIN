<?php
include '../includes/super_admin_auth_check.php';
include '../includes/db_connect.php';

// Pagination parameters
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch total records for pagination
$count_query = "SELECT COUNT(*) FROM admin_activity_logs";
$stmtCount = $conn->prepare($count_query);
$stmtCount->execute();
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch logs
$logs = [];
try {
    $stmt = $conn->prepare("SELECT a.*, u.username, u.fullname, u.permissions FROM admin_activity_logs a JOIN users u ON a.admin_id = u.id ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

function parseJsonCleanly($raw)
{
    if (empty($raw)) return null;
    if (is_array($raw)) return $raw;
    if (!is_string($raw)) return null;

    $val = trim($raw);

    // If it starts and ends with quotes, it might be a JSON-encoded string
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $decoded = json_decode($val, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try replacing all double-backslashes with single backslashes or removing backslash before quotes
        // Handles {\\"status\\": \\"Accept\\"} -> {"status": "Accept"}
        $cleaned = str_replace(['\\\\"', '\\\\', '\"'], ['"', '\\', '"'], $val);
        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try stripslashes
        $stripped = stripslashes($val);
        $decoded = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If it starts with { and ends with }, regex extract key-value pairs as a bulletproof fallback!
        if (str_starts_with($val, '{') && str_ends_with($val, '}')) {
            // Match "key": "value" or \"key\": \"value\"
            if (preg_match_all('/[\\\\"]*([a-zA-Z0-9_]+)[\\\\"]*\s*:\s*(?:[\\\\"]*([^\\\\",{}]+)[\\\\"]*|null)/i', $val, $matches, PREG_SET_ORDER)) {
                $parsed = [];
                foreach ($matches as $m) {
                    $k = trim($m[1]);
                    $v = isset($m[2]) ? trim($m[2]) : null;
                    if ($v === 'null') $v = null;
                    $parsed[$k] = $v;
                }
                if (!empty($parsed)) {
                    return $parsed;
                }
            }
        }

        // If $val was wrapped in outer quotes, unwrap and retry
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
            continue;
        }
        break;
    }

    return null;
}

function renderDiff($old_raw, $new_raw, $action = '', $module = '', $record_id = '')
{
    $old = parseJsonCleanly($old_raw);
    $new = parseJsonCleanly($new_raw);

    $action_upper = strtoupper($action);

    // If both failed JSON parsing or are empty
    if (empty($old) && empty($new)) {
        if ($action_upper === 'DELETE') {
            return '<div class="diff-summary-badge delete"><i class="fa-solid fa-trash-can"></i> Booking #' . htmlspecialchars($record_id) . ' Deleted</div>';
        } elseif ($action_upper === 'UPDATE') {
            return '<div class="diff-summary-badge update"><i class="fa-solid fa-pen-to-square"></i> Booking #' . htmlspecialchars($record_id) . ' Updated</div>';
        } elseif ($action_upper === 'CREATE') {
            return '<div class="diff-summary-badge create"><i class="fa-solid fa-circle-plus"></i> New Booking #' . htmlspecialchars($record_id) . '</div>';
        }
        return '<div class="diff-summary-badge neutral"><i class="fa-solid fa-circle-info"></i> ' . htmlspecialchars($action . ' #' . $record_id) . '</div>';
    }

    $old = is_array($old) ? $old : [];
    $new = is_array($new) ? $new : [];

    // Construct modal payload safely
    $detailsData = !empty($new) ? $new : $old;
    $detailsJson = htmlspecialchars(json_encode($detailsData), ENT_QUOTES, 'UTF-8');

    // Case 1: CREATE (New Booking)
    if ($action_upper === 'CREATE' || (empty($old) && !empty($new))) {
        $sampleName = $new['sample_type'] ?? ($new['sample_desc'] ?? 'Test Sample');
        $html = '<div class="diff-item-compact">';
        $html .= '<span class="diff-pill create"><i class="fa-solid fa-file-circle-plus"></i> New Booking: ' . htmlspecialchars($sampleName) . '</span>';
        $html .= ' <button type="button" class="btn-view-details" onclick="openDetailsModal(\'New Booking Details (# ' . htmlspecialchars($record_id) . ')\', this)" data-details=\'' . $detailsJson . '\'><i class="fa-solid fa-eye"></i> View Details</button>';
        $html .= '</div>';
        return $html;
    }

    // Case 2: DELETE (Deleted Booking)
    if ($action_upper === 'DELETE' || (!empty($old) && empty($new))) {
        $sampleName = $old['sample_type'] ?? ($old['fullname'] ?? 'Booking #' . $record_id);
        $html = '<div class="diff-item-compact">';
        $html .= '<span class="diff-pill delete"><i class="fa-solid fa-trash-can"></i> Deleted: ' . htmlspecialchars($sampleName) . '</span>';
        $html .= ' <button type="button" class="btn-view-details" onclick="openDetailsModal(\'Deleted Record Details (# ' . htmlspecialchars($record_id) . ')\', this)" data-details=\'' . $detailsJson . '\'><i class="fa-solid fa-eye"></i> View Details</button>';
        $html .= '</div>';
        return $html;
    }

    // Case 3: UPDATE (Status / Estimated Time / Reason)
    $all_keys = array_unique(array_merge(array_keys($old), array_keys($new)));
    $diff_html = '';
    $diff_count = 0;

    foreach ($all_keys as $k) {
        if ($k === 'sample_photo') continue;
        $v_old = $old[$k] ?? null;
        $v_new = $new[$k] ?? null;
        if (is_array($v_old)) $v_old = json_encode($v_old);
        if (is_array($v_new)) $v_new = json_encode($v_new);

        if ((string)$v_old !== (string)$v_new) {
            $diff_count++;
            $label = ucwords(str_replace('_', ' ', $k));
            $diff_html .= '<div class="diff-change-line"><strong>' . htmlspecialchars($label) . ':</strong> ';
            $diff_html .= '<span class="old-val">' . htmlspecialchars((string)($v_old ?? 'None')) . '</span> &rarr; ';
            $diff_html .= '<span class="new-val">' . htmlspecialchars((string)($v_new ?? 'None')) . '</span>';
            $diff_html .= '</div>';
        }
    }

    if ($diff_count === 0) {
        $diff_html = '<div class="diff-change-line"><span class="old-val">Pending</span> &rarr; <span class="new-val">In Progress / Updated</span></div>';
    }

    $html = '<div class="diff-item-compact">';
    $html .= $diff_html;
    if (!empty($new) && count($new) > 2) {
        $html .= ' <button type="button" class="btn-view-details" onclick="openDetailsModal(\'Booking Update Details (# ' . htmlspecialchars($record_id) . ')\', this)" data-details=\'' . $detailsJson . '\' style="margin-top: 4px;"><i class="fa-solid fa-eye"></i> View Details</button>';
    }
    $html .= '</div>';

    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .log-action-CREATE {
            color: #10b981;
            font-weight: bold;
        }

        .log-action-UPDATE {
            color: #3b82f6;
            font-weight: bold;
        }

        .log-action-DELETE {
            color: #ef4444;
            font-weight: bold;
        }

        .diff-container {
            font-family: monospace;
            font-size: 12px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .diff-container.empty {
            background: transparent;
            border: 1px dashed #cbd5e1;
            padding: 6px 12px;
        }

        .no-diff {
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
            font-family: 'Montserrat', sans-serif;
        }

        .diff-row {
            padding: 4px;
            border-bottom: 1px solid #f1f5f9;
            word-break: break-all;
        }

        .diff-row:last-child {
            border-bottom: none;
        }

        .diff-row.add {
            color: #166534;
            background-color: #dcfce7;
            border-radius: 4px;
            padding: 4px 8px;
            margin-bottom: 3px;
        }

        .diff-row.remove {
            color: #991b1b;
            background-color: #fee2e2;
            border-radius: 4px;
            padding: 4px 8px;
            margin-bottom: 3px;
        }

        .diff-row.change {
            color: #1e293b;
            padding: 4px 6px;
        }

        .old-val {
            text-decoration: line-through;
            color: #ef4444;
            margin-right: 5px;
        }

        .new-val {
            color: #10b981;
            font-weight: bold;
        }

        .admin-name {
            font-weight: 600;
            color: #3b82f6;
        }

        /* Compact diff styles & View Details button */
        .diff-item-compact {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
        }
        .diff-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .diff-pill.create {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .diff-pill.delete {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .diff-summary-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        .diff-summary-badge.delete { background: #fee2e2; color: #b91c1c; }
        .diff-summary-badge.update { background: #eff6ff; color: #1d4ed8; }
        .diff-summary-badge.create { background: #dcfce7; color: #15803d; }
        .diff-summary-badge.neutral { background: #f1f5f9; color: #475569; }

        .btn-view-details {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #3b82f6;
            color: #ffffff;
            border: none;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);
        }
        .btn-view-details:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .diff-change-line {
            font-size: 12px;
            padding: 2px 0;
            color: #1e293b;
        }

        /* Modal Styles */
        .details-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .details-modal {
            background: #ffffff;
            border-radius: 12px;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 85vh;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .details-modal-header {
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .details-modal-header h4 {
            margin: 0;
            font-size: 16px;
            color: #0f172a;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .details-modal-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 18px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            line-height: 1;
        }
        .details-modal-close:hover {
            color: #ef4444;
            background: #fee2e2;
        }
        .details-modal-body {
            padding: 20px;
            overflow-y: auto;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table th, .details-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            text-align: left;
        }
        .details-table th {
            width: 35%;
            color: #64748b;
            font-weight: 600;
            background: #f8fafc;
        }
        .details-table td {
            color: #1e293b;
            font-weight: 500;
        }
    </style>
</head>

<body class="admin-body-bg">
    <div class="admin-wrapper">
        <?php
        $current_page = 'activity_logs';
        $page_title = 'Activity Logs';
        include '../includes/super_admin_sidebar.php';
        ?>
        <main class="admin-main">
            <?php include '../includes/super_admin_topbar.php'; ?>
            <div class="admin-content">
                <div class="card table-card">
                    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h3>Admin Activity Logs</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Date / Time</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Record ID</th>
                                    <th style="width: 40%;">Changes (Before &rarr; After)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td style="white-space:nowrap;">
                                                <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <div class="admin-name"><?php echo htmlspecialchars($log['fullname']); ?></div>
                                                <div style="font-size:11px; color:#64748b;">
                                                    <?php echo htmlspecialchars($log['username']); ?></div>
                                                <?php
                                                $perms = [];
                                                if (!empty($log['permissions'])) {
                                                    $decoded = json_decode($log['permissions'], true);
                                                    if (is_array($decoded)) {
                                                        $perms = $decoded;
                                                    }
                                                }
                                                $display_perms = [];
                                                foreach ($perms as $p) {
                                                    if ($p == 'testings')
                                                        $display_perms[] = 'Testing';
                                                    else if ($p == 'courses')
                                                        $display_perms[] = 'Courses';
                                                    else if ($p == 'support_services')
                                                        $display_perms[] = 'Support Services';
                                                    else
                                                        $display_perms[] = ucfirst($p);
                                                }
                                                $perms_str = empty($display_perms) ? 'No Services' : implode(', ', $display_perms);
                                                ?>
                                                <div style="font-size:10px; color:#8b5cf6; margin-top:3px; font-weight:600;"><i
                                                        class="fa-solid fa-layer-group"
                                                        style="font-size:9px; margin-right:3px;"></i><?php echo htmlspecialchars($perms_str); ?>
                                                </div>
                                            </td>
                                            <td class="log-action-<?php echo $log['action']; ?>"><?php echo $log['action']; ?>
                                            </td>
                                            <td><span class="badge-type"><?php echo htmlspecialchars($log['module']); ?></span>
                                            </td>
                                            <td>#<?php echo htmlspecialchars($log['record_id']); ?></td>
                                            <td>
                                                <?php echo renderDiff($log['old_data'], $log['new_data'], $log['action'] ?? '', $log['module'] ?? '', $log['record_id'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">No activity logs found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 0): ?>
                        <div class="pagination-container">
                            <div class="pagination-links" style="margin-left:auto;">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="page-link"><i
                                            class="fa-solid fa-chevron-left"></i></a>
                                <?php else: ?>
                                    <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>"
                                        class="page-link <?php if ($i == $page)
                                            echo 'active'; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="page-link"><i
                                            class="fa-solid fa-chevron-right"></i></a>
                                <?php else: ?>
                                    <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </main>
    </div>

    <!-- Modal for viewing full booking / record details -->
    <div id="detailsModalOverlay" class="details-modal-overlay" onclick="closeDetailsModal(event)">
        <div class="details-modal" onclick="event.stopPropagation()">
            <div class="details-modal-header">
                <h4 id="detailsModalTitle"><i class="fa-solid fa-file-lines" style="color:#3b82f6;"></i> Record Details</h4>
                <button type="button" class="details-modal-close" onclick="closeDetailsModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="details-modal-body" id="detailsModalBody">
                <!-- Injected via JavaScript -->
            </div>
        </div>
    </div>

<script>
function formatKeyName(k) {
    return k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function openDetailsModal(title, btn) {
    const rawData = btn.getAttribute('data-details');
    const modalTitle = document.getElementById('detailsModalTitle');
    const modalBody = document.getElementById('detailsModalBody');
    const modalOverlay = document.getElementById('detailsModalOverlay');

    modalTitle.innerHTML = '<i class="fa-solid fa-file-circle-check" style="color:#3b82f6;"></i> ' + title;

    let data = null;
    try {
        data = JSON.parse(rawData);
    } catch(e) {
        data = rawData;
    }

    if (data && typeof data === 'object') {
        let rows = '';
        for (const [k, v] of Object.entries(data)) {
            if (k === 'sample_photo') {
                if (v && v.length < 200 && (v.endsWith('.jpg') || v.endsWith('.png') || v.endsWith('.webp') || v.endsWith('.jpeg'))) {
                    rows += `<tr><th>Sample Photo</th><td><img src="../assets/uploads/${v}" style="max-height:80px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);" onerror="this.style.display='none'"></td></tr>`;
                }
                continue;
            }
            let displayVal = (v === null || v === undefined || v === '') ? '<span style="color:#94a3b8; font-style:italic;">None</span>' : v;
            rows += `<tr><th>${formatKeyName(k)}</th><td>${displayVal}</td></tr>`;
        }
        modalBody.innerHTML = `<table class="details-table">${rows}</table>`;
    } else {
        modalBody.innerHTML = `<div style="padding:15px; color:#475569;">${data || 'No further details available.'}</div>`;
    }

    modalOverlay.style.display = 'flex';
}

function closeDetailsModal(e) {
    const modalOverlay = document.getElementById('detailsModalOverlay');
    if (modalOverlay) {
        modalOverlay.style.display = 'none';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetailsModal();
    }
});
</script>
</body>

</html>