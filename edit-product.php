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

// --- 3. Get Product ID from URL ---
if (!isset($_GET['id'])) {
    die("Error: No product ID specified.");
}
$product_id = (int)$_GET['id'];

// --- NEW: Get filter params from URL to pass back ---
$search_term = $_GET['search'] ?? '';
$filter_company_id = $_GET['company'] ?? '';
$query_string = http_build_query(['search' => $search_term, 'company' => $filter_company_id]);


// --- 4. Handle UPDATE Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = $_POST['product_name'];
    $company_id = $_POST['company_id'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $sql = "UPDATE products SET 
                product_name = ?, company_id = ?, price = ?, stock = ?
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sidiii", $product_name, $company_id, $price, $stock, $product_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Product updated successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
    
    // UPDATED: Redirect back to the filtered list
    header("Location: products.php?" . $query_string);
    exit();
}

// --- 5. Fetch All COMPANIES (for the dropdown) ---
$companies = [];
$sql_companies = "SELECT id, company_name FROM companies WHERE user_id = ?";
$stmt_companies = $conn->prepare($sql_companies);
$stmt_companies->bind_param("i", $user_id);
$stmt_companies->execute();
$result_companies = $stmt_companies->get_result();
while($row = $result_companies->fetch_assoc()) {
    $companies[] = $row;
}
$stmt_companies->close();

// --- 6. Fetch the Product Data for the Form ---
$sql = "SELECT product_name, company_id, price, stock FROM products WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("Error: Product not found or you do not have permission.");
}
$product = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - SaaS Invoicer</title>
    
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
            background-color: white;
            border: 1px solid #D1D5DB; 
            border-radius: 0.375rem; 
            min-height: auto;
            padding: 0.5rem; 
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">Invoicer</span>
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Companies</a>
                        <a href="products.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 rounded-md text-sm font-medium" aria-current="page">Products</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4">Welcome, <?php echo htmlspecialchars($user_email); ?>!</span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-600">
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Edit Product</h1>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Update Product Details</h2>
            
            <!-- UPDATED: Form action now includes the query string -->
            <form action="edit-product.php?id=<?php echo $product_id; ?>&<?php echo $query_string; ?>" method="POST" class="space-y-4">
                
                <div>
                    <label for="company_id" class="block text-sm font-medium text-gray-700">Company</label>
                    <select id="company_id" name="company_id" required>
                        <option value="">Select a Company</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo ($company['id'] == $product['company_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required
                           value="<?php echo htmlspecialchars($product['product_name']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required 
                           value="<?php echo htmlspecialchars($product['price']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" min="0" required 
                           value="<?php echo htmlspecialchars($product['stock']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>

                <div class="flex gap-4">
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Product
                    </button>
                    <!-- UPDATED: Cancel button links back to the filtered list -->
                    <a href="products.php?<?php echo $query_string; ?>"
                       class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for Choices.js -->
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