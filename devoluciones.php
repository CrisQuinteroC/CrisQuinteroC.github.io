<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "devoluciones";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");

$mensaje = "";
$error = "";
$busqueda = trim($_GET["q"] ?? "");
$fechaInicioFiltro = trim($_GET["fecha_inicio"] ?? "");
$fechaLimiteFiltro = trim($_GET["fecha_limite"] ?? "");

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

/* =========================
   REGISTRAR DEVOLUCIÓN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_devolucion") {
    try {
        $idPrestamo = (int)($_POST["ID_Prestamo"] ?? 0);
        $recibidoPor = (int)($_SESSION["Matricula_Uss"] ?? 0);

        if ($idPrestamo <= 0) {
            throw new Exception("No se encontró el préstamo a devolver.");
        }

        if ($recibidoPor <= 0) {
            throw new Exception("No se encontró el usuario que recibe.");
        }

        $stmt = $conexion->prepare("CALL sp_registrar_devolucion(?, ?)");
        $stmt->bind_param("ii", $idPrestamo, $recibidoPor);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;
        $mensaje = $data["mensaje"] ?? "Devolución registrada correctamente.";

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (Throwable $e) {
        limpiar_call($conexion);
        $error = $e->getMessage();
    }
}

/* =========================
   PRÉSTAMOS ACTIVOS
========================= */
$where = ["p.Estado_Prestamo = 'Activo'"];
$params = [];
$types = "";

if ($busqueda !== "") {
    $where[] = "(
        CAST(IFNULL(p.Grupo_PrestamoID, '') AS CHAR) LIKE ?
        OR a.Num_Marbete LIKE ?
        OR a.Activo_Desc LIKE ?
        OR COALESCE(al.Nombre_Alumno, d.Nombre_Docente) LIKE ?
        OR CAST(COALESCE(p.Matricula_Alumno, p.Matricula_Docente) AS CHAR) LIKE ?
    )";
    $like = "%" . $busqueda . "%";
    $params = [$like, $like, $like, $like, $like];
    $types .= "sssss";
}

if ($fechaInicioFiltro !== "") {
    $where[] = "DATE(p.Fecha_Inicio) = ?";
    $params[] = $fechaInicioFiltro;
    $types .= "s";
}

if ($fechaLimiteFiltro !== "") {
    $where[] = "DATE(p.Fecha_Limite) = ?";
    $params[] = $fechaLimiteFiltro;
    $types .= "s";
}

$sqlActivos = "
    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        p.Cantidad_Solicitada,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Comentarios,
        p.Estado_Prestamo,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        COALESCE(CAST(p.Matricula_Alumno AS CHAR), CAST(p.Matricula_Docente AS CHAR)) AS SolicitanteMatricula,
        u.Nombre_Lab,
        ur.Nombre_Uss AS RegistradoPorNombre
    FROM prestamos p
    INNER JOIN activos a ON a.ID_Activo = p.ID_Activo
    LEFT JOIN alumnos al ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN ubicaciones u ON u.ID_Lab = p.ID_Lab
    LEFT JOIN usuarios ur ON ur.Matricula_Uss = p.Registrado_Por
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.Fecha_Limite ASC, p.Grupo_PrestamoID ASC, p.ID_Prestamo ASC
";

$stmtActivos = $conexion->prepare($sqlActivos);
if (!empty($params)) {
    $stmtActivos->bind_param($types, ...$params);
}
$stmtActivos->execute();

$prestamosActivos = [];
$resActivos = $stmtActivos->get_result();
while ($row = $resActivos->fetch_assoc()) {
    $prestamosActivos[] = $row;
}
$resActivos->free();
$stmtActivos->close();

