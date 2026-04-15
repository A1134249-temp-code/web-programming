<?php
    session_start();
    
    if (isset($_GET["Id"])){

        $id = $_GET["Id"];
        
        if (isset($_SESSION["catalog"][$id]) && isset($_COOKIE["Cart"][$id])){

            setcookie("Cart[$id]", "", time() - 3600);
            
        }
    }

    header("Location: shoppingcart.php");
    exit;
?>