<?php
require 'config/database.php';
require 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logAction($pdo_app, $_SESSION['user_id'], 'Logged out');
}

session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
