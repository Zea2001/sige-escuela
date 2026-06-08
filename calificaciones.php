<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}

include 'conexion.php';

$mensaje = "";
$id_curso_seleccionado = isset($_POST['id_curso']) ? intval($_POST['id_curso']) : 0;
$id_tarea_seleccionada = isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;

// --- LÓGICA 1: GUARDAR O ACTUALIZAR CALIFICACIONES ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_notas'])) {
    $notas = $_POST['nota']; // Arreglo con las calificaciones
    $observaciones = $_POST['observaciones']; // Arreglo con comentarios

    if (!empty($notas)) {
        foreach ($notas as $id_estudiante => $valor_nota) {
            $comentario = isset($observaciones[$id_estudiante]) ? trim($observaciones[$id_estudiante]) : "";
            
            // Si el casillero está vacío, saltamos al siguiente estudiante para no llenar la BD de ceros vacíos
            if ($valor_nota === "") continue;

            $valor_nota = floatval($valor_nota);

            // Verificar si ya existe calificación previa para este alumno en esta tarea
            $stmt_check = $conexion->prepare("SELECT id_calificacion FROM calificaciones WHERE id_estudiante = ? AND id_tarea = ?");
            $stmt_check->bind_param("ii", $id_estudiante, $id_tarea_seleccionada);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result();

            if ($res_check->num_rows > 0) {
                // Si ya existe, se actualiza la nota y la observación
                $calif = $res_check->fetch_assoc();
                $stmt_up = $conexion->prepare("UPDATE calificaciones SET nota = ?, observaciones = ? WHERE id_calificacion = ?");
                $stmt_up->bind_param("dsi", $valor_nota, $comentario, $calif['id_calificacion']);
                $stmt_up->execute();
                $stmt_up->close();
            } else {
                // Si no existe, se inserta una nueva fila de calificación
                $stmt_ins = $conexion->prepare("INSERT INTO calificaciones (nota, observaciones, id_estudiante, id_tarea) VALUES (?, ?, ?, ?)");
                $stmt_ins->bind_param("dsii", $valor_nota, $comentario, $id_estudiante, $id_tarea_seleccionada);
                $stmt_ins->execute();
                $stmt_ins->close();
            }
            $stmt_check->close();
        }
        $mensaje = "<div class='alerta exito'>Cuaderno de calificaciones actualizado correctamente.</div>";
    }
}

// Consultar todos los cursos disponibles
$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");

// Consultar tareas del curso seleccionado
$tareas_query = null;
if ($id_curso_seleccionado > 0) {
    $stmt_tar = $conexion->prepare("SELECT id_tarea, titulo_tarea FROM tareas WHERE id_curso = ? ORDER BY id_tarea DESC");
    $stmt_tar->bind_param("i", $id_curso_seleccionado);
    $stmt_tar->execute();
    $tareas_query = $stmt_tar->get_result();
    $stmt_tar->close();
}

