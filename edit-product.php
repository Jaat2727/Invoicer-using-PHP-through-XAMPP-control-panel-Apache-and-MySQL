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

// --- 3. Get Product ID from URL ---
if (!isset($_GET['id'])) {
    die("Error: No product ID specified.");
}
$product_id = (int)$_GET['id'];
$query_string = http_build_query(['search' => $_GET['search'] ?? '']);

// --- 4. Handle UPDATE Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $sql = "UPDATE products SET product_name = ?, price = ?, stock = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdiii", $product_name, $price, $stock, $product_id, $user_id);

    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Product updated successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();

    header("Location: products.php?" . $query_string);
    exit();
}

// --- 5. Fetch the Product Data for the Form ---
$sql = "SELECT product_name, price, stock FROM products WHERE id = ? AND user_id = ?";
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
</head>
<body class="bg-gray-100">

    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">Invoicer</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Edit Product</h1>
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Update Product Details</h2>
            <form action="edit-product.php?id=<?php echo $product_id; ?>&<?php echo $query_string; ?>" method="POST" class="space-y-4">
                <div>
                    <label for="product_name" class="block text-sm font-medium text-gray-700">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required value="<?php echo htmlspecialchars($product['product_name']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($product['price']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" min="0" required value="<?php echo htmlspecialchars($product['stock']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                </div>
                <div class="flex gap-4">
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Update Product</button>
                    <a href="products.php?<?php echo $query_string; ?>" class="w-full flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>