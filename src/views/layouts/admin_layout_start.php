<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin access check
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: /invoice_generator/index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Prime Invoice</title>
    <meta name="csrf-token" content="<?php echo Csrf::generateToken(); ?>">

    <link rel="stylesheet" href="../../public/css/style.css">
    <script src="../../public/js/sweetalert2.all.min.js"></script>


    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            display: flex;
            margin: 0;
            min-height: 100vh;
        }
        aside {
            width: 260px;
            background-color: #1e293b;
            height: 100vh;
            padding: 22px;
            border-right: 1px solid #334155;
            overflow-y: auto;
        }
        .sidebar-title {
            color: #38bdf8;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 35px;
            text-align: center;
        }
        .sidebar-link {
            display: block;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 6px;
            color: #cbd5e1;
            font-size: 16px;
        }
        .sidebar-link:hover {
            background-color: #334155;
        }
        .logout-btn {
            margin-top: 25px;
            background-color: #dc2626;
            color: white;
            text-align: center;
        }
        .logout-btn:hover {
            background-color: #ef4444;
        }
        main {
            flex: 1;
            padding: 30px;
            height: 100vh;
            overflow-y: auto;
        }
    </style>

    <script>
        function loadPage(url) {
            window.location.href = url;
        }

        function confirmLogoutAdmin(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Logout?',
                text: "End your admin session?",
                icon: 'warning',
                background: '#1f2937', color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Logout'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../logout.php';
                }
            });
        }
    </script>
</head>

<body>

<!-- ====================== ADMIN SIDEBAR ====================== -->
<aside>
    <div class="sidebar-title">Admin Panel</div>

    <!-- USE RELATIVE PATHS -->
    <a href="./admin_dashboard.php" class="sidebar-link">📊 Dashboard</a>
    <a href="./admin_companies.php" class="sidebar-link">🏢 Companies</a>
    <a href="./admin_customers.php" class="sidebar-link">👥 Global Customers</a>
    <a href="./admin_invoices.php" class="sidebar-link">📑 Global Invoices</a>
    <a href="./admin_announcements.php" class="sidebar-link">📢 Announcements</a>
    
    <a href="./admin_settings.php" class="sidebar-link">⚙️ Platform Settings</a>
    <a href="./admin_support.php" class="sidebar-link">🎫 Support Tickets</a>
    <a href="./admin_logs.php" class="sidebar-link">📜 Audit Logs</a>

    <a href="../../logout.php" onclick="confirmLogoutAdmin(event)" class="sidebar-link logout-btn">🚪 Logout</a>
</aside>

<!-- ====================== MAIN CONTENT AREA ====================== -->
<main>
