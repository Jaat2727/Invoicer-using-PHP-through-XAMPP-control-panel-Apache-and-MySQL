<?php
// --- 1. Start the Session and Check Login ---
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email']; // for the nav bar

// --- 2. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "invoicer_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- 3. Get Search Parameters ---
$search_term = $_GET['search'] ?? ''; // Get search term from URL
$query_string = http_build_query(['search' => $search_term]); // Build query string for links

// --- 4. Handle Form Submission (Add Company) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['company_name'])) {
    
    $company_name = $_POST['company_name'];
    $gstin = $_POST['gstin'];
    $state = $_POST['state'];
    $state_code = $_POST['state_code'];
    $address = $_POST['address'];

    $sql = "INSERT INTO companies (user_id, company_name, gstin, state, state_code, address) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $user_id, $company_name, $gstin, $state, $state_code, $address);
    
    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Company added successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
    
    // Redirect to prevent form resubmission
    header("Location: companies.php?" . $query_string);
    exit();
}

// --- 5. Get the message from SESSION ---
$form_message = "";
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    unset($_SESSION['form_message']); // Clear it so it only shows once
}

// --- 6. Fetch All Companies for This User (with filtering) ---
$companies = [];
$sql = "SELECT id, company_name, gstin, state FROM companies WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($search_term)) {
    $sql .= " AND company_name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}
$sql .= " ORDER BY company_name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies - SaaS Invoicer</title>
    
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
    
    <!-- UPDATED NAVIGATION BAR -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Left side: Logo and main links -->
                <div class="flex items-center min-w-0">
                    <span class="text-2xl font-bold text-blue-600 flex-shrink-0">Invoicer</span>
                    <!-- Links to other pages -->
                    <div class="hidden sm:ml-6 sm:flex sm:items-baseline sm:space-x-4">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Companies</a>
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Products</a>
                        <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Create Invoice</a>
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
                <a href="companies.php" class="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Companies</a>
                <a href="products.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Products</a>
                <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Create Invoice</a>
                <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Invoice History</a>
                <a href="settings.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Area (Unchanged) -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Manage Your Companies (Customers)</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left Column: Add New Company Form -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Add New Company</h2>
                    
                    <form action="companies.php?<?php echo $query_string; ?>" method="POST" class="space-y-4">
                        <div>
                            <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                            <input type="text" id="company_name" name="company_name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        <div>
                            <label for="gstin" class="block text-sm font-medium text-gray-700">GSTIN (15 Digits)</label>
                            <input type="text" id="gstin" name="gstin" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                            <select id="state" name="state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                                <option value="">Select a State / UT</option>
                                <option value="Andhra Pradesh">Andhra Pradesh</option>
                                <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                <option value="Assam">Assam</option>
                                <option value="Bihar">Bihar</option>
                                <option value="Chandigarh">Chandigarh</option>
                                <option value="Chhattisgarh">Chhattisgarh</option>
                                <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Goa">Goa</option>
                                <option value="Gujarat">Gujarat</option>
                                <option value="Haryana">Haryana</option>
                                <option value="Himachal Pradesh">Himachal Pradesh</option>
                                <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                <option value="Jharkhand">Jharkhand</option>
                                <option value="Karnataka">Karnataka</option>
                                <option value="Kerala">Kerala</option>
                                <option value="Ladakh">Ladakh</option>
                                <option value="Lakshadweep">Lakshadweep</option>
                                <option value="Madhya Pradesh">Madhya Pradesh</option>
                                <option value="Maharashtra">Maharashtra</option>
                                <option value="Manipur">Manipur</option>
                                <option value="Meghalaya">Meghalaya</option>
                                <option value="Mizoram">Mizoram</option>
                                <option value="Nagaland">Nagaland</option>
                                <option value="Odisha">Odisha</option>
                                <option value="Puducherry">Puducherry</option>
                                <option value="Punjab">Punjab</option>
                                <option value="Rajasthan">Rajasthan</option>
                                <option value="Sikkim">Sikkim</option>
                                <option value="Tamil Nadu">Tamil Nadu</option>
                                <option value="Telangana">Telangana</option>
                                <option value="Tripura">Tripura</option>
                                <option value="Uttar Pradesh">Uttar Pradesh</option>
                                <option value="Uttarakhand">Uttarakhand</option>
                                <option value="West Bengal">West Bengal</option>
                            </select>
                        </div>
                        <div>
                            <label for="state_code" class="block text-sm font-medium text-gray-700">State Code</label>
                            <input type="text" id="state_code" name="state_code" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-gray-100" 
                                   readonly>
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea id="address" name="address" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"></textarea>
                        </div>

                        <div>
                            <button type="submit"
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Save Company
                            </button>
                        </div>
                        <div id="form-message" class="text-center">
                            <?php echo $form_message; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: List of Companies -->
            <div class="lg:col-span-2">
                
                <!-- Search Bar -->
                <div class="bg-white shadow-md rounded-lg p-4 mb-6">
                    <form action="companies.php" method="GET" class="flex gap-4">
                        <div class="flex-grow">
                            <label for="search" class="sr-only">Search by Name</label>
                            <input type="text" name="search" id="search"
                                   placeholder="Search by company name..."
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        </div>
                        <div class="flex-shrink-0 flex gap-2">
                            <button type="submit"
                                    class="flex-shrink-0 justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Search
                            </button>
                            <a href="companies.php"
                               class="flex-shrink-0 justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Company List Table -->
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GSTIN</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">State</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($companies)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        <?php if (!empty($search_term)): ?>
                                            No companies found matching "<?php echo htmlspecialchars($search_term); ?>".
                                        <?php else: ?>
                                            No companies added yet. Use the form on the left to add your first one.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($companies as $company): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($company['company_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($company['gstin']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($company['state']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="edit-company.php?id=<?php echo $company['id']; ?>&<?php echo $query_string; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                            <a href="delete.php?type=company&id=<?php echo $company['id']; ?>&<?php echo $query_string; ?>" 
                                               class="text-red-600 hover:text-red-900 ml-4"
                                               onclick="return confirm('Are you sure you want to delete this company?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- JavaScript for State Code mapping (Unchanged) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stateCodes = { "Jammu and Kashmir": "01", "Himachal Pradesh": "02", "Punjab": "03", "Chandigarh": "04", "Uttarakhand": "05", "Haryana": "06", "Delhi": "07", "Rajasthan": "08", "Uttar Pradesh": "09", "Bihar": "10", "Sikkim": "11", "Arunachal Pradesh": "12", "Nagaland": "13", "Manipur": "14", "Mizoram": "15", "Tripura": "16", "Meghalaya": "17", "Assam": "18", "West Bengal": "19", "Jharkhand": "20", "Odisha": "21", "Chhattisgarh": "22", "Madhya Pradesh": "23", "Gujarat": "24", "Dadra and Nagar Haveli and Daman and Diu": "26", "Maharashtra": "27", "Andhra Pradesh": "37", "Karnataka": "29", "Goa": "30", "Lakshadweep": "31", "Kerala": "32", "Tamil Nadu": "33", "Puducherry": "34", "Andaman and Nicobar Islands": "35", "Telangana": "36", "Ladakh": "38" };
            const stateSelect = document.getElementById('state');
            const stateCodeInput = document.getElementById('state_code');
            stateSelect.addEventListener('change', function() {
                const selectedState = this.value;
                stateCodeInput.value = stateCodes[selectedState] || "";
            });
        });
    </script>
</body>
</html>