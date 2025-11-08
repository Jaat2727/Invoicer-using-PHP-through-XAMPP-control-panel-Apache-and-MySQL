<?php
// --- 1. Start Session & Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// --- 2. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoicer_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. Get Filter/Sort Parameters ---
$search_term = $_GET['search'] ?? ''; 
$filter_company_id = $_GET['company'] ?? ''; 
$sort_by = $_GET['sort_by'] ?? 'invoice_date'; // Default sort
$order = $_GET['order'] ?? 'DESC'; // Default order

// Build query string for links and forms
$query_string = http_build_query([
    'search' => $search_term, 
    'company' => $filter_company_id,
    'sort_by' => $sort_by,
    'order' => $order
]);

// --- 4. Get the message from SESSION ---
$form_message = "";
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    unset($_SESSION['form_message']); // Clear it
}

// --- 5. Fetch All COMPANIES (for the filter dropdown) ---
$companies = [];
$sql_companies = "SELECT id, company_name FROM companies WHERE user_id = ? ORDER BY company_name ASC";
$stmt_companies = $conn->prepare($sql_companies);
$stmt_companies->bind_param("i", $user_id);
$stmt_companies->execute();
$result_companies = $stmt_companies->get_result();
while($row = $result_companies->fetch_assoc()) {
    $companies[] = $row;
}
$stmt_companies->close();

// --- 6. Fetch All INVOICES (with filter and sort) ---
$invoices = [];
$sql = "SELECT i.id, i.invoice_number, i.invoice_date, i.total_amount, i.payment_status, c.company_name 
        FROM invoices i
        JOIN companies c ON i.company_id = c.id
        WHERE i.user_id = ?";
$params = [$user_id];
$types = "i";

