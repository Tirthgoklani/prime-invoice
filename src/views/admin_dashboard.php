<?php
require_once "../../config/config.php";
include __DIR__ . "/layouts/admin_layout_start.php";

// Admin authentication
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// ===== Fetch Global Stats =====
// Use try-catch to prevent page crash if SQL fails
try {
    $total_companies = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'] ?? 0;
    $active_companies = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status='active'")->fetch_assoc()['total'] ?? 0;
    $blocked_companies = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status='blocked'")->fetch_assoc()['total'] ?? 0;
    $total_customers = $conn->query("SELECT COUNT(*) AS total FROM clients")->fetch_assoc()['total'] ?? 0;
    $total_invoices = $conn->query("SELECT COUNT(*) AS total FROM invoices")->fetch_assoc()['total'] ?? 0;

    // ===== Generate 6-Month Chart Data =====
    $month_labels = [];
    $company_registrations = [];
    $invoice_counts = [];

    for ($i = 5; $i >= 0; $i--) {
        $m_start = date('Y-m-01', strtotime("-$i months"));
        $m_end   = date('Y-m-t', strtotime("-$i months"));

        $reg_query = "SELECT COUNT(*) AS c FROM users WHERE created_at BETWEEN '$m_start' AND '$m_end'";
        $inv_query = "SELECT COUNT(*) AS c FROM invoices WHERE created_at BETWEEN '$m_start' AND '$m_end'";

        $company_registrations[] = (int) ($conn->query($reg_query)->fetch_assoc()['c'] ?? 0);
        $invoice_counts[] = (int) ($conn->query($inv_query)->fetch_assoc()['c'] ?? 0);
        
        $month_labels[] = date('M Y', strtotime($m_start));
    }
} catch (Exception $e) {
    // Fallback data in case of DB error
    $total_companies = $active_companies = $blocked_companies = $total_customers = $total_invoices = 0;
    $month_labels = ['Error'];
    $company_registrations = [0];
    $invoice_counts = [0];
}
// ... PHP Logic Ends
?>

<div class="w-full px-6 bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <h1 class="text-4xl font-extrabold text-white mb-8 text-center">
        Platform Admin Dashboard
    </h1>

    <!-- Statistic Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        
        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center border border-blue-500/60 transition hover:scale-105 duration-300">
            <h2 class="text-xl font-semibold text-blue-400 mb-2">Total Companies</h2>
            <p class="text-4xl font-bold text-blue-300"><?= $total_companies ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center border border-emerald-500/60 transition hover:scale-105 duration-300">
            <h2 class="text-xl font-semibold text-emerald-400 mb-2">Active Companies</h2>
            <p class="text-4xl font-bold text-emerald-300"><?= $active_companies ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center border border-red-500/60 transition hover:scale-105 duration-300">
            <h2 class="text-xl font-semibold text-red-400 mb-2">Blocked</h2>
            <p class="text-4xl font-bold text-red-300"><?= $blocked_companies ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center border border-purple-500/60 transition hover:scale-105 duration-300">
            <h2 class="text-xl font-semibold text-purple-400 mb-2">Customers</h2>
            <p class="text-4xl font-bold text-purple-300"><?= $total_customers ?></p>
        </div>

        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center border border-yellow-500/60 transition hover:scale-105 duration-300">
            <h2 class="text-xl font-semibold text-yellow-300 mb-2">Invoices</h2>
            <p class="text-4xl font-bold text-yellow-200"><?= $total_invoices ?></p>
        </div>

    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

        <!-- Company Registrations Chart -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-emerald-400 mb-4 text-center">
                Company Registrations (Last 6 Months)
            </h3>
            <!-- FIXED HEIGHT CONTAINER FOR CHART.JS -->
            <div class="relative w-full" style="height: 320px;">
                <canvas id="companyRegChart"></canvas>
            </div>
        </div>

        <!-- Invoice Activity Chart -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-blue-400 mb-4 text-center">
                Invoice Activity (Last 6 Months)
            </h3>
            <!-- FIXED HEIGHT CONTAINER FOR CHART.JS -->
            <div class="relative w-full" style="height: 320px;">
                <canvas id="invoiceChart"></canvas>
            </div>
        </div>

    </div>

</div>

<!-- Load Chart.js from Local -->
<script src="../../public/js/chart.umd.min.js"></script>

<script>
(function() {
    'use strict';

    // Data from PHP
    const chartLabels = <?php echo json_encode($month_labels); ?>;
    const companyData = <?php echo json_encode($company_registrations); ?>;
    const invoiceData = <?php echo json_encode($invoice_counts); ?>;

    const colors = {
        emerald: 'rgb(52, 211, 153)',
        emeraldBg: 'rgba(52, 211, 153, 0.2)',
        blue: 'rgb(96, 165, 250)',
        blueBg: 'rgba(96, 165, 250, 0.2)',
    };

    function initCharts() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            return;
        }

        const companyCtx = document.getElementById('companyRegChart')?.getContext('2d');
        const invoiceCtx = document.getElementById('invoiceChart')?.getContext('2d');

        if (companyCtx) {
            new Chart(companyCtx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'New Companies',
                        data: companyData,
                        borderColor: colors.emerald,
                        backgroundColor: colors.emeraldBg,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#9ca3af', stepSize: 1 }
                        },
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#9ca3af' }
                        }
                    }
                }
            });
        }

        if (invoiceCtx) {
            new Chart(invoiceCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Invoices Created',
                        data: invoiceData,
                        backgroundColor: colors.blueBg,
                        borderColor: colors.blue,
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#9ca3af', stepSize: 1 }
                        },
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.1)' },
                            ticks: { color: '#9ca3af' }
                        }
                    }
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
})();
</script>

<?php include __DIR__ . "/layouts/admin_layout_end.php"; ?>
