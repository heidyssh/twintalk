<?php
date_default_timezone_set('America/Tegucigalpa');
$host = "127.0.0.1";        
$user = "root";             
$pass = "";                 
$db   = "twintalk_academy_db";  
$port = 3307;               

$mysqli = new mysqli($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
