<?php
session_start();

if (!isset($_SESSION['id_docente'])) {
    header("Location: index.html");
    exit();
}

include 'conexion.php';

$mensaje = "";

if (!isset($_GET['id'])) {
    header("Location: estudiantes.php");
    exit();
}

$id_estudiante = intval($_GET['id']);

// Actualizar los datos modificados
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_estudiante'])) {
    $nombres   = trim($_POST['nombres']);
    $apellidos = trim($_POST['apellidos']);
    $cedula    = trim($_POST['cedula']);
    $id_curso  = $_POST['id_curso'];

    if (!empty($nombres) && !empty($apellidos) && !empty($cedula) && !empty($id_curso)) {
        $stmt_update = $conexion->prepare("UPDATE estudiantes SET nombres = ?, apellidos = ?, cedula = ?, id_curso = ? WHERE id_estudiante = ?");
        $stmt_update->bind_param("sssii", $nombres, $apellidos, $cedula, $id_curso, $id_estudiante);
        
        if ($stmt_update->execute()) {
            header("Location: estudiantes.php");
            exit();
        } else {
            $mensaje = "<div class='alerta error'>Error al actualizar: La cédula ya pertenece a otro estudiante.</div>";
        }
        $stmt_update->close();
    }
}

// Obtener datos actuales del estudiante
$stmt_est = $conexion->prepare("SELECT * FROM estudiantes WHERE id_estudiante = ?");
$stmt_est->bind_param("i", $id_estudiante);
$stmt_est->execute();
$estudiante = $stmt_est->get_result()->fetch_assoc();
$stmt_est->close();

$cursos_query = $conexion->query("SELECT id_curso, nombre_curso, paralelo FROM cursos");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGE - Editar Estudiante</title>
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
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="btn-logout-nuevo">Cerrar Sesión</a>
            </div>
        </aside>

        <main class="contenido-principal">
            <header class="topbar">
                <h1>Modificar Información</h1>
                <p>Corrija los datos del estudiante seleccionado</p>
            </header>

            <?php echo $mensaje; ?>

            <section class="seccion-tarjeta" style="max-width: 600px;">
                <h3>Formulario de Edición</h3>
                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="actualizar_estudiante" value="1">
                    
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.9em;">Nombres</label>
                        <input type="text" name="nombres" value="<?php echo htmlspecialchars($estudiante['nombres']); ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:4px;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.9em;">Apellidos</label>
                        <input type="text" name="apellidos" value="<?php echo htmlspecialchars($estudiante['apellidos']); ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:4px;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.9em;">Cédula</label>
                        <input type="text" name="cedula" value="<?php echo htmlspecialchars($estudiante['cedula']); ?>" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:4px;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:0.9em;">Curso Asignado</label>
                        <select name="id_curso" required style="width:100%; padding:10px; border:1px solid #cbd5e0; border-radius:4px; background-color:#f8f9fa;">
                            <?php while($curso = $cursos_query->fetch_assoc()): ?>
                                <option value="<?php echo $curso['id_curso']; ?>" <?php if($curso['id_curso'] == $estudiante['id_curso']) echo 'selected'; ?>>
                                    <?php echo $curso['nombre_curso'] . " '" . $curso['paralelo'] . "'"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" class="btn-guardar" style="flex:1;">Guardar Cambios</button>
                        <a href="estudiantes.php" style="flex:1; text-align:center; background-color:#e2e8f0; color:#4a5568; padding:10px; border-radius:4px; text-decoration:none; font-weight:600; font-size:0.9em;">Cancelar</a>
                    </div>
                </form>
            </section>
        </main>
    </div>

</body>
</html>