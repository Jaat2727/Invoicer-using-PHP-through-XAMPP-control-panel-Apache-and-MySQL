<?php
// --- This is our reset.php script ---

// --- 1. Database Connection ---
require_once 'db.php';

// --- 2. Get Data from Form ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $recovery_code = $_POST['recovery_code'];
    $new_password = $_POST['new_password'];

    // --- 3. Find The User by Email ---
    $sql = "SELECT id, recovery_code FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // --- 4. User Found, Now Verify Recovery Code ---
        $user = $result->fetch_assoc();

        // --- THIS IS THE MAGIC ---
        // We check the plain-text code ($recovery_code) from the form
        // against the hash ($user['recovery_code']) from the database.
        if (password_verify($recovery_code, $user['recovery_code'])) {

            // --- 5. CODES MATCH! Reset the password. ---

            // Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the user's password in the database
            $update_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_password_hash, $user['id']);
            $update_stmt->execute();

            // Send them to the login page with a success message
            header("Location: login.html?message=success&data=" . urlencode("Password reset! You can now log in."));
            exit();

        } else {
            // --- CODES DO NOT MATCH! ---
            header("Location: reset-password.html?message=error&data=" . urlencode("Invalid email or recovery code."));
            exit();
        }

    } else {
        // --- USER NOT FOUND ---
        // We send the same error message to prevent security risks
        header("Location: reset-password.html?message=error&data=" . urlencode("Invalid email or recovery code."));
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: reset-password.html?message=error&data=" . urlencode("Invalid request."));
    exit();
}
?>