<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "ubicaciones";

$rolUsuario = $_SESSION["Rol_Uss"] ?? "";
$puedeEliminarUbicacion = ($rolUsuario === "Administrador");

$mensaje = "";
$error = "";
$mensajeEditar = "";
$errorEditar = "";
$mensajeEliminar = "";
$errorEliminar = "";

$busqueda = trim($_GET["q"] ?? "");

function limpiar_call(mysqli $conexion): void {
    while ($conexion->more_results()) {
        $conexion->next_result();
        $extra = $conexion->store_result();
        if ($extra instanceof mysqli_result) {
            $extra->free();
        }
    }
}

function h(?string $texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/* =========================
   REGISTRAR UBICACIÓN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_ubicacion") {
    try {
        $nombreLab = trim($_POST["Nombre_Lab"] ?? "");

        if ($nombreLab === "") {
            throw new Exception("Escribe el nombre de la ubicación.");
        }

        $stmt = $conexion->prepare("CALL sp_registrar_ubicacion(?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el registro de la ubicación.");
        }

        $stmt->bind_param("s", $nombreLab);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $mensaje = $data["mensaje"] ?? "Ubicación registrada correctamente.";

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $error = $e->getMessage();
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* =========================
   EDITAR UBICACIÓN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_ubicacion") {
    try {
        $idLab = (int)($_POST["ID_Lab"] ?? 0);
        $nombreLab = trim($_POST["Nombre_Lab"] ?? "");

        if ($idLab <= 0) {
            throw new Exception("No se encontró la ubicación a editar.");
        }

        if ($nombreLab === "") {
            throw new Exception("Escribe el nombre de la ubicación.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_ubicacion(?, ?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la edición de la ubicación.");
        }

        $stmt->bind_param("is", $idLab, $nombreLab);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $mensajeEditar = $data["mensaje"] ?? "Ubicación actualizada correctamente.";

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $errorEditar = $e->getMessage();
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $errorEditar = $e->getMessage();
    }
}

/* =========================
   ELIMINAR UBICACIÓN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_ubicacion") {
    try {
        if (!$puedeEliminarUbicacion) {
            throw new Exception("No tienes permisos para eliminar ubicaciones.");
        }

        $idLab = (int)($_POST["ID_Lab"] ?? 0);

        if ($idLab <= 0) {
            throw new Exception("No se encontró la ubicación a eliminar.");
        }

        $stmt = $conexion->prepare("CALL sp_eliminar_ubicacion(?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la eliminación de la ubicación.");
        }

        $stmt->bind_param("i", $idLab);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $mensajeEliminar = $data["mensaje"] ?? "Ubicación eliminada correctamente.";

        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $stmt->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $errorEliminar = $e->getMessage();
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $errorEliminar = $e->getMessage();
    }
}

/* =========================
   LISTAR UBICACIONES
========================= */
$ubicaciones = [];

$stmtListado = $conexion->prepare("CALL sp_listar_ubicaciones(?)");
if ($stmtListado) {
    $stmtListado->bind_param("s", $busqueda);
    $stmtListado->execute();

    $resListado = $stmtListado->get_result();
    if ($resListado) {
        while ($row = $resListado->fetch_assoc()) {
            $ubicaciones[] = $row;
        }
        $resListado->free();
    }

    $stmtListado->close();
    limpiar_call($conexion);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ubicaciones | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/ubicaciones.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Gestión de inventario</p>
            <h1>Ubicaciones / Laboratorios</h1>
        </div>

        <button class="btn-primary" type="button" id="openModalBtn">+ Nueva ubicación</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($mensajeEditar): ?>
        <div class="alert success"><?php echo h($mensajeEditar); ?></div>
    <?php endif; ?>

    <?php if ($mensajeEliminar): ?>
        <div class="alert success"><?php echo h($mensajeEliminar); ?></div>
    <?php endif; ?>

    <?php if ($errorEliminar): ?>
        <div class="alert error"><?php echo h($errorEliminar); ?></div>
    <?php endif; ?>

    <div class="table-card">
        <div class="table-card__head table-card__head--stack">
            <div>
                <h2>Ubicaciones registradas</h2>
                <p>Administra aulas, laboratorios, almacenes y demás espacios del inventario.</p>
            </div>

            <form method="GET" class="toolbar">
                <div class="search-box">
                    <input
                        type="text"
                        name="q"
                        placeholder="Buscar por nombre de ubicación"
                        value="<?php echo h($busqueda); ?>"
                    >
                    <button type="submit" class="btn-secondary">Buscar</button>
                </div>
            </form>
        </div>

        <div class="stats-row">
            <div class="mini-stat">
                <span class="mini-stat__label">Total</span>
                <strong class="mini-stat__value"><?php echo count($ubicaciones); ?></strong>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de ubicación</th>
                        <th>Activos asignados</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ubicaciones)): ?>
                        <?php foreach ($ubicaciones as $u): ?>
                            <tr>
                                <td><?php echo (int)$u["ID_Lab"]; ?></td>
                                <td><?php echo h($u["Nombre_Lab"]); ?></td>
                                <td>
                                    <span class="badge-soft">
                                        <?php echo (int)$u["TotalActivos"]; ?> activo(s)
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button
                                            type="button"
                                            class="btn-table"
                                            onclick='abrirModalEditar(
                                                <?php echo (int)$u["ID_Lab"]; ?>,
                                                <?php echo htmlspecialchars(json_encode($u["Nombre_Lab"]), ENT_QUOTES, "UTF-8"); ?>
                                            )'
                                        >
                                            Editar
                                        </button>

                                        <?php if ($puedeEliminarUbicacion): ?>
                                            <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar esta ubicación?');">
                                                <input type="hidden" name="accion" value="eliminar_ubicacion">
                                                <input type="hidden" name="ID_Lab" value="<?php echo (int)$u["ID_Lab"]; ?>">
                                                <button type="submit" class="btn-table btn-table--danger">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="muted">No hay ubicaciones registradas o no coinciden con la búsqueda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVA -->
