<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "solicitantes";

mysqli_set_charset($conexion, "utf8mb4");

$rolUsuario = $_SESSION["Rol_Uss"] ?? "";
$puedeEliminar = in_array($rolUsuario, ["Administrador", "admin"], true);

$tab = $_GET["tab"] ?? "alumnos";
if (!in_array($tab, ["alumnos", "docentes"], true)) {
    $tab = "alumnos";
}

$estadoAlumnos  = $_GET["estado_alumnos"] ?? "Todos";
$estadoDocentes = $_GET["estado_docentes"] ?? "Todos";

if (!in_array($estadoAlumnos, ["Todos", "Activo", "Baja"], true)) {
    $estadoAlumnos = "Todos";
}
if (!in_array($estadoDocentes, ["Todos", "Activo", "Baja"], true)) {
    $estadoDocentes = "Todos";
}

$qAlumnos  = trim($_GET["q_alumnos"] ?? "");
$qDocentes = trim($_GET["q_docentes"] ?? "");

$mensaje = "";
$error = "";
$modalError = "";
$modalEditarError = "";
$modalEstadoError = "";
$abrirModalNuevo = "";
$abrirModalEditar = "";
$abrirModalEstado = "";
$editarData = [];
$estadoData = [];

function h($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, "UTF-8");
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

function obtenerMensajeExcepcion(Throwable $e): string {
    $msg = trim($e->getMessage());
    return $msg !== "" ? $msg : "Ocurrió un error inesperado.";
}

