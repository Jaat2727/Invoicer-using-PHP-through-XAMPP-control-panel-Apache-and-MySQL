<?php
// Start the session to get the code
session_start();

// Check if the recovery code is in the session.
// If not, they don't belong here.
if (!isset($_SESSION['new_recovery_code'])) {
    header("Location: login.html");
    exit();
}

// Get the code from the session
$recovery_code = $_SESSION['new_recovery_code'];

// IMPORTANT: Unset the session variable so this page
// only works ONE TIME.
unset($_SESSION['new_recovery_code']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Save Your Recovery Code! - SaaS Invoicer</title>
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
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 text-center">
            
            <div>
                <svg class="mx-auto h-12 w-auto text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>

                <h2 class="mt-6 text-3xl font-bold tracking-tight text-gray-900">
                    Account Created! One Last Step...
                </h2>
                <p class="mt-4 text-lg text-gray-700">
                    Please save your **Account Recovery Code**. This is the
                    <strong class="text-red-600">ONLY WAY</strong>
                    to reset your password if you forget it.
                </p>
            </div>

            <div class="bg-white shadow-md rounded-lg p-8">
                <p class="text-gray-600">Write this code down and keep it somewhere safe:</p>
                <div class="my-4 p-4 bg-gray-100 border-2 border-dashed border-gray-300 rounded-md">
                    <p class="text-3xl font-mono font-bold text-gray-800 tracking-widest">
                        <?php echo htmlspecialchars($recovery_code); ?>
                    </p>
                </div>
            </div>

            <div>
                <a href="login.html" 
                   class="group relative flex w-full justify-center rounded-md border border-transparent bg-blue-600 py-3 px-4 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    I have saved my code. Continue to Login &rarr;
                </a>
            </div>

        </div>
    </div>
</body>
</html>