<?php
require_once 'config.php';

$con = new mysqli(BDhost, BDuser, BDpasss, BDnombre);

if($con->connect_error){
    die("Error al conectar: " . $con->connect_error);
}
$con->set_charset("utf8mb4");
?>