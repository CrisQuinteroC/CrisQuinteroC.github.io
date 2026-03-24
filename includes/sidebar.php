<?php
$activePage = $activePage ?? "";

function is_active(string $key, string $activePage): string {
  return ($key === $activePage) ? " is-active" : "";
}
?>

<aside class="sidebar sidebar--mint">
  <a class="sb__brand" href="index.php" aria-label="Ir al dashboard">
    <span class="sb__logo" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none">
        <path d="M12 2l8 4.5v11L12 22 4 17.5v-11L12 2z" stroke="currentColor" stroke-width="1.8" />
        <path d="M12 2v20" stroke="currentColor" stroke-width="1.2" opacity=".55"/>
        <path d="M4 6.5l8 4.5 8-4.5" stroke="currentColor" stroke-width="1.2" opacity=".55"/>
      </svg>
    </span>
    <div class="sb__brandText">
      <strong>Inventario</strong>
      <span>UTN</span>
    </div>
  </a>

  <nav class="sb__nav" aria-label="Navegación principal">
    <a class="sb__link<?= is_active("dashboard", $activePage) ?>" href="index.php">
      <span class="sb__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <path d="M4 4h7v7H4V4zM13 4h7v4h-7V4zM13 10h7v10h-7V10zM4 13h7v7H4v-7z"
                stroke="currentColor" stroke-width="1.8" />
        </svg>
      </span>
      <span>Dashboard</span>
    </a>

    <a class="sb__link<?= is_active("activos", $activePage) ?>" href="activos.php">
      <span class="sb__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <path d="M4 7l8-4 8 4v10l-8 4-8-4V7z" stroke="currentColor" stroke-width="1.8"/>
          <path d="M4 7l8 4 8-4" stroke="currentColor" stroke-width="1.2" opacity=".6"/>
        </svg>
      </span>
      <span>Activos</span>
    </a>

    <a class="sb__link<?= is_active("prestamos", $activePage) ?>" href="prestamos.php">
      <span class="sb__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <path d="M8 7h8M8 12h8M8 17h5M6 3h12a2 2 0 012 2v14l-4-2-4 2-4-2-4 2V5a2 2 0 012-2z" stroke="currentColor" stroke-width="1.8"/>
        </svg>
      </span>
      <span>Préstamos</span>
    </a>

    <a class="sb__link<?= is_active("devoluciones", $activePage) ?>" href="devoluciones.php">
      <span class="sb__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <path d="M9 14l-4-4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M5 10h9a5 5 0 110 10h-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span>Devoluciones</span>
    </a>

    <a class="sb__link<?= is_active("ubicaciones", $activePage) ?>" href="ubicaciones.php">
      <span class="sb__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
          <path d="M12 21s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z" stroke="currentColor" stroke-width="1.8"/>
          <path d="M12 12a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="1.2" opacity=".6"/>
        </svg>
      </span>
      <span>Ubicaciones</span>
    </a>
  </nav>

  <a class="sb__link<?= is_active("solicitantes", $activePage) ?>" href="solicitantes.php">
    <span class="sb__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
        <path d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="10" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
        <path d="M20 8v6M17 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
    </span>
    <span>Solicitantes</span>
  </a>

  <?php if (($_SESSION["Rol_Uss"] ?? "") === "Administrador"): ?>
  <a class="sb__link<?= is_active("usuarios", $activePage) ?>" href="usuarios.php">
    <span class="sb__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8"/>
        <path d="M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
    </span>
    <span>Usuarios</span>
  </a>
<?php endif; ?>

  <div class="sb__footer">
    <a class="sb__logout" href="logout.php">
      <span aria-hidden="true">⟵</span>
      <span>Cerrar sesión</span>
    </a>
  </div>
</aside>