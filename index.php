<?php

// needs a BIG refactor


include("db.class.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));



while ($dado = db::fetch("select * from emails")) {
        echo "<pre>";
        print_r($dado);
        echo "</pre>";
}


$check = db::fetch("select * from emails");
echo "q";
print_r($check);