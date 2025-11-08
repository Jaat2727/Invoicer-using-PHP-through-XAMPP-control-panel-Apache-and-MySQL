<?php
// --- 1. Start Session & Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoicer_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. Check for POST Data ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $company_id = (int)$_POST['company_id'];
    $invoice_date = $_POST['invoice_date'];
    $vehicle_number = $_POST['vehicle_number'] ?? '';
    $state_of_supply = $_POST['state_of_supply'] ?? '';
    
    // *** Get all the calculated amounts ***
    $subtotal = (float)$_POST['subtotal'];
    $cgst_amount = (float)$_POST['cgst_amount'];
    $sgst_amount = (float)$_POST['sgst_amount'];
    $igst_amount = (float)$_POST['igst_amount'];
    $total_amount = (float)$_POST['total_amount']; // This is the Grand Total

    // Get the JSON string of items and decode it
    $invoice_items_json = $_POST['invoice_items'];
    $invoice_items = json_decode($invoice_items_json, true);

    if (empty($invoice_items)) {
        die("Error: No items in invoice.");
    }

    // --- 4. Database Transaction ---
    $conn->begin_transaction();

    try {
        // --- Step A: Insert into 'invoices' table ---
        // We'll get a unique invoice number later. For now, just a placeholder.
        $invoice_number = "INV-" . time(); 
        
        // *** This SQL is now correct and includes all tax columns ***
        $sql_invoice = "INSERT INTO invoices (user_id, company_id, invoice_number, invoice_date, vehicle_number, 
                                            state_of_supply, total_amount, payment_status,
                                            cgst_amount, sgst_amount, igst_amount)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Overdue', ?, ?, ?)";
        
        $stmt_invoice = $conn->prepare($sql_invoice);
        // *** Bind_param matches all 10 fields ***
        $stmt_invoice->bind_param("iissssdddd", $user_id, $company_id, $invoice_number, $invoice_date, $vehicle_number, 
                                            $state_of_supply, $total_amount, 
                                            $cgst_amount, $sgst_amount, $igst_amount);
        $stmt_invoice->execute();
        
        $new_invoice_id = $conn->insert_id;
        $stmt_invoice->close();

        // --- Step B: Insert into 'invoice_items' table (Unchanged) ---
        $sql_items = "INSERT INTO invoice_items (invoice_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)";
        $stmt_items = $conn->prepare($sql_items);
        
        // --- Step C: Update 'products' stock (Unchanged) ---
        $sql_stock = "UPDATE products SET stock = stock - ? WHERE id = ? AND user_id = ?";
        $stmt_stock = $conn->prepare($sql_stock);

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
        }
        
        $stmt_items->close();
        $stmt_stock->close();

        // If all went well, commit the transaction
        $conn->commit();
        
        // --- 5. Redirect to Invoice History ---
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