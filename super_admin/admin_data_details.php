<?php
include '../includes/super_admin_auth_check.php';
include '../includes/db_connect.php';

$admin_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Fetch target admin details
$target_admin = null;
if ($admin_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'admin' LIMIT 1");
    $stmt->execute(['id' => $admin_id]);
    $target_admin = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$target_admin) {
    header("Location: view_admin_data.php");
    exit();
}

$admin_perms = [];
if (!empty($target_admin['permissions'])) {
    $decoded = json_decode($target_admin['permissions'], true);
    if (is_array($decoded)) {
        $admin_perms = $decoded;
    }
}

$isTestingsAdmin = in_array('testings', $admin_perms);
$hasFullHistory = in_array('full_history', $admin_perms);
$adminStartDate = !empty($target_admin['created_at']) ? date('Y-m-d 00:00:00', strtotime($target_admin['created_at'])) : null;

// Fetch bookings for testings admin (or overall company testing bookings for Super Admin audit)
$bookings = [];
$stats = [
    'total' => 0,
    'complete' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'rejected' => 0,
    'overdue' => 0
];

if ($isTestingsAdmin || !empty($target_admin)) {
    $stmtBookings = $conn->query("SELECT * FROM test_bookings ORDER BY id DESC");
    $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

    // Compute stats
    $stats['total'] = count($bookings);
    foreach ($bookings as $b) {
        $st = $b['status'];
        if ($st === 'Complete')
            $stats['complete']++;
        elseif ($st === 'Pending')
            $stats['pending']++;
        elseif ($st === 'Accept') {
            $stats['in_progress']++;
            if (!empty($b['estimated_time']) && strtotime($b['estimated_time']) < time()) {
                $stats['overdue']++;
            }
        } elseif ($st === 'Cancel')
            $stats['rejected']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($target_admin['fullname']); ?> - Admin Data Details | AKC Super Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #f8fafc;
            color: #1e293b;
            border-color: #94a3b8;
        }

        .admin-meta-banner {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px 28px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .admin-meta-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-avatar-lg {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }

        .stat-badge-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .stat-badge-pill.pending {
            background-color: #fff7ed;
            color: #c2410c;
        }

        .stat-badge-pill.accept {
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        .stat-badge-pill.complete {
            background-color: #ecfdf5;
            color: #047857;
        }

        .stat-badge-pill.cancel {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .stat-badge-pill.overdue {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .admin-table {
            min-width: 850px;
        }

        .admin-table th,
        .admin-table td {
            white-space: nowrap;
        }
    </style>
</head>

<body class="admin-body-bg">

    <div class="admin-wrapper">
        <?php
        $current_page = 'view_admin_data';
        $page_title = 'View Admin Data';
        include '../includes/super_admin_sidebar.php';
        ?>

        <!-- Right Content Area -->
        <main class="admin-main">
            <?php include '../includes/super_admin_topbar.php'; ?>

            <div class="admin-content">

                <div style="margin-bottom: 20px;">
                    <a href="view_admin_data.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back to Admin Services
                    </a>
                </div>

                <!-- Admin Meta Profile Banner -->
                <div class="admin-meta-banner">
                    <div class="admin-meta-info">
                        <div class="admin-avatar-lg">
                            <?php echo strtoupper(substr($target_admin['fullname'] ?: $target_admin['username'], 0, 2)); ?>
                        </div>
                        <div>
                            <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 4px;">
                                <?php echo htmlspecialchars($target_admin['fullname']); ?>
                                <span
                                    style="font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 20px; margin-left: 8px; <?php echo $target_admin['is_active'] ? 'background:#dcfce7; color:#15803d;' : 'background:#fee2e2; color:#b91c1c;'; ?>">
                                    <?php echo $target_admin['is_active'] ? 'Active Admin' : 'Inactive'; ?>
                                </span>
                            </h2>
                            <div style="font-size: 13.5px; color: #64748b; display: flex; gap: 15px; flex-wrap: wrap;">
                                <span><i class="fa-regular fa-envelope"></i>
                                    <?php echo htmlspecialchars($target_admin['email']); ?></span>
                                <span><i class="fa-solid fa-user-tag"></i> Username:
                                    <strong><?php echo htmlspecialchars($target_admin['username']); ?></strong></span>
                                <span><i class="fa-regular fa-calendar"></i> Added:
                                    <strong><?php echo date('M d, Y', strtotime($target_admin['created_at'])); ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <span
                            style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 700;">
                            <?php echo $isTestingsAdmin ? '🧪 Testings Division' : (in_array('courses', $admin_perms) ? '🎓 Courses Division' : (in_array('support_services', $admin_perms) ? '🎧 Support Division' : 'None Assigned')); ?>
                        </span>
                        <?php if ($hasFullHistory): ?>
                            <span
                                style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700;"
                                title="Super Admin granted permission to view older bookings prior to assignment">
                                📜 Full History Access
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isTestingsAdmin || !empty($bookings)): ?>
                    <!-- Mini Stats Row -->
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 25px;">
                        <div
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">
                            <span
                                style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase;">Total
                                Bookings</span>
                            <h3 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 4px 0 0;">
                                <?php echo $stats['total']; ?></h3>
                        </div>
                        <div
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">
                            <span
                                style="font-size: 12px; color: #16a34a; font-weight: 600; text-transform: uppercase;">Completed</span>
                            <h3 style="font-size: 22px; font-weight: 700; color: #16a34a; margin: 4px 0 0;">
                                <?php echo $stats['complete']; ?></h3>
                        </div>
                        <div
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">
                            <span
                                style="font-size: 12px; color: #ea580c; font-weight: 600; text-transform: uppercase;">Pending</span>
                            <h3 style="font-size: 22px; font-weight: 700; color: #ea580c; margin: 4px 0 0;">
                                <?php echo $stats['pending']; ?></h3>
                        </div>
                        <div
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center;">
                            <span style="font-size: 12px; color: #2563eb; font-weight: 600; text-transform: uppercase;">In
                                Progress</span>
                            <h3 style="font-size: 22px; font-weight: 700; color: #2563eb; margin: 4px 0 0;">
                                <?php echo $stats['in_progress']; ?></h3>
                        </div>
                        <div
                            style="background: white; border: 1px solid #fee2e2; border-radius: 10px; padding: 14px; text-align: center;">
                            <span
                                style="font-size: 12px; color: #dc2626; font-weight: 600; text-transform: uppercase;">Overdue</span>
                            <h3 style="font-size: 22px; font-weight: 700; color: #dc2626; margin: 4px 0 0;">
                                <?php echo $stats['overdue']; ?></h3>
                        </div>
                    </div>

                    <!-- Bookings Table for this Admin -->
                    <div class="card table-card">
                        <div class="card-header">
                            <h3>Bookings in this Admin's Scope (<?php echo count($bookings); ?>)</h3>
                            <small style="color: #64748b;">Read-only view for Super Admin auditing</small>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">ID</th>
                                        <th>Sample Information</th>
                                        <th>Client Details</th>
                                        <th>Submission Date</th>
                                        <th>Status</th>
                                        <th>Target Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($bookings) > 0): ?>
                                        <?php foreach ($bookings as $b):
                                            $st = $b['status'];
                                            $isOverdue = ($st === 'Accept' && !empty($b['estimated_time']) && strtotime($b['estimated_time']) < time());
                                            $displaySt = ($st === 'Accept') ? 'In Progress' : (($st === 'Cancel') ? 'Rejected' : $st);
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $b['id']; ?></strong></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($b['sample_type']); ?></strong><br>
                                                    <small
                                                        style="color: #64748b;"><?php echo htmlspecialchars($b['num_samples']); ?>
                                                        sample(s)</small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($b['fullname']); ?></strong><br>
                                                    <small
                                                        style="color: #64748b;"><?php echo htmlspecialchars($b['email']); ?></small>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                                                <td>
                                                    <span class="stat-badge-pill <?php echo strtolower($st); ?>">
                                                        <?php echo $displaySt; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($b['estimated_time']) && $st === 'Accept'): ?>
                                                        <?php if ($isOverdue): ?>
                                                            <span class="stat-badge-pill overdue">
                                                                <i class="fa-solid fa-triangle-exclamation"></i> OVERDUE
                                                                (<?php echo date('M d, H:i', strtotime($b['estimated_time'])); ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span style="font-size: 13px; color: #1e40af; font-weight: 600;">
                                                                <i class="fa-regular fa-clock"></i>
                                                                <?php echo date('M d, Y H:i', strtotime($b['estimated_time'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php elseif ($st === 'Complete' && !empty($b['completed_at'])): ?>
                                                        <span style="font-size: 12px; color: #15803d;"><i class="fa-solid fa-check"></i>
                                                            <?php echo date('M d, Y', strtotime($b['completed_at'])); ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #94a3b8; font-size: 13px;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="empty-state">No bookings currently active within this
                                                administrator's scope.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card"
                        style="padding: 40px; text-align: center; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 48px; color: #94a3b8; margin-bottom: 15px;"><i
                                class="fa-solid fa-folder-open"></i></div>
                        <h3 style="color: #1e293b; font-size: 18px; margin-bottom: 8px;">No Test Booking Records for this
                            Admin</h3>
                        <p style="color: #64748b; max-width: 500px; margin: 0 auto;">
                            This administrator is currently allocated to
                            <strong><?php echo in_array('courses', $admin_perms) ? 'Courses & Training' : (in_array('support_services', $admin_perms) ? 'Technical Support' : 'None'); ?></strong>.
                            Test booking records only apply to administrators assigned to the <strong>🧪 Testings</strong>
                            division.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

</body>

</html>