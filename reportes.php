<?php
session_start();
if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}
include 'conexion.php';

// Capturamos el curso seleccionado ya sea por el cambio de select o por el botón de enviar
$id_curso_seleccionado = isset($_POST['id_curso']) ? intval($_POST['id_curso']) : 0;
$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");

$estudiantes_reporte = [];
$nombre_curso_completo = "";

if ($id_curso_seleccionado > 0) {
    // Obtener el nombre del curso actual para la vista previa
    $stmt_c = $conexion->prepare("SELECT nombre_curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt_c->bind_param("i", $id_curso_seleccionado);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result()->fetch_assoc();
    if($res_c) {
        $nombre_curso_completo = $res_c['nombre_curso'] . " '" . $res_c['paralelo'] . "'";
    }
    $stmt_c->close();

    // 1. Obtener la nómina de estudiantes del curso
    $sql = "SELECT id_estudiante, nombres, apellidos, cedula FROM estudiantes WHERE id_curso = ? ORDER BY apellidos ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_curso_seleccionado);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $id_est = $row['id_estudiante'];
        
        // 2. Calcular el Promedio de Calificaciones
        $stmt_nota = $conexion->prepare("SELECT AVG(nota) as promedio FROM calificaciones WHERE id_estudiante = ?");
        $stmt_nota->bind_param("i", $id_est);
        $stmt_nota->execute();
        $res_nota = $stmt_nota->get_result()->fetch_assoc();
        $promedio = $res_nota['promedio'] ? number_format($res_nota['promedio'], 2) : "0.00";
        $stmt_nota->close();
        
        // 3. Calcular el Porcentaje de Asistencia
        $stmt_total_dias = $conexion->prepare("SELECT COUNT(DISTINCT fecha) as total FROM asistencias WHERE id_estudiante IN (SELECT id_estudiante FROM estudiantes WHERE id_curso = ?)");
        $stmt_total_dias->bind_param("i", $id_curso_seleccionado);
        $stmt_total_dias->execute();
        $total_dias = $stmt_total_dias->get_result()->fetch_assoc()['total'];
        $stmt_total_dias->close();
        
        $stmt_asistio = $conexion->prepare("SELECT COUNT(*) as asistencias FROM asistencias WHERE id_estudiante = ? AND (estado = 'Asiste' OR estado = 'Justificado')");
        $stmt_asistio->bind_param("i", $id_est);
        $stmt_asistio->execute();
        $dias_presente = $stmt_asistio->get_result()->fetch_assoc()['asistencias'];
        $stmt_asistio->close();
        
        $porcentaje_asistencia = ($total_dias > 0) ? number_format(($dias_presente / $total_dias) * 100, 0) . "%" : "100%";
        
        $estudiantes_reporte[] = [
            'nombres' => $row['apellidos'] . ", " . $row['nombres'],
            'cedula' => $row['cedula'],
            'promedio' => $promedio,
            'asistencia' => $porcentaje_asistencia
        ];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGE - Reportes Generales</title>
    <link rel="stylesheet" href="estilos_panel.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>SIGE Escuela</h2>
                <p>Docente: <?php echo htmlspecialchars($_SESSION['nombre_docente']); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="estudiantes.php">📂 Estudiantes</a>
                <a href="asistencias.php">📅 Control Asistencia</a>
                <a href="tareas.php">📝 Gestión de Tareas</a>
                <a href="calificaciones.php">📊 Calificaciones</a>
                <a href="reportes.php" class="activo">🖨️ Reportes PDF</a>
            </nav>
            <div class="sidebar-footer"><a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a></div>
        </aside>

        <main class="contenido-principal">
            <header class="topbar">
                <h1>Generador de Reportes Oficiales</h1>
                <p>Genere sábanas de notas y porcentajes de asistencia listos para impresión</p>
            </header>

            <section class="seccion-tarjeta">
                <h3>Selección de Curso para Reporte</h3>
                <form action="reportes.php" method="POST" class="formulario-horizontal">
                    <div class="campo" style="min-width: 300px;">
                        <label>Seleccione el nivel académico</label>
                        <select name="id_curso" required onchange="this.form.submit()">
                            <option value="">Seleccione un curso...</option>
                            <?php 
                            $cursos_query->data_seek(0);
                            while($c = $cursos_query->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $c['id_curso']; ?>" <?php if($id_curso_seleccionado == $c['id_curso']) echo 'selected'; ?>>
                                    <?php echo $c['nombre_curso'] . " '" . $c['paralelo'] . "'"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="campo boton-contenedor">
                        <button type="submit" class="btn-guardar" style="background-color: #4a5568;">🔍 Ver Reporte</button>
                    </div>
                </form>
            </section>

            <?php if ($id_curso_seleccionado > 0): ?>
                <section class="seccion-tarjeta">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h3 style="margin: 0;">Vista Previa de Impresión</h3>
                            <p style="margin: 5px 0 0 0; color: #718096; font-size: 0.9em;">Curso: <strong><?php echo htmlspecialchars($nombre_curso_completo); ?></strong></p>
                        </div>
                        <form action="descargar_pdf.php" method="POST" target="_blank">
                            <input type="hidden" name="id_curso" value="<?php echo $id_curso_seleccionado; ?>">
                            <button type="submit" class="btn-guardar" style="background-color: #e53e3e; border: 1px solid #c53030; font-weight: bold; min-width: 220px;">
                                📥 Descargar PDF Oficial
                            </button>
                        </form>
                    </div>

                    <div style="background: white; border: 2px dashed #cbd5e0; padding: 30px; border-radius: 6px; box-shadow: inset 0 0 10px rgba(0,0,0,0.02);">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="color: #1a365d; text-transform: uppercase; margin: 0; font-size: 1.4em;">Escuela de Educación Básica "Manuel Zea"</h2>
                            <p style="font-size: 0.95em; color: #4a5568; margin: 5px 0 0 0; font-weight: 600; letter-spacing: 1px;">SÁBANA INTEGRAL DE RENDIMIENTO ACADÉMICO</p>
                        </div>
                        
                        <hr style="border: 0; border-top: 2px solid #1a365d; margin-bottom: 15px;">
                        
                        <div style="display: flex; justify-content: space-between; font-size: 0.95em; color: #2d3748; margin-bottom: 15px; background: #f7fafc; padding: 10px; border-radius: 4px;">
                            <span><strong>Docente:</strong> <?php echo htmlspecialchars($_SESSION['nombre_docente']); ?></span>
                            <span><strong>Curso:</strong> <?php echo htmlspecialchars($nombre_curso_completo); ?></span>
                            <span><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></span>
                        </div>
                        
                        <div class="tabla-responsiva">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #1a365d; color: white;">
                                        <th style="padding: 12px; border: 1px solid #cbd5e0; text-align: left;">Nómina del Estudiante</th>
                                        <th style="padding: 12px; border: 1px solid #cbd5e0; text-align: center; width: 20%;">Cédula</th>
                                        <th style="padding: 12px; border: 1px solid #cbd5e0; text-align: center; width: 20%;">Promedio Notas</th>
                                        <th style="padding: 12px; border: 1px solid #cbd5e0; text-align: center; width: 20%;">% Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($estudiantes_reporte)): ?>
                                        <?php foreach($estudiantes_reporte as $est): ?>
                                            <tr>
                                                <td style="padding: 10px; border: 1px solid #cbd5e0;"><strong><?php echo htmlspecialchars($est['nombres']); ?></strong></td>
                                                <td style="padding: 10px; border: 1px solid #cbd5e0; text-align: center; color: #4a5568;"><?php echo htmlspecialchars($est['cedula']); ?></td>
                                                <td style="padding: 10px; border: 1px solid #cbd5e0; text-align: center; font-weight: bold; color: #2b6cb0; font-size: 1.1em;"><?php echo $est['promedio']; ?></td>
                                                <td style="padding: 10px; border: 1px solid #cbd5e0; text-align: center; font-weight: bold; color: #2f855a; font-size: 1.1em;"><?php echo $est['asistencia']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 20px; color: #a0aec0;" class="tabla-vacia">No existen estudiantes matriculados en este curso para procesar el reporte.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>