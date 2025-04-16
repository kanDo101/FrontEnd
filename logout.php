<?php
session_start();

// Destroy the session to log out the user
session_unset();
session_destroy();

// Redirect to the landing page after logging out
header("Location: ./auth/landing.html");
exit();
?>
