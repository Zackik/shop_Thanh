<?php
//Logout of system web
session_start();
session_destroy();
header("Location: login.php");
?>
