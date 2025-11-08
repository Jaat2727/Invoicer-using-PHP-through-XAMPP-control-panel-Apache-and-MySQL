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

$form_message = "";

// --- 3. Handle Form Submission (INSERT or UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get all form data
    $company_name = $_POST['company_name'] ?? '';
    $gstin = $_POST['gstin'] ?? '';
    $pan = $_POST['pan'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';
    $upi_id = $_POST['upi_id'] ?? '';
    $tagline = $_POST['tagline'] ?? '';
    $address = $_POST['address'] ?? '';
    $my_state = $_POST['my_state'] ?? ''; // The 'state' field

    // Get tax rates
    $default_cgst_rate = (float)($_POST['default_cgst_rate'] ?? 9.00);
    $default_sgst_rate = (float)($_POST['default_sgst_rate'] ?? 9.00);
    $default_igst_rate = (float)($_POST['default_igst_rate'] ?? 18.00);

    // This is the "UPSERT" query that includes the 'state' column
    $sql = "INSERT INTO settings (user_id, company_name, gstin, pan, mobile, email, upi_id, tagline, address, state, default_cgst_rate, default_sgst_rate, default_igst_rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company_name = VALUES(company_name),
                gstin = VALUES(gstin),
                pan = VALUES(pan),
                mobile = VALUES(mobile),
                email = VALUES(email),
                upi_id = VALUES(upi_id),
                tagline = VALUES(tagline),
                address = VALUES(address),
                state = VALUES(state),
                default_cgst_rate = VALUES(default_cgst_rate),
                default_sgst_rate = VALUES(default_sgst_rate),
                default_igst_rate = VALUES(default_igst_rate)";

    $stmt = $conn->prepare($sql);
    // The bind_param must match all 13 fields (i, 9*s, 3*d)
    $stmt->bind_param("isssssssssddd", $user_id, $company_name, $gstin, $pan, $mobile, $email, $upi_id, $tagline, $address, $my_state, $default_cgst_rate, $default_sgst_rate, $default_igst_rate);

    if ($stmt->execute()) {
        $form_message = "<p class='text-green-600'>Settings saved successfully!</p>";
    } else {
        $form_message = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

// --- 4. Fetch Existing Settings (to pre-fill the form) ---
$settings = [];
$sql_fetch = "SELECT * FROM settings WHERE user_id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $user_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
if ($result->num_rows == 1) {
    $settings = $result->fetch_assoc();
}
$stmt_fetch->close();
$conn->close();

// Helper function to easily get values and escape them
function get_setting($settings, $key) {
    return htmlspecialchars($settings[$key] ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>My Company Settings - SaaS Invoicer</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { 'sans': ['Inter', 'sans-serif'], }, }, },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
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
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Create Invoice</a>
                        <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Invoice History</a>
                        <a href="inventory.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Inventory</a>
                        <a href="settings.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Settings</a>
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
                <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Create Invoice</a>
                <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Invoice History</a>
                <a href="inventory.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Inventory</a>
                <a href="settings.php" class="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">

        <h1 class="text-3xl font-bold text-gray-900 mb-6">My Company Settings</h1>

        <form action="settings.php" method="POST" class="space-y-6">

            <!-- Form Message -->
            <div id="form-message" class="text-center">
                <?php echo $form_message; ?>
            </div>

            <!-- Company Info Card -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Your Company Information</h2>
                <p class="text-sm text-gray-600 mb-6">This information will appear on all your invoices.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Your Company Name</label>
                        <input type="text" id="company_name" name="company_name" required
                               value="<?php echo get_setting($settings, 'company_name'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="gstin" class="block text-sm font-medium text-gray-700">Your GSTIN</label>
                        <input type="text" id="gstin" name="gstin"
                               value="<?php echo get_setting($settings, 'gstin'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="pan" class="block text-sm font-medium text-gray-700">Your PAN</label>
                        <input type="text" id="pan" name="pan"
                               value="<?php echo get_setting($settings, 'pan'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="mobile" class="block text-sm font-medium text-gray-700">Your Mobile</label>
                        <input type="text" id="mobile" name="mobile"
                               value="<?php echo get_setting($settings, 'mobile'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Your Email</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo get_setting($settings, 'email'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="upi_id" class="block text-sm font-medium text-gray-700">Your UPI ID</label>
                        <input type="text" id="upi_id" name="upi_id"
                               value="<?php echo get_setting($settings, 'upi_id'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>

                    <!-- Your State -->
                    <div class="col-span-1">
                        <label for="my_state" class="block text-sm font-medium text-gray-700">Your State (for GST)</label>
                        <select id="my_state" name="my_state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                            <option value="">Select Your State / UT</option>
                            <?php
                            $states_list = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chandigarh", "Chhattisgarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jammu and Kashmir", "Jharkhand", "Karnataka", "Kerala", "Ladakh", "Lakshadweep", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Puducherry", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal"];
                            $current_state = get_setting($settings, 'state');
                            foreach ($states_list as $state) {
                                $selected = ($current_state == $state) ? 'selected' : '';
                                echo "<option value=\"$state\" $selected>$state</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-span-1">
                        <label for="tagline" class="block text-sm font-medium text-gray-700">Invoice Tagline (Optional)</label>
                        <input type="text" id="tagline" name="tagline"
                               value="<?php echo get_setting($settings, 'tagline'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700">Your Address</label>
                        <textarea id="address" name="address" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"><?php echo get_setting($settings, 'address'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Default Tax Rates Card -->
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Default Tax Rates</h2>
                <p class="text-sm text-gray-600 mb-6">These rates will be used to auto-calculate GST on new invoices.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="default_cgst_rate" class="block text-sm font-medium text-gray-700">Default CGST Rate (%)</label>
                        <input type="number" step="0.01" min="0" id="default_cgst_rate" name="default_cgst_rate"
                               value="<?php echo get_setting($settings, 'default_cgst_rate'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="default_sgst_rate" class="block text-sm font-medium text-gray-700">Default SGST Rate (%)</label>
                        <input type="number" step="0.01" min="0" id="default_sgst_rate" name="default_sgst_rate"
                               value="<?php echo get_setting($settings, 'default_sgst_rate'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                    <div>
                        <label for="default_igst_rate" class="block text-sm font-medium text-gray-700">Default IGST Rate (%)</label>
                        <input type="number" step="0.01" min="0" id="default_igst_rate" name="default_igst_rate"
                               value="<?php echo get_setting($settings, 'default_igst_rate'); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="text-right">
                <button type="submit"
                        class="inline-flex justify-center py-3 px-6 border border-transparent rounded-md shadow-sm text-lg font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Settings
                </button>
            </div>

        </form>
    </div>

</body>
</html>