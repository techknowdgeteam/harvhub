<?php
// dev_header.php - Static header with HarvHub logo and notification bell
// This file should be included at the top of dev_app.php

// Calculate unread count from notifications
$initialUnreadCount = 0;
$allNotifications = [];

if (!empty($user['notifications'])) {
    $notificationsData = json_decode($user['notifications'], true);
    if (is_array($notificationsData)) {
        foreach ($notificationsData as $id => $notification) {
            if (isset($notification['update']) && $notification['update'] === 'new') {
                $initialUnreadCount++;
            }
            
            $message = $notification['message'] ?? '';
            $message = preg_replace('/^[\?\?]+\s*/', '', $message);
            $message = preg_replace('/[\?\?]/', '', $message);
            $message = preg_replace('/[\x{1F300}-\x{1F6FF}]/u', '', $message);
            
            $allNotifications[] = [
                'id' => $id,
                'section' => $notification['section'] ?? 'General',
                'message' => trim($message),
                'time' => $notification['time'] ?? date('Y-m-d H:i:s'),
                'type' => $notification['type'] ?? 'info',
                'update' => $notification['update'] ?? 'read'
            ];
        }
        
        usort($allNotifications, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
    }
}
?>

<!-- Top Header -->
<div class="harvhub-header-top">
    <div class="header-left">
        <span class="header-logo"><i class="fa-brands fa-pagelines"></i> HarvHub</span>
    </div>
    <div class="header-right">
        <div class="notification-bell" onclick="toggleHeaderNotifications()">
            <i class="fa-regular fa-bell"></i>
            <?php if ($initialUnreadCount > 0): ?>
                <span class="notification-badge" id="headerNotificationBadge"><?= $initialUnreadCount ?></span>
            <?php else: ?>
                <span class="notification-badge" id="headerNotificationBadge" style="display: none;">0</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notification Panel - Positioned as overlay -->
<div class="notification-panel" id="headerNotificationPanel">
    <div class="panel-content">  <!-- ADD THIS WRAPPER -->
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="close-notifications" onclick="toggleHeaderNotifications()">✕</button>
        </div>
        <div class="notification-list" id="headerNotificationList">
            <?php if (count($allNotifications) > 0): ?>
                <?php foreach ($allNotifications as $notification): 
                    $cleanMessage = $notification['message'];
                    $cleanMessage = preg_replace('/^[\?\?]+\s*/', '', $cleanMessage);
                    $cleanMessage = preg_replace('/[\?\?]/', '', $cleanMessage);
                    $cleanMessage = preg_replace('/[\x{1F300}-\x{1F6FF}]/u', '', $cleanMessage);
                ?>
                    <div class="notification-item <?= ($notification['update'] === 'new') ? 'unread' : '' ?> <?= htmlspecialchars($notification['type']) ?>"
                        data-id="<?= htmlspecialchars($notification['id']) ?>"
                        data-update="<?= htmlspecialchars($notification['update']) ?>">
                        <div class="notification-section"><?= htmlspecialchars($notification['section']) ?></div>
                        <div class="notification-message"><?= htmlspecialchars(trim($cleanMessage)) ?></div>
                        <div class="notification-time"><?= date('M d, H:i', strtotime($notification['time'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-notifications">No notifications</div>
            <?php endif; ?>
        </div>
    </div>  <!-- CLOSE THE WRAPPER -->
</div>

<script>
    // ============================================================
    // HEADER NOTIFICATION FUNCTIONS
    // ============================================================

    let headerNotificationPanelOpen = false;

    function toggleHeaderNotifications() {
        const panel = document.getElementById('headerNotificationPanel');
        if (headerNotificationPanelOpen) {
            panel.classList.remove('active');
            headerNotificationPanelOpen = false;
            markHeaderNotificationsAsRead();
        } else {
            panel.classList.add('active');
            headerNotificationPanelOpen = true;
            refreshHeaderNotifications();
        }
    }

    function markHeaderNotificationsAsRead() {
        const unreadItems = document.querySelectorAll('#headerNotificationList .notification-item.unread');
        if (unreadItems.length === 0) return;
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'mark_notifications_read=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('#headerNotificationList .notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                const badge = document.getElementById('headerNotificationBadge');
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }

    function refreshHeaderNotifications() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_notifications_list=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationList = document.getElementById('headerNotificationList');
                if (data.notifications && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notification => {
                        const unreadClass = notification.update === 'new' ? 'unread' : '';
                        const typeClass = notification.type || 'info';
                        
                        let cleanMessage = notification.message;
                        cleanMessage = cleanMessage.replace(/^[???]+\s*/, '');
                        cleanMessage = cleanMessage.replace(/[???]/g, '');
                        cleanMessage = cleanMessage.replace(/[\u{1F300}-\u{1F6FF}]/gu, '');
                        
                        html += `
                            <div class="notification-item ${unreadClass} ${typeClass}"
                                data-id="${notification.id}"
                                data-update="${notification.update}">
                                <div class="notification-section">${escapeHtml(notification.section)}</div>
                                <div class="notification-message">${escapeHtml(cleanMessage.trim())}</div>
                                <div class="notification-time">${formatHeaderDate(notification.time)}</div>
                            </div>
                        `;
                    });
                    notificationList.innerHTML = html;
                } else {
                    notificationList.innerHTML = '<div class="empty-notifications">No notifications</div>';
                }
                
                const badge = document.getElementById('headerNotificationBadge');
                if (data.unread_count > 0) {
                    if (badge) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'flex';
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing notifications:', error);
        });
    }

    function formatHeaderDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // Close notification panel when clicking outside
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('headerNotificationPanel');
        const bell = document.querySelector('.harvhub-header-top .notification-bell');
        
        if (headerNotificationPanelOpen && panel && !panel.contains(event.target) && !bell.contains(event.target)) {
            panel.classList.remove('active');
            headerNotificationPanelOpen = false;
            markHeaderNotificationsAsRead();
        }
    });

    // Poll for new notifications
    function pollHeaderNotifications() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'check_new_notifications=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('headerNotificationBadge');
                if (data.unread_count > 0) {
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        if (currentCount !== data.unread_count) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'flex';
                            if (headerNotificationPanelOpen) {
                                refreshHeaderNotifications();
                            }
                        }
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error polling notifications:', error);
        });
    }

    // Start polling every 3 seconds
    setInterval(pollHeaderNotifications, 3000);

    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>