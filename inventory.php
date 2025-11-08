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

// --- 3. Handle Form Submission (Manual Stock Update) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $change_quantity = (int)$_POST['change_quantity'];
    $reason = $_POST['reason'] ?? 'Manual Adjustment';

    if ($product_id > 0 && $change_quantity != 0) {
        $conn->begin_transaction();
        try {
            // Update product stock
            $sql_update = "UPDATE products SET stock = stock + ? WHERE id = ? AND user_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("iii", $change_quantity, $product_id, $user_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Log the change in inventory_history
            $sql_log = "INSERT INTO inventory_history (user_id, product_id, change_quantity, reason) VALUES (?, ?, ?, ?)";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("iiis", $user_id, $product_id, $change_quantity, $reason);
            $stmt_log->execute();
            $stmt_log->close();

            $conn->commit();
            $_SESSION['form_message'] = "<p class='text-green-600'>Stock updated successfully!</p>";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['form_message'] = "<p class='text-red-600'>Error updating stock: " . $exception->getMessage() . "</p>";
        }
    }
    header("Location: inventory.php");
    exit();
}

// --- 4. Fetch All Products for This User ---
$products = [];
$sql = "SELECT id, product_name, stock FROM products WHERE user_id = ? ORDER BY product_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
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
    <title>Inventory Management - SaaS Invoicer</title>

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
                        <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Invoice History</a>
                        <a href="inventory.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium">Inventory</a>
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
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Inventory Management</h1>

        <div class="bg-white shadow-md rounded-lg p-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adjust Stock</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['stock']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <form action="inventory.php" method="POST" class="flex items-center space-x-2">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="number" name="change_quantity" class="w-24 p-1 border rounded-md" placeholder="e.g., 50 or -10" required>
                                    <input type="text" name="reason" class="w-48 p-1 border rounded-md" placeholder="Reason (optional)">
                                    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>