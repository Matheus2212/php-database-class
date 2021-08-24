<?php

// needs a BIG refactor


include("db.class.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));

$query = db::pagedQuery("select * from emails", 10);

$count = 0;

while ($dado = db::fetch($query)) {

        $count++;
        while ($dado2 = db::pagedQuery("select * from monney", 5)) {
                $count++;
                echo "<pre>";
                print_r($dado2);
                echo "</pre>";
                if ($count == 4) {
                        break;
                }
        }

        echo "<pre>";
        print_r($dado);
        echo "</pre>";
        if ($count == 4) {
                break;
        }
}

db::page();
