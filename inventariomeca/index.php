<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "dashboard";

$nombreUsuario = $_SESSION["Nombre_Uss"] ?? "Usuario";
$rolUsuario    = $_SESSION["Rol_Uss"] ?? "Sin rol";

// =========================
// DASHBOARD: resumen
// =========================
$resumen = [
    "total_activos" => 0,
    "activos_disponibles" => 0,
    "activos_prestados" => 0,
    "activos_mantenimiento" => 0,
    "activos_baja" => 0,
    "activos_en_almacen" => 0,
    "prestamos_activos" => 0,
    "prestamos_devueltos" => 0,
    "prestamos_vencidos" => 0,
    "prestamos_hoy" => 0
];

$mensajeError = "";

try {
    $sql = "CALL sp_dashboard_resumen()";
    $res = mysqli_query($conexion, $sql);

    if ($res) {
        $fila = mysqli_fetch_assoc($res);
        if ($fila) {
            $resumen = array_merge($resumen, $fila);
        }
        mysqli_free_result($res);
    } else {
        throw new Exception("No se pudo consultar el resumen.");
    }

    while (mysqli_more_results($conexion)) {
        mysqli_next_result($conexion);
        $extraResult = mysqli_store_result($conexion);
        if ($extraResult instanceof mysqli_result) {
            mysqli_free_result($extraResult);
        }
    }

} catch (Throwable $e) {
    $mensajeError = "No se pudo cargar el resumen del dashboard.";
}

// =========================
// PRÉSTAMOS RECIENTES
// =========================
$prestamosRecientes = [];

$sqlPrestamos = "
    SELECT 
        p.Grupo_PrestamoID,
        a.Num_Marbete,
        a.Activo_Desc,
        p.Matricula_Alumno,
        p.Matricula_Docente,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Estado_Prestamo
    FROM prestamos p
    INNER JOIN activos a ON a.ID_Activo = p.ID_Activo
    ORDER BY p.Grupo_PrestamoID DESC
    LIMIT 5
";

$resultPrestamos = mysqli_query($conexion, $sqlPrestamos);
if ($resultPrestamos) {
    while ($row = mysqli_fetch_assoc($resultPrestamos)) {
        $prestamosRecientes[] = $row;
    }
    mysqli_free_result($resultPrestamos);
}

// =========================
// ACTIVOS RECIENTES
// =========================
$activosRecientes = [];

$sqlActivos = "
    SELECT 
        a.ID_Activo,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Estado,
        a.Tipo_Activo,
        a.Cantidad,
        u.Nombre_Lab
    FROM activos a
    LEFT JOIN ubicaciones u ON u.ID_Lab = a.ID_Lab
    ORDER BY a.ID_Activo DESC
    LIMIT 5
";

$resultActivos = mysqli_query($conexion, $sqlActivos);
if ($resultActivos) {
    while ($row = mysqli_fetch_assoc($resultActivos)) {
        $activosRecientes[] = $row;
    }
    mysqli_free_result($resultActivos);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Inventario Laboratorio</title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

    <?php include "includes/sidebar.php"; ?>

    <div class="main">
        <header class="topbar">
            <div>
                <p class="topbar__eyebrow">Panel principal</p>
                <h1>Inventario de Laboratorio - Mecatrónica</h1>
            </div>

            <div class="user">
                <span class="badge"><?php echo htmlspecialchars($nombreUsuario); ?></span>
                <span class="badge"><?php echo htmlspecialchars($rolUsuario); ?></span>
            </div>
        </header>

        <section class="welcome">
            <h2>Bienvenido/a, <?php echo htmlspecialchars($nombreUsuario); ?></h2>
            <p>Este es el panel principal del sistema de inventario y préstamos del laboratorio.</p>
        </section>

        <?php if ($mensajeError !== ""): ?>
            <div class="error"><?php echo htmlspecialchars($mensajeError); ?></div>
        <?php endif; ?>

        <section class="grid">
            <article class="card">
                <h3>Total de activos</h3>
                <div class="value"><?php echo (int)$resumen["total_activos"]; ?></div>
            </article>

            <article class="card">
                <h3>Activos disponibles</h3>
                <div class="value"><?php echo (int)$resumen["activos_disponibles"]; ?></div>
            </article>

            <article class="card">
                <h3>Activos prestados</h3>
                <div class="value"><?php echo (int)$resumen["activos_prestados"]; ?></div>
            </article>

            <article class="card">
                <h3>En almacén</h3>
                <div class="value"><?php echo (int)$resumen["activos_en_almacen"]; ?></div>
            </article>

            <article class="card">
                <h3>Préstamos activos</h3>
                <div class="value"><?php echo (int)$resumen["prestamos_activos"]; ?></div>
            </article>

            <article class="card">
                <h3>Préstamos vencidos</h3>
                <div class="value"><?php echo (int)$resumen["prestamos_vencidos"]; ?></div>
            </article>

            <article class="card">
                <h3>Devueltos</h3>
                <div class="value"><?php echo (int)$resumen["prestamos_devueltos"]; ?></div>
            </article>

            <article class="card">
                <h3>Préstamos hoy</h3>
                <div class="value"><?php echo (int)$resumen["prestamos_hoy"]; ?></div>
            </article>
        </section>

        <section class="tables">
            <article class="panel">
                <div class="panel__head">
                    <h3>Préstamos recientes</h3>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Marbete</th>
                            <th>Solicitante</th>
                            <th>Límite</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($prestamosRecientes)): ?>
                            <?php foreach ($prestamosRecientes as $p): ?>
                                <tr>
                                    <td><?php echo (int)$p["Grupo_PrestamoID"]; ?></td>
                                    <td><?php echo htmlspecialchars($p["Num_Marbete"]); ?></td>
                                    <td>
                                        <?php
                                            if (!empty($p["Matricula_Alumno"])) {
                                                echo "Alumno: " . htmlspecialchars($p["Matricula_Alumno"]);
                                            } elseif (!empty($p["Matricula_Docente"])) {
                                                echo "Docente: " . htmlspecialchars($p["Matricula_Docente"]);
                                            } else {
                                                echo '<span class="muted">Sin dato</span>';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p["Fecha_Limite"]); ?></td>
                                    <td>
                                        <span class="status"><?php echo htmlspecialchars($p["Estado_Prestamo"]); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="muted">No hay préstamos registrados todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </article>

            <article class="panel">
                <div class="panel__head">
                    <h3>Activos recientes</h3>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Marbete</th>
                            <th>Descripción</th>
                            <th>Ubicación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activosRecientes)): ?>
                            <?php foreach ($activosRecientes as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a["Num_Marbete"]); ?></td>
                                    <td><?php echo htmlspecialchars($a["Activo_Desc"]); ?></td>
                                    <td><?php echo htmlspecialchars($a["Nombre_Lab"] ?? "Sin ubicación"); ?></td>
                                    <td>
                                        <span class="status"><?php echo htmlspecialchars($a["Estado"]); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="muted">No hay activos registrados todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </article>
        </section>
    </div>

</body>
</html>