// Add filters
if (!empty($search_term)) {
    $sql .= " AND i.invoice_number LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}
if (!empty($filter_company_id)) {
    $sql .= " AND i.company_id = ?";
    $params[] = $filter_company_id;
    $types .= "i";
}

// Add Sorting
$sort_whitelist = [
    'invoice_date' => 'i.invoice_date',
    'total_amount' => 'i.total_amount',
    'payment_status' => 'i.payment_status',
    'company_name' => 'c.company_name'
];
$order_whitelist = ['ASC', 'DESC'];

if (isset($sort_whitelist[$sort_by]) && in_array(strtoupper($order), $order_whitelist)) {
    $sql .= " ORDER BY " . $sort_whitelist[$sort_by] . " " . strtoupper($order);
} else {
    // Default fallback
    $sql .= " ORDER BY i.invoice_date DESC, i.id DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice History - SaaS Invoicer</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { 'sans': ['Inter', 'sans-serif'], }, }, },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Custom styles for the status dropdowns */
        .status-select {
            font-weight: 500; /* medium */
            border-width: 2px;
            border-color: #D1D5DB; /* default border-gray-300 */
            background-position: right 0.5rem center;
            padding-right: 2.5rem !important; /* Make room for arrow */
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- NAVIGATION BAR (Fixed) -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Left side: Logo and main links -->
                <div class="flex items-center min-w-0">
                    <span class="text-2xl font-bold text-blue-600 flex-shrink-0">Invoicer</span>
                    <!-- Links to other pages -->
                    <div class="hidden sm:ml-6 sm:flex sm:items-baseline sm:space-x-4">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Companies</a>
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Create Invoice</a>
                        <a href="invoice-history.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Invoice History</a>
                        <a href="settings.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Settings</a>
                    </div>
                </div>
                <!-- Right side: Welcome and Logout -->
                <div class="flex items-center flex-shrink-0 ml-4">
                    <span class="hidden md:inline text-gray-700 mr-4 text-sm truncate">
                        Welcome, <?php echo htmlspecialchars($user_email); ?>!
                    </span>
                    <a href="logout.php" class="flex-shrink-0 bg-red-500 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-600">
                        Log Out
                    </a>
                </div>
            </div>
        </div>
        <!-- Mobile menu -->
        <div class="sm:hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
                <a href="companies.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Companies</a>
                <a href="products.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Products</a>
                <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Create Invoice</a>
                <a href="invoice-history.php" class="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Invoice History</a>
                <a href="settings.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Invoice History</h1>
        
        <!-- This is where the "Invoice Saved!" message will appear -->
        <div id="form-message" class="text-center mb-4">
            <?php echo $form_message; ?>
        </div>
        <!-- This is where the "Status updated!" message will appear -->
        <div id="status-update-message" class="text-center mb-4 font-medium"></div>


        <!-- Filter/Sort Bar -->
        <div class="bg-white shadow-md rounded-lg p-4 mb-6">
            <form action="invoice-history.php" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <!-- Search by Invoice # -->
                <div class="md:col-span-2">
                    <label for="search" class="sr-only">Search by Invoice #</label>
                    <input type="text" name="search" id="search"
                           placeholder="Search by Invoice #"
                           value="<?php echo htmlspecialchars($search_term); ?>"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>
                <!-- Filter by Company -->
                <div class="md:col-span-2">
                    <label for="company" class="sr-only">Filter by Company</label>
                    <select id="company" name="company" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo ($company['id'] == $filter_company_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Sort By -->
                <div>
                    <label for="sort_by" class="sr-only">Sort By</label>
                    <select id="sort_by" name="sort_by" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        <option value="invoice_date" <?php echo ($sort_by == 'invoice_date') ? 'selected' : ''; ?>>Sort by Date</option>
                        <option value="total_amount" <?php echo ($sort_by == 'total_amount') ? 'selected' : ''; ?>>Sort by Amount</option>
                        <option value="company_name" <?php echo ($sort_by == 'company_name') ? 'selected' : ''; ?>>Sort by Company</option>
                        <option value="payment_status" <?php echo ($sort_by == 'payment_status') ? 'selected' : ''; ?>>Sort by Status</option>
                    </select>
                </div>
                <!-- Order (ASC/DESC) -->
                <div>
                    <label for="order" class="sr-only">Order</label>
                    <select id="order" name="order" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        <option value="DESC" <?php echo (strtoupper($order) == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                        <option value="ASC" <?php echo (strtoupper($order) == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                    </select>
                </div>
                <!-- Buttons -->
                <div class="md:col-span-6 flex justify-end gap-2">
                    <button type="submit"
                            class="justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Filter
                    </button>
                    <a href="invoice-history.php"
                       class="justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Invoice List Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount (₹)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                No invoices found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date("d-m-Y", strtotime($invoice['invoice_date'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <select class="status-select p-1 rounded-md" data-invoice-id="<?php echo $invoice['id']; ?>">
                                        <option value="Overdue" <?php echo ($invoice['payment_status'] == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                        <option value="Paid" <?php echo ($invoice['payment_status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                        <option value="Ongoing" <?php echo ($invoice['payment_status'] == 'Ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <!-- This link now points to our new PDF file -->
                                    <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-600 hover:text-blue-900" target="_blank">View/Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript to handle status updates AND COLORS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelects = document.querySelectorAll('.status-select');
            const messageDiv = document.getElementById('status-update-message');

            // --- THIS IS THE FIX ---
            // This function sets the color and border color directly
            function setStatusColor(selectElement) {
                const status = selectElement.value;
                
                if (status === 'Paid') {
                    selectElement.style.color = '#38A169'; // text-green-600
                    selectElement.style.borderColor = '#38A169';
                } else if (status === 'Ongoing') {
                    selectElement.style.color = '#D69E2E'; // text-yellow-600
                    selectElement.style.borderColor = '#D69E2E';
                } else { // Overdue
                    selectElement.style.color = '#E53E3E'; // text-red-600
                    selectElement.style.borderColor = '#E53E3E';
                }
            }
            // --- END OF FIX ---

            statusSelects.forEach(select => {
                // 1. Set initial color on page load
                setStatusColor(select);

                // 2. Add change event listener
                select.addEventListener('change', function() {
                    // Set new color immediately for instant feedback
                    setStatusColor(this);

                    const invoiceId = this.dataset.invoiceId;
                    const newStatus = this.value;

                    // Show a "loading" message
                    messageDiv.textContent = 'Updating...';
                    messageDiv.className = 'text-center text-gray-600'; // Added text-center

                    // Send this change to the server
                    fetch('update-status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            invoice_id: invoiceId,
                            status: newStatus
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            messageDiv.textContent = 'Status updated successfully!';
                            messageDiv.className = 'text-center text-green-600';
                        } else {
                            messageDiv.textContent = `Error: ${data.message}`;
                            messageDiv.className = 'text-center text-red-600';
                            // We should probably revert the dropdown if it failed
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        messageDiv.textContent = 'An error occurred. Please try again.';
                        messageDiv.className = 'text-center text-red-600';
                    });
                });
            });
        });
    </script>
</body>
</html>