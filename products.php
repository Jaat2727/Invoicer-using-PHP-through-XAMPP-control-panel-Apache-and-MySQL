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

// --- 3. Get Filter Parameters ---
$search_term = $_GET['search'] ?? '';
$filter_company_id = $_GET['company'] ?? '';
$query_string = http_build_query(['search' => $search_term, 'company' => $filter_company_id]);

// --- 4. Handle Form Submission (Add Product) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_name'])) {
    $product_name = $_POST['product_name'];
    $company_id = $_POST['company_id'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $sql = "INSERT INTO products (user_id, product_name, company_id, price, stock) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isidi", $user_id, $product_name, $company_id, $price, $stock);
    
    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Product added successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: products.php?" . $query_string);
    exit();
}

// --- 5. Get the message from SESSION ---
$form_message = "";
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    unset($_SESSION['form_message']);
}

// --- 6. Fetch All COMPANIES (for the dropdowns) ---
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

// --- 7. Fetch All PRODUCTS for This User (with filtering) ---
$products = [];
$sql = "SELECT p.id, p.product_name, p.price, p.stock, c.company_name 
        FROM products p
        JOIN companies c ON p.company_id = c.id
        WHERE p.user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search_term)) {
    $sql .= " AND p.product_name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}
if (!empty($filter_company_id)) {
    $sql .= " AND p.company_id = ?";
    $params[] = $filter_company_id;
    $types .= "i";
}
$sql .= " ORDER BY p.product_name ASC";

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { 'sans': ['Inter', 'sans-serif'], }, }, },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .choices__inner {
            background-color: white; border: 1px solid #D1D5DB; border-radius: 0.375rem; 
            min-height: auto; padding: 0.5rem; 
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- UPDATED NAVIGATION BAR -->
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
                        <a href="products.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Products</a>
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Create Invoice</a>
                        <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Invoice History</a>
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
                <a href="products.php" classs="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Products</a>
                <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Create Invoice</a>
                <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Invoice History</a>
                <a href="settings.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area (Unchanged) -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Your Products</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column: Add New Product Form -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Product</h2>
                    
                    <form action="products.php?<?php echo $query_string; ?>" method="POST" class="space-y-4">
                        
                        <div>
                            <label for="company_id" class="block text-sm font-medium text-gray-700">Company (Customer)</label>
                            <select id="company_id" name="company_id" required>
                                <option value="">Select a Company</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                            <input type="text" id="product_name" name="product_name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                            <input type="number" id="stock" name="stock" min="0" required value="0"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>

                        <div>
                            <button type="submit"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Save Product
                            </button>
                        </div>
                        <div id="form-message" class="text-center">
                            <?php echo $form_message; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: List of Products -->
            <div class="lg:col-span-2">

                <!-- Filter Bar -->
                <div class="bg-white shadow-md rounded-lg p-4 mb-6">
                    <form action="products.php" method="GET" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-grow">
                            <label for="search" class="sr-only">Search by Name</label>
                            <input type="text" name="search" id="search"
                                   placeholder="Search by product name..."
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        <div class="flex-grow">
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
                        <div class="flex-shrink-0 flex gap-2">
                            <button type="submit"
                                    class="flex-shrink-0 w-full md:w-auto justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Filter
                            </button>
                            <a href="products.php"
                               class="flex-shrink-0 w-full md:w-auto justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Product List Table -->
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price (₹)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        <?php if (!empty($search_term) || !empty($filter_company_id)): ?>
                                            No products found matching your filters.
                                        <?php else: ?>
                                            No products added yet. Use the form on the left to add your first one.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($product['company_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($product['price'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $product['stock']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="edit-product.php?id=<?php echo $product['id']; ?>&<?php echo $query_string; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                            <a href="delete.php?type=product&id=<?php echo $product['id']; ?>&<?php echo $query_string; ?>" 
                                               class="text-red-600 hover:text-red-900 ml-4"
                                               onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
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

    <!-- JavaScript for Choices.js (Unchanged) -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const companySelect = document.getElementById('company_id');
            if (companySelect) {
                const choices = new Choices(companySelect, {
                    searchEnabled: true,
                    searchPlaceholderValue: 'Type to search...',
                    itemSelectText: 'Click to select',
                });
            }
        });
    </script>

</body>
</html>