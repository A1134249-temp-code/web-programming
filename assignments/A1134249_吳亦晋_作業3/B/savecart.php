<?php
    session_start();

    if (isset($_POST["Item"]) && isset($_POST["Quantity"])){

        $id = $_POST["Item"];
        $qty = $_POST["Quantity"];
        
        if (isset($_SESSION["catalog"][$id])){

            setcookie("Cart[$id]", $qty, time() + 3600);

        }
    }

    header("Location: shoppingcart.php");
    exit;
?>