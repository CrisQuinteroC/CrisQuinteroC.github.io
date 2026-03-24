<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "prestamos";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");

$mensaje = "";
$error = "";
$errorModal = false;
$busqueda = trim($_GET["q"] ?? "");
$tab = trim($_GET["tab"] ?? "Todos");
$fechaInicioFiltro = trim($_GET["fecha_inicio"] ?? "");
$fechaLimiteFiltro = trim($_GET["fecha_limite"] ?? "");

$tabsValidas = ["Todos", "Activo", "Entregado", "Devuelto", "Vencidos"];
if (!in_array($tab, $tabsValidas, true)) {
    $tab = "Todos";
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

function h($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function post_val(string $key, $default = "") {
    return $_POST[$key] ?? $default;
}

/* =========================
   UBICACIONES
========================= */
$ubicaciones = [];
$resUb = $conexion->query("CALL sp_listar_ubicaciones('')");
while ($row = $resUb->fetch_assoc()) {
    $ubicaciones[] = $row;
}
$resUb->free();
limpiar_call($conexion);

/* =========================
   REGISTRAR PRÉSTAMO MÚLTIPLE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "registrar_prestamo_multiple") {
    try {
        $tipoSolicitante = trim($_POST["Tipo_Solicitante"] ?? "");
        $matriculaAlumno = null;
        $matriculaDocente = null;
        $idLab = (int)($_POST["ID_Lab"] ?? 0);
        $comentarios = trim($_POST["Comentarios"] ?? "");
        $registradoPor = (int)($_SESSION["Matricula_Uss"] ?? 0);

        if ($tipoSolicitante === "Alumno") {
            $matriculaAlumno = (int)($_POST["Matricula_Alumno"] ?? 0);
            if ($matriculaAlumno <= 0) {
                throw new Exception("Debes buscar y seleccionar un alumno válido.");
            }
        } elseif ($tipoSolicitante === "Docente") {
            $matriculaDocente = (int)($_POST["Matricula_Docente"] ?? 0);
            if ($matriculaDocente <= 0) {
                throw new Exception("Debes buscar y seleccionar un docente válido.");
            }
        } else {
            throw new Exception("Selecciona el tipo de solicitante.");
        }

        if ($idLab <= 0) {
            throw new Exception("Selecciona una ubicación destino.");
        }

        $stmtUb = $conexion->prepare("SELECT Nombre_Lab FROM ubicaciones WHERE ID_Lab = ? LIMIT 1");
$stmtUb->bind_param("i", $idLab);
$stmtUb->execute();
$resUbSel = $stmtUb->get_result();
$ubicacionSel = $resUbSel ? $resUbSel->fetch_assoc() : null;

if ($resUbSel instanceof mysqli_result) {
    $resUbSel->free();
}
$stmtUb->close();

if (!$ubicacionSel) {
    throw new Exception("La ubicación seleccionada no existe.");
}

$nombreUbicacionSel = mb_strtolower(trim((string)$ubicacionSel["Nombre_Lab"]), 'UTF-8');
if ($nombreUbicacionSel === 'almacen' || $nombreUbicacionSel === 'almacén') {
    throw new Exception("No se puede registrar un préstamo con destino a Almacén.");
}

        if ($registradoPor <= 0) {
            throw new Exception("No se encontró el usuario que registra.");
        }

        $itemsJson = $_POST["items_json"] ?? "[]";
        $items = json_decode($itemsJson, true);

        if (!is_array($items) || count($items) === 0) {
            throw new Exception("Debes agregar al menos un ítem al préstamo.");
        }

        $conexion->begin_transaction();

        $stmtGrupo = $conexion->prepare("CALL sp_crear_grupo_prestamo(?,?,?,?,?)");
        $stmtGrupo->bind_param(
            "iiisi",
            $matriculaAlumno,
            $matriculaDocente,
            $idLab,
            $comentarios,
            $registradoPor
        );
        $stmtGrupo->execute();

        $resGrupo = $stmtGrupo->get_result();
        $grupo = $resGrupo ? $resGrupo->fetch_assoc() : null;
        $grupoId = (int)($grupo["Grupo_PrestamoID"] ?? 0);

        if ($resGrupo instanceof mysqli_result) {
            $resGrupo->free();
        }

        $stmtGrupo->close();
        limpiar_call($conexion);

        if ($grupoId <= 0) {
            throw new Exception("No se pudo crear el grupo del préstamo.");
        }

        foreach ($items as $item) {
            $idActivo = (int)($item["ID_Activo"] ?? 0);
            $cantidad = (int)($item["Cantidad"] ?? 1);
            $fechaLimite = trim($item["Fecha_Limite"] ?? "");

            if ($idActivo <= 0) {
                throw new Exception("Uno de los ítems no tiene un activo válido.");
            }

            if ($cantidad <= 0) {
                throw new Exception("Uno de los ítems tiene cantidad inválida.");
            }

            $fechaLimiteSql = null;
            if ($fechaLimite !== "") {
                $tmp = date("Y-m-d H:i:s", strtotime($fechaLimite));
                if (!$tmp || $tmp === "1970-01-01 00:00:00") {
                    throw new Exception("Una de las fechas límite no es válida.");
                }
                $fechaLimiteSql = $tmp;
            }

            $stmtItem = $conexion->prepare("CALL sp_agregar_item_prestamo(?,?,?,?)");
            $stmtItem->bind_param(
                "iiis",
                $grupoId,
                $idActivo,
                $cantidad,
                $fechaLimiteSql
            );
            $stmtItem->execute();

            $resItem = $stmtItem->get_result();
            if ($resItem instanceof mysqli_result) {
                $resItem->free();
            }

            $stmtItem->close();
            limpiar_call($conexion);
        }

        $conexion->commit();
        $mensaje = "Préstamo registrado correctamente con " . count($items) . " ítem(s).";
        $_POST = [];
} catch (Throwable $e) {
    if ($conexion->errno === 0) {
        // nada
    }
    try {
        $conexion->rollback();
    } catch (Throwable $rollbackError) {
        // ignorar rollback secundario
    }
    limpiar_call($conexion);
    $error = $e->getMessage();
    $errorModal = true;
}
}

/* =========================
   LISTADO
========================= */
$where = [];
$params = [];
$types = "";

if ($tab === "Activo") {
    $where[] = "p.Estado_Prestamo = 'Activo'";
} elseif ($tab === "Entregado") {
    $where[] = "p.Estado_Prestamo = 'Entregado'";
} elseif ($tab === "Devuelto") {
    $where[] = "p.Estado_Prestamo = 'Devuelto'";
} elseif ($tab === "Vencidos") {
    $where[] = "p.Estado_Prestamo = 'Activo' AND p.Fecha_Limite < NOW()";
}

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

$sqlPrestamos = "
    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        p.Cantidad_Solicitada,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Fecha_Devolucion,
        p.Estado_Prestamo,
        p.Comentarios,
        p.Matricula_Alumno,
        p.Matricula_Docente,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        u.Nombre_Lab,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        ur.Nombre_Uss AS RegistradoPorNombre
    FROM prestamos p
    INNER JOIN activos a ON a.ID_Activo = p.ID_Activo
    LEFT JOIN ubicaciones u ON u.ID_Lab = p.ID_Lab
    LEFT JOIN alumnos al ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN usuarios ur ON ur.Matricula_Uss = p.Registrado_Por
";

if (!empty($where)) {
    $sqlPrestamos .= " WHERE " . implode(" AND ", $where);
}

$sqlPrestamos .= " ORDER BY p.ID_Prestamo DESC";

$stmtList = $conexion->prepare($sqlPrestamos);
if (!empty($params)) {
    $stmtList->bind_param($types, ...$params);
}
$stmtList->execute();

$prestamos = [];
$resList = $stmtList->get_result();
while ($row = $resList->fetch_assoc()) {
    $prestamos[] = $row;
}
$resList->free();
$stmtList->close();

/* =========================
   RESUMEN
========================= */
$resumen = [
    "total" => 0,
    "activos" => 0,
    "entregados" => 0,
    "devueltos" => 0,
    "vencidos" => 0
];

$resResumen = $conexion->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN Estado_Prestamo = 'Activo' THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN Estado_Prestamo = 'Entregado' THEN 1 ELSE 0 END) AS entregados,
        SUM(CASE WHEN Estado_Prestamo = 'Devuelto' THEN 1 ELSE 0 END) AS devueltos,
        SUM(CASE WHEN Estado_Prestamo = 'Activo' AND Fecha_Limite < NOW() THEN 1 ELSE 0 END) AS vencidos
    FROM prestamos
");
if ($resResumen) {
    $fila = $resResumen->fetch_assoc();
    if ($fila) {
        $resumen = $fila;
    }
    $resResumen->free();
}

$itemsJsonPost = post_val("items_json", "[]");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/prestamos.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Control de préstamos</p>
            <h1>Préstamos</h1>
        </div>

        <button class="btn-primary" type="button" id="openModalBtn">+ Nuevo préstamo</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <div class="resume-grid">
        <div class="mini-card">
            <span class="mini-card__label">Total</span>
            <strong><?php echo (int)$resumen["total"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Activos</span>
            <strong><?php echo (int)$resumen["activos"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Entregados</span>
            <strong><?php echo (int)$resumen["entregados"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Devueltos</span>
            <strong><?php echo (int)$resumen["devueltos"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Vencidos</span>
            <strong><?php echo (int)$resumen["vencidos"]; ?></strong>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card__head table-card__head--stack">
            <div>
                <h2>Listado de préstamos</h2>
                <p>Consulta préstamos activos, entregados, devueltos o vencidos.</p>
            </div>

            <form method="GET" class="toolbar toolbar--filters">
                <input type="hidden" name="tab" value="<?php echo h($tab); ?>">

                <div class="search-box">
                    <input type="text" name="q" placeholder="Buscar por ID, marbete, activo o solicitante" value="<?php echo h($busqueda); ?>">
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
                        <a class="btn-clear" href="prestamos.php?tab=<?php echo urlencode($tab); ?>">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="tabs">
            <?php foreach ($tabsValidas as $tabItem): ?>
                <a class="tab <?php echo $tab === $tabItem ? 'is-active' : ''; ?>"
                   href="?tab=<?php echo urlencode($tabItem); ?>&q=<?php echo urlencode($busqueda); ?>&fecha_inicio=<?php echo urlencode($fechaInicioFiltro); ?>&fecha_limite=<?php echo urlencode($fechaLimiteFiltro); ?>">
                    <?php echo h($tabItem); ?>
                </a>
            <?php endforeach; ?>
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
    <th>Estado</th>
    <th>Registró</th>
</tr>
                </thead>
                <tbody>
                    <?php if (!empty($prestamos)): ?>
                        <?php foreach ($prestamos as $p): ?>
                            <?php
                                $esVencido = (
                                    $p["Estado_Prestamo"] === "Activo" &&
                                    !empty($p["Fecha_Limite"]) &&
                                    strtotime($p["Fecha_Limite"]) < time()
                                );
                                $estadoVisual = $esVencido ? "Vencido" : $p["Estado_Prestamo"];
                                $matricula = $p["Matricula_Alumno"] ?: $p["Matricula_Docente"];
                            ?>
                            <tr>
    <td><?php echo $p["Grupo_PrestamoID"] ? "#" . (int)$p["Grupo_PrestamoID"] : "—"; ?></td>
    <td>
        <div class="cell-stack">
            <strong><?php echo h($p["Activo_Desc"]); ?></strong>
            <span><?php echo h($p["Num_Marbete"]); ?> · <?php echo h($p["Tipo_Activo"]); ?> · ID interno #<?php echo (int)$p["ID_Prestamo"]; ?></span>
        </div>
    </td>
                                <td>
                                    <div class="cell-stack">
                                        <strong><?php echo h($p["SolicitanteNombre"]); ?></strong>
                                        <span><?php echo h($matricula); ?></span>
                                    </div>
                                </td>
                                <td><?php echo (int)$p["Cantidad_Solicitada"]; ?></td>
                                <td><?php echo h($p["Nombre_Lab"] ?? "Sin ubicación"); ?></td>
                                <td><?php echo h(date("d/m/Y H:i", strtotime($p["Fecha_Inicio"]))); ?></td>
                                <td>
                                    <?php if (!empty($p["Fecha_Limite"])): ?>
                                        <?php echo h(date("d/m/Y H:i", strtotime($p["Fecha_Limite"]))); ?>
                                    <?php else: ?>
                                        No aplica
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status status--<?php echo strtolower($estadoVisual === "Vencido" ? "vencido" : $p["Estado_Prestamo"]); ?>">
                                        <?php echo h($estadoVisual); ?>
                                    </span>
                                </td>
                                <td><?php echo h($p["RegistradoPorNombre"] ?? "Usuario"); ?></td>
                            </tr>
                            <?php if (!empty($p["Comentarios"])): ?>
                                <tr class="row-note">
                                    <td colspan="9"><strong>Comentarios:</strong> <?php echo h($p["Comentarios"]); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="muted">No hay préstamos registrados en esta vista.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="prestamoModal" aria-hidden="true">
    <div class="modal__backdrop" id="closeModalBackdrop"></div>

    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
<div class="modal__header">
    <div>
        <h2 id="modalTitle">Registrar préstamo</h2>
        <p>Busca el solicitante, agrega uno o varios ítems y mezcla consumibles y no consumibles en una sola captura.</p>
    </div>
    <button class="modal__close" type="button" id="closeModalBtn" aria-label="Cerrar">×</button>
</div>

<?php if ($errorModal && $error): ?>
    <div class="alert error alert--modal"><?php echo h($error); ?></div>
<?php endif; ?>

        <form method="POST" class="modal-form" id="formPrestamo">
            <input type="hidden" name="accion" value="registrar_prestamo_multiple">
            <input type="hidden" name="ID_Activo" id="ID_Activo" value="">
            <input type="hidden" name="Matricula_Alumno" id="Matricula_Alumno" value="<?php echo h(post_val("Matricula_Alumno")); ?>">
            <input type="hidden" name="Matricula_Docente" id="Matricula_Docente" value="<?php echo h(post_val("Matricula_Docente")); ?>">
            <input type="hidden" name="items_json" id="items_json" value='<?php echo h($itemsJsonPost); ?>'>

            <div class="modal-grid">
                <div class="field">
                    <label for="Tipo_Solicitante">Tipo de solicitante</label>
                    <select name="Tipo_Solicitante" id="Tipo_Solicitante" required>
                        <option value="">Selecciona</option>
                        <option value="Alumno" <?php echo post_val("Tipo_Solicitante") === "Alumno" ? "selected" : ""; ?>>Alumno</option>
                        <option value="Docente" <?php echo post_val("Tipo_Solicitante") === "Docente" ? "selected" : ""; ?>>Docente</option>
                    </select>
                </div>

                <div class="field">
                    <label for="Matricula_Busqueda">Matrícula</label>
                    <div class="inline-search">
                        <input type="number" id="Matricula_Busqueda" placeholder="Captura la matrícula" autocomplete="off">
                        <button type="button" class="btn-secondary" id="btnBuscarSolicitante">Buscar</button>
                    </div>
                    <small class="helper">Primero elige si es alumno o docente.</small>
                </div>

                <div class="field field--full">
                    <label>Solicitante encontrado</label>
                    <div class="result-box" id="solicitanteResultado">
                        <span class="muted">Todavía no se ha seleccionado ningún solicitante.</span>
                    </div>
                </div>

                <div class="field">
                    <label for="ID_Lab">Ubicación / destino</label>
<select name="ID_Lab" id="ID_Lab" required>
    <option value="">Selecciona una ubicación</option>
    <?php foreach ($ubicaciones as $u): ?>
        <?php
            $nombreUb = trim((string)$u["Nombre_Lab"]);
            $esAlmacen = mb_strtolower($nombreUb, 'UTF-8') === 'almacén' || mb_strtolower($nombreUb, 'UTF-8') === 'almacen';
        ?>
        <option
            value="<?php echo (int)$u["ID_Lab"]; ?>"
            <?php echo ((int)post_val("ID_Lab") === (int)$u["ID_Lab"]) ? "selected" : ""; ?>
            <?php echo $esAlmacen ? 'disabled' : ''; ?>
        >
            <?php echo h($nombreUb . ($esAlmacen ? ' (No disponible para préstamo)' : '')); ?>
        </option>
    <?php endforeach; ?>
</select>
                </div>

                <div class="field">
                    <label for="Comentarios">Comentarios generales</label>
                    <textarea name="Comentarios" id="Comentarios" rows="3" placeholder="Opcional"><?php echo h(post_val("Comentarios")); ?></textarea>
                </div>

                <div class="field">
                    <label for="Num_Marbete_Busqueda">Número de marbete</label>
                    <div class="inline-search">
                        <input type="text" id="Num_Marbete_Busqueda" placeholder="Ej. 1.2.3.23" autocomplete="off">
                        <button type="button" class="btn-secondary" id="btnBuscarActivo">Buscar</button>
                    </div>
                    <small class="helper">Puedes escribirlo o pegarlo como si viniera de un escáner.</small>
                </div>

                <div class="field">
                    <label>Activo encontrado</label>
                    <div class="result-box" id="activoResultado">
                        <span class="muted">Todavía no se ha seleccionado ningún activo.</span>
                    </div>
                </div>

                <div class="field">
                    <label for="Cantidad_Item">Cantidad</label>
                    <input type="number" id="Cantidad_Item" min="1" value="1">
                    <small class="helper">Para no consumibles siempre será 1.</small>
                </div>

                <div class="field">
                    <label for="Fecha_Limite_Item">Fecha límite del ítem</label>
                    <input type="datetime-local" id="Fecha_Limite_Item">
                    <small class="helper">Solo aplica para activos no consumibles.</small>
                </div>

                <div class="field field--full">
                    <button type="button" class="btn-primary" id="btnAgregarItem">Agregar ítem</button>
                </div>

                <div class="field field--full">
                    <label>Ítems agregados</label>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Marbete</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Fecha límite</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tablaItemsBody">
                                <tr>
                                    <td colspan="7" class="muted">Todavía no agregas ítems.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="cancelModalBtn">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar préstamo</button>
            </div>
        </form>
    </div>
</div>

<script>
const body = document.body;
const modal = document.getElementById("prestamoModal");
const openBtn = document.getElementById("openModalBtn");
const closeBtn = document.getElementById("closeModalBtn");
const cancelBtn = document.getElementById("cancelModalBtn");
const backdrop = document.getElementById("closeModalBackdrop");
const formPrestamo = document.getElementById("formPrestamo");

const activoInput = document.getElementById("Num_Marbete_Busqueda");
const btnBuscarActivo = document.getElementById("btnBuscarActivo");
const activoResultado = document.getElementById("activoResultado");
const hiddenIDActivo = document.getElementById("ID_Activo");

const tipoSolicitante = document.getElementById("Tipo_Solicitante");
const matriculaInput = document.getElementById("Matricula_Busqueda");
const btnBuscarSolicitante = document.getElementById("btnBuscarSolicitante");
const solicitanteResultado = document.getElementById("solicitanteResultado");
const hiddenAlumno = document.getElementById("Matricula_Alumno");
const hiddenDocente = document.getElementById("Matricula_Docente");

const idLabInput = document.getElementById("ID_Lab");
const itemsJsonInput = document.getElementById("items_json");
const tablaItemsBody = document.getElementById("tablaItemsBody");
const cantidadInput = document.getElementById("Cantidad_Item");
const fechaLimiteItemInput = document.getElementById("Fecha_Limite_Item");
const btnAgregarItem = document.getElementById("btnAgregarItem");

let activoActual = null;
const itemsPrestamo = [];

function openModal(limpiar = true) {
    if (limpiar) {
        resetPrestamoForm();
    }
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    body.classList.add("modal-open");
}

function closeModal() {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    body.classList.remove("modal-open");
}

function limpiarActivoSeleccionado() {
    activoActual = null;
    hiddenIDActivo.value = "";
    activoResultado.innerHTML = '<span class="muted">Todavía no se ha seleccionado ningún activo.</span>';
    cantidadInput.value = 1;
    cantidadInput.min = 1;
    cantidadInput.removeAttribute("max");
    fechaLimiteItemInput.value = "";
    fechaLimiteItemInput.disabled = false;
}

function limpiarSolicitanteSeleccionado() {
    hiddenAlumno.value = "";
    hiddenDocente.value = "";
    solicitanteResultado.innerHTML = '<span class="muted">Todavía no se ha seleccionado ningún solicitante.</span>';
}

function renderItems() {
    tablaItemsBody.innerHTML = "";

    if (itemsPrestamo.length === 0) {
        tablaItemsBody.innerHTML = `
            <tr>
                <td colspan="7" class="muted">Todavía no agregas ítems.</td>
            </tr>
        `;
        itemsJsonInput.value = "[]";
        return;
    }

    itemsPrestamo.forEach((item, idx) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>${item.Num_Marbete}</td>
            <td>${item.Activo_Desc}</td>
            <td>${item.Tipo_Activo}</td>
            <td>${item.Cantidad}</td>
            <td>${item.Fecha_Limite ? item.Fecha_Limite : 'No aplica'}</td>
            <td>${item.Tipo_Activo === 'Consumible' ? 'Entregado' : 'Activo'}</td>
            <td><button type="button" class="btn-delete-item" data-index="${idx}">Quitar</button></td>
        `;
        tablaItemsBody.appendChild(tr);
    });

    itemsJsonInput.value = JSON.stringify(itemsPrestamo);
}

function resetPrestamoForm() {
    formPrestamo.reset();

    hiddenIDActivo.value = "";
    hiddenAlumno.value = "";
    hiddenDocente.value = "";

    activoInput.value = "";
    matriculaInput.value = "";
    activoActual = null;

    itemsPrestamo.length = 0;
    itemsJsonInput.value = "[]";

    activoResultado.innerHTML = '<span class="muted">Todavía no se ha seleccionado ningún activo.</span>';
    solicitanteResultado.innerHTML = '<span class="muted">Todavía no se ha seleccionado ningún solicitante.</span>';

    cantidadInput.value = 1;
    cantidadInput.min = 1;
    cantidadInput.removeAttribute("max");
    fechaLimiteItemInput.value = "";
    fechaLimiteItemInput.disabled = false;

    renderItems();
}

async function parseJsonResponse(resp) {
    const text = await resp.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error("Respuesta no JSON:", text);
        throw new Error("El servidor devolvió una respuesta inválida.");
    }
}

async function buscarActivo() {
    const marbete = activoInput.value.trim();

    if (!marbete) {
        limpiarActivoSeleccionado();
        alert("Captura un número de marbete.");
        return;
    }

    try {
        const resp = await fetch(`api/buscar_activo_prestamo.php?marbete=${encodeURIComponent(marbete)}`);
        const data = await parseJsonResponse(resp);

        if (!resp.ok || !data.ok) {
            throw new Error(data.message || "No se pudo buscar el activo.");
        }

        const a = data.data;
        activoActual = a;
        hiddenIDActivo.value = a.ID_Activo;

        activoResultado.innerHTML = `
            <div class="result-stack">
                <strong>${a.Activo_Desc}</strong>
                <span><b>Marbete:</b> ${a.Num_Marbete}</span>
                <span><b>Tipo:</b> ${a.Tipo_Activo}</span>
                <span><b>Estado:</b> ${a.Estado}</span>
                <span><b>Ubicación actual:</b> ${a.Nombre_Lab ?? "Sin ubicación"}</span>
                <span><b>Cantidad disponible:</b> ${a.Cantidad}</span>
            </div>
        `;

        if (a.Tipo_Activo === "Consumible") {
            cantidadInput.value = 1;
            cantidadInput.min = 1;
            cantidadInput.max = a.Cantidad;
            fechaLimiteItemInput.value = "";
            fechaLimiteItemInput.disabled = true;
        } else {
            cantidadInput.value = 1;
            cantidadInput.min = 1;
            cantidadInput.max = 1;
            fechaLimiteItemInput.disabled = false;
        }
    } catch (err) {
        limpiarActivoSeleccionado();
        alert(err.message);
    }
}

async function buscarSolicitante() {
    const tipo = tipoSolicitante.value.trim();
    const matricula = matriculaInput.value.trim();

    if (!tipo) {
        alert("Primero selecciona si es Alumno o Docente.");
        return;
    }

    if (!matricula) {
        limpiarSolicitanteSeleccionado();
        alert("Captura una matrícula.");
        return;
    }

    try {
        const resp = await fetch(`api/buscar_solicitante_prestamo.php?tipo=${encodeURIComponent(tipo)}&matricula=${encodeURIComponent(matricula)}`);
        const data = await parseJsonResponse(resp);

        if (!resp.ok || !data.ok) {
            throw new Error(data.message || "No se pudo buscar el solicitante.");
        }

        const s = data.data;
        hiddenAlumno.value = "";
        hiddenDocente.value = "";

        if (s.Tipo === "Alumno") {
            hiddenAlumno.value = s.Matricula;
        } else {
            hiddenDocente.value = s.Matricula;
        }

        solicitanteResultado.innerHTML = `
            <div class="result-stack">
                <strong>${s.Nombre}</strong>
                <span><b>Tipo:</b> ${s.Tipo}</span>
                <span><b>Matrícula:</b> ${s.Matricula}</span>
                ${s.Carrera ? `<span><b>Carrera:</b> ${s.Carrera}</span>` : ""}
                ${s.Grupo ? `<span><b>Grupo:</b> ${s.Grupo}</span>` : ""}
                <span><b>Contacto:</b> ${s.Contacto}</span>
                <span><b>Estado:</b> ${s.Estado}</span>
            </div>
        `;
    } catch (err) {
        limpiarSolicitanteSeleccionado();
        alert(err.message);
    }
}

function agregarItemPrestamo() {
    if (!activoActual || !hiddenIDActivo.value) {
        alert("Primero busca un activo válido.");
        return;
    }

    const tipo = activoActual.Tipo_Activo;
    const cantidad = parseInt(cantidadInput.value || "1", 10);
    const fechaLimite = fechaLimiteItemInput.value.trim();

    if (tipo === "Consumible") {
        if (cantidad <= 0) {
            alert("La cantidad debe ser mayor a 0.");
            return;
        }
        if (cantidad > parseInt(activoActual.Cantidad, 10)) {
            alert("La cantidad solicitada excede la existencia disponible.");
            return;
        }
    } else {
        if (!fechaLimite) {
            alert("Los no consumibles requieren fecha límite.");
            return;
        }
        if (cantidad !== 1) {
            alert("Un no consumible solo puede agregarse con cantidad 1.");
            return;
        }
    }

    const yaExiste = itemsPrestamo.some(i => Number(i.ID_Activo) === Number(activoActual.ID_Activo));
    if (yaExiste) {
        alert("Ese activo ya fue agregado a la lista.");
        return;
    }

    itemsPrestamo.push({
        ID_Activo: parseInt(activoActual.ID_Activo, 10),
        Num_Marbete: activoActual.Num_Marbete,
        Activo_Desc: activoActual.Activo_Desc,
        Tipo_Activo: activoActual.Tipo_Activo,
        Cantidad: tipo === "Consumible" ? cantidad : 1,
        Fecha_Limite: tipo === "Consumible" ? "" : fechaLimite
    });

    limpiarActivoSeleccionado();
    activoInput.value = "";
    renderItems();
}

openBtn?.addEventListener("click", () => openModal(true));
closeBtn?.addEventListener("click", closeModal);
cancelBtn?.addEventListener("click", closeModal);
backdrop?.addEventListener("click", closeModal);

window.addEventListener("keydown", function(e) {
    if (e.key === "Escape") closeModal();
});

activoInput?.addEventListener("input", limpiarActivoSeleccionado);
tipoSolicitante?.addEventListener("change", limpiarSolicitanteSeleccionado);
matriculaInput?.addEventListener("input", limpiarSolicitanteSeleccionado);

btnBuscarActivo?.addEventListener("click", buscarActivo);
btnBuscarSolicitante?.addEventListener("click", buscarSolicitante);
btnAgregarItem?.addEventListener("click", agregarItemPrestamo);

activoInput?.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        buscarActivo();
    }
});

matriculaInput?.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        e.preventDefault();
        buscarSolicitante();
    }
});

tablaItemsBody?.addEventListener("click", function(e) {
    const btn = e.target.closest(".btn-delete-item");
    if (!btn) return;

    const index = parseInt(btn.dataset.index, 10);
    if (Number.isNaN(index)) return;

    itemsPrestamo.splice(index, 1);
    renderItems();
});

formPrestamo?.addEventListener("submit", function(e) {
    if (!tipoSolicitante.value) {
        e.preventDefault();
        alert("Debes seleccionar el tipo de solicitante.");
        return;
    }

    if (tipoSolicitante.value === "Alumno" && !hiddenAlumno.value) {
        e.preventDefault();
        alert("Debes buscar y seleccionar un alumno por matrícula.");
        return;
    }

    if (tipoSolicitante.value === "Docente" && !hiddenDocente.value) {
        e.preventDefault();
        alert("Debes buscar y seleccionar un docente por matrícula.");
        return;
    }

    if (!idLabInput.value) {
        e.preventDefault();
        alert("Debes seleccionar una ubicación destino.");
        return;
    }

    if (itemsPrestamo.length === 0) {
        e.preventDefault();
        alert("Debes agregar al menos un ítem.");
        return;
    }

    itemsJsonInput.value = JSON.stringify(itemsPrestamo);
});

(function cargarItemsPrevios() {
    const raw = itemsJsonInput.value || "[]";

    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length > 0) {
            parsed.forEach(item => itemsPrestamo.push(item));
        }
    } catch (e) {
        itemsPrestamo.length = 0;
    }

    renderItems();
})();

<?php if ($errorModal && $error): ?>
openModal(false);
<?php endif; ?>
</script>

</body>
</html>