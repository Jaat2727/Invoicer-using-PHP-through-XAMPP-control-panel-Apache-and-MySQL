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
require_once 'db.php';

// --- 3. Get Filter/Sort Parameters ---
$search_term = $_GET['search'] ?? '';
$filter_company_id = $_GET['company'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'invoice_date';
$order = $_GET['order'] ?? 'DESC';

// --- 4. Fetch All COMPANIES (for the filter dropdown) ---
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

// --- 5. Fetch All INVOICES (with filter and sort) ---
$invoices = [];
$sql = "SELECT i.id, i.invoice_number, i.invoice_date, i.total_amount, i.payment_status, c.company_name
        FROM invoices i
        JOIN companies c ON i.company_id = c.id
        WHERE i.user_id = ? AND i.invoice_number LIKE ?";

$params = [$user_id, "%" . $search_term . "%"];
$types = "is";

if (!empty($filter_company_id)) {
    $sql .= " AND i.company_id = ?";
    $params[] = $filter_company_id;
    $types .= "i";
}

$sort_whitelist = ['invoice_date', 'total_amount', 'payment_status', 'company_name'];
$order_whitelist = ['ASC', 'DESC'];
if (in_array($sort_by, $sort_whitelist) && in_array($order, $order_whitelist)) {
    $sql .= " ORDER BY {$sort_by} {$order}";
} else {
    $sql .= " ORDER BY invoice_date DESC";
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
</head>
<body class="bg-gray-100">

    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">Invoicer</span>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Companies</a>
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Create Invoice</a>
                        <a href="invoice-history.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium">Invoice History</a>
                        <a href="inventory.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Inventory</a>
                        <a href="settings.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Settings</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="hidden md:inline text-gray-700 mr-4 text-sm">Welcome, <?php echo htmlspecialchars($user_email); ?>!</span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-600">Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Invoice History</h1>
        <div id="status-update-message" class="text-center mb-4 font-medium"></div>

        <div class="bg-white shadow-md rounded-lg p-4 mb-6">
            <form action="invoice-history.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="search" placeholder="Search by Invoice #" value="<?php echo htmlspecialchars($search_term); ?>" class="p-2 border rounded-md">
                <select name="company" class="p-2 border rounded-md">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" <?php echo ($company['id'] == $filter_company_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sort_by" class="p-2 border rounded-md">
                    <option value="invoice_date" <?php echo ($sort_by == 'invoice_date') ? 'selected' : ''; ?>>Sort by Date</option>
                    <option value="total_amount" <?php echo ($sort_by == 'total_amount') ? 'selected' : ''; ?>>Sort by Amount</option>
                    <option value="company_name" <?php echo ($sort_by == 'company_name') ? 'selected' : ''; ?>>Sort by Company</option>
                </select>
                <select name="order" class="p-2 border rounded-md">
                    <option value="DESC" <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                </select>
                <div class="md:col-span-4 flex justify-end gap-2">
                    <button type="submit" class="py-2 px-4 bg-blue-600 text-white rounded-md">Filter</button>
                    <a href="invoice-history.php" class="py-2 px-4 bg-gray-300 text-black rounded-md">Clear</a>
                </div>
            </form>
        </div>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount (₹)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="px-6 py-4 text-center">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                <td class="px-6 py-4"><?php echo date("d-m-Y", strtotime($invoice['invoice_date'])); ?></td>
                                <td class="px-6 py-4"><?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td class="px-6 py-4">
                                    <select class="status-select p-1 rounded-md" data-invoice-id="<?php echo $invoice['id']; ?>">
                                        <option value="Overdue" <?php echo ($invoice['payment_status'] == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                                        <option value="Paid" <?php echo ($invoice['payment_status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                        <option value="Ongoing" <?php echo ($invoice['payment_status'] == 'Ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="text-blue-600" target="_blank">View/Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const invoiceId = this.dataset.invoiceId;
                const newStatus = this.value;
                document.getElementById('status-update-message').textContent = 'Updating...';
                fetch('update-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ invoice_id: invoiceId, status: newStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('status-update-message').textContent = 'Status updated successfully!';
                    } else {
                        document.getElementById('status-update-message').textContent = `Error: ${data.message}`;
                    }
                })
            });
        });
    </script>
</body>
</html>