<?php
// --- This is our backend script (signup.php) ---

// START THE SESSION! This is now required.
session_start();

// --- 1. Database Connection ---
require_once 'db.php';

// --- 2. Get Data from Form ---
// Check if data was sent via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get the email and password from the form
    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- 3. Validate Data (Simple) ---
    if (empty($email) || empty($password)) {
        // Send back an error
        header("Location: signup.html?message=error&data=" . urlencode("Email and password are required."));
        exit();
    }

    // --- 4. Secure the Password ---
    // This is the MOST IMPORTANT step.
    // We use password_hash() to create a secure hash of the password.
    // We NEVER store the plain-text password.
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // --- NEW! 4.5. Generate Recovery Code ---
    // This creates a 16-character code like "A1B2-C3D4-E5F6-7890"
    $recovery_code = implode('-', str_split(strtoupper(bin2hex(random_bytes(8))), 4));

    // --- NEW! 4.6. Hash the Recovery Code ---
    // We will store this hash in the database, not the code itself.
    $recovery_code_hash = password_hash($recovery_code, PASSWORD_DEFAULT);

    // --- 5. Prepare SQL to Insert User ---
    // We update the SQL to include the new column
    $sql = "INSERT INTO users (email, password_hash, recovery_code) VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        header("Location: signup.html?message=error&data=" . urlencode("Error preparing statement."));
        exit();
    }

    // "sss" means we are binding three strings: email, pass_hash, recovery_hash
    $stmt->bind_param("sss", $email, $password_hash, $recovery_code_hash);

    // --- 6. Execute and Check Result ---
    // We will 'try' to execute the query.
    // If it fails (like a duplicate email), we will 'catch' the exception.
    try {
        $stmt->execute();

        // --- MODIFIED SUCCESS! ---
        // We give the user the *original* $recovery_code (plain text)
        // while the database only stores the $recovery_code_hash.
        $_SESSION['new_recovery_code'] = $recovery_code;
        header("Location: show-recovery-code.php");
        exit();

    } catch (mysqli_sql_exception $e) {
        // FAILED!
        // Check if the error is the 'Duplicate entry' error (code 1062)
        if ($e->getCode() == 1062) {
            // Send a specific error code instead of a full sentence
            header("Location: signup.html?message=error&data=duplicate_email");
        } else {
            // For any other error, send a generic message
            header("Location: signup.html?message=error&data=" . urlencode("An error occurred. Please try again."));
        }
        exit();
    }

    // --- 7. Close connections ---
    $stmt->close();
    $conn->close();

} else {
    // If someone just types "signup.php" in their browser
    header("Location: signup.html?message=error&data=" . urlencode("Invalid request."));
    exit();
}

?>