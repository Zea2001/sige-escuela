<?php
$servidor = "localhost";
$usuario  = "root"; 
$password = "MMaa11.."; // <-- Cambiado aquí
$base_datos = "sige_escuela";

$conexion = new mysqli($servidor, $usuario, $password, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
?>