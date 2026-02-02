</main>
</main>
</div> <!-- Close MAIN WRAPPER -->

<script>
// Auto-dismiss notifications after 5 seconds
function autoDismissNotifications() {
    const notifications = document.querySelectorAll('[data-auto-dismiss="true"]');
    notifications.forEach(notification => {
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 5000);
    });
}

// Run auto-dismiss on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoDismissNotifications);
} else {
    autoDismissNotifications();
}
</script>

</body>
</html>
