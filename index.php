<?php

// needs a BIG refactor


include("db.class.php");

db::addConnection('default', array("HOST" => "localhost", "USER" => "root", "PASSWORD" => "", "NAME" => "test"));

$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";

while ($dado = db::fetch("describe emails")) {
        echo "<pre>";
        print_r($dado);
        echo "</pre>";
}

$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";
$emails = db::fetchAll("describe emails");
echo "<pre>";
print_R($emails);
echo "</pre>";
echo "<hr/>";

echo "<br/><br/>Oi<br/><br/>";
$check = db::fetch("describe emails");
print_r($check);
