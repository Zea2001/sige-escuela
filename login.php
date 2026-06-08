<?php
session_start();
include 'conexion.php';

$error_mensaje = ""; // Variable para almacenar el error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo']);
    $password = trim($_POST['password']);

    if (!empty($correo) && !empty($password)) {
        
        // CORRECCIÓN: Volvemos a apuntar a la tabla 'usuarios' y sus columnas reales
        $stmt = $conexion->prepare("SELECT id_usuario, nombre_completo, password_hash FROM usuarios WHERE correo = ?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();
            
            // Verificar la contraseña encriptada
            if (password_verify($password, $usuario['password_hash'])) {
                // CORRECCIÓN: Guardamos las variables de sesión tal como las lee estudiantes.php
                $_SESSION['id_docente'] = $usuario['id_usuario'];
                $_SESSION['nombre_docente'] = $usuario['nombre_completo'];
                
                header("Location: estudiantes.php");
                exit();
            } else {
                $error_mensaje = "Contraseña incorrecta.";
            }
        } else {
            $error_mensaje = "El correo electrónico no está registrado.";
        }
        $stmt->close();
    } else {
        $error_mensaje = "Por favor, llene todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGE Escuela - Iniciar Sesión</title>
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #edf2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            margin-bottom: 24px;
            color: #1a365d;
            text-align: center;
        }
        .campo {
            margin-bottom: 20px;
        }
        .campo label {
            display: block;
            margin-bottom: 6px;
            color: #4a5568;
            font-size: 0.9em;
            font-weight: 600;
        }
        .campo input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e0;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #2b6cb0;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-login:hover {
            background-color: #1a365d;
        }

        /* CUADRO DE ALERTA ROJA INTEGRADO */
        .alerta-login {
            background-color: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
            font-weight: 500;
            transition: opacity 0.5s ease;
            opacity: 1;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>SIGE Escuela</h2>

        <?php if (!empty($error_mensaje)): ?>
            <div class="alerta-login" id="caja-error">
                ⚠️ <?php echo $error_mensaje; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="campo">
                <label>Correo Electrónico</label>
                <input type="email" name="correo" required placeholder="ejemplo@correo.com" value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
            </div>
            
            <div class="campo">
                <label>Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-login">Iniciar Sesión</button>
        </form>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            const errorBox = document.getElementById('caja-error');
            if (errorBox) {
                setTimeout(() => {
                    errorBox.style.opacity = '0'; // CORRECCIÓN: Agregado el punto faltante (.style)
                    
                    setTimeout(() => {
                        errorBox.style.display = 'none';
                    }, 500); 
                }, 4000); // 4 segundos en pantalla antes de desaparecer
            }
        });
    </script>

</body>
</html>