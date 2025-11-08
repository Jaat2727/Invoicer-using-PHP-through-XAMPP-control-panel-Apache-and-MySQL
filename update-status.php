<?php
// --- 1. Start Session & Check Login ---
session_start();
header('Content-Type: application/json'); // Set header to return JSON

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Get Data from JavaScript (fetch) ---
// We are reading 'php://input' because the data is sent as JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['invoice_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit();
}

$invoice_id = (int)$data['invoice_id'];
$status = $data['status'];
$allowed_statuses = ['Overdue', 'Paid', 'Ongoing']; // Whitelist for security

if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit();
}

// --- 3. Database Connection ---
require_once 'db.php';

// --- 4. Update the Invoice ---
// We check BOTH invoice_id AND user_id for security
$sql = "UPDATE invoices SET payment_status = ? WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $status, $invoice_id, $user_id);

if ($stmt->execute()) {
    // Success!
    echo json_encode(['success' => true, 'message' => 'Status updated.']);
} else {
    // Failed
    echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
}

$stmt->close();
$conn->close();
exit();
?>