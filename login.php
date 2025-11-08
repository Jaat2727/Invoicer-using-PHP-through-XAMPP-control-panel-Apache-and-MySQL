<?php
// --- This is our login.php script ---

// We MUST start a session to store login information
session_start();

// --- 1. Database Connection ---
require_once 'db.php';

// --- 2. Get Data from Form ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- 3. Find The User ---
    $sql = "SELECT id, email, password_hash FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // --- 4. User Found, Now Verify Password ---
        $user = $result->fetch_assoc();

        // This is the magic!
        // password_verify() compares the plain-text password with the hash in the database.
        if (password_verify($password, $user['password_hash'])) {

            // --- 5. PASSWORD IS CORRECT! ---
            // Store user info in the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            // Send them to the secret dashboard
            header("Location: dashboard.php");
            exit();

        } else {
            // --- PASSWORD IS WRONG! ---
            header("Location: login.html?message=error&data=" . urlencode("Incorrect email or password."));
            exit();
        }

    } else {
        // --- USER NOT FOUND ---
        header("Location: login.html?message=error&data=" . urlencode("Incorrect email or password."));
        exit();
    }

    $stmt->close();
    $conn->close();

} else {
    header("Location: login.html?message=error&data=" . urlencode("Invalid request."));
    exit();
}
?>