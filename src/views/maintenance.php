<?php
// maintenance.php - Maintenance Mode Page
// This page does NOT include config.php to avoid infinite redirect loops, 
// but we might need to connect to DB manually if we want to show dynamic dates.
// Or, we can just pass the date via URL info if we redirect? 
// Better: config.php will SKIP the check if the current page is maintenance.php.

require_once '../../config/config.php';

// Check if we are actually in maintenance mode. 
// If NOT, and user is just randomly visiting this page, redirect them to index.
$maintenance_mode = false;
$end_time = null;

$sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('maintenance_mode', 'maintenance_end_time')";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'maintenance_mode') $maintenance_mode = (bool)$row['setting_value'];
        if ($row['setting_key'] === 'maintenance_end_time') $end_time = $row['setting_value'];
    }
}

// If maintenance is OFF, and user is NOT an admin testing it, redirect to home
if (!$maintenance_mode && !isset($_SESSION['admin_loggedin'])) {
    header("Location: ../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance - Prime Invoice</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="max-w-xl w-full bg-gray-800 rounded-2xl shadow-2xl p-10 text-center border border-gray-700">
        <div class="mb-6">
            <svg class="w-24 h-24 text-yellow-500 mx-auto animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
        </div>
        
        <h1 class="text-4xl font-extrabold text-white mb-4">We'll be back soon!</h1>
        <p class="text-lg text-gray-400 mb-8">
            Our site is currently undergoing scheduled maintenance to improve your experience. 
            We apologize for the inconvenience.
        </p>

        <?php if ($end_time && strtotime($end_time) > time()): ?>
        <div class="bg-gray-900 rounded-lg p-6 mb-8 border border-gray-700">
            <p class="text-sm text-gray-400 uppercase tracking-widest mb-2">Expected Completion</p>
            <div class="text-2xl font-mono text-yellow-400">
                <?php echo date("F j, Y, g:i a", strtotime($end_time)); ?>
            </div>
            <p class="text-xs text-gray-500 mt-2" id="countdown">Calculating...</p>
        </div>
        <?php endif; ?>

        <div class="space-y-4">
            <a href="dashboard.php" class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg transition-colors">
                Try Refreshing
            </a>
            
            <?php if (isset($_SESSION['admin_loggedin'])): ?>
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <p class="text-sm text-gray-500 mb-2">You are an Admin.</p>
                    <a href="admin_dashboard.php" class="text-yellow-400 hover:text-yellow-300 underline text-sm">
                        Go to Admin Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    <?php if ($end_time && strtotime($end_time) > time()): ?>
        const endTime = new Date("<?php echo date('c', strtotime($end_time)); ?>").getTime();
        
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance < 0) {
                clearInterval(timer);
                document.getElementById("countdown").innerHTML = "Maintenance should be complete now.";
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById("countdown").innerHTML = 
                `Time Remaining: ${days}d ${hours}h ${minutes}m ${seconds}s`;
        }, 1000);
    <?php endif; ?>

    // Force full page reload if loaded via AJAX (SPA)
    // If the URL in the address bar doesn't contain 'maintenance.php', we are likely inside the dashboard shell.
    // We redirect to the full maintenance page to hide the sidebar/header.
    if (window.location.href.indexOf('maintenance.php') === -1) {
        window.location.href = window.location.href.replace(/dashboard\.php|manage_invoices\.php|create_invoice\.php|view_invoice\.php|view_ticket\.php|admin_settings\.php/g, 'maintenance.php'); 
        // Or simpler: just reload to the current URL which is now maintenance.php?
        // No, fetch returned the content. The browser URL is still dashboard.php.
        // We need to construct the correct URL to maintenance.php.
        // Let's assume it's in the same directory as the views.
        const path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        window.location.href = path + '/maintenance.php';
    }
    // Poll for status change (Real-time update)
    setInterval(() => {
        fetch('check_maintenance_status.php')
            .then(response => response.json())
            .then(data => {
                // If maintenance is OFF (false), execute recovery
                if (data.maintenance === false) {
                    // Show message
                    const container = document.querySelector('.max-w-xl');
                    container.innerHTML = `
                        <div class="animate-pulse">
                            <h1 class="text-4xl font-extrabold text-green-400 mb-4">We are Back!</h1>
                            <p class="text-lg text-gray-300 mb-8">The site is live. Redirecting you...</p>
                        </div>
                    `;
                    
                    // Redirect after short delay
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                }
            })
            .catch(err => console.error("Poll error", err));
    }, 5000); // Check every 5 seconds
    </script>

</body>
</html>
