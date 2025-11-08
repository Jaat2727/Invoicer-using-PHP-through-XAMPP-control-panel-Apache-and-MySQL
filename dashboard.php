<?php
// --- 1. Start the Session and Check Login ---
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html?message=error&data=" . urlencode("You must be logged in."));
    exit();
}
$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SaaS Invoicer</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
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
                        <a href="dashboard.php" class="text-blue-600 border-b-2 border-blue-600 px-3 py-2 text-sm font-medium" aria-current="page">Dashboard</a>
                        <a href="companies.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Companies</a>
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
                <!-- Mobile menu button (we can add this later) -->
            </div>
        </div>
        <!-- Mobile menu, show/hide based on menu state (we can add this later) -->
        <div class="sm:hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="text-blue-600 block px-3 py-2 rounded-md text-base font-medium" aria-current="page">Dashboard</a>
                <a href="companies.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Companies</a>
                <a href="products.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Products</a>
                <a href="create-invoice.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Create Invoice</a>
                <a href="invoice-history.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Invoice History</a>
                <a href="settings.php" class="text-gray-700 hover:text-blue-600 block px-3 py-2 rounded-md text-base font-medium">Settings</a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Area -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Your Dashboard</h1>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Welcome to Your Invoicer!</h2>
            <p class="text-gray-700">
                This is your main dashboard. We will add statistics and graphs here later, just as you requested.
            </p>
            <p class="text-gray-700 mt-4">
                For now, you can manage your app using the links in the navigation bar:
            </p>
            <ul class="list-disc list-inside mt-4 text-gray-700 space-y-2">
                <li><strong>Companies:</strong> Add or edit your customers.</li>
                <li><strong>Products:</strong> Manage the products and services you sell.</li>
                <li><strong>Create Invoice:</strong> Build a new invoice for a customer.</li>
                <li><strong>Invoice History:</strong> View your past invoices.</li>
                <li><strong>Settings:</strong> Update your company's information.</li>
            </ul>
        </div>
    </div>
</body>
</html>