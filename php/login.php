<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$mensaje = '';

if (!isset($_SESSION['token_login'])) {
    $_SESSION['token_login'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_login'];

if (isset($_GET['mensaje']) && $_GET['mensaje'] == 'cuenta_activada') {
    $mensaje = 'Su cuenta fue activada. Ingrese con su cédula y la contraseña que eligió.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['token']) || !is_string($_POST['token'])
        || !hash_equals($token, $_POST['token'])) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $username = '';
    $password = '';
    if (isset($_POST['username']) && is_string($_POST['username'])) {
        $username = trim($_POST['username']);
    }
    if (isset($_POST['password']) && is_string($_POST['password'])) {
        $password = $_POST['password'];
    }

    $cedula_sql = $con->real_escape_string($username);
    $sql = "SELECT ID_Usuario, Cedula, Contraseña, Rol, Estado
            FROM Usuario WHERE Cedula = '$cedula_sql'";
    $resultado = $con->query($sql);

    if (!$resultado) {
        error_log($con->error);
        $mensaje = 'No se pudo iniciar sesión. Intente nuevamente.';
    } elseif ($resultado->num_rows == 0) {
        $mensaje = 'La cédula o la contraseña son incorrectas.';
    } else {
        $usuario = $resultado->fetch_assoc();
        if (!password_verify($password, $usuario['Contraseña'])) {
            $mensaje = 'La cédula o la contraseña son incorrectas.';
        } elseif ($usuario['Estado'] != 'Activo') {
            $mensaje = 'Su cuenta todavía no está activa.';
        } else {
            // Recordar el QR si el paciente llegó desde ese enlace.
            $codigo = '';
            if (isset($_SESSION['codigo_documentacion'])
                && is_string($_SESSION['codigo_documentacion'])
                && preg_match('/^[a-f0-9]{64}$/', $_SESSION['codigo_documentacion'])) {
                $codigo = $_SESSION['codigo_documentacion'];
            }

            $archivo_qr = filter_var($_SESSION['archivo_documentacion'] ?? 0, FILTER_VALIDATE_INT);

            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['id_usuario'] = (int) $usuario['ID_Usuario'];
            $_SESSION['cedula'] = $usuario['Cedula'];
            $_SESSION['rol'] = $usuario['Rol'];

            if ($usuario['Rol'] == 'Conductor') {
                header('Location: /prueba/php/CONDUCTOR/inicio_conductor.php');
            } elseif ($usuario['Rol'] == 'Paciente' && $archivo_qr > 0) {
                header('Location: /prueba/php/PACIENTE/documentacion_paciente.php?archivo=' . $archivo_qr);
            } elseif ($codigo != '') {
                header('Location: /prueba/php/PACIENTE/documentacion_paciente.php?codigo=' . urlencode($codigo));
            } elseif ($usuario['Rol'] == 'Paciente') {
                header('Location: /prueba/php/PACIENTE/documentacion_paciente.php');
            } elseif ($usuario['Rol'] == 'Medico') {
                header('Location: /prueba/php/MEDICO/inicio_medico.php');
            } else {
                header('Location: /prueba/panelprincipal.php');
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOSPITAL DE CLINICA</title>

    <!-- Normalize CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome (iconos) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/prueba/css/style.css">
</head>

<body>

<header>

    <div class="encabezado">

        <img src="/prueba/img/LOGO PROYECTO DHC.png"
             alt="Logo"
             class="logo">

        <h1>HOSPITAL DE CLINICA</h1>

    </div>

    <div class="topnav">

        <div class="nav-links">

            <a class="active" href="/prueba/php/login.php">Ingresar</a>

            <a href="/prueba/index.html">Volver Atras</a>

        </div>

    </div>

</header>

<main>

    <section class="hero">

        <div class="login">

            <h2>Iniciar Sesión</h2>

            <p class="texto-login">
                Ingrese sus datos para acceder a la plataforma.
            </p>

            <?php
            if ($mensaje != "") {
                echo "<p>" . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . "</p>";
            }
            ?>

            <form class="form-login" action="login.php" method="post">

                <input type="hidden" name="token" value="<?php echo $token; ?>">

                <label for="username">

                    <i class="fa-solid fa-user"></i>

                    Cédula

                </label>

                <input type="text"
                       id="username"
                       name="username"
                       maxlength="20"
                       autocomplete="username"
                       required>

                <br><br>

                <label for="password">

                    <i class="fa-solid fa-lock"></i>

                    Contraseña

                </label>

                <input type="password"
                       id="password"
                       name="password"
                       autocomplete="current-password"
                       required>

                <br><br>

                <button type="submit">
                    Iniciar Sesión
                </button>

                <p>
                    <a href="recuperar_contraseña.php">
                        ¿Olvidaste tu contraseña?
                    </a>
                </p>

                <p>
                    ¿Es su primer ingreso como paciente?
                    <a href="/prueba/php/PACIENTE/activar_cuenta.php">Activar mi cuenta</a>
                </p>

                <p>
                    Personal del hospital:
                    <a href="/prueba/php/registrarse.php">Solicitar acceso</a>
                </p>

            </form>

        </div>

    </section>

</main>

<footer>

    <p>
        &copy; 2026 Hospital de Clínica.
        Todos los derechos reservados a DHC.
    </p>

</footer>

</body>

</html>


<?php
$con->close();
?>
