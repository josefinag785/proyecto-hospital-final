
<?php

require_once 'conexion.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $cedula = $_POST["cedula"];
    $correo = $_POST["correo"];
    $contrasena = $_POST["contrasena"];
    $confirmar = $_POST["confirmar_contrasena"];

    // Comprobar que las contraseñas coincidan
    if ($contrasena != $confirmar) {

        $mensaje = "Las contraseñas no coinciden.";

    } else {

        // Buscar si la cédula y el correo existen en Persona
        $sqlPersona = "SELECT * FROM Persona
                       WHERE Cedula = '$cedula'
                       AND correo = '$correo'";

        $resultadoPersona = $con->query($sqlPersona);

        if ($resultadoPersona->num_rows == 0) {

            $mensaje = "La cédula y el correo no corresponden a una persona registrada.";

        } else {

            // Comprobar si ya existe un usuario con esa cédula
            $sqlUsuario = "SELECT * FROM Usuario
                           WHERE Cedula = '$cedula'";

            $resultadoUsuario = $con->query($sqlUsuario);

            if ($resultadoUsuario->num_rows > 0) {

                $mensaje = "Ya existe un usuario con esa cédula.";

            } else {

                // Encriptar la contraseña
                $contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

                // Crear el usuario como pendiente
                $sql = "INSERT INTO Usuario
                        (Cedula, Contraseña, Rol, Estado)
                        VALUES
                        ('$cedula', '$contrasena', 'Pendiente', 'Pendiente')";

                if ($con->query($sql) === TRUE) {

                    $mensaje = "Registro realizado correctamente. Un administrador debe aprobar su cuenta.";

                } else {

                    $mensaje = "Error al registrar el usuario: " . $con->error;

                }
            }
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

            <a class="active" href="registrarse.php">Registrarse</a>

            <a href="index.html">Volver Atras</a>

        </div>

    </div>

</header>

<main>

    <section class="hero">

        <div class="registro">

            <h2>Registrarse</h2>

            <p class="texto-registro">
                Ingrese sus datos para crear una cuenta.
            </p>

            <?php

            if ($mensaje != "") {

                echo "<p>" . $mensaje . "</p>";

            }

            ?>

            <form class="form-registro" action="registrarse.php" method="post">

                <div class="fila">

                    <div class="campo">

                        <label for="cedula">

                            <i class="fa-solid fa-id-card"></i>

                            Cédula

                        </label>

                        <input type="text"
                               id="cedula"
                               name="cedula"
                               required>

                    </div>

                    <div class="campo">

                        <label for="correo">

                            <i class="fa-solid fa-envelope"></i>

                            Correo electrónico

                        </label>

                        <input type="email"
                               id="correo"
                               name="correo"
                               required>

                    </div>

                </div>

                <div class="fila">

                    <div class="campo">

                        <label for="contrasena">

                            <i class="fa-solid fa-lock"></i>

                            Contraseña

                        </label>

                        <input type="password"
                               id="contrasena"
                               name="contrasena"
                               required>

                    </div>

                    <div class="campo">

                        <label for="confirmar_contrasena">

                            <i class="fa-solid fa-lock"></i>

                            Confirmar contraseña

                        </label>

                        <input type="password"
                               id="confirmar_contrasena"
                               name="confirmar_contrasena"
                               required>

                    </div>

                </div>

                <button type="submit">
                    Registrarse
                </button>

                <p>

                    ¿Ya tiene una cuenta?

                    <a href="login.php">
                        Iniciar Sesión
                    </a>

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

