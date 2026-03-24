<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "activos";

$rolUsuario = $_SESSION["Rol_Uss"] ?? "";

$mensaje = "";
$error = "";
$mensajeEstado = "";
$errorEstado = "";
$mensajeEditar = "";
$errorEditar = "";
$mensajeEliminar = "";
$errorEliminar = "";

$tab = trim($_GET["tab"] ?? "Todos");
$tabsValidas = ["Todos", "Activo", "Prestado", "Mantenimiento", "Baja"];
if (!in_array($tab, $tabsValidas, true)) {
    $tab = "Todos";
}

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
   UBICACIONES
========================= */
$ubicaciones = [];
$resUb = $conexion->query("SELECT ID_Lab, Nombre_Lab FROM ubicaciones ORDER BY Nombre_Lab ASC");
if ($resUb) {
    while ($row = $resUb->fetch_assoc()) {
        $ubicaciones[] = $row;
    }
    $resUb->free();
}

/* =========================
   REGISTRAR ACTIVO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_activo") {
    try {
        $marbete = trim($_POST["Num_Marbete"] ?? "");
        $desc    = trim($_POST["Activo_Desc"] ?? "");
        $estado  = trim($_POST["Estado"] ?? "Activo");
        $lab     = isset($_POST["ID_Lab"]) ? (int)$_POST["ID_Lab"] : 1;
        $tipo    = trim($_POST["Tipo_Activo"] ?? "No Consumible");
        $cantidad = ($tipo === "Consumible")
            ? max(1, (int)($_POST["Cantidad"] ?? 1))
            : 1;

        if ($marbete === "" || $desc === "") {
            throw new Exception("Completa los campos obligatorios del activo.");
        }

        $stmt = $conexion->prepare("CALL sp_registrar_activo(?,?,?,?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar el registro del activo.");
        }

        $stmt->bind_param("sssisi", $marbete, $desc, $estado, $lab, $tipo, $cantidad);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $mensaje = $data["mensaje"] ?? "Activo registrado correctamente.";

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
   EDITAR ACTIVO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "editar_activo") {
    try {
        $idActivo = (int)($_POST["ID_Activo"] ?? 0);
        $marbete  = trim($_POST["Num_Marbete"] ?? "");
        $desc     = trim($_POST["Activo_Desc"] ?? "");
        $estado   = trim($_POST["Estado"] ?? "Activo");
        $lab      = isset($_POST["ID_Lab"]) ? (int)($_POST["ID_Lab"] ?? 1) : 1;
        $tipo     = trim($_POST["Tipo_Activo"] ?? "No Consumible");
        $cantidad = ($tipo === "Consumible")
            ? max(1, (int)($_POST["Cantidad"] ?? 1))
            : 1;

        if ($idActivo <= 0) {
            throw new Exception("No se encontró el activo a editar.");
        }

        if ($marbete === "" || $desc === "") {
            throw new Exception("Completa los campos obligatorios para editar el activo.");
        }

        $stmt = $conexion->prepare("CALL sp_actualizar_activo(?,?,?,?,?,?,?)");
        if (!$stmt) {
            throw new Exception("No se pudo preparar la edición del activo.");
        }

        $stmt->bind_param("isssisi", $idActivo, $marbete, $desc, $estado, $lab, $tipo, $cantidad);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $mensajeEditar = $data["mensaje"] ?? "Activo actualizado correctamente.";

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
   CAMBIAR ESTADO DE ACTIVO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "cambiar_estado") {
    try {
        $idActivo = (int)($_POST["ID_Activo"] ?? 0);
        $nuevoEstado = trim($_POST["Nuevo_Estado"] ?? "");

        if ($idActivo <= 0 || $nuevoEstado === "") {
            throw new Exception("Selecciona un activo y un estado válido.");
        }

        $stmtEstado = $conexion->prepare("CALL sp_actualizar_estado_activo(?, ?)");
        if (!$stmtEstado) {
            throw new Exception("No se pudo preparar el cambio de estado.");
        }

        $stmtEstado->bind_param("is", $idActivo, $nuevoEstado);
        $stmtEstado->execute();

        $resultEstado = $stmtEstado->get_result();
        $dataEstado = $resultEstado ? $resultEstado->fetch_assoc() : null;

        $mensajeEstado = $dataEstado["mensaje"] ?? "Estado actualizado correctamente.";

        if ($resultEstado instanceof mysqli_result) {
            $resultEstado->free();
        }

        $stmtEstado->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $errorEstado = $e->getMessage();
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $errorEstado = $e->getMessage();
    }
}

/* =========================
   ELIMINAR ACTIVO
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "eliminar_activo") {
    try {
        if ($rolUsuario !== "Administrador") {
            throw new Exception("Solo un administrador puede eliminar activos.");
        }

        $idActivo = (int)($_POST["ID_Activo"] ?? 0);
        if ($idActivo <= 0) {
            throw new Exception("No se encontró el activo a eliminar.");
        }

        $stmtEliminar = $conexion->prepare("CALL sp_eliminar_activo(?)");
        if (!$stmtEliminar) {
            throw new Exception("No se pudo preparar la eliminación del activo.");
        }

        $stmtEliminar->bind_param("i", $idActivo);
        $stmtEliminar->execute();

        $resultEliminar = $stmtEliminar->get_result();
        $dataEliminar = $resultEliminar ? $resultEliminar->fetch_assoc() : null;

        $mensajeEliminar = $dataEliminar["mensaje"] ?? "Activo eliminado correctamente.";

        if ($resultEliminar instanceof mysqli_result) {
            $resultEliminar->free();
        }

        $stmtEliminar->close();
        limpiar_call($conexion);

    } catch (mysqli_sql_exception $e) {
        $errorEliminar = $e->getMessage();
        limpiar_call($conexion);
    } catch (Throwable $e) {
        $errorEliminar = $e->getMessage();
    }
}

/* =========================
   LISTAR ACTIVOS
========================= */
$activos = [];

