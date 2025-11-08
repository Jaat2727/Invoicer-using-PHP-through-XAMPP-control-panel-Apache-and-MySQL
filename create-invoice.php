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

// --- 3. Fetch Company Settings and Companies ---
$settings = [];
$sql_settings = "SELECT state, default_cgst_rate, default_sgst_rate, default_igst_rate FROM settings WHERE user_id = ?";
$stmt_settings = $conn->prepare($sql_settings);
$stmt_settings->bind_param("i", $user_id);
$stmt_settings->execute();
$result_settings = $stmt_settings->get_result();
if ($result_settings->num_rows == 1) {
    $settings = $result_settings->fetch_assoc();
} else {
    header("Location: settings.php?message=error&data=" . urlencode("Please set up your company settings first."));
    exit();
}
$stmt_settings->close();

$companies = [];
$sql_companies = "SELECT id, company_name, state FROM companies WHERE user_id = ? ORDER BY company_name ASC";
$stmt_companies = $conn->prepare($sql_companies);
$stmt_companies->bind_param("i", $user_id);
$stmt_companies->execute();
$result_companies = $stmt_companies->get_result();
while($row = $result_companies->fetch_assoc()) {
    $companies[] = $row;
}
$stmt_companies->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Invoice - SaaS Invoicer</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
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
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium">Create Invoice</a>
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

        <h1 class="text-3xl font-bold text-gray-900 mb-6">Create New Invoice</h1>

        <form id="invoice-form" method="POST" action="save-invoice.php">

            <input type="hidden" name="invoice_items" id="invoice_items_json">
            <input type="hidden" name="total_amount" id="grandtotal_hidden">

            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Invoice Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label for="company_id" class="block text-sm font-medium text-gray-700">Bill To (Customer)</label>
                        <select id="company_id" name="company_id" required>
                            <option value="">Select a Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" data-state="<?php echo htmlspecialchars($company['state']); ?>">
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="invoice_date" class="block text-sm font-medium text-gray-700">Invoice Date</label>
                        <input type="date" id="invoice_date" name="invoice_date" required value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label for="vehicle_number" class="block text-sm font-medium text-gray-700">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div>
                        <label for="state_of_supply" class="block text-sm font-medium text-gray-700">State of Supply</label>
                        <input type="text" id="state_of_supply" name="state_of_supply" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100" readonly>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add Items</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label for="product_id" class="block text-sm font-medium text-gray-700">Product</label>
                        <select id="product_id">
                            <option value="">Select a product</option>
                        </select>
                        <span id="stock-info" class="text-xs text-gray-500 mt-1 block"></span>
                    </div>
                    <div>
                        <label for="item_price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                        <input type="number" id="item_price" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border bg-gray-100" readonly>
                    </div>
                    <div>
                        <label for="item_quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                        <input type="number" id="item_quantity" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border">
                    </div>
                    <div class="md:col-span-1">
                        <button type="button" id="add-item-button" class="w-full justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Add Item</button>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table id="invoice-items-table" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price (₹)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total (₹)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr id="no-items-row">
                            <td colspan="5" class="px-6 py-4 text-center">No items added yet.</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right">Subtotal</td>
                            <td id="subtotal" class="px-6 py-3">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="cgst-row" style="display: none;">
                            <td colspan="3" class="px-6 py-3 text-right">CGST</td>
                            <td id="cgst-amount" class="px-6 py-3">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="sgst-row" style="display: none;">
                            <td colspan="3" class="px-6 py-3 text-right">SGST</td>
                            <td id="sgst-amount" class="px-6 py-3">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="igst-row" style="display: none;">
                            <td colspan="3" class="px-6 py-3 text-right">IGST</td>
                            <td id="igst-amount" class="px-6 py-3">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right font-bold">Grand Total</td>
                            <td id="grandtotal" class="px-6 py-3 font-bold">₹0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-6 text-right">
                <button type="submit" id="save-invoice-button" class="py-3 px-6 border rounded-md shadow-sm text-lg font-medium text-white bg-green-600 hover:bg-green-700">Save Invoice</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

    <script>
        const myCompanyState = "<?php echo $settings['state']; ?>";
        const taxRates = {
            cgst: <?php echo (float)$settings['default_cgst_rate']; ?>,
            sgst: <?php echo (float)$settings['default_sgst_rate']; ?>,
            igst: <?php echo (float)$settings['default_igst_rate']; ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            const companySelect = new Choices('#company_id');
            const productSelect = new Choices('#product_id');
            let productData = [];
            let invoiceItems = {};

            fetch('get-products.php')
                .then(response => response.json())
                .then(data => {
                    productData = data;
                    const choices = [{ value: '', label: 'Select a product' }, ...data];
                    productSelect.setChoices(choices, 'value', 'label', true);
                });

            companySelect.passedElement.element.addEventListener('change', function(event) {
                const companyId = event.detail.value;
                const selectedOption = companySelect.passedElement.element.querySelector(`option[value="${companyId}"]`);
                document.getElementById('state_of_supply').value = selectedOption ? selectedOption.dataset.state : "";
                updateTotals();
            });

            productSelect.passedElement.element.addEventListener('change', function(event) {
                const productId = event.detail.value;
                const product = productData.find(p => p.value == productId);
                if (product) {
                    document.getElementById('item_price').value = product.customProperties.price;
                    updateStockInfo(productId);
                }
            });

            document.getElementById('add-item-button').addEventListener('click', function() {
                const productId = productSelect.getValue(true);
                if (!productId) { return; }

                const product = productData.find(p => p.value == productId);
                const price = parseFloat(document.getElementById('item_price').value);
                const quantity = parseInt(document.getElementById('item_quantity').value);
                const availableStock = product.customProperties.stock;
                const quantityInCart = invoiceItems[productId] ? invoiceItems[productId].quantity : 0;

                if (quantity + quantityInCart > availableStock) {
                    alert(`Cannot add ${quantity}. You only have ${availableStock} available.`);
                    return;
                }

                if (invoiceItems[productId]) {
                    invoiceItems[productId].quantity += quantity;
                } else {
                    invoiceItems[productId] = {
                        label: product.label,
                        quantity: quantity,
                        price: price,
                    };
                }
                renderTable();
                updateTotals();
            });

            function renderTable() {
                const tableBody = document.querySelector('#invoice-items-table tbody');
                tableBody.innerHTML = '';
                if (Object.keys(invoiceItems).length === 0) {
                    tableBody.innerHTML = '<tr id="no-items-row"><td colspan="5" class="px-6 py-4 text-center">No items added yet.</td></tr>';
                    return;
                }
                for (const id in invoiceItems) {
                    const item = invoiceItems[id];
                    const total = item.quantity * item.price;
                    const row = `
                        <tr>
                            <td class="px-6 py-4">${item.label}</td>
                            <td class="px-6 py-4">${item.quantity}</td>
                            <td class="px-6 py-4">${item.price.toFixed(2)}</td>
                            <td class="px-6 py-4">${total.toFixed(2)}</td>
                            <td class="px-6 py-4"><button type="button" class="text-red-600" data-id="${id}">Remove</button></td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                }
            }

            document.querySelector('#invoice-items-table tbody').addEventListener('click', function(event) {
                if (event.target.tagName === 'BUTTON') {
                    const id = event.target.dataset.id;
                    delete invoiceItems[id];
                    renderTable();
                    updateTotals();
                }
            });

            function updateTotals() {
                let subtotal = 0;
                for (const id in invoiceItems) {
                    subtotal += invoiceItems[id].quantity * invoiceItems[id].price;
                }

                const customerState = document.getElementById('state_of_supply').value;
                let cgst = 0, sgst = 0, igst = 0;

                if (myCompanyState && customerState && myCompanyState !== customerState) {
                    igst = subtotal * (taxRates.igst / 100);
                    document.getElementById('igst-row').style.display = 'table-row';
                    document.getElementById('cgst-row').style.display = 'none';
                    document.getElementById('sgst-row').style.display = 'none';
                } else {
                    cgst = subtotal * (taxRates.cgst / 100);
                    sgst = subtotal * (taxRates.sgst / 100);
                    document.getElementById('igst-row').style.display = 'none';
                    document.getElementById('cgst-row').style.display = 'table-row';
                    document.getElementById('sgst-row').style.display = 'table-row';
                }

                document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
                document.getElementById('cgst-amount').textContent = `₹${cgst.toFixed(2)}`;
                document.getElementById('sgst-amount').textContent = `₹${sgst.toFixed(2)}`;
                document.getElementById('igst-amount').textContent = `₹${igst.toFixed(2)}`;
                document.getElementById('grandtotal').textContent = `₹${(subtotal + cgst + sgst + igst).toFixed(2)}`;
                document.getElementById('grandtotal_hidden').value = subtotal + cgst + sgst + igst;
            }

            function updateStockInfo(productId) {
                if (!productId) {
                    document.getElementById('stock-info').textContent = '';
                    return;
                }
                const product = productData.find(p => p.value == productId);
                if (product) {
                    const available = product.customProperties.stock;
                    const inCart = invoiceItems[productId] ? invoiceItems[productId].quantity : 0;
                    const remaining = available - inCart;
                    document.getElementById('stock-info').textContent = `(Available: ${remaining})`;
                }
            }

            document.getElementById('invoice-form').addEventListener('submit', function(event) {
                document.getElementById('invoice_items_json').value = JSON.stringify(Object.keys(invoiceItems).map(id => ({
                    productId: id,
                    quantity: invoiceItems[id].quantity,
                    price: invoiceItems[id].price
                })));
            });
        });
    </script>
</body>
</html>