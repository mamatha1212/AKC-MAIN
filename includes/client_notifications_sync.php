<?php
// includes/client_notifications_sync.php

$sync_db_notif = [];
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'client') {
    try {
        $client_user_id = (int) $_SESSION['user_id'];
        $client_email = strtolower(trim($_SESSION['email'] ?? $_SESSION['username'] ?? ''));
        $client_username = strtolower(trim($_SESSION['username'] ?? ''));

        // Strictly use the currently logged-in client's authenticated user ID, email & username
        $userEmails = array_filter(array_unique([$client_email, $client_username]));
        
        // Also fetch user record email from users table to ensure 100% email accuracy
        $stmtU = $conn->prepare("SELECT email, username FROM users WHERE id = :uid");
        $stmtU->execute(['uid' => $client_user_id]);
        $uRow = $stmtU->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            if (!empty($uRow['email'])) $userEmails[] = strtolower(trim($uRow['email']));
            if (!empty($uRow['username'])) $userEmails[] = strtolower(trim($uRow['username']));
        }
        $userEmails = array_values(array_filter(array_unique($userEmails)));

        // Find all bookings belonging ONLY to this specific logged-in client
        $placeholders = implode(',', array_fill(0, count($userEmails), '?'));
        $stmtBookings = $conn->prepare("
            SELECT id, email, sample_type, status, created_at, accepted_at, completed_at, rejected_at, rejection_reason 
            FROM test_bookings 
            WHERE LOWER(TRIM(email)) IN ($placeholders)
            ORDER BY id DESC
        ");
        $stmtBookings->execute($userEmails);
        $clientBookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clientBookings as $cb) {
            $bid = $cb['id'];
            $bstatus = $cb['status'];
            $stype = $cb['sample_type'] ?: 'Sample';

            // Check if notification already exists for this booking and user
            $checkNotif = $conn->prepare("
                SELECT COUNT(*) FROM user_notifications 
                WHERE booking_id = :bid AND user_id = :uid
            ");
            $checkNotif->execute(['bid' => $bid, 'uid' => $client_user_id]);
            $existingCount = (int) $checkNotif->fetchColumn();

            // If no notification exists for this booking, generate one
            if ($existingCount === 0) {
                $status_labels = [
                    'Pending' => 'Pending',
                    'Accept' => 'In Progress',
                    'Complete' => 'Completed',
                    'Cancel' => 'Rejected'
                ];
                $label = $status_labels[$bstatus] ?? $bstatus;
                $msg = "Your booking #$bid ($stype) status is $label.";
                if ($bstatus === 'Complete') {
                    $msg = "Your booking #$bid ($stype) has been Completed. Report files are now available.";
                } elseif ($bstatus === 'Cancel' && !empty($cb['rejection_reason'])) {
                    $msg .= " Reason: " . $cb['rejection_reason'];
                }

                $notifDate = $cb['completed_at'] ?? $cb['accepted_at'] ?? $cb['rejected_at'] ?? $cb['created_at'] ?? date('Y-m-d H:i:s');

                $insNotif = $conn->prepare("
                    INSERT INTO user_notifications (user_id, booking_id, title, message, is_read, created_at) 
                    VALUES (:uid, :bid, 'Booking Status Updated', :msg, 0, :dt)
                ");
                $insNotif->execute([
                    'uid' => $client_user_id,
                    'bid' => $bid,
                    'msg' => $msg,
                    'dt' => $notifDate
                ]);
            }
        }

        // 2. Fetch all notifications strictly for bookings belonging to this client
        $stmtDbNotify = $conn->prepare("
            SELECT DISTINCT un.* 
            FROM user_notifications un
            INNER JOIN test_bookings tb ON un.booking_id = tb.id
            WHERE LOWER(TRIM(tb.email)) IN ($placeholders)
            ORDER BY un.created_at DESC 
            LIMIT 50
        ");
        $stmtDbNotify->execute($userEmails);
        $sync_db_notif = $stmtDbNotify->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sync_db_notif as &$n) {
            if (!empty($n['booking_id'])) {
                $stmtB = $conn->prepare("SELECT * FROM test_bookings WHERE id = :bid");
                $stmtB->execute(['bid' => $n['booking_id']]);
                $b = $stmtB->fetch(PDO::FETCH_ASSOC);
                if ($b) {
                    // Remove large base64 data to prevent exceeding Vercel 4.5MB payload limit
                    unset($b['sample_photo']);
                    $stmtF = $conn->prepare("SELECT * FROM booking_files WHERE booking_id = :bid");
                    $stmtF->execute(['bid' => $b['id']]);
                    $b['attached_files'] = $stmtF->fetchAll(PDO::FETCH_ASSOC);
                }
                $n['booking_data'] = $b;
            }
        }
        unset($n);
    } catch (PDOException $e) {
    }
}
?>
<script>
    const dbStatusNotificationsData = <?php echo json_encode($sync_db_notif); ?>;

    function renderGlobalNotifications() {
        const badge = document.getElementById('notificationBadge');
        const dropdownList = document.getElementById('notificationList');
        const pageList = document.getElementById('pageNotificationList'); // Only on notifications page
        const sidebarBadge = document.getElementById('sidebarNotificationBadge');

        function parseClientNotifDate(dtStr) {
            if (!dtStr) return new Date();
            let s = dtStr.replace(' ', 'T');
            if (!s.endsWith('Z') && !s.includes('+')) {
                s += 'Z';
            }
            return new Date(s);
        }

        let statusAlerts = dbStatusNotificationsData.map(n => {
            return {
                ...n,
                isRead: n.is_read == 1,
                sortTime: parseClientNotifDate(n.created_at).getTime()
            };
        });

        // Filter active alerts for unread counts
        const activeStatusAlerts = statusAlerts.filter(a => !a.isRead);
        const totalUnreadCount = activeStatusAlerts.length;

        // 1. UPDATE BADGES
        if (badge) {
            if (totalUnreadCount > 0) {
                badge.innerText = totalUnreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        if (sidebarBadge) {
            if (totalUnreadCount > 0) {
                sidebarBadge.innerText = totalUnreadCount;
                sidebarBadge.style.display = 'inline-block';
            } else {
                sidebarBadge.style.display = 'none';
            }
        }

        // Sort ALL alerts (newest first)
        const allAlerts = statusAlerts.sort((a, b) => b.sortTime - a.sortTime);
        const unreadAlerts = allAlerts.filter(a => !a.isRead);

        // 2. POPULATE TOPBAR DROPDOWN (Unread Only)
        if (dropdownList) {
            if (unreadAlerts.length === 0) {
                dropdownList.innerHTML = '<div class="notification-empty">No active alerts</div>';
            } else {
                dropdownList.innerHTML = '';
                unreadAlerts.forEach(n => {
                    const item = document.createElement('a');
                    item.className = 'notification-item unread';
                    item.href = 'javascript:void(0);';

                    const created = parseClientNotifDate(n.created_at);
                    const timeStr = created.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });

                    item.innerHTML = `
                    <div class="notification-item-text">
                        <i class="fa-solid fa-circle-info" style="color: #3b82f6; margin-right: 5px;"></i>
                        <strong>Status Update</strong>: ${n.message}
                    </div>
                    <div class="notification-item-time">
                        ${timeStr}
                    </div>
                `;

                    item.onclick = function () {
                        const formData = new FormData();
                        formData.append('action', 'read_one');
                        formData.append('id', n.id);

                        fetch('mark_notifications_read.php', { method: 'POST', body: formData })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    const orig = dbStatusNotificationsData.find(db_n => db_n.id === n.id);
                                    if (orig) orig.is_read = 1;
                                    renderGlobalNotifications();
                                }
                            });

                        if (typeof openViewModal === 'function' && n.booking_data) {
                            openViewModal(n.booking_data);
                            document.getElementById('notificationDropdown').classList.remove('show');
                        } else {
                            window.location.href = 'client_notifications.php';
                        }
                    };
                    dropdownList.appendChild(item);
                });
            }
        }

        // 3. POPULATE FULL PAGE LIST (All alerts, read and unread)
        if (pageList) {
            if (allAlerts.length === 0) {
                pageList.innerHTML = `
                <div class="notifications-empty-state">
                    <i class="fa-solid fa-bell-slash"></i>
                    <h3>No Notification History</h3>
                    <p>You don't have any notifications yet.</p>
                </div>`;
            } else {
                pageList.innerHTML = '';
                allAlerts.forEach(n => {
                    const item = document.createElement('div');
                    item.className = 'notification-card-item' + (!n.isRead ? ' unread' : '');

                    const created = parseClientNotifDate(n.created_at);
                    const timeStr = created.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });

                    item.innerHTML = `
                    <div class="notification-card-content">
                        <div class="notification-card-icon ${n.isRead ? 'read' : 'unread'}" style="background: ${n.isRead ? '#f1f5f9' : '#eff6ff'}; color: ${n.isRead ? '#94a3b8' : '#3b82f6'};">
                            <i class="fa-solid fa-circle-info"></i>
                        </div>
                        <div class="notification-card-text">
                            <div class="notification-card-title">${n.title || 'Status Update: Booking #' + n.booking_id}</div>
                            <div class="notification-card-msg">${n.message}</div>
                            <div class="notification-card-time"><i class="fa-regular fa-clock"></i> ${timeStr}</div>
                        </div>
                    </div>
                    <div class="notification-card-meta">
                        ${n.isRead ? '' : '<span class="new-badge">New</span>'}
                        <button class="view-btn-notif"><i class="fa-solid fa-eye"></i> View Details</button>
                    </div>
                `;
                    item.onclick = function () {
                        if (!n.isRead) {
                            const formData = new FormData();
                            formData.append('action', 'read_one');
                            formData.append('id', n.id);
                            fetch('mark_notifications_read.php', { method: 'POST', body: formData })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        const orig = dbStatusNotificationsData.find(db_n => db_n.id === n.id);
                                        if (orig) orig.is_read = 1;
                                        renderGlobalNotifications();
                                    }
                                });
                        }
                        if (typeof openViewModal === 'function' && n.booking_data) {
                            openViewModal(n.booking_data);
                        }
                    };
                    pageList.appendChild(item);
                });
            }
        }
    }

    // Run both on DOM ready and after a short tick to ensure page elements like pageNotificationList exist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotifications);
    } else {
        initNotifications();
    }
    window.addEventListener('load', renderGlobalNotifications);
    setTimeout(renderGlobalNotifications, 100);

    function initNotifications() {
        renderGlobalNotifications();

        // Topbar dropdown toggle
        const bellBtn = document.getElementById('notificationBellBtn');
        if (bellBtn) {
            bellBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown) dropdown.classList.toggle('show');
            });
        }

        const handleClearAll = function (e) {
            if (e) e.stopPropagation();

            const formData = new FormData();
            formData.append('action', 'read_all');
            fetch('mark_notifications_read.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        dbStatusNotificationsData.forEach(n => n.is_read = 1);
                        renderGlobalNotifications();
                    }
                });

            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) dropdown.classList.remove('show');
        };

        const clearBtn = document.getElementById('clearNotificationsBtn');
        if (clearBtn) clearBtn.addEventListener('click', handleClearAll);

        const pageClearBtn = document.getElementById('markAllReadPageBtn');
        if (pageClearBtn) pageClearBtn.addEventListener('click', handleClearAll);

        // Close dropdown clicking outside
        document.addEventListener('click', function (e) {
            const dropdown = document.getElementById('notificationDropdown');
            const container = document.getElementById('notificationContainer');
            if (dropdown && container && !container.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    });
</script>