$stmtListado = $conexion->prepare("CALL sp_listar_activos(?, ?)");
if ($stmtListado) {
    $stmtListado->bind_param("ss", $tab, $busqueda);
    $stmtListado->execute();

    $resListado = $stmtListado->get_result();
    if ($resListado) {
        while ($row = $resListado->fetch_assoc()) {
            $activos[] = $row;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activos | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/activos.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Gestión de inventario</p>
            <h1>Lista de Activos</h1>
        </div>

        <button class="btn-primary" type="button" id="openModalBtn">+ Nuevo activo</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($mensajeEditar): ?>
        <div class="alert success"><?php echo h($mensajeEditar); ?></div>
    <?php endif; ?>

    <?php if ($mensajeEstado): ?>
        <div class="alert success"><?php echo h($mensajeEstado); ?></div>
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
                <h2>Activos registrados</h2>
                <p>Consulta, corrige y administra el inventario desde esta vista.</p>
            </div>

            <form method="GET" class="toolbar">
                <input type="hidden" name="tab" value="<?php echo h($tab); ?>">
                <div class="search-box">
                    <input
                        type="text"
                        name="q"
                        placeholder="Buscar por marbete o descripción"
                        value="<?php echo h($busqueda); ?>"
                    >
                    <button type="submit" class="btn-secondary">Buscar</button>
                </div>
            </form>
        </div>

        <div class="tabs">
            <?php foreach ($tabsValidas as $tabItem): ?>
                <a
                    class="tab <?php echo $tab === $tabItem ? 'is-active' : ''; ?>"
                    href="?tab=<?php echo urlencode($tabItem); ?>&q=<?php echo urlencode($busqueda); ?>"
                >
                    <?php echo h($tabItem === 'Activo' ? 'Activos' : $tabItem); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Marbete</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($activos)): ?>
                        <?php foreach ($activos as $a): ?>
                            <tr>
                                <td><?php echo h($a["Num_Marbete"]); ?></td>
                                <td><?php echo h($a["Activo_Desc"]); ?></td>
                                <td><?php echo h($a["Tipo_Activo"]); ?></td>
                                <td><?php echo (int)$a["Cantidad"]; ?></td>
                                <td><?php echo h($a["Nombre_Lab"] ?? "Almacén"); ?></td>
                                <td>
                                    <span class="status status--<?php echo strtolower(str_replace(' ', '-', $a["Estado"])); ?>">
                                        <?php echo h($a["Estado"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button
                                            type="button"
                                            class="btn-table"
                                            onclick='abrirModalEditar(
                                                <?php echo (int)$a["ID_Activo"]; ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Num_Marbete"]), ENT_QUOTES, "UTF-8"); ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Activo_Desc"]), ENT_QUOTES, "UTF-8"); ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Tipo_Activo"]), ENT_QUOTES, "UTF-8"); ?>,
                                                <?php echo (int)$a["Cantidad"]; ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Estado"]), ENT_QUOTES, "UTF-8"); ?>,
                                                <?php echo (int)$a["ID_Lab"]; ?>
                                            )'
                                        >
                                            Editar
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-table"
                                            onclick='abrirModalEstado(
                                                <?php echo (int)$a["ID_Activo"]; ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Estado"]), ENT_QUOTES, "UTF-8"); ?>,
                                                <?php echo htmlspecialchars(json_encode($a["Activo_Desc"]), ENT_QUOTES, "UTF-8"); ?>
                                            )'
                                        >
                                            Estado
                                        </button>

                                        <?php if ($rolUsuario === "Administrador" && (int)$a["TotalPrestamos"] === 0): ?>
                                            <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar este activo?');">
                                                <input type="hidden" name="accion" value="eliminar_activo">
                                                <input type="hidden" name="ID_Activo" value="<?php echo (int)$a['ID_Activo']; ?>">
                                                <button type="submit" class="btn-table btn-table--danger">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="muted">No hay activos que coincidan con esta vista.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVO -->
