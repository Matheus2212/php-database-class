<?php

include("db.class.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));

$resultado = db::search("teste comum verdadeiro falso com amor", "emails");

while ($dado = db::fetch($resultado)) {
        echo "<pre>";
        print_r($dado);
        echo "</pre>";
}
