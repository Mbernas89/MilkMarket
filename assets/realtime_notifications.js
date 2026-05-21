document.addEventListener('DOMContentLoaded', function() {
    const navNotifCount = document.getElementById('nav-notif-count');
    const navMsgCount = document.getElementById('nav-msg-count');
    const globalUnreadDot = document.getElementById('global-nav-unread-dot');

    async function updateUnreadCounts() {
        try {
            const response = await fetch('../api/get_unread_counts.php');
            const result = await response.json();

            if (result.status === 'success') {
                const counts = result.data;

                // Update Notifications count in dropdown
                if (navNotifCount) {
                    if (counts.unread_notifications > 0) {
                        navNotifCount.textContent = counts.unread_notifications;
                        navNotifCount.style.display = 'inline-block';
                    } else {
                        navNotifCount.style.display = 'none';
                    }
                }

                // Update Messages count in dropdown
                if (navMsgCount) {
                    if (counts.unread_messages > 0) {
                        navMsgCount.textContent = counts.unread_messages;
                        navMsgCount.style.display = 'inline-block';
                    } else {
                        navMsgCount.style.display = 'none';
                    }
                }

                // Update Global Alert Dot next to avatar
                if (globalUnreadDot) {
                    if (counts.total_unread > 0) {
                        globalUnreadDot.style.display = 'block';
                    } else {
                        globalUnreadDot.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching unread counts:', error);
        }
    }

    // Initial check and start polling
    updateUnreadCounts();
    setInterval(updateUnreadCounts, 10000); // Check every 10 seconds for global badges
});
