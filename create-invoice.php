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

// --- 3. Fetch *YOUR* Company Settings (for GST calculation) ---
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

// --- 4. Fetch All *CUSTOMER* Companies (for the dropdown) ---
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
        .choices__input { padding: 0 !important; }
        .choices[data-type*="select-one"]::after { display: none; }
        #invoice-items-table th, #invoice-items-table td { padding: 12px 16px; }
        .tax-row { display: none; }
    </style>
</head>
<body class="bg-gray-100">
    
    <!-- NAVIGATION BAR (Fixed) -->
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
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Create Invoice</a>
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
                <a href="products.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Products</a>
                <a href="create-invoice.php" class="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Create Invoice</a>
                <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Invoice History</a>
                <a href="settings.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Create New Invoice</h1>

        <form id="invoice-form" method="POST" action="save-invoice.php">
        
            <input type="hidden" name="invoice_items" id="invoice_items_json">
            <input type="hidden" name="subtotal" id="subtotal_hidden">
            <input type="hidden" name="cgst_amount" id="cgst_hidden">
            <input type="hidden" name="sgst_amount" id="sgst_hidden">
            <input type="hidden" name="igst_amount" id="igst_hidden">
            <input type="hidden" name="total_amount" id="grandtotal_hidden">

            <!-- Invoice Details Card -->
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
                        <input type="date" id="invoice_date" name="invoice_date" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="vehicle_number" class="block text-sm font-medium text-gray-700">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    
                    <!-- NEW: GST TYPE OVERRIDE -->
                    <div>
                        <label for="gst_type" class="block text-sm font-medium text-gray-700">GST Type</label>
                        <select id="gst_type" name="gst_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                            <option value="auto">Auto-Detect</option>
                            <option value="intra">Intrastate (CGST/SGST)</option>
                            <option value="inter">Interstate (IGST)</option>
                        </select>
                    </div>
                    <!-- State of Supply (now auto-filled) -->
                    <div>
                        <label for="state_of_supply" class="block text-sm font-medium text-gray-700">State of Supply</label>
                        <input type="text" id="state_of_supply" name="state_of_supply"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-gray-100" readonly>
                    </div>
                </div>
            </div>

            <!-- Add Items Card (Unchanged) -->
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add Items</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div class="md:col-span-2">
                        <label for="product_id" class="block text-sm font-medium text-gray-700">Product</label>
                        <select id="product_id">
                            <option value="">Select a company first</option>
                        </select>
                        <span id="stock-info" class="text-xs text-gray-500 mt-1 block"></span>
                    </div>
                    <div>
                        <label for="item_price" class="block text-sm font-medium text-gray-700">Price (₹)</label>
                        <input type="number" id="item_price" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-gray-100" readonly>
                    </div>
                    <div>
                        <label for="item_quantity" class="block text-sm font-medium text-gray-700">Quantity</label>
                        <input type="number" id="item_quantity" min="1" value="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div class="md:col-span-1">
                        <button type="button" id="add-item-button" class="w-full justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Add Item
                        </button>
                    </div>
                </div>
            </div>

            <!-- Invoice Items Table (Unchanged) -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table id="invoice-items-table" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price (₹)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total (₹)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr id="no-items-row">
                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                No items added yet.
                            </td>
                        </tr>
                    </tbody>
                    <!-- Totals Footer (Unchanged) -->
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-700">Subtotal</td>
                            <td id="subtotal" class="px-6 py-3 text-left text-sm font-bold text-gray-900">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="cgst-row" class="tax-row">
                            <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-700">
                                CGST @ <span id="cgst-rate"><?php echo $settings['default_cgst_rate']; ?></span>%
                            </td>
                            <td id="cgst-amount" class="px-6 py-3 text-left text-sm font-bold text-gray-900">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="sgst-row" class="tax-row">
                            <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-700">
                                SGST @ <span id="sgst-rate"><?php echo $settings['default_sgst_rate']; ?></span>%
                            </td>
                            <td id="sgst-amount" class="px-6 py-3 text-left text-sm font-bold text-gray-900">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr id="igst-row" class="tax-row">
                            <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-700">
                                IGST @ <span id="igst-rate"><?php echo $settings['default_igst_rate']; ?></span>%
                            </td>
                            <td id="igst-amount" class="px-6 py-3 text-left text-sm font-bold text-gray-900">₹0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="px-6 py-3 text-right text-lg font-bold text-gray-700">Grand Total</td>
                            <td id="grandtotal" class="px-6 py-3 text-left text-lg font-bold text-gray-900">₹0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Save Invoice Button (Unchanged) -->
            <div class="mt-6 text-right">
                <button type="submit" id="save-invoice-button" class="py-3 px-6 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Save Invoice
                </button>
            </div>
        </form>
    </div>

    <!-- Choices.js Script -->
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    
    <!-- Main JavaScript Logic for the page -->
    <script>
        // --- 0. Get Data from PHP ---
        const myCompanyState = "<?php echo $settings['state']; ?>";
        const taxRates = {
            cgst: <?php echo (float)$settings['default_cgst_rate']; ?>,
            sgst: <?php echo (float)$settings['default_sgst_rate']; ?>,
            igst: <?php echo (float)$settings['default_igst_rate']; ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            // --- 1. Initialize Dropdowns ---
            const companySelect = new Choices('#company_id', {
                searchEnabled: true, searchPlaceholderValue: 'Search customers...', itemSelectText: '',
            });
            const productSelect = new Choices('#product_id', {
                searchEnabled: true, searchPlaceholderValue: 'Search products...', itemSelectText: '',
            });

            // --- 2. State Variables ---
            let productData = []; 
            let invoiceItems = {}; 
            let customerState = ""; 
            
            // --- NEW: GST Override Variable ---
            let gstOverride = 'auto'; // 'auto', 'intra', 'inter'

            // Get DOM Elements
            const itemPriceInput = document.getElementById('item_price');
            const itemQuantityInput = document.getElementById('item_quantity');
            const stockInfo = document.getElementById('stock-info');
            const addItemButton = document.getElementById('add-item-button');
            const itemsTableBody = document.querySelector('#invoice-items-table tbody');
            const noItemsRow = document.getElementById('no-items-row');
            const stateOfSupplyInput = document.getElementById('state_of_supply');
            const invoiceForm = document.getElementById('invoice-form');
            
            // --- NEW: GST Type Dropdown ---
            const gstTypeSelect = document.getElementById('gst_type');

            // Tax Rows
            const cgstRow = document.getElementById('cgst-row');
            const sgstRow = document.getElementById('sgst-row');
            const igstRow = document.getElementById('igst-row');

            // --- 3. Handle Company Change ---
            companySelect.passedElement.element.addEventListener('change', function(event) {
                const companyId = event.detail.value;
                const selectedOption = companySelect.passedElement.element.querySelector(`option[value="${companyId}"]`);
                customerState = selectedOption ? selectedOption.dataset.state : "";
                
                stateOfSupplyInput.value = customerState; // Auto-fill State of Supply

                // Trigger GST check
                checkGstLogic(); 
                
                // Fetch products (unchanged)
                if (!companyId) {
                    productSelect.clearStore();
                    productSelect.setChoices([{ value: '', label: 'Select a company first' }], 'value', 'label', true);
                    return;
                }
                fetch(`get-products.php?company_id=${companyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) { throw new Error(data.error); }
                        productData = data; 
                        
                        if (data.length === 0) {
                            productSelect.clearStore();
                            productSelect.setChoices([{ value: '', label: 'No products for this company' }], 'value', 'label', true);
                        } else {
                            const choices = [{ value: '', label: 'Select a product' }, ...data];
                            productSelect.clearStore();
                            productSelect.setChoices(choices, 'value', 'label', true);
                        }
                    })
                    .catch(error => { console.error('Error fetching products:', error); alert('Error fetching products.'); });
            });
            
            // --- NEW: Handle GST Type Override ---
            gstTypeSelect.addEventListener('change', function() {
                gstOverride = this.value; // Update our override variable
                checkGstLogic(); // Re-run the logic
            });

            // --- NEW: Central GST Logic Function ---
            function checkGstLogic() {
                let isInterstate = false; // Default
                
                // Determine if it's Inter or Intra
                if (gstOverride === 'auto') {
                    // AUTO-DETECT
                    if (myCompanyState && customerState && myCompanyState !== customerState) {
                        isInterstate = true;
                    }
                } else if (gstOverride === 'inter') {
                    // FORCE INTERSTATE
                    isInterstate = true;
                } else if (gstOverride === 'intra') {
                    // FORCE INTRASTATE
                    isInterstate = false;
                }

                // Show/Hide the correct rows based on the result
                if (isInterstate) {
                    cgstRow.style.display = 'none';
                    sgstRow.style.display = 'none';
                    igstRow.style.display = 'table-row';
                } else {
                    cgstRow.style.display = 'table-row';
                    sgstRow.style.display = 'table-row';
                    igstRow.style.display = 'none';
                }
                
                // Pass the final decision to the totals calculator
                updateTotals(isInterstate);
            }


            // --- (All other functions from 4 to 8 are the same as before) ---
            
            // --- 4. Update Price & Stock when Product Changes ---
            productSelect.passedElement.element.addEventListener('change', function(event) {
                const productId = event.detail.value;
                if (!productId) {
                    itemPriceInput.value = '';
                    stockInfo.textContent = '';
                    return;
                }
                const product = productData.find(p => p.value == productId);
                if (product) {
                    itemPriceInput.value = product.customProperties.price;
                    updateStockInfo(productId);
                }
            });

            // --- 5. Add Item to Table ---
            addItemButton.addEventListener('click', function() {
                const productId = productSelect.getValue(true);
                if (!productId) { alert('Please select a product.'); return; }
                
                const productLabel = productSelect.getValue(false).label; 
                const price = parseFloat(itemPriceInput.value);
                let quantity = parseInt(itemQuantityInput.value);

                if (isNaN(price) || price < 0) { alert('Invalid price.'); return; }
                if (isNaN(quantity) || quantity <= 0) { quantity = 1; }

                const product = productData.find(p => p.value == productId);
                const availableStock = product.customProperties.stock;
                const quantityInCart = invoiceItems[productId] ? invoiceItems[productId].quantity : 0;
                
                if (quantity + quantityInCart > availableStock) {
                    alert(`Cannot add ${quantity}. \nStock Error: You only have ${availableStock} available, and ${quantityInCart} are already in your cart. \n\nYou can add ${availableStock - quantityInCart} more.`);
                    return;
                }

                if (noItemsRow) { noItemsRow.style.display = 'none'; }

                invoiceItems[productId] = {
                    quantity: (quantityInCart + quantity),
                    price: price,
                    label: productLabel
                };

                const existingRow = document.getElementById(`item-row-${productId}`);
                
                if (existingRow) {
                    existingRow.cells[1].textContent = invoiceItems[productId].quantity;
                    existingRow.cells[3].textContent = (invoiceItems[productId].quantity * price).toFixed(2);
                } else {
                    const newRow = itemsTableBody.insertRow();
                    newRow.id = `item-row-${productId}`;
                    newRow.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${productLabel}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${invoiceItems[productId].quantity}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${price.toFixed(2)}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${(invoiceItems[productId].quantity * price).toFixed(2)}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button type="button" class="text-red-600 hover:text-red-900" data-product-id="${productId}">Remove</button>
                        </td>
                    `;
                }
                checkGstLogic(); // This will call updateTotals with the correct tax state
                updateStockInfo(productId);
                resetAddItemForm();
            });
            
            // --- 6. Handle Item Removal ---
            itemsTableBody.addEventListener('click', function(event) {
                if (event.target.tagName === 'BUTTON' && event.target.dataset.productId) {
                    const productId = event.target.dataset.productId;
                    event.target.closest('tr').remove();
                    delete invoiceItems[productId];
                    if (Object.keys(invoiceItems).length === 0 && noItemsRow) {
                        noItemsRow.style.display = 'table-row';
                    }
                    checkGstLogic(); // This will call updateTotals
                    const currentProductId = productSelect.getValue(true);
                    if(currentProductId) {
                         updateStockInfo(currentProductId);
                    }
                }
            });

            // --- 7. Handle Form Submission ---
            invoiceForm.addEventListener('submit', function(event) {
                if (Object.keys(invoiceItems).length === 0) {
                    alert('You cannot save an empty invoice. Please add at least one item.');
                    event.preventDefault(); 
                    return;
                }
                
                const itemsToSubmit = Object.keys(invoiceItems).map(id => {
                    return {
                        productId: id,
                        quantity: invoiceItems[id].quantity,
                        price: invoiceItems[id].price
                    };
                });
                document.getElementById('invoice_items_json').value = JSON.stringify(itemsToSubmit);
                
                // --- MODIFIED: We check the FINAL decision on GST type ---
                const subtotal = calculateSubtotal();
                document.getElementById('subtotal_hidden').value = subtotal;
                
                let isFinalInterstate = false;
                if (gstOverride === 'auto') {
                    if (myCompanyState && customerState && myCompanyState !== customerState) {
                        isFinalInterstate = true;
                    }
                } else if (gstOverride === 'inter') {
                    isFinalInterstate = true;
                }
                
                const grandTotal = calculateGrandTotal(isFinalInterstate);
                document.getElementById('grandtotal_hidden').value = grandTotal;
                
                if(isFinalInterstate) {
                    document.getElementById('igst_hidden').value = calculateTax(subtotal, taxRates.igst);
                    document.getElementById('cgst_hidden').value = 0;
                    document.getElementById('sgst_hidden').value = 0;
                } else {
                    document.getElementById('igst_hidden').value = 0;
                    document.getElementById('cgst_hidden').value = calculateTax(subtotal, taxRates.cgst);
                    document.getElementById('sgst_hidden').value = calculateTax(subtotal, taxRates.sgst);
                }
            });

            // --- 8. Helper Functions ---
            function calculateSubtotal() {
                let subtotal = 0;
                Object.keys(invoiceItems).forEach(id => {
                    subtotal += invoiceItems[id].price * invoiceItems[id].quantity;
                });
                return subtotal;
            }

            function calculateTax(amount, rate) {
                return (amount * (rate / 100));
            }

            // --- MODIFIED: Now takes 'isInterstate' as an argument ---
            function calculateGrandTotal(isInterstate) {
                const subtotal = calculateSubtotal();
                let total = subtotal;
                if (isInterstate) {
                    total += calculateTax(subtotal, taxRates.igst);
                } else {
                    total += calculateTax(subtotal, taxRates.cgst);
                    total += calculateTax(subtotal, taxRates.sgst);
                }
                return total;
            }

            // --- MODIFIED: Now takes 'isInterstate' as an argument ---
            function updateTotals(isInterstate) {
                const subtotal = calculateSubtotal();
                document.getElementById('subtotal').textContent = `₹${subtotal.toFixed(2)}`;
                let grandTotal = subtotal;

                if (isInterstate) {
                    const igst = calculateTax(subtotal, taxRates.igst);
                    document.getElementById('igst-amount').textContent = `₹${igst.toFixed(2)}`;
                    grandTotal += igst;
                } else {
                    const cgst = calculateTax(subtotal, taxRates.cgst);
                    const sgst = calculateTax(subtotal, taxRates.sgst);
                    document.getElementById('cgst-amount').textContent = `₹${cgst.toFixed(2)}`;
                    document.getElementById('sgst-amount').textContent = `₹${sgst.toFixed(2)}`;
                    grandTotal += cgst + sgst;
                }
                document.getElementById('grandtotal').textContent = `₹${grandTotal.toFixed(2)}`;
            }
            
            function resetAddItemForm() {
                productSelect.setChoiceByValue('');
                itemPriceInput.value = '';
                itemQuantityInput.value = '1';
                stockInfo.textContent = '';
            }

            function updateStockInfo(productId) {
                if (!productId) {
                    stockInfo.textContent = '';
                    return;
                }
                const product = productData.find(p => p.value == productId);
                if (product) {
                    const available = product.customProperties.stock;
                    const inCart = invoiceItems[productId] ? invoiceItems[productId].quantity : 0;
                    const remaining = available - inCart;
                    stockInfo.textContent = `(Available: ${available} / In Cart: ${inCart} / Remaining: ${remaining})`;
                }
            }

            // Initial GST check on page load (in case form is pre-filled, etc.)
            checkGstLogic();
        });
    </script>
</body>
</html>