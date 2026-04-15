<?php
    session_start();
?>

<table border="1">

    <tr>
        <th>功能</th><th>編號</th><th>名稱</th><th>價格</th><th>數量</th>
    </tr>

    <?php

        $total = 0;
            
        if (isset($_COOKIE["Cart"]) && isset($_SESSION["catalog"])) {
                
            foreach ($_COOKIE["Cart"] as $id => $qty) {
                    
                $name = $_SESSION["catalog"][$id]["name"];
                $price = $_SESSION["catalog"][$id]["price"];
                $total += $price * $qty;
                    
                echo <<<HTML
                    <tr>
                        <td><a href="delete.php?Id=$id">刪除</a></td>
                        <td>$id</td>
                        <td>$name</td>
                        <td>$price</td>
                        <td>$qty</td>
                    <tr>
                    HTML;

            }
        }
    ?>

    <tr>
        <td colspan="5" align="right">總金額 = NT$<?php echo $total; ?>元</td>
    </tr>

</table>

<hr>

<a href="catalog.php">商品目錄</a> | <a href="shoppingcart.php">檢視購物車</a>