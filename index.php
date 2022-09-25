<?php

// needs a BIG refactor


include("DbClass.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));

while ($dado = db::fetch("SELECT * FROM emails")) {
        
        echo "<pre>";
        print_r($dado);
        $lista = db::fetch("SELECT * FROM listas");
        print_r($lista);
        echo "</pre>";
}

db::performance();