<div class="modal" id="ubicacionModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeModalBackdrop"></div>

    <div class="modal__dialog modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal__header">
            <div>
                <h2 id="modalTitle">Nueva ubicación</h2>
                <p>Registra una nueva aula, laboratorio o espacio.</p>
            </div>
            <button class="modal__close" type="button" id="closeModalBtn" aria-label="Cerrar">×</button>
        </div>

        <?php if ($error): ?>
            <div class="alert error modal-alert"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="registrar_ubicacion">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="Nombre_Lab">Nombre de ubicación</label>
                    <input
                        type="text"
                        id="Nombre_Lab"
                        name="Nombre_Lab"
                        maxlength="15"
                        required
                        value="<?php echo h($_POST["Nombre_Lab"] ?? ""); ?>"
                    >
                    <small class="field__hint">Ejemplo: Lab Redes, Almacén, Aula 3.</small>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar ubicación</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal" id="editarModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeEditarBackdrop"></div>

    <div class="modal__dialog modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="editarTitle">
        <div class="modal__header">
            <div>
                <h2 id="editarTitle">Editar ubicación</h2>
                <p>Actualiza el nombre de la ubicación seleccionada.</p>
            </div>
            <button class="modal__close" type="button" id="closeEditarBtn" aria-label="Cerrar">×</button>
        </div>

        <?php if ($errorEditar): ?>
            <div class="alert error modal-alert"><?php echo h($errorEditar); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="editar_ubicacion">
            <input type="hidden" name="ID_Lab" id="editar_ID_Lab" value="<?php echo h($_POST["ID_Lab"] ?? ""); ?>">

            <div class="modal-grid modal-grid--one">
                <div class="field">
                    <label for="editar_Nombre_Lab">Nombre de ubicación</label>
                    <input
                        type="text"
                        id="editar_Nombre_Lab"
                        name="Nombre_Lab"
                        maxlength="15"
                        required
                        value="<?php echo h($_POST["Nombre_Lab"] ?? ""); ?>"
                    >
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelEditarBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
const body = document.body;

/* Modal nuevo */
const modal = document.getElementById("ubicacionModal");
const openModalBtn = document.getElementById("openModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const cancelModalBtn = document.getElementById("cancelModalBtn");
const closeModalBackdrop = document.getElementById("closeModalBackdrop");

/* Modal editar */
const editarModal = document.getElementById("editarModal");
const closeEditarBtn = document.getElementById("closeEditarBtn");
const cancelEditarBtn = document.getElementById("cancelEditarBtn");
const closeEditarBackdrop = document.getElementById("closeEditarBackdrop");

function abrirModal() {
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    body.classList.add("modal-open");
}

function cerrarModal() {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    body.classList.remove("modal-open");
}

function abrirModalEditar(id, nombre) {
    document.getElementById("editar_ID_Lab").value = id;
    document.getElementById("editar_Nombre_Lab").value = nombre ?? "";
    editarModal.classList.add("is-open");
    editarModal.setAttribute("aria-hidden", "false");
    body.classList.add("modal-open");
}

function cerrarModalEditar() {
    editarModal.classList.remove("is-open");
    editarModal.setAttribute("aria-hidden", "true");
    body.classList.remove("modal-open");
}

openModalBtn?.addEventListener("click", abrirModal);
closeModalBtn?.addEventListener("click", cerrarModal);
cancelModalBtn?.addEventListener("click", cerrarModal);
closeModalBackdrop?.addEventListener("click", cerrarModal);

closeEditarBtn?.addEventListener("click", cerrarModalEditar);
cancelEditarBtn?.addEventListener("click", cerrarModalEditar);
closeEditarBackdrop?.addEventListener("click", cerrarModalEditar);

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        cerrarModal();
        cerrarModalEditar();
    }
});

<?php if ($error): ?>
abrirModal();
<?php endif; ?>

<?php if ($errorEditar): ?>
abrirModalEditar(
    <?php echo (int)($_POST["ID_Lab"] ?? 0); ?>,
    <?php echo json_encode($_POST["Nombre_Lab"] ?? ""); ?>
);
<?php endif; ?>
</script>

</body>
</html>