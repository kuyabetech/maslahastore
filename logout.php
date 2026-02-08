<?php session_destroy(); header('Location: login.php'); ?>
<?php
session_start();
session_destroy();
header("Location: login.php");
exit;