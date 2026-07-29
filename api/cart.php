<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    //========================================
    // GET
    //========================================
    case "GET":

        if (isset($_GET['user_id'])) {

            $user_id = intval($_GET['user_id']);

            $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id=? ORDER BY id DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();

            $cart = [];

            while ($row = $result->fetch_assoc()) {
                $cart[] = $row;
            }

            echo json_encode([
                "status" => true,
                "total" => count($cart),
                "cart" => $cart
            ]);

        } else {

            $result = $conn->query("SELECT * FROM cart ORDER BY id DESC");

            $cart = [];

            while ($row = $result->fetch_assoc()) {
                $cart[] = $row;
            }

            echo json_encode([
                "status" => true,
                "total" => count($cart),
                "cart" => $cart
            ]);
        }

    break;

    //========================================
    // POST
    //========================================
    case "POST":

        $user_id = $_POST['user_id'] ?? 0;
        $product_name = $_POST['product_name'] ?? "";
        $product_price = $_POST['product_price'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;

        if ($user_id == 0 || empty($product_name)) {

            echo json_encode([
                "status" => false,
                "message" => "Missing required fields"
            ]);
            exit();
        }

        $stmt = $conn->prepare("

            INSERT INTO cart
            (user_id,product_name,product_price,quantity)

            VALUES
            (?,?,?,?)

        ");

        $stmt->bind_param(
            "isdi",
            $user_id,
            $product_name,
            $product_price,
            $quantity
        );

        if ($stmt->execute()) {

            echo json_encode([
                "status" => true,
                "message" => "Product added to cart",
                "cart_id" => $conn->insert_id
            ]);

        } else {

            echo json_encode([
                "status" => false,
                "message" => "Insert Failed"
            ]);

        }

    break;

    //========================================
    // PUT
    //========================================
    case "PUT":

        parse_str(file_get_contents("php://input"), $_PUT);

        $id = $_GET['id'] ?? 0;

        $quantity = $_PUT['quantity'] ?? 1;

        $stmt = $conn->prepare("

            UPDATE cart

            SET quantity=?

            WHERE id=?

        ");

        $stmt->bind_param(
            "ii",
            $quantity,
            $id
        );

        if ($stmt->execute()) {

            echo json_encode([
                "status" => true,
                "message" => "Cart updated successfully"
            ]);

        } else {

            echo json_encode([
                "status" => false,
                "message" => "Update Failed"
            ]);

        }

    break;

    //========================================
    // DELETE
    //========================================
    case "DELETE":

        $id = $_GET['id'] ?? 0;

        $stmt = $conn->prepare("DELETE FROM cart WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            echo json_encode([
                "status" => true,
                "message" => "Cart item deleted"
            ]);

        } else {

            echo json_encode([
                "status" => false,
                "message" => "Delete Failed"
            ]);

        }

    break;

    default:

        echo json_encode([
            "status" => false,
            "message" => "Method Not Allowed"
        ]);
}