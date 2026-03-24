<?php
require_once "conexion.php";
require_once "includes/auth.php";
require_once __DIR__ . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$color = "FFFFFF"; // default

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset("utf8mb4");

function limpiar_call(mysqli $conexion): void {
    while ($conexion->more_results()) {
        $conexion->next_result();
        $extra = $conexion->store_result();
        if ($extra instanceof mysqli_result) {
            $extra->free();
        }
    }
}

function textoTipoReporte(string $tipo): string {
    return match ($tipo) {
        'prestamos' => '( X ) PRÉSTAMOS     (   ) DEVUELTOS     (   ) ACTIVOS     (   ) VENCIDOS',
        'devueltos' => '(   ) PRÉSTAMOS     ( X ) DEVUELTOS     (   ) ACTIVOS     (   ) VENCIDOS',
        'activos'   => '(   ) PRÉSTAMOS     (   ) DEVUELTOS     ( X ) ACTIVOS     (   ) VENCIDOS',
        'vencidos'  => '(   ) PRÉSTAMOS     (   ) DEVUELTOS     (   ) ACTIVOS     ( X ) VENCIDOS',
        default     => '( X ) PRÉSTAMOS     (   ) DEVUELTOS     (   ) ACTIVOS     (   ) VENCIDOS',
    };
}

function htexto($valor, string $default = '—'): string {
    $valor = trim((string)$valor);
    return $valor !== '' ? $valor : $default;
}

$tipoReporte = trim($_GET["tipo_reporte"] ?? "prestamos");
$fechaInicio = trim($_GET["fecha_inicio"] ?? "");
$fechaFin    = trim($_GET["fecha_fin"] ?? "");

$reportesValidos = ["prestamos", "devueltos", "activos", "vencidos"];
if (!in_array($tipoReporte, $reportesValidos, true)) {
    $tipoReporte = "prestamos";
}

$fechaInicioParam = ($fechaInicio !== "") ? $fechaInicio : null;
$fechaFinParam    = ($fechaFin !== "") ? $fechaFin : null;

$registros = [];

/* =========================
   USUARIO EN SESIÓN
========================= */
$usuarioSesion = trim((string)($_SESSION["Nombre_Uss"] ?? "Usuario"));
$textoFechaInicio = ($fechaInicio !== "") ? date("d/m/Y", strtotime($fechaInicio)) : "Sin filtro";
$textoFechaFin    = ($fechaFin !== "") ? date("d/m/Y", strtotime($fechaFin)) : "Sin filtro";
$textoImpresion   = date("d/m/Y H:i");

/* =========================
   OBTENER DATOS DEL REPORTE
========================= */
try {
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
    http_response_code(500);
    exit("Error al obtener los datos del reporte: " . $e->getMessage());
}

/* =========================
   CARGAR PLANTILLA
========================= */
$rutaPlantilla = __DIR__ . "/plantillas/reporte_prestamos_plantilla.xlsx";

if (!file_exists($rutaPlantilla)) {
    http_response_code(500);
    exit("No se encontró la plantilla del reporte en: " . $rutaPlantilla);
}

try {
    $spreadsheet = IOFactory::load($rutaPlantilla);
} catch (Throwable $e) {
    http_response_code(500);
    exit("No se pudo abrir la plantilla del reporte: " . $e->getMessage());
}

$sheet = $spreadsheet->getActiveSheet();

/* =========================
   ENCABEZADO DE LA PLANTILLA
========================= */
/*
    Según la plantilla:
    - A10: tipo de reporte
    - A13: abajo de "USUARIO SOLICITANTE:"
    - F13: abajo de "PRÉSTAMOS DE:"
    - F15: abajo de "HASTA:"
    - H13: abajo de "FECHA DE IMPRESIÓN:"
*/
$sheet->setCellValue("A10", textoTipoReporte($tipoReporte));

$sheet->setCellValue("A13", $usuarioSesion);
$sheet->setCellValue("F13", $textoFechaInicio);
$sheet->setCellValue("F15", $textoFechaFin);
$sheet->setCellValue("H13", $textoImpresion);

/* Alineación del encabezado */
foreach (["A13", "F13", "F15", "H13"] as $celda) {
    $sheet->getStyle($celda)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($celda)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle($celda)->getAlignment()->setWrapText(true);
}

/* =========================
   ÁREA DE DATOS
========================= */
$filaInicioDatos    = 18;
$filaFirmas         = 44;
$capacidadOriginal  = $filaFirmas - $filaInicioDatos;
$totalRegistros     = count($registros);

