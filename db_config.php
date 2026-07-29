<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "swiftbuy";
//dataname random follow think you

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    //error connect
    die("Connection failed: " . $conn->connect_error);
}
?>