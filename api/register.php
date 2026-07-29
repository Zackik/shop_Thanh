<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

if ($_SERVER['REQUEST_METHOD'] != "POST") {

    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit();
}

/*
    Có thể nhận:
    - form-data
    - x-www-form-urlencoded
    - raw JSON
*/

$data = json_decode(file_get_contents("php://input"), true);

$full_name = $_POST['full_name']
    ?? $_GET['full_name']
    ?? $data['full_name']
    ?? '';

$email = $_POST['email']
    ?? $_GET['email']
    ?? $data['email']
    ?? '';

$password = $_POST['password']
    ?? $_GET['password']
    ?? $data['password']
    ?? '';

$phone = $_POST['phone']
    ?? $_GET['phone']
    ?? $data['phone']
    ?? '';

$address = $_POST['address']
    ?? $_GET['address']
    ?? $data['address']
    ?? '';

$role = $_POST['role']
    ?? $_GET['role']
    ?? $data['role']
    ?? 'user';

if (
    empty($full_name) ||
    empty($email) ||
    empty($password) ||
    empty($phone) ||
    empty($address)
) {

    echo json_encode([
        "status" => false,
        "message" => "Please fill all fields"
    ]);

    exit();
}

/* kiểm tra email */

$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    echo json_encode([
        "status" => false,
        "message" => "Email already exists"
    ]);

    exit();
}

/* mã hóa password */

$hashPassword = password_hash($password, PASSWORD_DEFAULT);

$profile = "profile_picture/default.jpg";

$stmt = $conn->prepare("

INSERT INTO users

(full_name,email,password,role,address,profile_picture,phone)

VALUES

(?,?,?,?,?,?,?)

");

$stmt->bind_param(

"sssssss",

$full_name,
$email,
$hashPassword,
$role,
$address,
$profile,
$phone

);

if ($stmt->execute()) {

    echo json_encode([

        "status" => true,

        "message" => "Register Success",

        "user" => [

            "id" => $conn->insert_id,

            "full_name" => $full_name,

            "email" => $email,

            "phone" => $phone,

            "address" => $address,

            "role" => $role,

            "profile_picture" => $profile

        ]

    ]);

} else {

    echo json_encode([

        "status" => false,

        "message" => "Register Failed"

    ]);

}