<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once 'conexion.php';

$con->set_charset('utf8mb4');

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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
            FROM Usuario
            WHERE Cedula = '$cedula_sql'";

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

            // Guardar qué usuario acaba de ingresar.
            $_SESSION = [];

            session_regenerate_id(true);

            $_SESSION['id_usuario'] = (int) $usuario['ID_Usuario'];
            $_SESSION['cedula'] = $usuario['Cedula'];
            $_SESSION['rol'] = $usuario['Rol'];

            if ($usuario['Rol'] == 'Medico') {

               header('Location: /prueba/php/MEDICO/inicio_medico.php');

            } else {

                header('Location: /prueba/panelprincipal.html');

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
                echo "<p>" . $mensaje . "</p>";
            }
            ?>

            <form class="form-login" action="login.php" method="post">

                <label for="username">

                    <i class="fa-solid fa-user"></i>

                    Usuario o correo electrónico

                </label>

                <input type="text"
                       id="username"
                       name="username"
                       required>

                <br><br>

                <label for="password">

                    <i class="fa-solid fa-lock"></i>

                    Contraseña

                </label>

                <input type="password"
                       id="password"
                       name="password"
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
                    ¿No tiene una cuenta?
                    <a href="/prueba/php/registrarse.php">Registrarse</a>
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

