<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    //==========================
    // GET
    //==========================
    case "GET":

        if(isset($_GET['id'])){

            $id = intval($_GET['id']);

            $stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();

            $result = $stmt->get_result();

            if($result->num_rows>0){

                echo json_encode([
                    "status"=>true,
                    "order"=>$result->fetch_assoc()
                ]);

            }else{

                echo json_encode([
                    "status"=>false,
                    "message"=>"Order not found"
                ]);

            }

        }else{

            $result=$conn->query("SELECT * FROM orders ORDER BY id DESC");

            $orders=[];

            while($row=$result->fetch_assoc()){
                $orders[]=$row;
            }

            echo json_encode([
                "status"=>true,
                "total"=>count($orders),
                "orders"=>$orders
            ]);

        }

    break;

    //==========================
    // POST
    //==========================
    case "POST":

        $user_id=$_POST['user_id'] ?? 0;
        $total=$_POST['total'] ?? 0;
        $status=$_POST['status'] ?? "Pending";
        $payment=$_POST['payment_method'] ?? "";
        $address=$_POST['address'] ?? "";

        if($user_id==0){

            echo json_encode([
                "status"=>false,
                "message"=>"User ID required"
            ]);
            exit();
        }

        $stmt=$conn->prepare("

        INSERT INTO orders
        (user_id,total,status,payment_method,address)

        VALUES(?,?,?,?,?)

        ");

        $stmt->bind_param(
            "idsss",
            $user_id,
            $total,
            $status,
            $payment,
            $address
        );

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Order Created",
                "order_id"=>$conn->insert_id
            ]);

        }else{

            echo json_encode([
                "status"=>false,
                "message"=>"Insert Failed"
            ]);

        }

    break;

    //==========================
    // PUT
    //==========================
    case "PUT":

        parse_str(file_get_contents("php://input"),$_PUT);

        $id=$_GET['id'];

        $total=$_PUT['total'];
        $status=$_PUT['status'];
        $payment=$_PUT['payment_method'];
        $address=$_PUT['address'];

        $stmt=$conn->prepare("

        UPDATE orders

        SET

        total=?,
        status=?,
        payment_method=?,
        address=?

        WHERE id=?

        ");

        $stmt->bind_param(
            "dsssi",
            $total,
            $status,
            $payment,
            $address,
            $id
        );

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Order Updated"
            ]);

        }else{

            echo json_encode([
                "status"=>false,
                "message"=>"Update Failed"
            ]);

        }

    break;

    //==========================
    // DELETE
    //==========================
    case "DELETE":

        $id=$_GET['id'];

        $stmt=$conn->prepare("DELETE FROM orders WHERE id=?");
        $stmt->bind_param("i",$id);

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Order Deleted"
            ]);

        }else{

            echo json_encode([
                "status"=>false,
                "message"=>"Delete Failed"
            ]);

        }

    break;

    default:

        echo json_encode([
            "status"=>false,
            "message"=>"Method Not Allowed"
        ]);
}