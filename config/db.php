<?php
date_default_timezone_set('America/Tegucigalpa');
$host = "127.0.0.1";        // igual que en config.inc.php
$user = "root";             // igual
$pass = "";                 // sin contraseña
$db   = "twintalk_academy_db";  // el nombre de tu base
$port = 3307;               // IMPORTANTE: este puerto

$mysqli = new mysqli($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