/* =========================
   REGISTRAR ALUMNO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_alumno") {
    $tab = "alumnos";

    try {
        $matricula = (int)($_POST["Matricula_Alumno"] ?? 0);
        $nombre    = trim($_POST["Nombre_Alumno"] ?? "");
        $carrera   = trim($_POST["Carrera"] ?? "");
        $grupo     = trim($_POST["Grupo"] ?? "");
        $contacto  = trim($_POST["Contacto_Alumno"] ?? "");

        if ($matricula <= 0 || $nombre === "" || $carrera === "" || $grupo === "" || $contacto === "") {
            throw new Exception("Completa todos los campos del alumno.");
        }

        $stmt = $conexion->prepare("CALL sp_registrar_alumno(?,?,?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el registro del alumno.");
        }

        $stmt->bind_param("issss", $matricula, $nombre, $carrera, $grupo, $contacto);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Alumno registrado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalError = obtenerMensajeExcepcion($e);
        $abrirModalNuevo = "alumno";
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalError = obtenerMensajeExcepcion($e);
        $abrirModalNuevo = "alumno";
    }
}

/* =========================
   REGISTRAR DOCENTE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_docente") {
    $tab = "docentes";

    try {
        $matricula = (int)($_POST["Matricula_Docente"] ?? 0);
        $nombre    = trim($_POST["Nombre_Docente"] ?? "");
        $contacto  = trim($_POST["Contacto_Docente"] ?? "");

        if ($matricula <= 0 || $nombre === "" || $contacto === "") {
            throw new Exception("Completa todos los campos del docente.");
        }

        $stmt = $conexion->prepare("CALL sp_registrar_docente(?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el registro del docente.");
        }

        $stmt->bind_param("iss", $matricula, $nombre, $contacto);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Docente registrado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalError = obtenerMensajeExcepcion($e);
        $abrirModalNuevo = "docente";
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalError = obtenerMensajeExcepcion($e);
        $abrirModalNuevo = "docente";
    }
}

/* =========================
   EDITAR ALUMNO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_alumno") {
    $tab = "alumnos";

    try {
        $matriculaOriginal = (int)($_POST["Matricula_Alumno_Original"] ?? 0);
        $matriculaNueva    = (int)($_POST["Matricula_Alumno"] ?? 0);
        $nombre            = trim($_POST["Nombre_Alumno"] ?? "");
        $carrera           = trim($_POST["Carrera"] ?? "");
        $grupo             = trim($_POST["Grupo"] ?? "");
        $contacto          = trim($_POST["Contacto_Alumno"] ?? "");

        if ($matriculaOriginal <= 0 || $matriculaNueva <= 0 || $nombre === "" || $carrera === "" || $grupo === "" || $contacto === "") {
            throw new Exception("Completa todos los campos para editar el alumno.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_alumno(?,?,?,?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la edición del alumno.");
        }

        $stmt->bind_param("iissss", $matriculaOriginal, $matriculaNueva, $nombre, $carrera, $grupo, $contacto);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Alumno actualizado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalEditarError = obtenerMensajeExcepcion($e);
        $abrirModalEditar = "alumno";
        $editarData = [
            "Matricula_Alumno_Original" => $_POST["Matricula_Alumno_Original"] ?? "",
            "Matricula_Alumno" => $_POST["Matricula_Alumno"] ?? "",
            "Nombre_Alumno" => $_POST["Nombre_Alumno"] ?? "",
            "Carrera" => $_POST["Carrera"] ?? "",
            "Grupo" => $_POST["Grupo"] ?? "",
            "Contacto_Alumno" => $_POST["Contacto_Alumno"] ?? "",
        ];
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalEditarError = obtenerMensajeExcepcion($e);
        $abrirModalEditar = "alumno";
        $editarData = [
            "Matricula_Alumno_Original" => $_POST["Matricula_Alumno_Original"] ?? "",
            "Matricula_Alumno" => $_POST["Matricula_Alumno"] ?? "",
            "Nombre_Alumno" => $_POST["Nombre_Alumno"] ?? "",
            "Carrera" => $_POST["Carrera"] ?? "",
            "Grupo" => $_POST["Grupo"] ?? "",
            "Contacto_Alumno" => $_POST["Contacto_Alumno"] ?? "",
        ];
    }
}

/* =========================
   EDITAR DOCENTE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_docente") {
    $tab = "docentes";

    try {
        $matriculaOriginal = (int)($_POST["Matricula_Docente_Original"] ?? 0);
        $matriculaNueva    = (int)($_POST["Matricula_Docente"] ?? 0);
        $nombre            = trim($_POST["Nombre_Docente"] ?? "");
        $contacto          = trim($_POST["Contacto_Docente"] ?? "");

        if ($matriculaOriginal <= 0 || $matriculaNueva <= 0 || $nombre === "" || $contacto === "") {
            throw new Exception("Completa todos los campos para editar el docente.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_docente(?,?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la edición del docente.");
        }

        $stmt->bind_param("iiss", $matriculaOriginal, $matriculaNueva, $nombre, $contacto);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Docente actualizado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalEditarError = obtenerMensajeExcepcion($e);
        $abrirModalEditar = "docente";
        $editarData = [
            "Matricula_Docente_Original" => $_POST["Matricula_Docente_Original"] ?? "",
            "Matricula_Docente" => $_POST["Matricula_Docente"] ?? "",
            "Nombre_Docente" => $_POST["Nombre_Docente"] ?? "",
            "Contacto_Docente" => $_POST["Contacto_Docente"] ?? "",
        ];
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalEditarError = obtenerMensajeExcepcion($e);
        $abrirModalEditar = "docente";
        $editarData = [
            "Matricula_Docente_Original" => $_POST["Matricula_Docente_Original"] ?? "",
            "Matricula_Docente" => $_POST["Matricula_Docente"] ?? "",
            "Nombre_Docente" => $_POST["Nombre_Docente"] ?? "",
            "Contacto_Docente" => $_POST["Contacto_Docente"] ?? "",
        ];
    }
}

/* =========================
   CAMBIAR ESTADO ALUMNO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cambiar_estado_alumno") {
    $tab = "alumnos";

    try {
        $matricula = (int)($_POST["Matricula_Alumno"] ?? 0);
        $estado    = trim($_POST["Estado"] ?? "");

        if ($matricula <= 0 || !in_array($estado, ["Activo", "Baja"], true)) {
            throw new Exception("Datos inválidos para cambiar el estado del alumno.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_estado_alumno(?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el cambio de estado del alumno.");
        }

        $stmt->bind_param("is", $matricula, $estado);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Estado del alumno actualizado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalEstadoError = obtenerMensajeExcepcion($e);
        $abrirModalEstado = "alumno";
        $estadoData = [
            "Matricula_Alumno" => $_POST["Matricula_Alumno"] ?? "",
            "Estado" => $_POST["Estado"] ?? "",
        ];
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalEstadoError = obtenerMensajeExcepcion($e);
        $abrirModalEstado = "alumno";
        $estadoData = [
            "Matricula_Alumno" => $_POST["Matricula_Alumno"] ?? "",
            "Estado" => $_POST["Estado"] ?? "",
        ];
    }
}

/* =========================
   CAMBIAR ESTADO DOCENTE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cambiar_estado_docente") {
    $tab = "docentes";

    try {
        $matricula = (int)($_POST["Matricula_Docente"] ?? 0);
        $estado    = trim($_POST["Estado"] ?? "");

        if ($matricula <= 0 || !in_array($estado, ["Activo", "Baja"], true)) {
            throw new Exception("Datos inválidos para cambiar el estado del docente.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_estado_docente(?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el cambio de estado del docente.");
        }

        $stmt->bind_param("is", $matricula, $estado);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Estado del docente actualizado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $modalEstadoError = obtenerMensajeExcepcion($e);
        $abrirModalEstado = "docente";
        $estadoData = [
            "Matricula_Docente" => $_POST["Matricula_Docente"] ?? "",
            "Estado" => $_POST["Estado"] ?? "",
        ];
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $modalEstadoError = obtenerMensajeExcepcion($e);
        $abrirModalEstado = "docente";
        $estadoData = [
            "Matricula_Docente" => $_POST["Matricula_Docente"] ?? "",
            "Estado" => $_POST["Estado"] ?? "",
        ];
    }
}

/* =========================
   ELIMINAR ALUMNO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_alumno") {
    $tab = "alumnos";

    try {
        if (!$puedeEliminar) {
            throw new Exception("Solo un administrador puede eliminar alumnos.");
        }

        $matricula = (int)($_POST["Matricula_Alumno"] ?? 0);
        if ($matricula <= 0) {
            throw new Exception("No se encontró el alumno a eliminar.");
        }

        $stmt = $conexion->prepare("CALL sp_eliminar_alumno(?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la eliminación del alumno.");
        }

        $stmt->bind_param("i", $matricula);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Alumno eliminado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $error = obtenerMensajeExcepcion($e);
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $error = obtenerMensajeExcepcion($e);
    }
}

/* =========================
   ELIMINAR DOCENTE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_docente") {
    $tab = "docentes";

    try {
        if (!$puedeEliminar) {
            throw new Exception("Solo un administrador puede eliminar docentes.");
        }

        $matricula = (int)($_POST["Matricula_Docente"] ?? 0);
        if ($matricula <= 0) {
            throw new Exception("No se encontró el docente a eliminar.");
        }

        $stmt = $conexion->prepare("CALL sp_eliminar_docente(?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la eliminación del docente.");
        }

        $stmt->bind_param("i", $matricula);
        $stmt->execute();

        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $mensaje = $row["mensaje"] ?? "Docente eliminado correctamente.";

        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $error = obtenerMensajeExcepcion($e);
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $error = obtenerMensajeExcepcion($e);
    }
}

/* =========================
   LISTAR ALUMNOS
========================= */
$alumnos = [];