<div class="modal" id="activoModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeModalBackdrop"></div>

    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal__header">
            <div>
                <h2 id="modalTitle">Añadir activo</h2>
                <p>Captura la información del equipo o material.</p>
            </div>
            <button class="modal__close" type="button" id="closeModalBtn" aria-label="Cerrar">×</button>
        </div>

        <?php if ($error): ?>
            <div class="alert error modal-alert"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form" id="formActivo">
            <input type="hidden" name="accion" value="registrar_activo">

            <div class="modal-grid">
                <div class="field">
                    <label for="Num_Marbete">Número de marbete</label>
                    <input type="text" id="Num_Marbete" name="Num_Marbete" required value="<?php echo h($_POST['Num_Marbete'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="Activo_Desc">Descripción</label>
                    <input type="text" id="Activo_Desc" name="Activo_Desc" required value="<?php echo h($_POST['Activo_Desc'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="Tipo_Activo">Tipo de activo</label>
                    <select name="Tipo_Activo" id="Tipo_Activo">
                        <option value="No Consumible" <?php echo (($_POST['Tipo_Activo'] ?? '') === 'No Consumible') ? 'selected' : ''; ?>>No Consumible</option>
                        <option value="Consumible" <?php echo (($_POST['Tipo_Activo'] ?? '') === 'Consumible') ? 'selected' : ''; ?>>Consumible</option>
                    </select>
                </div>

                <div class="field field--cantidad" id="cantidadWrap">
                    <label for="Cantidad">Cantidad</label>
                    <input type="number" id="Cantidad" name="Cantidad" min="1" value="<?php echo h($_POST['Cantidad'] ?? '1'); ?>">
                    <small class="field__hint">Solo se usa para artículos consumibles.</small>
                </div>

                <div class="field">
                    <label for="Estado">Estado</label>
                    <select name="Estado" id="Estado">
                        <option value="Activo" <?php echo (($_POST['Estado'] ?? 'Activo') === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Mantenimiento" <?php echo (($_POST['Estado'] ?? '') === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                        <option value="Baja" <?php echo (($_POST['Estado'] ?? '') === 'Baja') ? 'selected' : ''; ?>>Baja</option>
                    </select>
                </div>

                <div class="field">
                    <label for="ID_Lab">Ubicación</label>
                    <select name="ID_Lab" id="ID_Lab">
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?php echo (int)$u['ID_Lab']; ?>" <?php echo ((int)($_POST['ID_Lab'] ?? 1) === (int)$u['ID_Lab']) ? 'selected' : ''; ?>>
                                <?php echo h($u['Nombre_Lab']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar activo</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal" id="editarModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeEditarBackdrop"></div>

    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="editarTitle">
        <div class="modal__header">
            <div>
                <h2 id="editarTitle">Editar activo</h2>
                <p>Corrige errores de captura o actualiza los datos del activo.</p>
            </div>
            <button class="modal__close" type="button" id="closeEditarBtn" aria-label="Cerrar">×</button>
        </div>

        <?php if ($errorEditar): ?>
            <div class="alert error modal-alert"><?php echo h($errorEditar); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form" id="formEditarActivo">
            <input type="hidden" name="accion" value="editar_activo">
            <input type="hidden" name="ID_Activo" id="editar_ID_Activo" value="<?php echo h($_POST['ID_Activo'] ?? ''); ?>">

            <div class="modal-grid">
                <div class="field">
                    <label for="editar_Num_Marbete">Número de marbete</label>
                    <input type="text" id="editar_Num_Marbete" name="Num_Marbete" required value="<?php echo h($_POST['Num_Marbete'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="editar_Activo_Desc">Descripción</label>
                    <input type="text" id="editar_Activo_Desc" name="Activo_Desc" required value="<?php echo h($_POST['Activo_Desc'] ?? ''); ?>">
                </div>

                <div class="field">
                    <label for="editar_Tipo_Activo">Tipo de activo</label>
                    <select name="Tipo_Activo" id="editar_Tipo_Activo">
                        <option value="No Consumible" <?php echo (($_POST['Tipo_Activo'] ?? '') === 'No Consumible') ? 'selected' : ''; ?>>No Consumible</option>
                        <option value="Consumible" <?php echo (($_POST['Tipo_Activo'] ?? '') === 'Consumible') ? 'selected' : ''; ?>>Consumible</option>
                    </select>
                </div>

                <div class="field field--cantidad" id="editarCantidadWrap">
                    <label for="editar_Cantidad">Cantidad</label>
                    <input type="number" id="editar_Cantidad" name="Cantidad" min="1" value="<?php echo h($_POST['Cantidad'] ?? '1'); ?>">
                    <small class="field__hint">Solo se usa para artículos consumibles.</small>
                </div>

                <div class="field">
                    <label for="editar_Estado">Estado</label>
                    <select name="Estado" id="editar_Estado">
                        <option value="Activo" <?php echo (($_POST['Estado'] ?? 'Activo') === 'Activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="Mantenimiento" <?php echo (($_POST['Estado'] ?? '') === 'Mantenimiento') ? 'selected' : ''; ?>>Mantenimiento</option>
                        <option value="Baja" <?php echo (($_POST['Estado'] ?? '') === 'Baja') ? 'selected' : ''; ?>>Baja</option>
                    </select>
                </div>

                <div class="field">
                    <label for="editar_ID_Lab">Ubicación</label>
                    <select name="ID_Lab" id="editar_ID_Lab">
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?php echo (int)$u['ID_Lab']; ?>">
                                <?php echo h($u['Nombre_Lab']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelEditarBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL ESTADO -->
<div class="modal" id="estadoModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeEstadoBackdrop"></div>

    <div class="modal__dialog modal__dialog--small" role="dialog" aria-modal="true" aria-labelledby="estadoTitle">
        <div class="modal__header">
            <div>
                <h2 id="estadoTitle">Cambiar estado</h2>
                <p id="estadoActivoNombre">Selecciona el nuevo estado del activo.</p>
            </div>
            <button class="modal__close" type="button" id="closeEstadoBtn" aria-label="Cerrar">×</button>
        </div>

        <?php if ($errorEstado): ?>
            <div class="alert error modal-alert"><?php echo h($errorEstado); ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="cambiar_estado">
            <input type="hidden" name="ID_Activo" id="estado_ID_Activo">

            <div class="field">
                <label for="Nuevo_Estado">Nuevo estado</label>
                <select name="Nuevo_Estado" id="Nuevo_Estado" required>
                    <option value="Activo">Activo</option>
                    <option value="Mantenimiento">Mantenimiento</option>
                    <option value="Baja">Baja</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelEstadoBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambio</button>
            </div>
        </form>
    </div>
</div>

<script>
const bodyEl = document.body;

/* Nuevo activo */
const modal = document.getElementById("activoModal");
const openModalBtn = document.getElementById("openModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const closeModalBackdrop = document.getElementById("closeModalBackdrop");
const cancelModalBtn = document.getElementById("cancelModalBtn");
const tipoActivo = document.getElementById("Tipo_Activo");
const cantidadWrap = document.getElementById("cantidadWrap");
const cantidadInput = document.getElementById("Cantidad");

function openModal() {
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    bodyEl.classList.add("modal-open");
}

function closeModal() {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    bodyEl.classList.remove("modal-open");
}

function toggleCantidad() {
    const esConsumible = tipoActivo.value === "Consumible";
    if (esConsumible) {
        cantidadWrap.style.display = "block";
        cantidadInput.required = true;
        cantidadInput.value = (Number(cantidadInput.value) > 0) ? cantidadInput.value : 1;
    } else {
        cantidadWrap.style.display = "none";
        cantidadInput.required = false;
        cantidadInput.value = 1;
    }
}

openModalBtn.addEventListener("click", openModal);
closeModalBtn.addEventListener("click", closeModal);
closeModalBackdrop.addEventListener("click", closeModal);
cancelModalBtn.addEventListener("click", closeModal);
tipoActivo.addEventListener("change", toggleCantidad);
toggleCantidad();

/* Editar activo */
const editarModal = document.getElementById("editarModal");
const closeEditarBtn = document.getElementById("closeEditarBtn");
const closeEditarBackdrop = document.getElementById("closeEditarBackdrop");
const cancelEditarBtn = document.getElementById("cancelEditarBtn");
const editarTipoActivo = document.getElementById("editar_Tipo_Activo");
const editarCantidadWrap = document.getElementById("editarCantidadWrap");
const editarCantidadInput = document.getElementById("editar_Cantidad");

function abrirModalEditar(id, marbete, descripcion, tipo, cantidad, estado, idLab) {
    document.getElementById("editar_ID_Activo").value = id;
    document.getElementById("editar_Num_Marbete").value = marbete;
    document.getElementById("editar_Activo_Desc").value = descripcion;
    document.getElementById("editar_Tipo_Activo").value = tipo;
    document.getElementById("editar_Cantidad").value = cantidad;
    document.getElementById("editar_Estado").value = estado;
    document.getElementById("editar_ID_Lab").value = idLab;

    toggleCantidadEditar();

    editarModal.classList.add("is-open");
    editarModal.setAttribute("aria-hidden", "false");
    bodyEl.classList.add("modal-open");
}

function cerrarModalEditar() {
    editarModal.classList.remove("is-open");
    editarModal.setAttribute("aria-hidden", "true");
    bodyEl.classList.remove("modal-open");
}

function toggleCantidadEditar() {
    const esConsumible = editarTipoActivo.value === "Consumible";
    if (esConsumible) {
        editarCantidadWrap.style.display = "block";
        editarCantidadInput.required = true;
        editarCantidadInput.value = (Number(editarCantidadInput.value) > 0) ? editarCantidadInput.value : 1;
    } else {
        editarCantidadWrap.style.display = "none";
        editarCantidadInput.required = false;
        editarCantidadInput.value = 1;
    }
}

closeEditarBtn.addEventListener("click", cerrarModalEditar);
closeEditarBackdrop.addEventListener("click", cerrarModalEditar);
cancelEditarBtn.addEventListener("click", cerrarModalEditar);
editarTipoActivo.addEventListener("change", toggleCantidadEditar);
toggleCantidadEditar();

/* Cambiar estado */
const estadoModal = document.getElementById("estadoModal");
const closeEstadoBtn = document.getElementById("closeEstadoBtn");
const closeEstadoBackdrop = document.getElementById("closeEstadoBackdrop");
const cancelEstadoBtn = document.getElementById("cancelEstadoBtn");
const estadoIdInput = document.getElementById("estado_ID_Activo");
const nuevoEstadoSelect = document.getElementById("Nuevo_Estado");
const estadoActivoNombre = document.getElementById("estadoActivoNombre");

function abrirModalEstado(id, estadoActual, nombreActivo) {
    estadoIdInput.value = id;
    nuevoEstadoSelect.value = (estadoActual === "Prestado") ? "Activo" : estadoActual;
    estadoActivoNombre.textContent = "Activo: " + nombreActivo;

    estadoModal.classList.add("is-open");
    estadoModal.setAttribute("aria-hidden", "false");
    bodyEl.classList.add("modal-open");
}

function cerrarModalEstado() {
    estadoModal.classList.remove("is-open");
    estadoModal.setAttribute("aria-hidden", "true");
    bodyEl.classList.remove("modal-open");
}

closeEstadoBtn.addEventListener("click", cerrarModalEstado);
closeEstadoBackdrop.addEventListener("click", cerrarModalEstado);
cancelEstadoBtn.addEventListener("click", cerrarModalEstado);

document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        if (modal.classList.contains("is-open")) closeModal();
        if (editarModal.classList.contains("is-open")) cerrarModalEditar();
        if (estadoModal.classList.contains("is-open")) cerrarModalEstado();
    }
});

<?php if ($error): ?>
openModal();
<?php endif; ?>

<?php if ($errorEditar): ?>
editarModal.classList.add("is-open");
editarModal.setAttribute("aria-hidden", "false");
bodyEl.classList.add("modal-open");
toggleCantidadEditar();
<?php endif; ?>

<?php if ($errorEstado): ?>
estadoModal.classList.add("is-open");
estadoModal.setAttribute("aria-hidden", "false");
bodyEl.classList.add("modal-open");
<?php endif; ?>
</script>

</body>
</html>