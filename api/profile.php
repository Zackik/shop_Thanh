<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    //====================================
    // GET USER PROFILE
    //====================================
    case "GET":

        if (!isset($_GET['id'])) {

            echo json_encode([
                "status" => false,
                "message" => "User ID is required"
            ]);
            exit();
        }

        $id = intval($_GET['id']);

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                email,
                role,
                address,
                profile_picture,
                phone,
                created_at
            FROM users
            WHERE id=?
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            echo json_encode([
                "status" => true,
                "profile" => $result->fetch_assoc()
            ]);

        } else {

            echo json_encode([
                "status" => false,
                "message" => "User not found"
            ]);

        }

    break;

    //====================================
    // UPDATE USER PROFILE
    //====================================
    case "PUT":

        parse_str(file_get_contents("php://input"), $_PUT);

        if (!isset($_GET['id'])) {

            echo json_encode([
                "status" => false,
                "message" => "User ID is required"
            ]);
            exit();
        }

        $id = intval($_GET['id']);

        $full_name = $_PUT['full_name'] ?? "";
        $phone = $_PUT['phone'] ?? "";
        $address = $_PUT['address'] ?? "";
        $profile_picture = $_PUT['profile_picture'] ?? "";

        $stmt = $conn->prepare("
            UPDATE users

            SET

            full_name=?,
            phone=?,
            address=?,
            profile_picture=?

            WHERE id=?
        ");

        $stmt->bind_param(
            "ssssi",
            $full_name,
            $phone,
            $address,
            $profile_picture,
            $id
        );

        if ($stmt->execute()) {

            echo json_encode([
                "status" => true,
                "message" => "Profile updated successfully"
            ]);

        } else {

            echo json_encode([
                "status" => false,
                "message" => "Update failed"
            ]);

        }

    break;

    default:

        echo json_encode([
            "status" => false,
            "message" => "Method Not Allowed"
        ]);
}