/* =========================
   HISTORIAL DEVUELTOS
========================= */
$historial = [];
$resHistorial = $conexion->query("
    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        p.Cantidad_Solicitada,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Fecha_Devolucion,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        ur.Nombre_Uss AS RecibidoPorNombre
    FROM prestamos p
    INNER JOIN activos a ON a.ID_Activo = p.ID_Activo
    LEFT JOIN alumnos al ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN usuarios ur ON ur.Matricula_Uss = p.Recibido_Por
    WHERE p.Estado_Prestamo = 'Devuelto'
    ORDER BY p.Fecha_Devolucion DESC
    LIMIT 15
");
while ($row = $resHistorial->fetch_assoc()) {
    $historial[] = $row;
}
$resHistorial->free();

/* =========================
   RESUMEN
========================= */
$resumen = [
    "pendientes" => 0,
    "vencidos" => 0,
    "devueltos_hoy" => 0,
    "grupos_abiertos" => 0
];

$resResumen = $conexion->query("
    SELECT
        SUM(CASE WHEN Estado_Prestamo = 'Activo' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN Estado_Prestamo = 'Activo' AND Fecha_Limite < NOW() THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN Estado_Prestamo = 'Devuelto' AND DATE(Fecha_Devolucion) = CURDATE() THEN 1 ELSE 0 END) AS devueltos_hoy,
        COUNT(DISTINCT CASE WHEN Estado_Prestamo = 'Activo' THEN Grupo_PrestamoID END) AS grupos_abiertos
    FROM prestamos
");
if ($resResumen) {
    $fila = $resResumen->fetch_assoc();
    if ($fila) {
        $resumen = $fila;
    }
    $resResumen->free();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/devoluciones.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Control de devoluciones</p>
            <h1>Devoluciones</h1>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="resume-grid">
        <div class="mini-card">
            <span class="mini-card__label">Pendientes</span>
            <strong><?php echo (int)$resumen["pendientes"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Vencidos</span>
            <strong><?php echo (int)$resumen["vencidos"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Devueltos hoy</span>
            <strong><?php echo (int)$resumen["devueltos_hoy"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Grupos abiertos</span>
            <strong><?php echo (int)$resumen["grupos_abiertos"]; ?></strong>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card__head table-card__head--stack">
            <div>
                <h2>Préstamos pendientes de devolución</h2>
                <p>Aquí solo aparecen ítems no consumibles que siguen activos.
                    <a href="reportes_prestamos.php" class="btn">Ver reportes</a>
                </p>
            </div>

            <form method="GET" class="toolbar toolbar--filters">
                <div class="search-box">
                    <input
                        type="text"
                        name="q"
                        placeholder="Buscar por grupo, marbete, activo, solicitante o matrícula"
                        value="<?php echo h($busqueda); ?>"
                    >
                </div>

                <div class="filter-row">
                    <div class="filter-field">
                        <label for="fecha_inicio">Fecha de préstamo</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo h($fechaInicioFiltro); ?>">
                    </div>

                    <div class="filter-field">
                        <label for="fecha_limite">Fecha límite</label>
                        <input type="date" name="fecha_limite" id="fecha_limite" value="<?php echo h($fechaLimiteFiltro); ?>">
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn-secondary">Filtrar</button>
                        <a class="btn-clear" href="devoluciones.php">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Activo</th>
                        <th>Solicitante</th>
                        <th>Cant.</th>
                        <th>Ubicación</th>
                        <th>Inicio</th>
                        <th>Límite</th>
                        <th>Estado</th>
                        <th>Registró</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($prestamosActivos)): ?>
                        <?php foreach ($prestamosActivos as $p): ?>
                            <?php $vencido = !empty($p["Fecha_Limite"]) && strtotime($p["Fecha_Limite"]) < time(); ?>
                            <tr>
                                <td><?php echo $p["Grupo_PrestamoID"] ? "#" . (int)$p["Grupo_PrestamoID"] : "—"; ?></td>
                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($p["Activo_Desc"]); ?></strong>
                                        <span>
                                            <?php echo h($p["Num_Marbete"]); ?>
                                            · <?php echo h($p["Tipo_Activo"]); ?>
                                            · ID interno #<?php echo (int)$p["ID_Prestamo"]; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($p["SolicitanteNombre"]); ?></strong>
                                        <span><?php echo h($p["SolicitanteMatricula"]); ?></span>
                                    </div>
                                </td>
                                <td><?php echo (int)$p["Cantidad_Solicitada"]; ?></td>
                                <td><?php echo h($p["Nombre_Lab"] ?? "Sin ubicación"); ?></td>
                                <td><?php echo h(date("d/m/Y H:i", strtotime($p["Fecha_Inicio"]))); ?></td>
                                <td>
                                    <?php echo !empty($p["Fecha_Limite"]) ? h(date("d/m/Y H:i", strtotime($p["Fecha_Limite"]))) : "No aplica"; ?>
                                </td>
                                <td>
                                    <span class="status status--<?php echo $vencido ? 'vencido' : 'activo'; ?>">
                                        <?php echo $vencido ? 'Vencido' : 'Activo'; ?>
                                    </span>
                                </td>
                                <td><?php echo h($p["RegistradoPorNombre"] ?? "Usuario"); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('¿Registrar devolución de este ítem?');">
                                        <input type="hidden" name="accion" value="registrar_devolucion">
                                        <input type="hidden" name="ID_Prestamo" value="<?php echo (int)$p["ID_Prestamo"]; ?>">
                                        <button type="submit" class="btn-table">Registrar devolución</button>
                                    </form>
                                </td>
                            </tr>
                            <?php if (!empty($p["Comentarios"])): ?>
                                <tr class="row-note">
                                    <td colspan="10">
                                        <strong>Comentarios:</strong> <?php echo h($p["Comentarios"]); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="muted">No hay préstamos activos para devolver.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-card history-card">
        <div class="table-card__head">
            <div>
                <h2>Últimas devoluciones</h2>
                <p>Historial reciente de ítems ya devueltos.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Activo</th>
                        <th>Solicitante</th>
                        <th>Cant.</th>
                        <th>Fecha límite</th>
                        <th>Fecha devolución</th>
                        <th>Recibió</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historial)): ?>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td><?php echo $h["Grupo_PrestamoID"] ? "#" . (int)$h["Grupo_PrestamoID"] : "—"; ?></td>
                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($h["Activo_Desc"]); ?></strong>
                                        <span><?php echo h($h["Num_Marbete"]); ?> · <?php echo h($h["Tipo_Activo"]); ?></span>
                                    </div>
                                </td>
                                <td><?php echo h($h["SolicitanteNombre"]); ?></td>
                                <td><?php echo (int)$h["Cantidad_Solicitada"]; ?></td>
                                <td>
                                    <?php echo !empty($h["Fecha_Limite"]) ? h(date("d/m/Y H:i", strtotime($h["Fecha_Limite"]))) : "No aplica"; ?>
                                </td>
                                <td><?php echo h(date("d/m/Y H:i", strtotime($h["Fecha_Devolucion"]))); ?></td>
                                <td><?php echo h($h["RecibidoPorNombre"] ?? "Usuario"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="muted">Todavía no hay devoluciones registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>