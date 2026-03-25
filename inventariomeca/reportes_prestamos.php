<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "reportes_prestamos";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");

$tipoReporte = trim($_GET["tipo_reporte"] ?? "prestamos");
$fechaInicio = trim($_GET["fecha_inicio"] ?? "");
$fechaFin    = trim($_GET["fecha_fin"] ?? "");

$reportesValidos = ["prestamos", "devueltos", "activos", "vencidos"];
if (!in_array($tipoReporte, $reportesValidos, true)) {
    $tipoReporte = "prestamos";
}

function h($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function limpiar_call(mysqli $conexion): void {
    while ($conexion->more_results()) {
        $conexion->next_result();
        $extra = $conexion->store_result();
        if ($extra instanceof mysqli_result) {
            $extra->free();
        }
    }
}

function descripcionTipoReporte(string $tipo): string {
    return match ($tipo) {
        "prestamos" => "Muestra todos los préstamos. Si capturas fechas, filtrará por fecha de préstamo.",
        "devueltos" => "Muestra los préstamos devueltos. Si capturas fechas, filtrará por fecha de devolución.",
        "activos"   => "Muestra los préstamos activos/no devueltos. Si capturas fechas, filtrará por fecha límite.",
        "vencidos"  => "Muestra solo los préstamos vencidos. Si capturas fechas, filtrará por fecha límite.",
        default     => "Consulta préstamos y descárgalos en Excel."
    };
}

$registros = [];
$error = "";

try {
    $fechaInicioParam = ($fechaInicio !== "") ? $fechaInicio : null;
    $fechaFinParam    = ($fechaFin !== "") ? $fechaFin : null;

    $stmt = $conexion->prepare("CALL sp_reporte_prestamos(?, ?, ?)");
    $stmt->bind_param("sss", $tipoReporte, $fechaInicioParam, $fechaFinParam);
    $stmt->execute();

    $res = $stmt->get_result();
    while ($fila = $res->fetch_assoc()) {
        $registros[] = $fila;
    }

    $res->free();
    $stmt->close();
    limpiar_call($conexion);

} catch (Throwable $e) {
    limpiar_call($conexion);
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de préstamos | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/devoluciones.css">
    <link rel="stylesheet" href="css/reportes_prestamos.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Reportes</p>
            <h1>Reportes de préstamos</h1>
        </div>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="table-card">
        <div class="table-card__head table-card__head--stack">
            <div>
                <h2>Filtros del reporte</h2>
                <p><?php echo h(descripcionTipoReporte($tipoReporte)); ?></p>
            </div>

            <form method="GET" class="toolbar toolbar--filters">
                <div class="filter-row">
                    <div class="filter-field">
                        <label for="tipo_reporte">Tipo de reporte</label>
                        <select name="tipo_reporte" id="tipo_reporte">
                            <option value="prestamos" <?php echo $tipoReporte === "prestamos" ? "selected" : ""; ?>>Préstamos</option>
                            <option value="devueltos" <?php echo $tipoReporte === "devueltos" ? "selected" : ""; ?>>Devueltos</option>
                            <option value="activos" <?php echo $tipoReporte === "activos" ? "selected" : ""; ?>>No devueltos / activos</option>
                            <option value="vencidos" <?php echo $tipoReporte === "vencidos" ? "selected" : ""; ?>>Solo vencidos</option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="fecha_inicio">Fecha inicio</label>
                        <input
                            type="date"
                            name="fecha_inicio"
                            id="fecha_inicio"
                            value="<?php echo h($fechaInicio); ?>"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="fecha_fin">Fecha fin</label>
                        <input
                            type="date"
                            name="fecha_fin"
                            id="fecha_fin"
                            value="<?php echo h($fechaFin); ?>"
                        >
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn-secondary">Ver reporte</button>
                        <a
                            class="btn-table"
                            href="exportar_reporte_prestamos.php?tipo_reporte=<?php echo urlencode($tipoReporte); ?>&fecha_inicio=<?php echo urlencode($fechaInicio); ?>&fecha_fin=<?php echo urlencode($fechaFin); ?>"
                        >
                            Descargar Excel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Activo</th>
                        <th>Solicitante</th>
                        <th>Cant.</th>
                        <th>Ubicación</th>
                        <th>Inicio</th>
                        <th>Límite</th>
                        <th>Devolución</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($registros)): ?>
                        <?php foreach ($registros as $r): ?>
                            <tr>
                                <td><?php echo !empty($r["Grupo_PrestamoID"]) ? "#" . (int)$r["Grupo_PrestamoID"] : "—"; ?></td>

                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($r["Activo_Desc"] ?? ""); ?></strong>
                                        <span>
                                            <?php echo h($r["Num_Marbete"] ?? "S/M"); ?>
                                            ·
                                            <?php echo h($r["Tipo_Activo"] ?? ""); ?>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($r["SolicitanteNombre"] ?? ""); ?></strong>
                                        <span><?php echo h($r["SolicitanteMatricula"] ?? ""); ?></span>
                                    </div>
                                </td>

                                <td><?php echo (int)($r["Cantidad_Solicitada"] ?? 0); ?></td>
                                <td><?php echo h($r["Nombre_Lab"] ?? "Sin ubicación"); ?></td>

                                <td>
                                    <?php echo !empty($r["Fecha_Inicio"])
                                        ? h(date("d/m/Y H:i", strtotime($r["Fecha_Inicio"])))
                                        : "—"; ?>
                                </td>

                                <td>
                                    <?php echo !empty($r["Fecha_Limite"])
                                        ? h(date("d/m/Y H:i", strtotime($r["Fecha_Limite"])))
                                        : "No aplica"; ?>
                                </td>

                                <td>
                                    <?php echo !empty($r["Fecha_Devolucion"])
                                        ? h(date("d/m/Y H:i", strtotime($r["Fecha_Devolucion"])))
                                        : "Pendiente"; ?>
                                </td>

                                <td><?php echo h($r["Estado_Prestamo"] ?? ""); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="muted">No hay registros para este reporte.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>