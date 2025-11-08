<?php
// --- 1. Start Session & Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Get Parameters ---
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    die("Invalid request.");
}
$type = $_GET['type'];
$id = (int)$_GET['id']; // Cast to integer for security

// --- 3. Database Connection ---
require_once 'db.php';

// --- 4. Prepare SQL based on type ---
$sql = "";
$redirect_page = "";

if ($type == 'company') {
    $sql = "DELETE FROM companies WHERE id = ? AND user_id = ?";
    $redirect_page = "companies.php";
} elseif ($type == 'product') {
    $sql = "DELETE FROM products WHERE id = ? AND user_id = ?";
    $redirect_page = "products.php";
} else {
    die("Invalid type.");
}

// --- 5. Execute Delete ---
// We include user_id in the WHERE clause. This is a CRITICAL security check.
// It ensures you can ONLY delete data that you OWN.
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);

if ($stmt->execute()) {
    $_SESSION['form_message'] = "<p class='text-green-600'>Item deleted successfully!</p>";
} else {
    // Handle foreign key constraint error (e.g., trying to delete a company that has invoices)
    if ($conn->errno == 1451) {
        $_SESSION['form_message'] = "<p class='text-red-600'>Cannot delete: This item is already used in an invoice.</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
}

$stmt->close();
$conn->close();

// --- 6. Redirect Back ---
header("Location: $redirect_page");
exit();
?>