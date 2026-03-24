<?php
require_once "../conexion.php";
require_once "../includes/auth.php";

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");

function limpiar_call(mysqli $conexion): void {
    while ($conexion->more_results()) {
        $conexion->next_result();
        $extra = $conexion->store_result();
        if ($extra instanceof mysqli_result) {
            $extra->free();
        }
    }
}

try {
    $marbete = trim($_GET["marbete"] ?? "");

    if ($marbete === "") {
        throw new Exception("Debes capturar un número de marbete.");
    }

    $stmt = $conexion->prepare("CALL sp_buscar_activo_por_marbete(?)");
    $stmt->bind_param("s", $marbete);
    $stmt->execute();

    $result = $stmt->get_result();
    $activo = $result ? $result->fetch_assoc() : null;

    if (!$activo) {
        throw new Exception("No se encontró un activo con ese número de marbete.");
    }

    $result?->free();
    $stmt->close();
    limpiar_call($conexion);

    echo json_encode([
        "ok" => true,
        "data" => $activo
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    limpiar_call($conexion);

    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}