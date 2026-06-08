<?php
session_start();
if (!isset($_SESSION['id_docente']) || !isset($_POST['id_curso'])) {
    exit("Acceso denegado.");
}

include 'conexion.php';
// Incluimos el cargador automático de Dompdf
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$id_curso = intval($_POST['id_curso']);

// Consultar datos del curso
$stmt_c = $conexion->prepare("SELECT nombre_curso, paralelo FROM cursos WHERE id_curso = ?");
$stmt_c->bind_param("i", $id_curso);
$stmt_c->execute();
$curso = $stmt_c->get_result()->fetch_assoc();
$stmt_c->close();

// Consultar nómina de alumnos
$sql = "SELECT id_estudiante, nombres, apellidos, cedula FROM estudiantes WHERE id_curso = ? ORDER BY apellidos ASC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_curso);
$stmt->execute();
$res = $stmt->get_result();

// --- CONSTRUCCIÓN DEL CONTENEDOR HTML DEL REPORTE IMPRESO ---
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Helvetica", Arial, sans-serif; color: #333; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #1a365d; font-size: 20px; margin: 0; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; color: #555; font-size: 13px; }
        .meta-info { margin-bottom: 20px; font-size: 12px; border-bottom: 2px solid #1a365d; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #1a365d; color: white; padding: 8px; font-size: 11px; text-transform: uppercase; border: 1px solid #cbd5e0; }
        td { padding: 8px; border: 1px solid #cbd5e0; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .footer-firma { margin-top: 80px; text-align: center; }
        .linea-firma { width: 200px; border-top: 1px solid #333; margin: 0 auto; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Escuela de Educación Básica "Manuel Zea "</h1>
        <p>Sistema Integral de Gestión Escolar (SIGE - Reporte General)</p>
    </div>

    <div class="meta-info">
        <strong>Docente de Aula:</strong> ' . htmlspecialchars($_SESSION['nombre_docente']) . '<br>
        <strong>Curso / Nivel:</strong> ' . htmlspecialchars($curso['nombre_curso']) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Paralelo:</strong> ' . htmlspecialchars($curso['paralelo']) . '<br>
        <strong>Fecha de Emisión:</strong> ' . date('d/m/Y') . '
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">N°</th>
                <th style="text-align: left; width: 45%;">Apellidos y Nombres</th>
                <th style="width: 15%;">Cédula</th>
                <th style="width: 15%;">Promedio Notas</th>
                <th style="width: 15%;">% Asistencia</th>
            </tr>
        </thead>
        <tbody>';

$contador = 1;
while ($row = $res->fetch_assoc()) {
    $id_est = $row['id_estudiante'];
    
    // Calcular Promedio
    $stmt_n = $conexion->prepare("SELECT AVG(nota) as promedio FROM calificaciones WHERE id_estudiante = ?");
    $stmt_n->bind_param("i", $id_est);
    $stmt_n->execute();
    $p_nota = $stmt_n->get_result()->fetch_assoc()['promedio'];
    $promedio = $p_nota ? number_format($p_nota, 2) : "0.00";
    $stmt_n->close();
    
    // Calcular Asistencia
    $stmt_td = $conexion->prepare("SELECT COUNT(DISTINCT fecha) as total FROM asistencias WHERE id_estudiante IN (SELECT id_estudiante FROM estudiantes WHERE id_curso = ?)");
    $stmt_td->bind_param("i", $id_curso);
    $stmt_td->execute();
    $total_dias = $stmt_td->get_result()->fetch_assoc()['total'];
    $stmt_td->close();
    
    $stmt_a = $conexion->prepare("SELECT COUNT(*) as asistencias FROM asistencias WHERE id_estudiante = ? AND (estado = 'Asiste' OR estado = 'Justificado')");
    $stmt_a->bind_param("i", $id_est);
    $stmt_a->execute();
    $dias_p = $stmt_a->get_result()->fetch_assoc()['asistencias'];
    $stmt_a->close();
    
    $porcentaje = ($total_dias > 0) ? number_format(($dias_p / $total_dias) * 100, 0) . "%" : "100%";

    $html .= '
            <tr>
                <td class="text-center">' . $contador++ . '</td>
                <td><strong>' . htmlspecialchars($row['apellidos'] . ", " . $row['nombres']) . '</strong></td>
                <td class="text-center">' . htmlspecialchars($row['cedula']) . '</td>
                <td class="text-center bold" style="color: #2b6cb0;">' . $promedio . '</td>
                <td class="text-center bold" style="color: #2f855a;">' . $porcentaje . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer-firma">
        <br><br>
        <div class="linea-firma">
            Prof. ' . htmlspecialchars($_SESSION['nombre_docente']) . '<br>
            <span style="font-size: 10px; color: #666;">Docente Responsable</span>
        </div>
    </div>

</body>
</html>';

$stmt->close();

// --- INICIALIZAR EL MOTOR DOMPDF ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Configurar tamaño de hoja (Carta / Vertical)
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

// Forzar la descarga del PDF con nombre personalizado
$dompdf->stream("Reporte_Rendimiento_Curso_" . $id_curso . ".pdf", array("Attachment" => true));
?>