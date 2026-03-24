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
    $tipo = trim($_GET["tipo"] ?? "");
    $matricula = (int)($_GET["matricula"] ?? 0);

    if ($tipo === "") {
        throw new Exception("Debes seleccionar el tipo de solicitante.");
    }

    if ($matricula <= 0) {
        throw new Exception("Debes capturar una matrícula válida.");
    }

    $stmt = $conexion->prepare("CALL sp_buscar_solicitante_por_matricula(?, ?)");
    $stmt->bind_param("si", $tipo, $matricula);
    $stmt->execute();

    $result = $stmt->get_result();
    $solicitante = $result ? $result->fetch_assoc() : null;

    if (!$solicitante) {
        throw new Exception("No se encontró el solicitante.");
    }

    $result?->free();
    $stmt->close();
    limpiar_call($conexion);

    echo json_encode([
        "ok" => true,
        "data" => $solicitante
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    limpiar_call($conexion);

    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}