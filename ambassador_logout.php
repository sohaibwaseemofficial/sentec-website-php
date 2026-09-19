<?php
session_start();

// Clear all ambassador session data
unset($_SESSION['ambassador_id']);
unset($_SESSION['ambassador_name']);
unset($_SESSION['ambassador_code']);
unset($_SESSION['ambassador_type']);

// Destroy the entire session
session_destroy();

// Redirect to home page
header('Location: index.php');
exit;