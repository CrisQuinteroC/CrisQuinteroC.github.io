<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION["Matricula_Uss"])) {
  header("Location: login.php");
  exit;
}

$empNombre = $_SESSION["Nombre_Uss"] ?? "Usuario";
$empRol    = $_SESSION["Rol_Uss"] ?? "Rol";

$iniciales = strtoupper(substr($empNombre, 0, 1));
if (strpos($empNombre, " ") !== false) {
  $partes = preg_split('/\s+/', trim($empNombre));
  if (count($partes) >= 2) {
    $iniciales .= strtoupper(substr($partes[1], 0, 1));
  }
}