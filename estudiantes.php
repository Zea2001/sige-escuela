<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}

include 'conexion.php';

$mensaje = "";

// --- LOGICA DE ELIMINACIÓN DIRECTA ---
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt_del = $conexion->prepare("DELETE FROM estudiantes WHERE id_estudiante = ?");
    $stmt_del->bind_param("i", $id_eliminar);
    if ($stmt_del->execute()) {
        $mensaje = "<div class='alerta exito'>Estudiante eliminado correctamente.</div>";
    } else {
        $mensaje = "<div class='alerta error'>Error al intentar eliminar el registro.</div>";
    }
    $stmt_del->close();
}

// --- LÓGICA: REGISTRAR UN NUEVO ESTUDIANTE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_estudiante'])) {
    $nombres   = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $cedula    = trim($_POST['cedula']);
    $id_curso  = $_POST['id_curso'];

    if (!empty($nombres) && !empty($apellidos) && !empty($cedula) && !empty($id_curso)) {
        $stmt = $conexion->prepare("INSERT INTO estudiantes (nombres, apellidos, cedula, id_curso) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $nombres, $apellidos, $cedula, $id_curso);
        
        if ($stmt->execute()) {
            $mensaje = "<div class='alerta exito'>Estudiante registrado correctamente.</div>";
        } else {
            $mensaje = "<div class='alerta error'>Error al registrar: La cédula ya existe.</div>";
        }
        $stmt->close();
    }
}

$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");

$sql_estudiantes = "SELECT e.id_estudiante, e.nombres, e.apellidos, e.cedula, c.nombre_curso, c.paralelo 
                    FROM estudiantes e 
                    INNER JOIN cursos c ON e.id_curso = c.id_curso
                    ORDER BY e.apellidos ASC";
$estudiantes_query = $conexion->query($sql_estudiantes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGE - Panel de Control</title>
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
                <a href="estudiantes.php" class="activo">📂 Estudiantes</a>
                <a href="asistencias.php">📅 Control Asistencia</a>
                <a href="tareas.php">📝 Gestión de Tareas</a>
                <a href="calificaciones.php">📊 Calificaciones</a>
                <a href="reportes.php">🖨️ Reportes PDF</a> 
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-principal">
            <header class="topbar">
                <h1>Gestión de Estudiantes</h1>
                <p>Administración y matrícula de alumnos en el sistema</p>
            </header>

            <?php echo $mensaje; ?>

            <section class="seccion-tarjeta">
                <h3>Agregar Nuevo Estudiante</h3>
                <form action="estudiantes.php" method="POST" class="formulario-horizontal">
                    <input type="hidden" name="registrar_estudiante" value="1">
                    
                    <div class="campo">
                        <label>Nombres</label>
                        <input type="text" name="nombres" required placeholder="Ej: Juan Carlos">
                    </div>
                    <div class="campo">
                        <label>Apellidos</label>
                        <input type="text" name="apellidos" required placeholder="Ej: Pérez Mora">
                    </div>
                    <div class="campo">
                        <label>Cédula</label>
                        <input type="text" name="cedula" required placeholder="Ej: 0912345678">
                    </div>
                    <div class="campo">
                        <label>Curso Asignado</label>
                        <select name="id_curso" required>
                            <option value="">Seleccione un curso...</option>
                            <?php while($curso = $cursos_query->fetch_assoc()): ?>
                                <option value="<?php echo $curso['id_curso']; ?>">
                                    <?php echo $curso['nombre_curso'] . " '" . $curso['paralelo'] . "'"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="campo boton-contenedor">
                        <button type="submit" class="btn-guardar">Matricular</button>
                    </div>
                </form>
            </section>

            <section class="seccion-tarjeta">
                <h3>Nómina de Estudiantes Registrados</h3>
                <div class="tabla-responsiva">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Apellidos y Nombres</th>
                                <th>Cédula</th>
                                <th>Curso / Nivel</th>
                                <th>Paralelo</th>
                                <th style="text-align: center;">Acciones</th> </tr>
                        </thead>
                        <tbody>
                            <?php if ($estudiantes_query->num_rows > 0): ?>
                                <?php while($estudiante = $estudiantes_query->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $estudiante['id_estudiante']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($estudiante['apellidos'] . ", " . $estudiante['nombres']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($estudiante['cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($estudiante['nombre_curso']); ?></td>
                                        <td><span class="badge-paralelo"><?php echo htmlspecialchars($estudiante['paralelo']); ?></span></td>
                                        
                                        <td style="text-align: center;">
                                            <div class="grupo-botones-tabla">
                                                <a href="editar_estudiante.php?id=<?php echo $estudiante['id_estudiante']; ?>" class="btn-accion btn-editar" title="Modificar Datos">✏️ Editar</a>
                                                <a href="estudiantes.php?eliminar=<?php echo $estudiante['id_estudiante']; ?>" class="btn-accion btn-eliminar" title="Eliminar Estudiante" onclick="return confirm('¿Está seguro de que desea eliminar a este estudiante? Esta acción borrará también sus notas y asistencias.');">🗑️ Eliminar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="tabla-vacia">No hay estudiantes matriculados todavía.</td>
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