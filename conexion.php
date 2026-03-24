<?php
$conexion = new mysqli("localhost", "root", "", "inventariomeca");
//$conexion = new mysqli("72.249.60.210", "ailicook_crisquinterocal", "IngeCobachino-3005", "ailicook_crudutn");
$conexion->set_charset("utf8");


if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
