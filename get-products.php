<?php
// --- 1. Start Session & Check Login ---
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Database Connection ---
require_once 'db.php';

// --- 3. Fetch All Products for this user ---
$products = [];
$sql = "SELECT id, product_name, price, stock FROM products WHERE user_id = ? ORDER BY product_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = [
            'value' => $row['id'],
            'label' => $row['product_name'],
            'customProperties' => [
                'price' => $row['price'],
                'stock' => $row['stock']
            ]
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode($products);
exit();
?>