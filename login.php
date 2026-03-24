<?php
session_start();
include("conexion.php"); // conexión mysqli en $conexion

// Si ya hay sesión, mandar al index
if (isset($_SESSION["Matricula_Uss"])) {
  header("Location: index.php");
  exit;
}

$mensaje = "";
$tipo = ""; // ok | error

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $matUss = trim($_POST["Matricula_Uss"] ?? "");
  $pass   = trim($_POST["Pswd_Uss"] ?? "");

  if ($matUss === "" || $pass === "") {
    $mensaje = "Completa todos los campos.";
    $tipo = "error";
  } elseif (!ctype_digit($matUss)) {
    $mensaje = "El ID de empleado debe ser numérico.";
    $tipo = "error";
  } else {

    $stmt = null;
    $res = null;

    try {
      // Ahora usamos el procedimiento almacenado
      $sql = "CALL sp_login_usuario(?)";
      $stmt = mysqli_prepare($conexion, $sql);

      if (!$stmt) {
        throw new Exception("No se pudo preparar la consulta.");
      }

      $idInt = (int)$matUss;
      mysqli_stmt_bind_param($stmt, "i", $idInt);

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("No se pudo ejecutar la consulta.");
      }

      $res = mysqli_stmt_get_result($stmt);
      $user = $res ? mysqli_fetch_assoc($res) : null;

      if (!$user) {
        $mensaje = "ID o contraseña incorrectos.";
        $tipo = "error";
      } else {
        if (password_verify($pass, $user["Pswd_Uss"])) {

          $_SESSION["Matricula_Uss"] = (int)$user["Matricula_Uss"];
          $_SESSION["Nombre_Uss"]    = $user["Nombre_Uss"];
          $_SESSION["Rol_Uss"]       = $user["Rol_Uss"];

          header("Location: index.php");
          exit;
        } else {
          $mensaje = "ID o contraseña incorrectos.";
          $tipo = "error";
        }
      }

    } catch (Throwable $e) {
      $mensaje = "Ocurrió un error al iniciar sesión. Intenta de nuevo.";
      $tipo = "error";
    } finally {
      if ($res instanceof mysqli_result) {
        mysqli_free_result($res);
      }

      if ($stmt instanceof mysqli_stmt) {
        mysqli_stmt_close($stmt);
      }

      // Importante después de CALL para limpiar resultados pendientes
      while (mysqli_more_results($conexion)) {
        mysqli_next_result($conexion);
        $extraResult = mysqli_store_result($conexion);
        if ($extraResult instanceof mysqli_result) {
          mysqli_free_result($extraResult);
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Iniciar sesión</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>

  <main class="auth">
    <section class="card">

      <div class="card__left">
        <div class="blob blob--big"></div>
        <div class="blob blob--small"></div>

        <div class="photo-wrap">
          <img
            class="photo"
            src="images/UTNLogin.jpg"
            alt="Imagen decorativa"
          />
        </div>
      </div>

      <div class="card__right">
        <h1 class="title">Iniciar sesión</h1>

        <?php if ($mensaje !== ""): ?>
          <div class="alert <?php echo $tipo === "ok" ? "alert--ok" : "alert--error"; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
          </div>
        <?php endif; ?>

        <form class="form" action="login.php" method="post" autocomplete="off">
          <label class="field">
            <span>ID de empleado</span>
            <input
              type="text"
              name="Matricula_Uss"
              placeholder="Ingresa tu ID de empleado aquí"
              value="<?php echo htmlspecialchars($_POST['Matricula_Uss'] ?? ''); ?>"
            />
          </label>

          <label class="field">
            <span>Contraseña</span>
            <input
              type="password"
              name="Pswd_Uss"
              placeholder="Ingresa tu contraseña aquí"
            />
          </label>

          <button class="btn btn--primary" type="submit">Iniciar Sesión</button>
        </form>
      </div>

    </section>
  </main>

</body>
</html>