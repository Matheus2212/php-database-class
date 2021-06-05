<?php

include("db.class.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));

db::insert(array("monney" => "1,00.50"), "monney");