try {
    $sqlAlumnos = "
        SELECT
            Matricula_Alumno,
            Nombre_Alumno,
            Carrera,
            Grupo,
            Contacto_Alumno,
            Estado
        FROM alumnos
        WHERE
            (? = 'Todos' OR Estado = ?)
            AND (
                ? = ''
                OR CAST(Matricula_Alumno AS CHAR) LIKE CONCAT('%', ?, '%')
                OR Nombre_Alumno LIKE CONCAT('%', ?, '%')
                OR Carrera LIKE CONCAT('%', ?, '%')
                OR Grupo LIKE CONCAT('%', ?, '%')
                OR Contacto_Alumno LIKE CONCAT('%', ?, '%')
            )
        ORDER BY Nombre_Alumno ASC
    ";

    $stmt = $conexion->prepare($sqlAlumnos);
    if (!$stmt) {
        throw new Exception("No se pudo preparar la consulta de alumnos.");
    }

    $stmt->bind_param(
        "ssssssss",
        $estadoAlumnos,
        $estadoAlumnos,
        $qAlumnos,
        $qAlumnos,
        $qAlumnos,
        $qAlumnos,
        $qAlumnos,
        $qAlumnos
    );
    $stmt->execute();

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $alumnos[] = $row;
    }

    $res->free();
    $stmt->close();

} catch (Throwable $e) {
    if ($error === "") {
        $error = "Error al cargar alumnos: " . obtenerMensajeExcepcion($e);
    }
}