/*
   Si el total supera el espacio disponible antes del área de firmas,
   insertamos filas nuevas para no encimar el contenido.
*/
if ($totalRegistros > $capacidadOriginal) {
    $filasExtra = $totalRegistros - $capacidadOriginal;
    $sheet->insertNewRowBefore($filaFirmas, $filasExtra);

    for ($fila = $filaFirmas; $fila < $filaFirmas + $filasExtra; $fila++) {
        $sheet->duplicateStyle($sheet->getStyle("A18:I18"), "A{$fila}:I{$fila}");
        $sheet->mergeCells("D{$fila}:E{$fila}");
        $sheet->getRowDimension($fila)->setRowHeight(25);
    }
}

/* Limpiar contenido previo del área de datos */
$ultimaFilaUso = max($filaInicioDatos + $totalRegistros - 1, $filaInicioDatos);

for ($fila = $filaInicioDatos; $fila <= $ultimaFilaUso; $fila++) {
    foreach (["A", "B", "C", "D", "F", "G", "H", "I"] as $col) {
        $sheet->setCellValue($col . $fila, "");
    }
}

/* =========================
   LLENAR DATOS DEL REPORTE
========================= */
$filaExcel = $filaInicioDatos;

foreach ($registros as $r) {
    $grupoID = !empty($r["Grupo_PrestamoID"]) 
    ? "#" . (int)$r["Grupo_PrestamoID"] 
    : "—";

    $sheet->setCellValue("A{$filaExcel}", $grupoID);
    $estado = "—";

if (!empty($r["Estado_Prestamo"])) {
    if ($r["Estado_Prestamo"] === "Devuelto") {
        $estado = "Devuelto";
    } elseif ($r["Estado_Prestamo"] === "Activo") {

        if (!empty($r["Fecha_Limite"])) {
            $fechaLimite = strtotime($r["Fecha_Limite"]);
            $ahora = time();

            if ($fechaLimite < $ahora) {
                $estado = "Vencido";
            } else {
                $estado = "Activo";
            }

        } else {
            $estado = "Activo";
        }

    } else {
        $estado = $r["Estado_Prestamo"];
    }
}

$sheet->setCellValue("B{$filaExcel}", $estado);

// 🎨 COLORES AQUÍ (JUSTO AQUÍ 👇)
$color = "FFFFFF"; // blanco por default

switch ($estado) {
    case "Devuelto":
        $color = "C6EFCE"; // verde
        break;
    case "Activo":
        $color = "FFF2CC"; // amarillo
        break;
    case "Vencido":
        $color = "F8CBAD"; // rojo claro
        break;
}

$sheet->getStyle("B{$filaExcel}")->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB($color);
    $sheet->setCellValue("C{$filaExcel}", htexto($r["Num_Marbete"] ?? "", "S/M"));
    $sheet->setCellValue("D{$filaExcel}", htexto($r["Activo_Desc"] ?? ""));
    $sheet->setCellValue("F{$filaExcel}", htexto($r["SolicitanteNombre"] ?? ""));
    $sheet->setCellValue("G{$filaExcel}", htexto($r["Contacto"] ?? ""));
    $sheet->setCellValue("H{$filaExcel}", htexto($r["Carrera"] ?? ""));
    $sheet->setCellValue("I{$filaExcel}", htexto($r["GrupoAcademico"] ?? ""));

    $sheet->getStyle("A{$filaExcel}:I{$filaExcel}")
        ->getAlignment()
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getStyle("A{$filaExcel}:I{$filaExcel}")
        ->getAlignment()
        ->setWrapText(true);

    $filaExcel++;
}

/* =========================
   SI NO HAY REGISTROS
========================= */
if ($totalRegistros === 0) {
    $sheet->mergeCells("A18:I18");
    $sheet->setCellValue("A18", "No se encontraron registros para este reporte.");
    $sheet->getStyle("A18")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A18")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

/* =========================
   DESCARGA
========================= */
$nombreArchivo = "reporte_prestamos_" . $tipoReporte . "_" . date("Y-m-d_H-i-s") . ".xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment;filename=\"{$nombreArchivo}\"");
header("Cache-Control: max-age=0");
header("Pragma: public");

$writer = IOFactory::createWriter($spreadsheet, "Xlsx");
$writer->save("php://output");
exit;