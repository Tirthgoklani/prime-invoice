
<?php
// dashboard.php with charts
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Get the logged-in user's ID

// Fetch Dashboard Data for the logged-in user
$total_invoices = 0;
$total_income = 0;
$monthly_income = 0;
$yearly_income = 0;

// Fetch Company Logo
$company_logo = null;
$logo_query = $conn->query("SELECT company_logo FROM user_settings WHERE user_id = $user_id LIMIT 1");
if ($logo_query && $logo_query->num_rows > 0) {
    $company_logo = $logo_query->fetch_assoc()['company_logo'];
}

// Total Invoices for the current user
$sql_total_invoices = "SELECT COUNT(*) AS total_count FROM invoices WHERE user_id = ?";
$stmt_total_invoices = $conn->prepare($sql_total_invoices);
$stmt_total_invoices->bind_param("i", $user_id);
$stmt_total_invoices->execute();
$result_total_invoices = $stmt_total_invoices->get_result();
if ($result_total_invoices && $row = $result_total_invoices->fetch_assoc()) {
    $total_invoices = $row['total_count'];
}
$stmt_total_invoices->close();

// Total Income for the current user
$sql_total_income = "SELECT SUM(total_amount) AS total_sum FROM invoices WHERE user_id = ?";
$stmt_total_income = $conn->prepare($sql_total_income);
$stmt_total_income->bind_param("i", $user_id);
$stmt_total_income->execute();
$result_total_income = $stmt_total_income->get_result();
if ($result_total_income && $row = $result_total_income->fetch_assoc()) {
    $total_income = $row['total_sum'] ? $row['total_sum'] : 0;
}
$stmt_total_income->close();

$first_day_of_month = date('Y-m-01');
$last_day_of_month = date('Y-m-t');

$sql_monthly_income = "SELECT SUM(total_amount) AS monthly_sum FROM invoices 
                      WHERE user_id = ? 
                      AND invoice_date >= ? 
                      AND invoice_date <= ?";

$stmt_monthly_income = $conn->prepare($sql_monthly_income);
$stmt_monthly_income->bind_param("iss", $user_id, $first_day_of_month, $last_day_of_month);
$stmt_monthly_income->execute();
$result_monthly_income = $stmt_monthly_income->get_result();

if ($result_monthly_income && $row = $result_monthly_income->fetch_assoc()) {
    $monthly_income = $row['monthly_sum'] ? $row['monthly_sum'] : 0;
}
$stmt_monthly_income->close();

// Yearly Income (for current year) for the current user
$current_year_start = date('Y-01-01 00:00:00');
$current_year_end = date('Y-12-31 23:59:59');
$sql_yearly_income = "SELECT SUM(total_amount) AS yearly_sum FROM invoices WHERE user_id = ? AND created_at BETWEEN ? AND ?";
$stmt_yearly_income = $conn->prepare($sql_yearly_income);
$stmt_yearly_income->bind_param("iss", $user_id, $current_year_start, $current_year_end);
$stmt_yearly_income->execute();
$result_yearly_income = $stmt_yearly_income->get_result();
if ($result_yearly_income && $row = $result_yearly_income->fetch_assoc()) {
    $yearly_income = $row['yearly_sum'] ? $row['yearly_sum'] : 0;
}
$stmt_yearly_income->close();

// Get Monthly Income Data for Chart (Last 6 months)
$monthly_data = [];
$monthly_labels = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $sql_month_data = "SELECT SUM(total_amount) AS amount FROM invoices 
                      WHERE user_id = ? AND invoice_date >= ? AND invoice_date <= ?";
    $stmt_month_data = $conn->prepare($sql_month_data);
    $stmt_month_data->bind_param("iss", $user_id, $month_start, $month_end);
    $stmt_month_data->execute();
    $result_month_data = $stmt_month_data->get_result();
    $month_row = $result_month_data->fetch_assoc();
    
    $monthly_data[] = $month_row['amount'] ? floatval($month_row['amount']) : 0;
    $monthly_labels[] = date('M Y', strtotime($month_start));
    $stmt_month_data->close();
}

