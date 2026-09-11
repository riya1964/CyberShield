<?php
/*
 * logout.php
 * ------------
 * Destroys the admin session and redirects back to login.
 */

session_start();
session_destroy();
header("Location: login.php");
exit;
?>
