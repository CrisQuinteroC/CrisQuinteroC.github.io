<?php
require_once "conexion.php";
require_once "includes/auth.php";

$activePage = "usuarios";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");
$conexion->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");

/* =========================
   SOLO ADMINISTRADOR
========================= */
if (($_SESSION["Rol_Uss"] ?? "") !== "Administrador") {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso denegado</title>
        <link rel="stylesheet" href="css/dashboard.css">
        <style>
            body{font-family:inherit;background:#f8fafc}
            .forbidden{
                max-width:720px;margin:60px auto;padding:28px;border-radius:24px;
                background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.06)
            }
            .forbidden h1{margin:0 0 10px;color:#0f172a}
            .forbidden p{margin:0 0 18px;color:#475569}
            .forbidden a{
                display:inline-block;padding:12px 18px;border-radius:14px;
                background:#16a34a;color:#fff;text-decoration:none;font-weight:700
            }
        </style>
    </head>
    <body>
        <div class="forbidden">
            <h1>Acceso denegado</h1>
            <p>Solo el administrador puede entrar al panel de usuarios.</p>
            <a href="index.php">Volver al dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$mensaje = "";
$error = "";
$busqueda = trim($_GET["q"] ?? "");

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

function post_val(string $key, $default = "") {
    return $_POST[$key] ?? $default;
}

/* =========================
   ACCIONES
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        if ($accion === "crear_usuario") {
            $matricula = (int)($_POST["Matricula_Uss"] ?? 0);
            $nombre = trim($_POST["Nombre_Uss"] ?? "");
            $rol = trim($_POST["Rol_Uss"] ?? "");
            $password = trim($_POST["Pswd_Uss"] ?? "");

            if ($matricula <= 0) {
                throw new Exception("La matrícula del usuario es obligatoria.");
            }
            if ($nombre === "") {
                throw new Exception("El nombre del usuario es obligatorio.");
            }
            if (!in_array($rol, ["Administrador", "Encargado"], true)) {
                throw new Exception("Selecciona un rol válido.");
            }
            if (mb_strlen($password) < 5) {
                throw new Exception("La contraseña debe tener al menos 5 caracteres.");
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conexion->prepare("CALL sp_registrar_usuario(?,?,?,?)");
            $stmt->bind_param("isss", $matricula, $nombre, $rol, $hash);
            $stmt->execute();

            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $mensaje = $row["mensaje"] ?? "Usuario registrado correctamente.";

            if ($res instanceof mysqli_result) $res->free();
            $stmt->close();
            limpiar_call($conexion);
            $_POST = [];
        }

        if ($accion === "editar_usuario") {
            $matriculaOriginal = (int)($_POST["Matricula_Original"] ?? 0);
            $matriculaNueva = (int)($_POST["Matricula_Uss_Edit"] ?? 0);
            $nombre = trim($_POST["Nombre_Uss_Edit"] ?? "");
            $rol = trim($_POST["Rol_Uss_Edit"] ?? "");

            if ($matriculaOriginal <= 0 || $matriculaNueva <= 0) {
                throw new Exception("La matrícula del usuario no es válida.");
            }
            if ($nombre === "") {
                throw new Exception("El nombre del usuario es obligatorio.");
            }
            if (!in_array($rol, ["Administrador", "Encargado"], true)) {
                throw new Exception("Selecciona un rol válido.");
            }

            $stmt = $conexion->prepare("CALL sp_actualizar_usuario(?,?,?,?)");
            $stmt->bind_param("iiss", $matriculaOriginal, $matriculaNueva, $nombre, $rol);
            $stmt->execute();

            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $mensaje = $row["mensaje"] ?? "Usuario actualizado correctamente.";

            if ($res instanceof mysqli_result) $res->free();
            $stmt->close();
            limpiar_call($conexion);
        }

        if ($accion === "cambiar_password") {
            $matricula = (int)($_POST["Matricula_Password"] ?? 0);
            $password = trim($_POST["Nueva_Pswd_Uss"] ?? "");

            if ($matricula <= 0) {
                throw new Exception("No se encontró el usuario.");
            }
            if (mb_strlen($password) < 5) {
                throw new Exception("La nueva contraseña debe tener al menos 5 caracteres.");
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conexion->prepare("CALL sp_actualizar_password_usuario(?,?)");
            $stmt->bind_param("is", $matricula, $hash);
            $stmt->execute();

            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $mensaje = $row["mensaje"] ?? "Contraseña actualizada correctamente.";

            if ($res instanceof mysqli_result) $res->free();
            $stmt->close();
            limpiar_call($conexion);
        }

        if ($accion === "eliminar_usuario") {
            $matricula = (int)($_POST["Matricula_Delete"] ?? 0);

            if ($matricula <= 0) {
                throw new Exception("No se encontró el usuario a eliminar.");
            }

            if ((int)($_SESSION["Matricula_Uss"] ?? 0) === $matricula) {
                throw new Exception("No puedes eliminar tu propio usuario mientras estás usando la sesión.");
            }

            $stmt = $conexion->prepare("CALL sp_eliminar_usuario(?)");
            $stmt->bind_param("i", $matricula);
            $stmt->execute();

            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $mensaje = $row["mensaje"] ?? "Usuario eliminado correctamente.";

            if ($res instanceof mysqli_result) $res->free();
            $stmt->close();
            limpiar_call($conexion);
        }
    } catch (Throwable $e) {
        limpiar_call($conexion);
        $error = $e->getMessage();
    }
}

/* =========================
   LISTADO
========================= */
$usuarios = [];
$stmt = $conexion->prepare("CALL sp_listar_usuarios(?)");
$stmt->bind_param("s", $busqueda);
$stmt->execute();

$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $usuarios[] = $row;
}
$res->free();
$stmt->close();
limpiar_call($conexion);

/* =========================
   RESUMEN
========================= */
$resumen = [
    "total" => 0,
    "admins" => 0,
    "encargados" => 0
];

$resResumen = $conexion->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN Rol_Uss = 'Administrador' THEN 1 ELSE 0 END) AS admins,
        SUM(CASE WHEN Rol_Uss = 'Encargado' THEN 1 ELSE 0 END) AS encargados
    FROM usuarios
");
if ($resResumen) {
    $fila = $resResumen->fetch_assoc();
    if ($fila) $resumen = $fila;
    $resResumen->free();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | Inventario</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/usuarios.css">
</head>
<body>

<?php include "includes/sidebar.php"; ?>

<div class="main">
    <div class="topbar">
        <div>
            <p class="topbar__eyebrow">Administración</p>
            <h1>Usuarios</h1>
        </div>

        <button class="btn-primary" type="button" id="openCreateModalBtn">+ Nuevo usuario</button>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert success"><?php echo h($mensaje); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <div class="resume-grid">
        <div class="mini-card">
            <span class="mini-card__label">Total</span>
            <strong><?php echo (int)$resumen["total"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Administradores</span>
            <strong><?php echo (int)$resumen["admins"]; ?></strong>
        </div>
        <div class="mini-card">
            <span class="mini-card__label">Encargados</span>
            <strong><?php echo (int)$resumen["encargados"]; ?></strong>
        </div>
    </div>

    <div class="table-card">
        <div class="table-card__head table-card__head--stack">
            <div>
                <h2>Gestor de usuarios</h2>
                <p>Aquí puedes crear, editar, cambiar contraseña o eliminar usuarios del sistema.</p>
            </div>

            <form method="GET" class="toolbar">
                <div class="search-box">
                    <input type="text" name="q" placeholder="Buscar por matrícula, nombre o rol" value="<?php echo h($busqueda); ?>">
                    <button type="submit" class="btn-secondary">Buscar</button>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Matrícula</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($usuarios)): ?>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo (int)$u["Matricula_Uss"]; ?></td>
                                <td><?php echo h($u["Nombre_Uss"]); ?></td>
                                <td>
                                    <span class="role-badge role-badge--<?php echo strtolower(h($u["Rol_Uss"])); ?>">
                                        <?php echo h($u["Rol_Uss"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-inline">
                                        <button
                                            type="button"
                                            class="btn-table"
                                            data-action="edit"
                                            data-matricula="<?php echo (int)$u["Matricula_Uss"]; ?>"
                                            data-nombre="<?php echo h($u["Nombre_Uss"]); ?>"
                                            data-rol="<?php echo h($u["Rol_Uss"]); ?>"
                                        >
                                            Editar
                                        </button>

                                        <button
                                            type="button"
                                            class="btn-table btn-table--amber"
                                            data-action="password"
                                            data-matricula="<?php echo (int)$u["Matricula_Uss"]; ?>"
                                            data-nombre="<?php echo h($u["Nombre_Uss"]); ?>"
                                        >
                                            Contraseña
                                        </button>

                                        <form method="POST" onsubmit="return confirm('¿Eliminar este usuario?');">
                                            <input type="hidden" name="accion" value="eliminar_usuario">
                                            <input type="hidden" name="Matricula_Delete" value="<?php echo (int)$u["Matricula_Uss"]; ?>">
                                            <button type="submit" class="btn-table btn-table--danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="muted">No hay usuarios para mostrar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL CREAR -->
<div class="modal" id="createUserModal" aria-hidden="true">
    <div class="modal__backdrop" data-close="createUserModal"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <div>
                <h2>Nuevo usuario</h2>
                <p>Crea un nuevo acceso para administrador o encargado.</p>
            </div>
            <button class="modal__close" type="button" data-close="createUserModal">×</button>
        </div>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="crear_usuario">

            <div class="modal-grid">
                <div class="field">
                    <label for="Matricula_Uss">Matrícula</label>
                    <input type="number" name="Matricula_Uss" id="Matricula_Uss" required>
                </div>

                <div class="field">
                    <label for="Nombre_Uss">Nombre</label>
                    <input type="text" name="Nombre_Uss" id="Nombre_Uss" required>
                </div>

                <div class="field">
                    <label for="Rol_Uss">Rol</label>
                    <select name="Rol_Uss" id="Rol_Uss" required>
                        <option value="">Selecciona</option>
                        <option value="Administrador">Administrador</option>
                        <option value="Encargado">Encargado</option>
                    </select>
                </div>

                <div class="field">
                    <label for="Pswd_Uss">Contraseña</label>
                    <input type="password" name="Pswd_Uss" id="Pswd_Uss" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close="createUserModal">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div class="modal" id="editUserModal" aria-hidden="true">
    <div class="modal__backdrop" data-close="editUserModal"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <div>
                <h2>Editar usuario</h2>
                <p>Actualiza matrícula, nombre o rol del usuario.</p>
            </div>
            <button class="modal__close" type="button" data-close="editUserModal">×</button>
        </div>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="editar_usuario">
            <input type="hidden" name="Matricula_Original" id="Matricula_Original">

            <div class="modal-grid">
                <div class="field">
                    <label for="Matricula_Uss_Edit">Matrícula</label>
                    <input type="number" name="Matricula_Uss_Edit" id="Matricula_Uss_Edit" required>
                </div>

                <div class="field">
                    <label for="Nombre_Uss_Edit">Nombre</label>
                    <input type="text" name="Nombre_Uss_Edit" id="Nombre_Uss_Edit" required>
                </div>

                <div class="field">
                    <label for="Rol_Uss_Edit">Rol</label>
                    <select name="Rol_Uss_Edit" id="Rol_Uss_Edit" required>
                        <option value="Administrador">Administrador</option>
                        <option value="Encargado">Encargado</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close="editUserModal">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PASSWORD -->
<div class="modal" id="passwordModal" aria-hidden="true">
    <div class="modal__backdrop" data-close="passwordModal"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <div>
                <h2>Cambiar contraseña</h2>
                <p id="passwordUserLabel">Actualiza la contraseña del usuario.</p>
            </div>
            <button class="modal__close" type="button" data-close="passwordModal">×</button>
        </div>

        <form method="POST" class="modal-form">
            <input type="hidden" name="accion" value="cambiar_password">
            <input type="hidden" name="Matricula_Password" id="Matricula_Password">

            <div class="modal-grid">
                <div class="field field--full">
                    <label for="Nueva_Pswd_Uss">Nueva contraseña</label>
                    <input type="password" name="Nueva_Pswd_Uss" id="Nueva_Pswd_Uss" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close="passwordModal">Cancelar</button>
                <button type="submit" class="btn-primary">Actualizar contraseña</button>
            </div>
        </form>
    </div>
</div>

<script>
const openCreateModalBtn = document.getElementById("openCreateModalBtn");
const createUserModal = document.getElementById("createUserModal");
const editUserModal = document.getElementById("editUserModal");
const passwordModal = document.getElementById("passwordModal");

function openModal(modal) {
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeModal(modal) {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".modal.is-open")) {
        document.body.classList.remove("modal-open");
    }
}

openCreateModalBtn?.addEventListener("click", () => openModal(createUserModal));

document.querySelectorAll("[data-close]").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-close");
        const modal = document.getElementById(id);
        if (modal) closeModal(modal);
    });
});

window.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal.is-open").forEach(closeModal);
    }
});

document.querySelectorAll('[data-action="edit"]').forEach(btn => {
    btn.addEventListener("click", () => {
        document.getElementById("Matricula_Original").value = btn.dataset.matricula;
        document.getElementById("Matricula_Uss_Edit").value = btn.dataset.matricula;
        document.getElementById("Nombre_Uss_Edit").value = btn.dataset.nombre;
        document.getElementById("Rol_Uss_Edit").value = btn.dataset.rol;
        openModal(editUserModal);
    });
});

document.querySelectorAll('[data-action="password"]').forEach(btn => {
    btn.addEventListener("click", () => {
        document.getElementById("Matricula_Password").value = btn.dataset.matricula;
        document.getElementById("passwordUserLabel").textContent = "Actualizar contraseña de " + btn.dataset.nombre + ".";
        openModal(passwordModal);
    });
});
</script>

</body>
</html>