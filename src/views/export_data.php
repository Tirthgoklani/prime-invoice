<?php
// export_data.php - Dedicated Export Page
require_once '../../config/config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if it's an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax) {
    include './layouts/common_layout_start.php';
}
?>

<title>Export Data - Prime Invoice</title>

<div class="max-w-4xl mx-auto bg-gray-900 p-8 rounded-lg shadow-xl text-gray-200">
    <h1 class="text-4xl font-extrabold text-white mb-2 text-center">Export Invoice Data</h1>
    <p class="text-center text-gray-400 mb-8">Generate reports for any date range in Excel or PDF format</p>

    <?php
    // Export-related errors
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
        $error_message = '';
        
        if ($error === 'missing_dates') {
            $error_message = 'Please select both start and end dates for export.';
        } elseif ($error === 'no_invoices') {
            $start = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
            $end = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';
            $error_message = "No invoices found between $start and $end. Try a different date range.";
        } elseif ($error === 'invalid_token') {
            $error_message = 'Invalid security token. Please try again.';
        } elseif ($error === 'invalid_request') {
            $error_message = 'Invalid request method. Please use the export form.';
        } elseif ($error === 'database_error') {
            $error_message = 'Database connection error. Please try again later or contact support.';
        } elseif ($error === 'export_failed') {
            $error_message = 'Failed to generate CSV export. Please try again or contact support.';
        } elseif ($error === 'pdf_failed') {
            $error_message = 'Failed to generate PDF export. Please try again or contact support.';
        } elseif ($error === 'pdf_library_missing') {
            $error_message = 'PDF export is currently unavailable. Please use CSV/Excel export instead.';
        }
        
        if ($error_message) {
            echo '<div class="bg-yellow-900/50 border border-yellow-500 text-yellow-200 px-4 py-3 rounded relative mb-6" role="alert" data-auto-dismiss="true">
                    <strong class="font-bold">Notice!</strong>
                    <span class="block sm:inline">' . $error_message . '</span>
                </div>';
        }
    }
    ?>

    <!-- Export Form -->
    <div class="bg-gray-800 p-8 rounded-lg border border-gray-700">
        <form action="../controllers/export_invoices.php" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::generateToken(); ?>">
            
            <!-- Date Range -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-300 mb-2">
                        From Date
                    </label>
                    <input type="date" id="start_date" name="start_date" 
                           class="w-full p-4 border border-gray-600 rounded-lg bg-gray-700 text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           required>
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-300 mb-2">
                        To Date
                    </label>
                    <input type="date" id="end_date" name="end_date" 
                           class="w-full p-4 border border-gray-600 rounded-lg bg-gray-700 text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           required>
                </div>
            </div>
            
            <!-- Format Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">
                    Export Format
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="relative flex items-center p-4 border-2 border-gray-600 rounded-lg cursor-pointer hover:border-blue-500 transition-all bg-gray-700/50">
                        <input type="radio" name="format" value="excel" class="mr-3 w-5 h-5 text-blue-600" checked>
                        <div>
                            <div class="font-semibold text-white">Excel (.csv)</div>
                            <div class="text-sm text-gray-400">Opens in Excel, Google Sheets</div>
                        </div>
                    </label>
                    
                    <label class="relative flex items-center p-4 border-2 border-gray-600 rounded-lg cursor-pointer hover:border-blue-500 transition-all bg-gray-700/50">
                        <input type="radio" name="format" value="pdf" class="mr-3 w-5 h-5 text-blue-600">
                        <div>
                            <div class="font-semibold text-white">PDF Document</div>
                            <div class="text-sm text-gray-400">Professional, print-ready</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <!-- Export Button -->
            <div class="pt-4">
                <button type="submit" 
                        class="w-full px-6 py-4 text-lg font-bold rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block mr-2 -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Data
                </button>
            </div>
        </form>
    </div>

    <!-- Info Section -->
    <div class="mt-8 p-6 bg-blue-900/20 border border-blue-500/30 rounded-lg">
        <h3 class="text-lg font-semibold text-blue-300 mb-3">About Exports</h3>
        <ul class="space-y-2 text-gray-300">
            <li class="flex items-start">
                <svg class="h-5 w-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Excel format is perfect for data analysis and custom calculations</span>
            </li>
            <li class="flex items-start">
                <svg class="h-5 w-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>PDF format provides professional reports ready for printing or sharing</span>
            </li>
            <li class="flex items-start">
                <svg class="h-5 w-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>All exports include invoice details, totals, tax, and discount information</span>
            </li>
        </ul>
    </div>
</div>

<?php
if (!$is_ajax) {
    include './layouts/common_layout_end.php';
}
?>
