<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}

include 'conexion.php';

$mensaje = "";
$id_curso_seleccionado = isset($_POST['id_curso']) ? intval($_POST['id_curso']) : 0;
$fecha_seleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// --- LÓGICA: GUARDAR LA ASISTENCIA EN BLOQUE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_asistencia'])) {
    $estados = $_POST['estado']; // Es un arreglo con los estados de cada alumno

    if (!empty($estados)) {
        foreach ($estados as $id_estudiante => $estado_valor) {
            // Verificar si ya existe asistencia para este alumno en esta fecha
            $stmt_check = $conexion->prepare("SELECT id_asistencia FROM asistencias WHERE id_estudiante = ? AND fecha = ?");
            $stmt_check->bind_param("is", $id_estudiante, $fecha_seleccionada);
            $stmt_check->execute();
            $resultado_check = $stmt_check->get_result();

            if ($resultado_check->num_rows > 0) {
                // Si ya existe, se actualiza el registro existente
                $asist = $resultado_check->fetch_assoc();
                $stmt_up = $conexion->prepare("UPDATE asistencias SET estado = ? WHERE id_asistencia = ?");
                $stmt_up->bind_param("si", $estado_valor, $asist['id_asistencia']);
                $stmt_up->execute();
                $stmt_up->close();
            } else {
                // Si no existe, se inserta una nueva fila
                $stmt_ins = $conexion->prepare("INSERT INTO asistencias (fecha, estado, id_estudiante) VALUES (?, ?, ?)");
                $stmt_ins->bind_param("ssi", $fecha_seleccionada, $estado_valor, $id_estudiante);
                $stmt_ins->execute();
                $stmt_ins->close();
            }
            $stmt_check->close();
        }
        $mensaje = "<div class='alerta exito'>Control de asistencia guardado correctamente para el día " . $fecha_seleccionada . ".</div>";
    }
}

// Consultar cursos disponibles
$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");

// Consultar estudiantes si hay un curso seleccionado
$estudiantes_query = null;
if ($id_curso_seleccionado > 0) {
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
    <title>SIGE - Control de Asistencia</title>
    <link rel="stylesheet" href="estilos_panel.css">
    <style>
        /* Estilos específicos para las opciones de asistencia */
        .opciones-asistencia {
            display: flex;
            gap: 15px;
        }
        .opcion-radio {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .opcion-radio input {
            width: auto !important;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <!-- Sidebar Reutilizado -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>SIGE Escuela</h2>
                <p>Docente: <?php echo htmlspecialchars($_SESSION['nombre_docente']); ?></p>
            </div>
            <nav class="sidebar-menu">
                <a href="estudiantes.php">📂 Estudiantes</a>
                <a href="asistencias.php" class="activo">📅 Control Asistencia</a>
                <a href="tareas.php">📝 Gestión de Tareas</a>
                <a href="calificaciones.php">📊 Calificaciones</a>
                <a href="reportes.php">🖨️ Reportes PDF</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a>
            </div>
        </aside>

        <!-- Contenido Central -->
        <main class="contenido-principal">
            <header class="topbar">
                <h1>Control de Asistencia Diaria</h1>
                <p>Seleccione el curso y registre la puntualidad de los alumnos</p>
            </header>

            <?php echo $mensaje; ?>

            <!-- Filtro de Curso y Fecha -->
            <section class="seccion-tarjeta">
                <h3>Filtros de Búsqueda</h3>
                <form action="asistencias.php" method="POST" class="formulario-horizontal">
                    <div class="campo">
                        <label>Seleccionar Curso</label>
                        <select name="id_curso" required onchange="this.form.submit()">
                            <option value="">Seleccione...</option>
                            <?php while($curso = $cursos_query->fetch_assoc()): ?>
                                <option value="<?php echo $curso['id_curso']; ?>" <?php if($id_curso_seleccionado == $curso['id_curso']) echo 'selected'; ?>>
                                    <?php echo $curso['nombre_curso'] . " '" . $curso['paralelo'] . "'"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label>Fecha del Registro</label>
                        <input type="date" name="fecha" value="<?php echo $fecha_seleccionada; ?>" required onchange="this.form.submit()">
                    </div>
                </form>
            </section>

            <!-- Formulario de Marcación de Asistencias -->
            <?php if ($id_curso_seleccionado > 0): ?>
                <section class="seccion-tarjeta">
                    <h3>Registro de Alumnos</h3>
                    <form action="asistencias.php" method="POST">
                        <input type="hidden" name="id_curso" value="<?php echo $id_curso_seleccionado; ?>">
                        <input type="hidden" name="fecha" value="<?php echo $fecha_seleccionada; ?>">
                        <input type="hidden" name="guardar_asistencia" value="1">

                        <div class="tabla-responsiva">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nómina de Alumnos</th>
                                        <th style="width: 50%;">Estado de Asistencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($estudiantes_query && $estudiantes_query->num_rows > 0): ?>
                                        <?php while($estudiante = $estudiantes_query->fetch_assoc()): 
                                            // Consultar el estado guardado actualmente si ya se registró antes
                                            $stmt_status = $conexion->prepare("SELECT estado FROM asistencias WHERE id_estudiante = ? AND fecha = ?");
                                            $stmt_status->bind_param("is", $estudiante['id_estudiante'], $fecha_seleccionada);
                                            $stmt_status->execute();
                                            $res_status = $stmt_status->get_result();
                                            $estado_actual = ($res_status->num_rows > 0) ? $res_status->fetch_assoc()['estado'] : 'Asiste';
                                            $stmt_status->close();
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($estudiante['apellidos'] . ", " . $estudiante['nombres']); ?></strong></td>
                                                <td>
                                                    <div class="opciones-asistencia">
                                                        <label class="opcion-radio" style="color: #2f855a;">
                                                            <input type="radio" name="estado[<?php echo $estudiante['id_estudiante']; ?>]" value="Asiste" <?php if($estado_actual == 'Asiste') echo 'checked'; ?>> Presente
                                                        </label>
                                                        <label class="opcion-radio" style="color: #c53030;">
                                                            <input type="radio" name="estado[<?php echo $estudiante['id_estudiante']; ?>]" value="Falta" <?php if($estado_actual == 'Falta') echo 'checked'; ?>> Falta
                                                        </label>
                                                        <label class="opcion-radio" style="color: #b7791f;">
                                                            <input type="radio" name="estado[<?php echo $estudiante['id_estudiante']; ?>]" value="Justificado" <?php if($estado_actual == 'Justificado') echo 'checked'; ?>> Justificado
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="tabla-vacia">No hay estudiantes matriculados en este curso.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($estudiantes_query && $estudiantes_query->num_rows > 0): ?>
                            <div style="margin-top: 20px; text-align: right;">
                                <button type="submit" class="btn-guardar" style="max-width: 200px;">Guardar Asistencias</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>