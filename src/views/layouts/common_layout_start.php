<?php
// common_layout_start.php
// This file contains the starting HTML, head, and the sidebar.
// It is included by content pages (dashboard.php, create_invoice.php, etc.)
// when they are accessed directly (not via AJAX).

// Ensure session is started if not already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (
    (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) &&
    (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true)
) {
    header("Location: ../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Invoice - Smart Easy Modern</title> <!-- Title will be updated by JavaScript -->
    <link rel="icon" type="image/png" href="../../logo.png">
    <meta name="csrf-token" content="<?php echo Csrf::generateToken(); ?>">


    <link rel="stylesheet" href="../../public/css/style.css">
    <script src="../../public/js/sweetalert2.all.min.js"></script>
    <script>
        function confirmLogout(event) {
            event.preventDefault();
            Swal.fire({
                title: 'Logout?',
                text: "Are you sure you want to log out?",
                icon: 'question',
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #111827; /* Dark gray background */
            color: #e5e7eb; /* Light gray text */
            display: flex;
            min-height: 100vh;
            overflow: hidden;
            opacity: 1;
        }
        /* prevent flash during maintenance redirect */
        body.checking-maintenance {
            opacity: 0;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #1f2937; /* Dark track */
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #4b5563; /* Gray thumb */
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #6b7280; /* Lighter gray on hover */
        }
        .sidebar-link.active {
            background-color: #2563eb; /* Blue active */
            color: #ffffff;
            font-weight: 600;
        }
        aside {
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
            padding-bottom: 2rem;
        }
        main {
            flex-grow: 1;
            height: 100vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
        }
        body::-webkit-scrollbar {
            display: none;
        }
        body {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .barcode-input {
            display: block !important;
            visibility: visible !important;
        }
    </style>
    <script>
        // Highlight active page in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            
            sidebarLinks.forEach(link => {
                const linkPage = link.getAttribute('href').split('/').pop();
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // poll for maintenance mode (only for non-admin users)
            <?php if(!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true): ?>
            setInterval(function() {
                fetch('check_maintenance_status.php')
                    .then(res => res.json())
                    .then(data => {
                        if(data.maintenance) {
                            window.location.href = 'maintenance.php';
                        }
                    })
                    .catch(err => console.log('Maintenance check failed'));
            }, 5000); // check every 5 seconds
            <?php endif; ?>
        });
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 dark:text-gray-100 checking-maintenance">
<script>
// show body after page loads (maintenance check already done in config.php)
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.remove('checking-maintenance');
});
</script>

<?php if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true): ?>
<div class="bg-purple-600 text-white text-center py-2 font-bold fixed top-0 w-full z-50 shadow-lg">
    🕵️ You are currently impersonating <?php echo htmlspecialchars($_SESSION['username']); ?>. 
    <a href="../controllers/admin_impersonate.php?action=stop" class="underline ml-2 bg-white text-purple-700 px-2 py-0.5 rounded text-sm hoever:bg-gray-100">Switch Back to Admin</a>
</div>
<div class="h-10"></div> <!-- Spacer -->
<?php endif; ?>

<!-- Banner removed as per user request -->

    <!-- Sidebar Navigation -->

    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-gray-800 text-gray-100 p-6 shadow-lg rounded-r-lg flex flex-col  border-r border-gray-700">
        <div> <img src="./logo.png" alt=""> </div>
            <!-- Nav -->
            <nav class="mt-10">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="dashboard">
                            📊 <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="create_invoice.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="create_invoice">
                            🧾 <span>Create Invoice</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_invoices.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="manage_invoices">
                            📑 <span>Manage Invoices</span>
                        </a>
                    </li>
                    <li>
                        <a href="export_data.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="export_data">
                            📊 <span>Export Data</span>
                        </a>
                    </li>
                    <li>
                        <a href="add_product.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="add_product">
                            ➕ <span>Add Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="manage_products.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="manage_products">
                            📦 <span>Manage Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="settings">
                            ⚙️ <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="support.php" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-gray-700 transition duration-300" data-page="support">
                            🎫 <span>Support</span>
                        </a>
                    </li>

                    <li>
                        <a href="../../logout.php" onclick="confirmLogout(event)" class="sidebar-link flex items-center gap-2 py-2 px-4 rounded-lg hover:bg-red-600 transition duration-300" data-page="logout">
                            🚪 <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <div class="text-center text-sm text-gray-400 bottom-0 mt-auto">
            &copy; 2025 Prime Invoice
        </div>
    </aside>

    <!-- Main Content Area -->
    <main id="main-content-area" class="flex-1 p-8 overflow-y-auto bg-gray-900 text-gray-100">
