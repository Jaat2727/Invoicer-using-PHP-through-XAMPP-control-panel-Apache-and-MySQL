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

// --- 3. Get Company ID from URL ---
if (!isset($_GET['id'])) {
    die("Error: No company ID specified.");
}
$company_id = (int)$_GET['id'];

// --- NEW: Get filter params from URL to pass back ---
$search_term = $_GET['search'] ?? '';
$query_string = http_build_query(['search' => $search_term]);


// --- 4. Handle UPDATE Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $company_name = $_POST['company_name'];
    $gstin = $_POST['gstin'];
    $state = $_POST['state'];
    $state_code = $_POST['state_code'];
    $address = $_POST['address'];

    $sql = "UPDATE companies SET 
                company_name = ?, gstin = ?, state = ?, state_code = ?, address = ? 
            WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssii", $company_name, $gstin, $state, $state_code, $address, $company_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['form_message'] = "<p class='text-green-600'>Company updated successfully!</p>";
    } else {
        $_SESSION['form_message'] = "<p class='text-red-600'>Error: " . $stmt->error . "</p>";
    }
    $stmt->close();
    
    // UPDATED: Redirect back to the filtered list
    header("Location: companies.php?" . $query_string);
    exit();
}

// --- 5. Fetch the Company Data for the Form ---
$sql = "SELECT company_name, gstin, state, state_code, address FROM companies WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows != 1) {
    die("Error: Company not found or you do not have permission.");
}
$company = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Company - SaaS Invoicer</title>
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
    
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-2xl font-bold text-blue-600">Invoicer</span>
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="companies.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 rounded-md text-sm font-medium" aria-current="page">Companies</a>
                        <a href="products.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Products</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-700 mr-4">Welcome, <?php echo htmlspecialchars($user_email); ?>!</span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-600">
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="max-w-xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Edit Company</h1>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Update Company Details</h2>
            
            <!-- UPDATED: Form action now includes the query string -->
            <form action="edit-company.php?id=<?php echo $company_id; ?>&<?php echo $query_string; ?>" method="POST" class="space-y-4">
                <div>
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                    <input type="text" id="company_name" name="company_name" required
                           value="<?php echo htmlspecialchars($company['company_name']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>
                <div>
                    <label for="gstin" class="block text-sm font-medium text-gray-700">GSTIN (15 Digits)</label>
                    <input type="text" id="gstin" name="gstin" 
                           value="<?php echo htmlspecialchars($company['gstin']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                </div>
                
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                    <select id="state" name="state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
                        <option value="">Select a State / UT</option>
                        <?php
                        $states = ["Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chandigarh", "Chhattisgarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Goa", "Gujarat", "Haryana", "Himachal Pradesh", "Jammu and Kashmir", "Jharkhand", "Karnataka", "Kerala", "Ladakh", "Lakshadweep", "Madhya Pradesh", "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Puducherry", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal"];
                        foreach ($states as $state_name) {
                            $selected = ($company['state'] == $state_name) ? 'selected' : '';
                            echo "<option value=\"$state_name\" $selected>$state_name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label for="state_code" class="block text-sm font-medium text-gray-700">State Code</label>
                    <input type="text" id="state_code" name="state_code" 
                           value="<?php echo htmlspecialchars($company['state_code']); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border bg-gray-100" 
                           readonly>
                </div>
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea id="address" name="address" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border"><?php echo htmlspecialchars($company['address']); ?></textarea>
                </div>

                <div class="flex gap-4">
                    <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Update Company
                    </button>
                    <!-- UPDATED: Cancel button links back to the filtered list -->
                    <a href="companies.php?<?php echo $query_string; ?>"
                       class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for State Code mapping -->
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