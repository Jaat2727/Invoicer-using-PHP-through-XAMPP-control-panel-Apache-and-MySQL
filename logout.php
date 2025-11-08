<?php
// --- This is our logout.php script ---

// Start the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Send the user back to the login page with a success message
header("Location: login.html?message=success&data=" . urlencode("You have been logged out."));
exit();
?>