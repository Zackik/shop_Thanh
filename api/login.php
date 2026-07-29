<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        "status" => false,
        "message" => "Only POST method is allowed"
    ]);
    exit();
}

// Lấy dữ liệu JSON hoặc form-data
$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? $_POST['email'] ?? '';
$password = $data['password'] ?? $_POST['password'] ?? '';
$role = $data['role'] ?? $_POST['role'] ?? '';

if (empty($email) || empty($password) || empty($role)) {
    echo json_encode([
        "status" => false,
        "message" => "Email, Password and Role are required"
    ]);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role=?");
$stmt->bind_param("ss", $email, $role);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    echo json_encode([
        "status" => false,
        "message" => "Account not found"
    ]);
    exit();

}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {

    echo json_encode([
        "status" => false,
        "message" => "Wrong password"
    ]);
    exit();

}

echo json_encode([
    "status" => true,
    "message" => "Login Success",
    "data" => [
        "id" => $user['id'],
        "full_name" => $user['full_name'],
        "email" => $user['email'],
        "phone" => $user['phone'],
        "address" => $user['address'],
        "role" => $user['role'],
        "profile_picture" => $user['profile_picture'],
        "created_at" => $user['created_at']
    ]
]);