// Consultar estudiantes si el curso y la tarea están seleccionados
$estudiantes_query = null;
if ($id_curso_seleccionado > 0 && $id_tarea_seleccionada > 0) {
    $stmt_est = $conexion->prepare("SELECT id_estudiante, nombres, apellidos FROM estudiantes WHERE id_curso = ? ORDER BY apellidos ASC");
    $stmt_est->bind_param("i", $id_curso_seleccionado);
    $stmt_est->execute();
    $estudiantes_query = $stmt_est->get_result();
    $stmt_est->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGE - Calificaciones</title>
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
                <a href="calificaciones.php" class="activo">📊 Calificaciones</a>
                <a href="reportes.php">🖨️ Reportes PDF</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-principal">
            <header class="topbar">
                <h1>Registro de Calificaciones</h1>
                <p>Gestione el rendimiento académico e ingrese las notas de cada actividad evaluativa</p>
            </header>

            <?php echo $mensaje; ?>

            <section class="seccion-tarjeta">
                <h3>Filtros de Evaluación</h3>
                <form action="calificaciones.php" method="POST" class="formulario-horizontal">
                    
                    <div class="campo">
                        <label>1. Seleccionar Curso</label>
                        <select name="id_curso" required onchange="this.form.submit()">
                            <option value="">Seleccione un curso...</option>
                            <?php while($curso = $cursos_query->fetch_assoc()): ?>
                                <option value="<?php echo $curso['id_curso']; ?>" <?php if($id_curso_seleccionado == $curso['id_curso']) echo 'selected'; ?>>
                                    <?php echo $curso['nombre_curso'] . " '" . $curso['paralelo'] . "'"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="campo" style="min-width: 250px;">
                        <label>2. Seleccionar Tarea / Actividad</label>
                        <select name="id_tarea" required <?php if($id_curso_seleccionado == 0) echo 'disabled'; ?> onchange="this.form.submit()">
                            <option value="">Seleccione una tarea...</option>
                            <?php if ($tareas_query): ?>
                                <?php while($tarea = $tareas_query->fetch_assoc()): ?>
                                    <option value="<?php echo $tarea['id_tarea']; ?>" <?php if($id_tarea_seleccionada == $tarea['id_tarea']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tarea['titulo_tarea']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                </form>
            </section>

            <?php if ($id_curso_seleccionado > 0 && $id_tarea_seleccionada > 0): ?>
                <section class="seccion-tarjeta">
                    <h3>Ingreso de Notas</h3>
                    <form action="calificaciones.php" method="POST">
                        <input type="hidden" name="id_curso" value="<?php echo $id_curso_seleccionado; ?>">
                        <input type="hidden" name="id_tarea" value="<?php echo $id_tarea_seleccionada; ?>">
                        <input type="hidden" name="guardar_notas" value="1">

                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nómina de Alumnos</th>
                                        <th style="width: 20%;">Calificación (1 - 10)</th>
                                        <th style="width: 45%;">Observaciones / Comentarios</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($estudiantes_query && $estudiantes_query->num_rows > 0): ?>
                                        <?php while($estudiante = $estudiantes_query->fetch_assoc()): 
                                            // Consultar si ya hay una nota guardada para este alumno en esta tarea
                                            $stmt_nota = $conexion->prepare("SELECT nota, observaciones FROM calificaciones WHERE id_estudiante = ? AND id_tarea = ?");
                                            $stmt_nota->bind_param("ii", $estudiante['id_estudiante'], $id_tarea_seleccionada);
                                            $stmt_nota->execute();
                                            $res_nota = $stmt_nota->get_result();
                                            
                                            $nota_actual = "";
                                            $obs_actual = "";
                                            if ($res_nota->num_rows > 0) {
                                                $datos_nota = $res_nota->fetch_assoc();
                                                $nota_actual = $datos_nota['nota'];
                                                $obs_actual = $datos_nota['observaciones'];
                                            }
                                            $stmt_nota->close();
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($estudiante['apellidos'] . ", " . $estudiante['nombres']); ?></strong></td>
                                                <td>
                                                    <input type="number" name="nota[<?php echo $estudiante['id_estudiante']; ?>]" 
                                                           value="<?php echo $nota_actual; ?>" 
                                                           min="0" max="10" step="0.01" 
                                                           placeholder="0.00" 
                                                           style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px; text-align: center; font-weight: bold; color: #1a365d;">
                                                </td>
                                                <td>
                                                    <input type="text" name="observaciones[<?php echo $estudiante['id_estudiante']; ?>]" 
                                                           value="<?php echo htmlspecialchars($obs_actual); ?>" 
                                                           placeholder="Ej: Felicitaciones, excelente esfuerzo..." 
                                                           style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="tabla-vacia">No hay estudiantes matriculados en este curso.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($estudiantes_query && $estudiantes_query->num_rows > 0): ?>
                            <div style="margin-top: 20px; text-align: right;">
                                <button type="submit" class="btn-guardar" style="max-width: 250px;">Asentar Calificaciones</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>