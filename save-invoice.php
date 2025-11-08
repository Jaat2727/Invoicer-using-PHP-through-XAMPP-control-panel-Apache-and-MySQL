<?php
// --- 1. Start Session & Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Database Connection ---
require_once 'db.php';

// --- 3. Check for POST Data ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data
    $company_id = (int)$_POST['company_id'];
    $invoice_date = $_POST['invoice_date'];
    $vehicle_number = $_POST['vehicle_number'] ?? '';
    $state_of_supply = $_POST['state_of_supply'] ?? '';

    $total_amount = (float)$_POST['total_amount'];

    $invoice_items_json = $_POST['invoice_items'];
    $invoice_items = json_decode($invoice_items_json, true);

    if (empty($invoice_items)) {
        die("Error: No items in invoice.");
    }

    // --- 4. Database Transaction ---
    $conn->begin_transaction();

    try {
        // --- Step A: Get New Invoice Number ---
        $sql_seq = "UPDATE invoice_sequences SET last_invoice_number = last_invoice_number + 1 WHERE user_id = ?";
        $stmt_seq = $conn->prepare($sql_seq);
        $stmt_seq->bind_param("i", $user_id);
        $stmt_seq->execute();
        $stmt_seq->close();

        $sql_get_seq = "SELECT last_invoice_number FROM invoice_sequences WHERE user_id = ?";
        $stmt_get_seq = $conn->prepare($sql_get_seq);
        $stmt_get_seq->bind_param("i", $user_id);
        $stmt_get_seq->execute();
        $result_seq = $stmt_get_seq->get_result();
        $seq_row = $result_seq->fetch_assoc();
        $new_invoice_num = $seq_row['last_invoice_number'];
        $invoice_number = 'INV-' . str_pad($new_invoice_num, 3, '0', STR_PAD_LEFT);
        $stmt_get_seq->close();

        // --- Step B: Insert into 'invoices' table ---
        $sql_invoice = "INSERT INTO invoices (user_id, company_id, invoice_number, invoice_date, vehicle_number, state_of_supply, total_amount, payment_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Overdue')";

        $stmt_invoice = $conn->prepare($sql_invoice);
        $stmt_invoice->bind_param("iissssd", $user_id, $company_id, $invoice_number, $invoice_date, $vehicle_number, $state_of_supply, $total_amount);
        $stmt_invoice->execute();

        $new_invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        // --- Step C: Insert Invoice Items and Update Stock ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);

        $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ? AND user_id = ?";
        $stmt_stock = $conn->prepare($sql_stock);

        $sql_history = "INSERT INTO inventory_history (user_id, product_id, change_quantity, reason, related_invoice_id) VALUES (?, ?, ?, 'Invoice Sale', ?)";
        $stmt_history = $conn->prepare($sql_history);

        foreach ($invoice_items as $item) {
            $product_id = (int)$item['productId'];
            $quantity = (int)$item['quantity'];
            $price = (float)$item['price'];

            // Insert item
            $stmt_items->bind_param("iiid", $new_invoice_id, $product_id, $quantity, $price);
            $stmt_items->execute();

            // Update stock
            $stmt_stock->bind_param("iii", $quantity, $product_id, $user_id);
            $stmt_stock->execute();

            // Log history
            $change_quantity = -$quantity;
            $stmt_history->bind_param("iiii", $user_id, $product_id, $change_quantity, $new_invoice_id);
            $stmt_history->execute();
        }

        $stmt_items->close();
        $stmt_stock->close();
        $stmt_history->close();

        $conn->commit();

        $_SESSION['form_message'] = "<p class='text-green-600'>Invoice ($invoice_number) saved successfully!</p>";
        header("Location: invoice-history.php");
        exit();

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        die("Error saving invoice: ". $exception->getMessage());
    }

} else {
    die("Invalid request.");
}
?>