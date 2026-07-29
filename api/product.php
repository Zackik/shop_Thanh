<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "../db_config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    //===========================
    // GET
    //===========================
    case "GET":

        if(isset($_GET['id'])){

            $id = intval($_GET['id']);

            $stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
            $stmt->bind_param("i",$id);
            $stmt->execute();

            $result = $stmt->get_result();

            if($result->num_rows>0){

                echo json_encode([
                    "status"=>true,
                    "product"=>$result->fetch_assoc()
                ]);

            }else{

                echo json_encode([
                    "status"=>false,
                    "message"=>"Product not found"
                ]);

            }

        }else{

            $result = $conn->query("SELECT * FROM products ORDER BY id DESC");

            $products=[];

            while($row=$result->fetch_assoc()){
                $products[]=$row;
            }

            echo json_encode([
                "status"=>true,
                "total"=>count($products),
                "products"=>$products
            ]);

        }

    break;

    //===========================
    // POST
    //===========================
    case "POST":

        $name=$_POST['name'] ?? '';
        $description=$_POST['description'] ?? '';
        $price=$_POST['price'] ?? 0;
        $stock=$_POST['stock'] ?? 0;
        $image=$_POST['image'] ?? '';

        if($name==""){

            echo json_encode([
                "status"=>false,
                "message"=>"Name is required"
            ]);
            exit();

        }

        $stmt=$conn->prepare("INSERT INTO products(name,description,price,stock,image) VALUES(?,?,?,?,?)");

        $stmt->bind_param("ssdis",$name,$description,$price,$stock,$image);

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Product Added",
                "id"=>$conn->insert_id
            ]);

        }else{

            echo json_encode([
                "status"=>false,
                "message"=>"Insert Failed"
            ]);

        }

    break;

    //===========================
    // PUT
    //===========================
    case "PUT":

        parse_str(file_get_contents("php://input"),$_PUT);

        $id=$_GET['id'];

        $name=$_PUT['name'];
        $description=$_PUT['description'];
        $price=$_PUT['price'];
        $stock=$_PUT['stock'];

        $stmt=$conn->prepare("UPDATE products SET

        name=?,
        description=?,
        price=?,
        stock=?

        WHERE id=?");

        $stmt->bind_param("ssdii",

        $name,
        $description,
        $price,
        $stock,
        $id

        );

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Product Updated"
            ]);

        }else{

            echo json_encode([
                "status"=>false,
                "message"=>"Update Failed"
            ]);

        }

    break;

    //===========================
    // DELETE
    //===========================
    case "DELETE":

        $id=$_GET['id'];

        $stmt=$conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i",$id);

        if($stmt->execute()){

            echo json_encode([
                "status"=>true,
                "message"=>"Product Deleted"
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
            "message"=>"Method not allowed"
        ]);

}