/* =========================
   LISTAR DOCENTES
========================= */
$docentes = [];

try {
    $sqlDocentes = "
        SELECT
            Matricula_Docente,
            Nombre_Docente,
            Contacto_Docente,
            Estado
        FROM docentes
        WHERE
            (? = 'Todos' OR Estado = ?)
            AND (
                ? = ''
                OR CAST(Matricula_Docente AS CHAR) LIKE CONCAT('%', ?, '%')
                OR Nombre_Docente LIKE CONCAT('%', ?, '%')
                OR Contacto_Docente LIKE CONCAT('%', ?, '%')
            )
        ORDER BY Nombre_Docente ASC
    ";

    $stmt = $conexion->prepare($sqlDocentes);
    if (!$stmt) {
        throw new Exception("No se pudo preparar la consulta de docentes.");
    }

    $stmt->bind_param(
        "ssssss",
        $estadoDocentes,
        $estadoDocentes,
        $qDocentes,
        $qDocentes,
        $qDocentes,
        $qDocentes
    );
    $stmt->execute();

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $docentes[] = $row;
    }

    $res->free();
    $stmt->close();

} catch (Throwable $e) {
    if ($error === "") {
        $error = "Error al cargar docentes: " . obtenerMensajeExcepcion($e);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitantes | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/solicitantes.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Gestión de inventario</p>
            <h1>Solicitantes</h1>
        </div>

        <div class="topbar__actions">
            <?php if ($tab === "alumnos"): ?>
                <button class="btn-primary" type="button" onclick="abrirModal('modalNuevoAlumno')">+ Nuevo alumno</button>
            <?php else: ?>
                <button class="btn-primary" type="button" onclick="abrirModal('modalNuevoDocente')">+ Nuevo docente</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="tabs-card">
        <div class="tabs-nav">
            <a class="tab-btn<?php echo $tab === 'alumnos' ? ' is-active' : ''; ?>" href="?tab=alumnos&estado_alumnos=<?php echo urlencode($estadoAlumnos); ?>&q_alumnos=<?php echo urlencode($qAlumnos); ?>">
                Alumnos
            </a>
            <a class="tab-btn<?php echo $tab === 'docentes' ? ' is-active' : ''; ?>" href="?tab=docentes&estado_docentes=<?php echo urlencode($estadoDocentes); ?>&q_docentes=<?php echo urlencode($qDocentes); ?>">
                Docentes
            </a>
        </div>

        <?php if ($tab === "alumnos"): ?>
            <div class="table-card">
                <div class="table-card__head table-card__head--stack">
                    <div>
                        <h2>Alumnos registrados</h2>
                        <p>Administra matrículas, carrera, grupo, contacto y estado.</p>
                    </div>

                    <form method="GET" class="toolbar toolbar--filters">
                        <input type="hidden" name="tab" value="alumnos">

                        <div class="segmented">
                            <?php foreach (["Todos", "Activo", "Baja"] as $opt): ?>
                                <a
                                    class="segmented__item<?php echo $estadoAlumnos === $opt ? ' is-active' : ''; ?>"
                                    href="?tab=alumnos&estado_alumnos=<?php echo urlencode($opt); ?>&q_alumnos=<?php echo urlencode($qAlumnos); ?>"
                                >
                                    <?php echo h($opt); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="search-box">
                            <input
                                type="text"
                                name="q_alumnos"
                                placeholder="Buscar alumno por matrícula, nombre, carrera, grupo o contacto"
                                value="<?php echo h($qAlumnos); ?>"
                            >
                            <input type="hidden" name="estado_alumnos" value="<?php echo h($estadoAlumnos); ?>">
                            <button type="submit" class="btn-secondary">Buscar</button>
                        </div>
                    </form>
                </div>

                <div class="stats-row">
                    <div class="mini-stat">
                        <span class="mini-stat__label">Total de alumnos</span>
                        <strong class="mini-stat__value"><?php echo count($alumnos); ?></strong>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre</th>
                                <th>Carrera</th>
                                <th>Grupo</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($alumnos)): ?>
                                <?php foreach ($alumnos as $a): ?>
                                    <tr>
                                        <td><?php echo (int)$a["Matricula_Alumno"]; ?></td>
                                        <td><?php echo h($a["Nombre_Alumno"]); ?></td>
                                        <td><?php echo h($a["Carrera"]); ?></td>
                                        <td><?php echo h($a["Grupo"]); ?></td>
                                        <td><?php echo h($a["Contacto_Alumno"]); ?></td>
                                        <td>
                                            <span class="status <?php echo $a["Estado"] === "Activo" ? "status--activo" : "status--baja"; ?>">
                                                <?php echo h($a["Estado"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button
                                                    type="button"
                                                    class="btn-table"
                                                    onclick='abrirEditarAlumno(
                                                        <?php echo json_encode($a["Matricula_Alumno"]); ?>,
                                                        <?php echo json_encode($a["Nombre_Alumno"]); ?>,
                                                        <?php echo json_encode($a["Carrera"]); ?>,
                                                        <?php echo json_encode($a["Grupo"]); ?>,
                                                        <?php echo json_encode($a["Contacto_Alumno"]); ?>
                                                    )'
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn-table btn-table--state"
                                                    onclick='abrirEstadoAlumno(
                                                        <?php echo json_encode($a["Matricula_Alumno"]); ?>,
                                                        <?php echo json_encode($a["Estado"]); ?>
                                                    )'
                                                >
                                                    Estado
                                                </button>

                                                <?php if ($puedeEliminar): ?>
                                                    <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar este alumno?');">
                                                        <input type="hidden" name="accion" value="eliminar_alumno">
                                                        <input type="hidden" name="Matricula_Alumno" value="<?php echo (int)$a["Matricula_Alumno"]; ?>">
                                                        <button type="submit" class="btn-table btn-table--danger">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="muted">No hay alumnos registrados o no coinciden con la búsqueda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-card__head table-card__head--stack">
                    <div>
                        <h2>Docentes registrados</h2>
                        <p>Administra matrícula, nombre, contacto y estado.</p>
                    </div>

                    <form method="GET" class="toolbar toolbar--filters">
                        <input type="hidden" name="tab" value="docentes">

                        <div class="segmented">
                            <?php foreach (["Todos", "Activo", "Baja"] as $opt): ?>
                                <a
                                    class="segmented__item<?php echo $estadoDocentes === $opt ? ' is-active' : ''; ?>"
                                    href="?tab=docentes&estado_docentes=<?php echo urlencode($opt); ?>&q_docentes=<?php echo urlencode($qDocentes); ?>"
                                >
                                    <?php echo h($opt); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div class="search-box">
                            <input
                                type="text"
                                name="q_docentes"
                                placeholder="Buscar docente por matrícula, nombre o contacto"
                                value="<?php echo h($qDocentes); ?>"
                            >
                            <input type="hidden" name="estado_docentes" value="<?php echo h($estadoDocentes); ?>">
                            <button type="submit" class="btn-secondary">Buscar</button>
                        </div>
                    </form>
                </div>

                <div class="stats-row">
                    <div class="mini-stat">
                        <span class="mini-stat__label">Total de docentes</span>
                        <strong class="mini-stat__value"><?php echo count($docentes); ?></strong>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Matrícula</th>
                                <th>Nombre</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($docentes)): ?>
                                <?php foreach ($docentes as $d): ?>
                                    <tr>
                                        <td><?php echo (int)$d["Matricula_Docente"]; ?></td>
                                        <td><?php echo h($d["Nombre_Docente"]); ?></td>
                                        <td><?php echo h($d["Contacto_Docente"]); ?></td>
                                        <td>
                                            <span class="status <?php echo $d["Estado"] === "Activo" ? "status--activo" : "status--baja"; ?>">
                                                <?php echo h($d["Estado"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button
                                                    type="button"
                                                    class="btn-table"
                                                    onclick='abrirEditarDocente(
                                                        <?php echo json_encode($d["Matricula_Docente"]); ?>,
                                                        <?php echo json_encode($d["Nombre_Docente"]); ?>,
                                                        <?php echo json_encode($d["Contacto_Docente"]); ?>
                                                    )'
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn-table btn-table--state"
                                                    onclick='abrirEstadoDocente(
                                                        <?php echo json_encode($d["Matricula_Docente"]); ?>,
                                                        <?php echo json_encode($d["Estado"]); ?>
                                                    )'
                                                >
                                                    Estado
                                                </button>

                                                <?php if ($puedeEliminar): ?>
                                                    <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar este docente?');">
                                                        <input type="hidden" name="accion" value="eliminar_docente">
                                                        <input type="hidden" name="Matricula_Docente" value="<?php echo (int)$d["Matricula_Docente"]; ?>">
                                                        <button type="submit" class="btn-table btn-table--danger">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="muted">No hay docentes registrados o no coinciden con la búsqueda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL NUEVO ALUMNO -->
