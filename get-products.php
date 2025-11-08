<?php
// --- 1. Start Session & Check Login ---
session_start();
header('Content-Type: application/json'); // Set header to return JSON

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Get Company ID from the request ---
if (!isset($_GET['company_id'])) {
    echo json_encode(['error' => 'No company ID provided']);
    exit();
}
$company_id = (int)$_GET['company_id'];

// --- 3. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoicer_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// --- 4. Fetch Products for this company and user ---
$products = [];
$sql = "SELECT id, product_name, price, stock 
        FROM products 
        WHERE user_id = ? AND company_id = ?
        ORDER BY product_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // We use 'value' and 'label' for Choices.js
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

// --- 5. Return products as JSON ---
echo json_encode($products);
exit();
?>