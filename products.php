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

// --- 3. Get Filter Parameters ---
$search_term = $_GET['search'] ?? '';
$query_string = http_build_query(['search' => $search_term]);

// --- 4. Handle Form Submission (Add Product) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_name'])) {
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $sql = "INSERT INTO products (user_id, product_name, price, stock) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdi", $user_id, $product_name, $price, $stock);

    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Product added successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();

    header("Location: products.php?" . $query_string);
    exit();
}

// --- 5. Get the message from SESSION ---
$form_message = "";
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// --- 6. Fetch All PRODUCTS for This User (with filtering) ---
$products = [];
$sql = "SELECT id, product_name, price, stock FROM products WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search_term)) {
    $sql .= " AND product_name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}
$sql .= " ORDER BY product_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
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
    <title>Manage Products - SaaS Invoicer</title>

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
                        <a href="products.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium">Products</a>
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
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Your Products</h1>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Product</h2>
                    <form action="products.php?<?php echo $query_string; ?>" method="POST" class="space-y-4">
                        <div>
                            <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                            <input type="text" id="product_name" name="product_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700">Initial Stock</label>
                            <input type="number" id="stock" name="stock" min="0" required value="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        <div>
                            <button type="submit" class="w-full flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Save Product</button>
                        </div>
                        <div id="form-message" class="text-center">
                            <?php echo $form_message; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="lg:col-span-2">
                <div class="bg-white shadow-md rounded-lg p-4 mb-6">
                    <form action="products.php" method="GET" class="flex gap-4">
                        <div class="flex-grow">
                            <input type="text" name="search" id="search" placeholder="Search by product name..." value="<?php echo htmlspecialchars($search_term); ?>" class="block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                        </div>
                        <div class="flex-shrink-0 flex gap-2">
                            <button type="submit" class="py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Search</button>
                            <a href="products.php" class="py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price (₹)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center">No products found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="px-6 py-4"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="px-6 py-4"><?php echo number_format($product['price'], 2); ?></td>
                                        <td class="px-6 py-4"><?php echo $product['stock']; ?></td>
                                        <td class="px-6 py-4">
                                            <a href="edit-product.php?id=<?php echo $product['id']; ?>&<?php echo $query_string; ?>" class="text-blue-600">Edit</a>
                                            <a href="delete.php?type=product&id=<?php echo $product['id']; ?>&<?php echo $query_string; ?>" class="text-red-600 ml-4" onclick="return confirm('Are you sure?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>