<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre   = trim($_POST['nombre']);
    $correo   = trim($_POST['correo']);
    $password = $_POST['password'];

    // Validar que los campos no estén vacíos
    if (!empty($nombre) && !empty($correo) && !empty($password)) {
        
        // Encriptar la contraseña de forma segura antes de guardarla
        $password_encriptada = password_hash($password, PASSWORD_BCRYPT);

        // Preparar la consulta SQL para evitar Inyección SQL
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre_completo, correo, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $correo, $password_encriptada);

        if ($stmt->execute()) {
            echo "<script>
                    alert('Usuario registrado con éxito. Ya puedes iniciar sesión.');
                    window.location.href = 'index.html';
                  </script>";
        } else {
            echo "<script>
                    alert('Error: El correo ya se encuentra registrado.');
                    window.location.href = 'index.html';
                  </script>";
        }
        $stmt->close();
    }
}
$conexion->close();
?>