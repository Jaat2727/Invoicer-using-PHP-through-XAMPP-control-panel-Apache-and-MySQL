<?php
// --- 1. Start the Session and Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// --- 2. Database Connection ---
require_once 'db.php';

// --- 3. Fetch Dashboard Metrics ---
// Total Invoices
$sql_total_invoices = "SELECT COUNT(id) as total FROM invoices WHERE user_id = ?";
$stmt_total = $conn->prepare($sql_total_invoices);
$stmt_total->bind_param("i", $user_id);
$stmt_total->execute();
$total_invoices = $stmt_total->get_result()->fetch_assoc()['total'];
$stmt_total->close();

// Total Customers
$sql_total_customers = "SELECT COUNT(id) as total FROM companies WHERE user_id = ?";
$stmt_customers = $conn->prepare($sql_total_customers);
$stmt_customers->bind_param("i", $user_id);
$stmt_customers->execute();
$total_customers = $stmt_customers->get_result()->fetch_assoc()['total'];
$stmt_customers->close();

// Revenue Metrics
$sql_revenue = "SELECT payment_status, SUM(total_amount) as total FROM invoices WHERE user_id = ? GROUP BY payment_status";
$stmt_revenue = $conn->prepare($sql_revenue);
$stmt_revenue->bind_param("i", $user_id);
$stmt_revenue->execute();
$revenue_results = $stmt_revenue->get_result();
$revenue = ['Paid' => 0, 'Overdue' => 0, 'Ongoing' => 0];
while ($row = $revenue_results->fetch_assoc()) {
    $revenue[$row['payment_status']] = $row['total'];
}
$stmt_revenue->close();

// Top Selling Products
$sql_top_products = "SELECT p.product_name, SUM(ii.quantity) as total_sold FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE p.user_id = ? GROUP BY p.product_name ORDER BY total_sold DESC LIMIT 5";
$stmt_top = $conn->prepare($sql_top_products);
$stmt_top->bind_param("i", $user_id);
$stmt_top->execute();
$top_products = $stmt_top->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_top->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SaaS Invoicer</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">

    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">Invoicer</span>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                        <a href="dashboard.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Companies</a>
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Create Invoice</a>
                        <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Invoice History</a>
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

        <h1 class="text-3xl font-bold text-gray-900 mb-6">Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-600">Total Invoices</h2>
                <p class="text-3xl font-bold text-blue-600"><?php echo $total_invoices; ?></p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-600">Total Customers</h2>
                <p class="text-3xl font-bold text-blue-600"><?php echo $total_customers; ?></p>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-600">Revenue (Paid)</h2>
                <p class="text-3xl font-bold text-green-600">₹<?php echo number_format($revenue['Paid'], 2); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Top Selling Products</h2>
                <canvas id="topProductsChart"></canvas>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Revenue Overview</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-600">Paid</p>
                        <p class="text-2xl font-bold text-green-600">₹<?php echo number_format($revenue['Paid'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Overdue</p>
                        <p class="text-2xl font-bold text-red-600">₹<?php echo number_format($revenue['Overdue'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Ongoing</p>
                        <p class="text-2xl font-bold text-yellow-600">₹<?php echo number_format($revenue['Ongoing'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const topProductsData = <?php echo json_encode($top_products); ?>;
        const labels = topProductsData.map(p => p.product_name);
        const data = topProductsData.map(p => p.total_sold);

        const ctx = document.getElementById('topProductsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Sold',
                    data: data,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>