<div class="modal" id="modalNuevoAlumno" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalNuevoAlumno')"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <div>
                <h2>Nuevo alumno</h2>
                <p>Registra un nuevo alumno en el sistema.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalNuevoAlumno')">×</button>
        </div>

        <?php if ($abrirModalNuevo === "alumno" && $modalError): ?>
            <div class="alert error modal-alert"><?php echo h($modalError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="registrar_alumno">

            <div class="modal-grid">
                <div class="field">
                    <label for="nuevo_matricula_alumno">Matrícula</label>
                    <input type="number" id="nuevo_matricula_alumno" name="Matricula_Alumno" required value="<?php echo h($_POST["Matricula_Alumno"] ?? ""); ?>">
                </div>

                <div class="field field--full">
                    <label for="nuevo_nombre_alumno">Nombre completo</label>
                    <input type="text" id="nuevo_nombre_alumno" name="Nombre_Alumno" maxlength="150" required value="<?php echo h($_POST["Nombre_Alumno"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="nuevo_carrera_alumno">Carrera</label>
                    <input type="text" id="nuevo_carrera_alumno" name="Carrera" maxlength="50" required value="<?php echo h($_POST["Carrera"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="nuevo_grupo_alumno">Grupo</label>
                    <input type="text" id="nuevo_grupo_alumno" name="Grupo" maxlength="20" required value="<?php echo h($_POST["Grupo"] ?? ""); ?>">
                </div>

                <div class="field field--full">
                    <label for="nuevo_contacto_alumno">Contacto</label>
                    <input type="text" id="nuevo_contacto_alumno" name="Contacto_Alumno" maxlength="15" required value="<?php echo h($_POST["Contacto_Alumno"] ?? ""); ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalNuevoAlumno')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar alumno</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NUEVO DOCENTE -->
<div class="modal" id="modalNuevoDocente" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalNuevoDocente')"></div>
    <div class="modal__dialog modal__dialog--small">
        <div class="modal__header">
            <div>
                <h2>Nuevo docente</h2>
                <p>Registra un nuevo docente en el sistema.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalNuevoDocente')">×</button>
        </div>

        <?php if ($abrirModalNuevo === "docente" && $modalError): ?>
            <div class="alert error modal-alert"><?php echo h($modalError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="registrar_docente">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="nuevo_matricula_docente">Matrícula</label>
                    <input type="number" id="nuevo_matricula_docente" name="Matricula_Docente" required value="<?php echo h($_POST["Matricula_Docente"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="nuevo_nombre_docente">Nombre completo</label>
                    <input type="text" id="nuevo_nombre_docente" name="Nombre_Docente" maxlength="150" required value="<?php echo h($_POST["Nombre_Docente"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="nuevo_contacto_docente">Contacto</label>
                    <input type="text" id="nuevo_contacto_docente" name="Contacto_Docente" maxlength="15" required value="<?php echo h($_POST["Contacto_Docente"] ?? ""); ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalNuevoDocente')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar docente</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR ALUMNO -->
<div class="modal" id="modalEditarAlumno" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalEditarAlumno')"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <div>
                <h2>Editar alumno</h2>
                <p>Actualiza los datos del alumno seleccionado.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalEditarAlumno')">×</button>
        </div>

        <?php if ($abrirModalEditar === "alumno" && $modalEditarError): ?>
            <div class="alert error modal-alert"><?php echo h($modalEditarError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="editar_alumno">
            <input type="hidden" name="Matricula_Alumno_Original" id="editar_alumno_original" value="<?php echo h($editarData["Matricula_Alumno_Original"] ?? ""); ?>">

            <div class="modal-grid">
                <div class="field">
                    <label for="editar_matricula_alumno">Matrícula</label>
                    <input type="number" id="editar_matricula_alumno" name="Matricula_Alumno" required value="<?php echo h($editarData["Matricula_Alumno"] ?? ""); ?>">
                </div>

                <div class="field field--full">
                    <label for="editar_nombre_alumno">Nombre completo</label>
                    <input type="text" id="editar_nombre_alumno" name="Nombre_Alumno" maxlength="150" required value="<?php echo h($editarData["Nombre_Alumno"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="editar_carrera_alumno">Carrera</label>
                    <input type="text" id="editar_carrera_alumno" name="Carrera" maxlength="50" required value="<?php echo h($editarData["Carrera"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="editar_grupo_alumno">Grupo</label>
                    <input type="text" id="editar_grupo_alumno" name="Grupo" maxlength="20" required value="<?php echo h($editarData["Grupo"] ?? ""); ?>">
                </div>

                <div class="field field--full">
                    <label for="editar_contacto_alumno">Contacto</label>
                    <input type="text" id="editar_contacto_alumno" name="Contacto_Alumno" maxlength="15" required value="<?php echo h($editarData["Contacto_Alumno"] ?? ""); ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarAlumno')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR DOCENTE -->
<div class="modal" id="modalEditarDocente" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalEditarDocente')"></div>
    <div class="modal__dialog modal__dialog--small">
        <div class="modal__header">
            <div>
                <h2>Editar docente</h2>
                <p>Actualiza los datos del docente seleccionado.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalEditarDocente')">×</button>
        </div>

        <?php if ($abrirModalEditar === "docente" && $modalEditarError): ?>
            <div class="alert error modal-alert"><?php echo h($modalEditarError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="editar_docente">
            <input type="hidden" name="Matricula_Docente_Original" id="editar_docente_original" value="<?php echo h($editarData["Matricula_Docente_Original"] ?? ""); ?>">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="editar_matricula_docente">Matrícula</label>
                    <input type="number" id="editar_matricula_docente" name="Matricula_Docente" required value="<?php echo h($editarData["Matricula_Docente"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="editar_nombre_docente">Nombre completo</label>
                    <input type="text" id="editar_nombre_docente" name="Nombre_Docente" maxlength="150" required value="<?php echo h($editarData["Nombre_Docente"] ?? ""); ?>">
                </div>

                <div class="field">
                    <label for="editar_contacto_docente">Contacto</label>
                    <input type="text" id="editar_contacto_docente" name="Contacto_Docente" maxlength="15" required value="<?php echo h($editarData["Contacto_Docente"] ?? ""); ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalEditarDocente')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ESTADO ALUMNO -->
<div class="modal" id="modalEstadoAlumno" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalEstadoAlumno')"></div>
    <div class="modal__dialog modal__dialog--small">
        <div class="modal__header">
            <div>
                <h2>Cambiar estado del alumno</h2>
                <p>Selecciona el nuevo estado del alumno.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalEstadoAlumno')">×</button>
        </div>

        <?php if ($abrirModalEstado === "alumno" && $modalEstadoError): ?>
            <div class="alert error modal-alert"><?php echo h($modalEstadoError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="cambiar_estado_alumno">
            <input type="hidden" name="Matricula_Alumno" id="estado_alumno_matricula" value="<?php echo h($estadoData["Matricula_Alumno"] ?? ""); ?>">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="estado_alumno_select">Estado</label>
                    <select id="estado_alumno_select" name="Estado" required>
                        <option value="Activo" <?php echo (($estadoData["Estado"] ?? "") === "Activo") ? "selected" : ""; ?>>Activo</option>
                        <option value="Baja" <?php echo (($estadoData["Estado"] ?? "") === "Baja") ? "selected" : ""; ?>>Baja</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalEstadoAlumno')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar estado</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ESTADO DOCENTE -->
<div class="modal" id="modalEstadoDocente" aria-hidden="true">
    <div class="modal__backdrop" onclick="cerrarModal('modalEstadoDocente')"></div>
    <div class="modal__dialog modal__dialog--small">
        <div class="modal__header">
            <div>
                <h2>Cambiar estado del docente</h2>
                <p>Selecciona el nuevo estado del docente.</p>
            </div>
            <button class="modal__close" type="button" onclick="cerrarModal('modalEstadoDocente')">×</button>
        </div>

        <?php if ($abrirModalEstado === "docente" && $modalEstadoError): ?>
            <div class="alert error modal-alert"><?php echo h($modalEstadoError); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="cambiar_estado_docente">
            <input type="hidden" name="Matricula_Docente" id="estado_docente_matricula" value="<?php echo h($estadoData["Matricula_Docente"] ?? ""); ?>">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="estado_docente_select">Estado</label>
                    <select id="estado_docente_select" name="Estado" required>
                        <option value="Activo" <?php echo (($estadoData["Estado"] ?? "") === "Activo") ? "selected" : ""; ?>>Activo</option>
                        <option value="Baja" <?php echo (($estadoData["Estado"] ?? "") === "Baja") ? "selected" : ""; ?>>Baja</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="cerrarModal('modalEstadoDocente')">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar estado</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");

    const abiertos = document.querySelectorAll(".modal.is-open");
    if (abiertos.length === 0) {
        document.body.classList.remove("modal-open");
    }
}

function abrirEditarAlumno(matricula, nombre, carrera, grupo, contacto) {
    document.getElementById("editar_alumno_original").value = matricula ?? "";
    document.getElementById("editar_matricula_alumno").value = matricula ?? "";
    document.getElementById("editar_nombre_alumno").value = nombre ?? "";
    document.getElementById("editar_carrera_alumno").value = carrera ?? "";
    document.getElementById("editar_grupo_alumno").value = grupo ?? "";
    document.getElementById("editar_contacto_alumno").value = contacto ?? "";
    abrirModal("modalEditarAlumno");
}

function abrirEditarDocente(matricula, nombre, contacto) {
    document.getElementById("editar_docente_original").value = matricula ?? "";
    document.getElementById("editar_matricula_docente").value = matricula ?? "";
    document.getElementById("editar_nombre_docente").value = nombre ?? "";
    document.getElementById("editar_contacto_docente").value = contacto ?? "";
    abrirModal("modalEditarDocente");
}

function abrirEstadoAlumno(matricula, estado) {
    document.getElementById("estado_alumno_matricula").value = matricula ?? "";
    document.getElementById("estado_alumno_select").value = estado ?? "Activo";
    abrirModal("modalEstadoAlumno");
}

function abrirEstadoDocente(matricula, estado) {
    document.getElementById("estado_docente_matricula").value = matricula ?? "";
    document.getElementById("estado_docente_select").value = estado ?? "Activo";
    abrirModal("modalEstadoDocente");
}

document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal.is-open").forEach(modal => {
            modal.classList.remove("is-open");
            modal.setAttribute("aria-hidden", "true");
        });
        document.body.classList.remove("modal-open");
    }
});

<?php if ($abrirModalNuevo === "alumno"): ?>
abrirModal("modalNuevoAlumno");
<?php endif; ?>

<?php if ($abrirModalNuevo === "docente"): ?>
abrirModal("modalNuevoDocente");
<?php endif; ?>

<?php if ($abrirModalEditar === "alumno"): ?>
abrirModal("modalEditarAlumno");
<?php endif; ?>

<?php if ($abrirModalEditar === "docente"): ?>
abrirModal("modalEditarDocente");
<?php endif; ?>

<?php if ($abrirModalEstado === "alumno"): ?>
abrirModal("modalEstadoAlumno");
<?php endif; ?>

<?php if ($abrirModalEstado === "docente"): ?>
abrirModal("modalEstadoDocente");
<?php endif; ?>
</script>

</body>
</html>