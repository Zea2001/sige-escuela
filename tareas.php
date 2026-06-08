<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}

include 'conexion.php';

$mensaje = "";

// --- LÓGICA 1: REGISTRAR UNA NUEVA TAREA CON DESCRIPCIÓN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_tarea'])) {
    $titulo_tarea  = trim($_POST['titulo_tarea']);
    $descripcion   = trim($_POST['descripcion']); // Nuevo campo enriquecido
    $fecha_envio   = $_POST['fecha_envio'];
    $fecha_limite  = $_POST['fecha_limite'];
    $id_curso      = $_POST['id_curso'];

    if (!empty($titulo_tarea) && !empty($fecha_envio) && !empty($fecha_limite) && !empty($id_curso)) {
        
        // Modificamos el INSERT para incluir la descripción
        $stmt = $conexion->prepare("INSERT INTO tareas (titulo_tarea, descripcion, fecha_envio, fecha_limite, id_curso) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $titulo_tarea, $descripcion, $fecha_envio, $fecha_limite, $id_curso);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alerta exito'>Tarea / Actividad programada con éxito.</div>";
        } else {
            $mensaje = "<div class='alerta error'>Error al registrar la tarea en la base de datos.</div>";
        }
        $stmt->close();
    }
}

// --- LÓGICA 2: ELIMINAR UNA TAREA ---
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt_del = $conexion->prepare("DELETE FROM tareas WHERE id_tarea = ?");
    $stmt_del->bind_param("i", $id_eliminar);
    if ($stmt_del->execute()) {
        $mensaje = "<div class='alerta exito'>Tarea eliminada correctamente.</div>";
    } else {
        $mensaje = "<div class='alerta error'>Error al eliminar la tarea.</div>";
    }
    $stmt_del->close();
}

$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");

// Consultamos también la descripción en la tabla
$sql_tareas = "SELECT t.id_tarea, t.titulo_tarea, t.descripcion, t.fecha_envio, t.fecha_limite, c.nombre_curso, c.paralelo 
               FROM tareas t
               INNER JOIN cursos c ON t.id_curso = c.id_curso
               ORDER BY t.fecha_limite ASC";
$tareas_query = $conexion->query($sql_tareas);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGE - Gestión de Tareas</title>
    <link rel="stylesheet" href="estilos_panel.css">
    
    <script src="https://js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
    <script type="text/javascript">
        // Transforma el textarea tradicional en un bloc de notas con formato
        bkLib.onDomLoaded(function() {
            new nicEditor({iconsPath : 'https://js.nicedit.com/nicEditorIcons.gif', buttonList : ['bold','italic','underline','ol','ul']}).panelInstance('descripcion_tarea');
        });
    </script>
    
    <style>
        /* Ajustes de diseño para el editor */
        .campo-bloque {
            width: 100%;
            margin-top: 15px;
        }
        .campo-bloque label {
            display: block;
            font-size: 0.85em;
            font-weight: 600;
            margin-bottom: 6px;
            color: #4a5568;
        }
        /* Estilo contenedor del editor para que combine con tu panel */
        .nicEdit-main {
            background-color: white !important;
            min-height: 120px;
        }
    </style>
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
                <a href="tareas.php" class="activo">📝 Gestión de Tareas</a>
                <a href="calificaciones.php">📊 Calificaciones</a>
                <a href="reportes.php">🖨️ Reportes PDF</a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-principal">
            <header class="topbar">
                <h1>Planificación y Gestión de Tareas</h1>
                <p>Asigne deberes, proyectos o talleres y defina los plazos de entrega</p>
            </header>

            <?php echo $mensaje; ?>

            <section class="seccion-tarjeta">
                <h3>Programar Nueva Actividad Evaluativa</h3>
                <form action="tareas.php" method="POST">
                    <input type="hidden" name="crear_tarea" value="1">
                    
                    <div class="formulario-horizontal">
                        <div class="campo" style="min-width: 250px;">
                            <label>Título de la Tarea</label>
                            <input type="text" name="titulo_tarea" required placeholder="Ej: Taller de Matemáticas">
                        </div>
                        
                        <div class="campo">
                            <label>Curso Destino</label>
                            <select name="id_curso" required>
                                <option value="">Seleccione un curso...</option>
                                <?php 
                                $cursos_query->data_seek(0); 
                                while($curso = $cursos_query->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $curso['id_curso']; ?>">
                                        <?php echo $curso['nombre_curso'] . " '" . $curso['paralelo'] . "'"; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="campo">
                            <label>Fecha de Envío</label>
                            <input type="date" name="fecha_envio" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="campo">
                            <label>Fecha de Entrega</label>
                            <input type="date" name="fecha_limite" required>
                        </div>
                    </div>

                    <div class="campo-bloque">
                        <label>Descripción detallada de la tarea / Instrucciones</label>
                        <textarea id="descripcion_tarea" name="descripcion" style="width: 100%; height: 120px;" placeholder="Escriba los ejercicios o instrucciones aquí..."></textarea>
                    </div>
                    
                    <div style="margin-top: 15px; text-align: right;">
                        <button type="submit" class="btn-guardar" style="max-width: 200px;">Programar Tarea</button>
                    </div>
                </form>
            </section>

            <section class="seccion-tarjeta">
                <h3>Cronograma de Tareas Registradas</h3>
                <div class="tabla-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título y Detalles</th>
                                <th>Curso / Nivel</th>
                                <th>F. Envío</th>
                                <th>F. Límite</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tareas_query && $tareas_query->num_rows > 0): ?>
                                <?php while($tarea = $tareas_query->fetch_assoc()): 
                                    $f_envio = date("d/m/Y", strtotime($tarea['fecha_envio']));
                                    $f_limite = date("d/m/Y", strtotime($tarea['fecha_limite']));
                                ?>
                                    <tr>
                                        <td><?php echo $tarea['id_tarea']; ?></td>
                                        <td>
                                            <div style="font-size: 1.05em; color: #1a365d;"><strong><?php echo htmlspecialchars($tarea['titulo_tarea']); ?></strong></div>
                                            <?php if(!empty($tarea['descripcion'])): ?>
                                                <div style="font-size: 0.88em; color: #4a5568; margin-top: 5px; background: #f7fafc; padding: 8px; border-radius: 4px; border-left: 3px solid #cbd5e0;">
                                                    <?php echo $tarea['descripcion']; // Al ser HTML guardado por el editor, se renderiza con sus negritas y formatos ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($tarea['nombre_curso'] . " '" . $tarea['paralelo'] . "'"); ?></td>
                                        <td><?php echo $f_envio; ?></td>
                                        <td><span class="badge-paralelo" style="background-color: #feebc8; color: #c05621; border: 1px solid #fbd38d;"><?php echo $f_limite; ?></span></td>
                                        
                                        <td style="text-align: center;">
                                            <div class="grupo-botones-tabla">
                                                <a href="tareas.php?eliminar=<?php echo $tarea['id_tarea']; ?>" class="btn-accion btn-eliminar" title="Eliminar Tarea" onclick="return confirm('¿Seguro que deseas borrar esta tarea? Se eliminarán también todas las notas asociadas.');">🗑️ Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="tabla-vacia">No hay tareas o actividades programadas en el sistema.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

</body>
</html>