// Get Invoice Count by Month (Last 6 months)
$invoice_count_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $sql_count_data = "SELECT COUNT(*) AS count FROM invoices 
                      WHERE user_id = ? AND invoice_date >= ? AND invoice_date <= ?";
    $stmt_count_data = $conn->prepare($sql_count_data);
    $stmt_count_data->bind_param("iss", $user_id, $month_start, $month_end);
    $stmt_count_data->execute();
    $result_count_data = $stmt_count_data->get_result();
    $count_row = $result_count_data->fetch_assoc();
    
    $invoice_count_data[] = intval($count_row['count']);
    $stmt_count_data->close();
}

// Fetch Announcements
$announcements = [];
$ann_query = $conn->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 3");
if ($ann_query && $ann_query->num_rows > 0) {
    while ($row = $ann_query->fetch_assoc()) {
        $announcements[] = $row;
    }
}


// Connection kept open for layout files
// $conn->close(); moved to end of file

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// If it's not an AJAX request, include the common layout start
if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>

<!-- Chart.js is now loaded globally in common_layout_end.php -->

<title>Prime Invoice - Smart Easy Modern</title>

<!-- Main content for the dashboard page (Dark Theme) -->
<div class="max-w-6xl mx-auto bg-gray-900 text-gray-100 p-8 rounded-lg shadow-xl">
    <h1 class="text-4xl font-extrabold text-white mb-8 text-center flex flex-col md:flex-row items-center justify-center gap-6">
        <?php if ($company_logo && file_exists("../../$company_logo")): ?>
            <img src="../../<?php echo htmlspecialchars($company_logo); ?>" alt="Company Logo" class="w-auto object-contain bg-white/5 rounded-xl p-2 backdrop-blur-sm border border-gray-700 shadow-lg hover:scale-105 transition-transform duration-300" style="height: 5.5rem;">
        <?php endif; ?>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?>!</span>
    </h1>

    <!-- System Announcements -->
    <?php if (!empty($announcements)): ?>
    <div id="announcement-section" class="mb-8">
        <div class="flex justify-between items-center mb-2">
            <h2 class="text-xl font-bold text-gray-300">📢 Announcements</h2>
            <button onclick="toggleAnnouncements()" id="toggle-ann-btn" class="text-sm text-blue-400 hover:text-blue-300 underline">
                Hide
            </button>
        </div>
        
        <div id="announcement-list" class="space-y-3 transition-all duration-300">
            <?php foreach($announcements as $ann): 
                $bg_colors = [
                    'info' => 'bg-blue-600 border-blue-400', 
                    'warning' => 'bg-yellow-600 border-yellow-400', 
                    'success' => 'bg-green-600 border-green-400', 
                    'danger' => 'bg-red-600 border-red-400'
                ];
                $bg_class = $bg_colors[$ann['type']] ?? 'bg-blue-600 border-blue-400';
            ?>
            <div class="<?php echo $bg_class; ?> border-l-4 p-4 rounded shadow-lg flex items-center animate-pulse-slow">
                <span class="text-2xl mr-3 filter drop-shadow-md">
                    <?php echo ($ann['type']=='info' ? 'ℹ️' : ($ann['type']=='warning' ? '⚠️' : ($ann['type']=='danger' ? '🚨' : '✅'))); ?>
                </span>
                <div>
                    <p class="text-white font-bold text-lg drop-shadow-sm"><?php echo htmlspecialchars($ann['message']); ?></p>
                    <p class="text-xs text-blue-50 mt-1 font-medium opacity-90"><?php echo date("F j, Y, g:i a", strtotime($ann['created_at'])); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        .animate-pulse-slow {
            animation: pulse-slow 2s ease-in-out infinite;
        }
    </style>

    <script>
    function toggleAnnouncements() {
        const list = document.getElementById('announcement-list');
        const btn = document.getElementById('toggle-ann-btn');
        const isHidden = list.classList.contains('hidden');
        
        if (isHidden) {
            list.classList.remove('hidden');
            btn.textContent = 'Hide';
            localStorage.setItem('announcements_hidden', 'false');
        } else {
            list.classList.add('hidden');
            btn.textContent = 'Show';
            localStorage.setItem('announcements_hidden', 'true');
        }
    }

    // Apply state on load
    document.addEventListener('DOMContentLoaded', () => {
        const list = document.getElementById('announcement-list');
        const btn = document.getElementById('toggle-ann-btn');
        if (localStorage.getItem('announcements_hidden') === 'true') {
            list.classList.add('hidden');
            btn.textContent = 'Show';
        }
    });
    </script>
    <?php endif; ?>

    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded relative mb-6" role="alert" data-auto-dismiss="true">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline">Invoice #<?php echo htmlspecialchars($_GET['invoice_id']); ?> created successfully.</span>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Invoices Card -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center min-h-[160px] flex flex-col justify-between border border-blue-500/60">
            <h2 class="text-2xl font-semibold text-blue-400 mb-2">Total Invoices</h2>
            <div class="flex-grow flex items-center justify-center">
                <p class="text-5xl font-bold text-blue-300"><?php echo $total_invoices; ?></p>
            </div>
        </div>

        <!-- Total Income Card -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center min-h-[160px] flex flex-col justify-between border border-emerald-500/60">
            <h2 class="text-2xl font-semibold text-emerald-400 mb-2">Total Income</h2>
            <div class="flex-grow flex items-center justify-center">
                <p class="text-5xl font-bold text-emerald-300">₹<?php echo number_format($total_income, 2); ?></p>
            </div>
        </div>

        <!-- Monthly Income Card -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center min-h-[160px] flex flex-col justify-between border border-purple-500/60">
            <h2 class="text-2xl font-semibold text-purple-400 mb-2">Monthly Income (<?php echo date('F Y'); ?>)</h2>
            <div class="flex-grow flex items-center justify-center">
                <p class="text-5xl font-bold text-purple-300">₹<?php echo number_format($monthly_income, 2); ?></p>
            </div>
            <p class="text-sm text-purple-300 mt-2">Based on invoice dates</p>
        </div>

        <!-- Yearly Income Card -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-md text-center min-h-[160px] flex flex-col justify-between border border-yellow-500/60">
            <h2 class="text-2xl font-semibold text-yellow-300 mb-2">Yearly Income (<?php echo date('Y'); ?>)</h2>
            <div class="flex-grow flex items-center justify-center">
                <p class="text-5xl font-bold text-yellow-200">₹<?php echo number_format($yearly_income, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Monthly Income Chart -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-emerald-400 mb-4 text-center">Monthly Income Trend</h3>
            <div class="relative h-80">
                <canvas id="monthlyIncomeChart"></canvas>
            </div>
        </div>

        <!-- Invoice Count Chart -->
        <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700">
            <h3 class="text-2xl font-bold text-blue-400 mb-4 text-center">Monthly Invoice Count</h3>
            <div class="relative h-80">
                <canvas id="invoiceCountChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Combined Chart -->
    <div class="bg-gray-800 p-6 rounded-lg shadow-lg border border-gray-700 mb-8">
        <h3 class="text-2xl font-bold text-purple-400 mb-4 text-center">Income vs Invoice Count Overview</h3>
        <div class="relative h-96">
            <canvas id="combinedChart"></canvas>
        </div>
    </div>
</div>

<script>
    // Define initialization function globally so it can be called reliably
    window.initDashboardCharts = function() {
        // Ensure Chart is loaded
        if (typeof Chart === 'undefined') {
            // If completely missing (direct load race condition), waiting for window.onload usually fixes it
            // because Chart.js is in the footer.
            console.warn('Chart.js not loaded yet. Retrying...');
            setTimeout(window.initDashboardCharts, 100);
            return;
        }

        // Cleanup existing charts to prevent canvas reuse errors
        if (window.dashboardChartInstances) {
            window.dashboardChartInstances.forEach(chart => chart.destroy());
        }
        window.dashboardChartInstances = [];

        // ===== Dark Theme Palette for Charts =====
        if (typeof window.chartColors === "undefined") {
            window.chartColors = {
                blue: { bg: 'rgba(96, 165, 250, 0.18)', border: 'rgb(96, 165, 250)', gradient: 'rgba(96, 165, 250, 0.35)' },
                emerald: { bg: 'rgba(52, 211, 153, 0.18)', border: 'rgb(52, 211, 153)', gradient: 'rgba(52, 211, 153, 0.35)' },
                purple: { bg: 'rgba(192, 132, 252, 0.18)', border: 'rgb(192, 132, 252)', gradient: 'rgba(192, 132, 252, 0.35)' },
                yellow: { bg: 'rgba(253, 224, 71, 0.18)', border: 'rgb(253, 224, 71)', gradient: 'rgba(253, 224, 71, 0.35)' }
            };
        }

        // ===== Global Chart.js Dark Defaults =====
        Chart.defaults.color = '#e5e7eb';
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial';
        Chart.defaults.plugins.legend.labels.color = '#f3f4f6';
        Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.95)';
        Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
        Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
        Chart.defaults.elements.line.borderJoinStyle = 'round';
        Chart.defaults.elements.line.borderCapStyle = 'round';

        // ===== Gridlines & axes in dark =====
        const darkGrid = 'rgba(255, 255, 255, 0.06)';
        const darkZeroLine = 'rgba(255, 255, 255, 0.25)';

        // ===== Monthly Income Chart (LINE) =====
        const monthlyIncomeCanvas = document.getElementById('monthlyIncomeChart');
        if (monthlyIncomeCanvas) {
            const monthlyIncomeCtx = monthlyIncomeCanvas.getContext('2d');
            const monthlyIncomeGradient = monthlyIncomeCtx.createLinearGradient(0, 0, 0, 400);
            monthlyIncomeGradient.addColorStop(0, window.chartColors.emerald.gradient);
            monthlyIncomeGradient.addColorStop(1, 'rgba(0,0,0,0)');

            window.dashboardChartInstances.push(new Chart(monthlyIncomeCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($monthly_labels); ?>,
                    datasets: [{
                        label: 'Monthly Income (₹)',
                        data: <?php echo json_encode($monthly_data); ?>,
                        backgroundColor: monthlyIncomeGradient,
                        borderColor: window.chartColors.emerald.border,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#111827',
                        pointBorderColor: window.chartColors.emerald.border,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { callback: (v) => '₹' + Number(v).toLocaleString() }, grid: { color: darkGrid, zeroLineColor: darkZeroLine } },
                        x: { grid: { display: false } }
                    }
                }
            }));
        }

        // ===== Invoice Count Chart (BAR) =====
        const invoiceCountCanvas = document.getElementById('invoiceCountChart');
        if (invoiceCountCanvas) {
            const invoiceCountCtx = invoiceCountCanvas.getContext('2d');
            window.dashboardChartInstances.push(new Chart(invoiceCountCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthly_labels); ?>,
                    datasets: [{
                        label: 'Invoice Count',
                        data: <?php echo json_encode($invoice_count_data); ?>,
                        backgroundColor: window.chartColors.blue.bg,
                        borderColor: window.chartColors.blue.border,
                        borderWidth: 2,
                        borderRadius: 8,
                        hoverBackgroundColor: window.chartColors.blue.gradient
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: darkGrid, zeroLineColor: darkZeroLine } },
                        x: { grid: { display: false } }
                    }
                }
            }));
        }

        // ===== Combined Chart (BAR + BAR) =====
        const combinedCanvas = document.getElementById('combinedChart');
        if (combinedCanvas) {
            const combinedCtx = combinedCanvas.getContext('2d');
            window.dashboardChartInstances.push(new Chart(combinedCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthly_labels); ?>,
                    datasets: [
                        {
                            label: 'Monthly Income (₹)',
                            data: <?php echo json_encode($monthly_data); ?>,
                            backgroundColor: window.chartColors.emerald.bg,
                            borderColor: window.chartColors.emerald.border,
                            borderWidth: 2,
                            borderRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Invoice Count',
                            data: <?php echo json_encode($invoice_count_data); ?>,
                            backgroundColor: window.chartColors.purple.bg,
                            borderColor: window.chartColors.purple.border,
                            borderWidth: 2,
                            borderRadius: 6,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: '#e5e7eb' } } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { type: 'linear', display: true, position: 'left', beginAtZero: true, grid: { color: darkGrid, zeroLineColor: darkZeroLine } },
                        y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
                    }
                }
            }));
        }
    };

    // Initialize logic
    if (document.readyState === 'loading') {
        // Direct load: Wait for DOM and Footer Scripts
        document.addEventListener('DOMContentLoaded', window.initDashboardCharts);
    } else {
        // AJAX load: Run immediately (Chart.js should be ready from previous page or cache)
        window.initDashboardCharts();
    }

    // Listener for future AJAX navigations to this page
    window.removeEventListener('dashboardLoaded', window.initDashboardCharts); // avoid duplicates
    window.addEventListener('dashboardLoaded', window.initDashboardCharts);
</script>

<?php
// If it's not an AJAX request, include the common layout end
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
