<?php

require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $cedula = $_POST['cedula'];
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $nacimiento = $_POST['nacimiento'];
    $direccion = $_POST['direccion'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];

    $sql = "INSERT INTO Persona (Cedula, Nombre, Apellido, Fecha_Nacimiento, Direccion, Correo, Telefono)
    VALUES ('$cedula', '$nombre', '$apellido', '$nacimiento', '$direccion', '$correo', '$telefono')";

    $con->query($sql);

    $sql = "INSERT INTO Paciente (Cedula)
    VALUES ('$cedula')";

    $con->query($sql);

    echo "Paciente registrado correctamente";
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nuevo paciente - Hospital de Clínicas</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/prueba/css/style.css">
</head>

<body>

    <header>
        <div class="encabezado">

            <img src="/prueba/img/LOGO PROYECTO DHC.png"
                alt="Logo Hospital de Clínicas"
                class="logo">

            <h1>HOSPITAL DE CLINICA</h1>

        </div>
    </header>


    <div class="perfil-contenedor">

        <aside class="menu-lateral">

            <h3>Menú</h3>

            <a href="panelprincipal.html">
                <i class="fa-solid fa-house"></i>
                Inicio
            </a>


            <details class="menu-desplegable" open>

                <summary>
                    <i class="fa-solid fa-user-plus"></i>
                    Pacientes
                </summary>

<div class="submenu">


                <a href="nuevo_paciente.php">

                    <i class="fa-solid fa-plus"></i>

                    Nuevo paciente

                </a>


                <a href="ver_paciente.php">

                    <i class="fa-solid fa-list"></i>

                    Ver pacientes

                </a>


            </div>

            </details>


            <details class="menu-desplegable">

                <summary>
                    <i class="fa-solid fa-truck-medical"></i>
                    Ambulancias
                </summary>

                <div class="submenu">

                    <a href="nuevo_traslado.php">
                        <i class="fa-solid fa-plus"></i>
                        Nuevo registro
                    </a>

                    <a href="registro_ambulancias.html">
                        <i class="fa-solid fa-list"></i>
                        Ver registros
                    </a>

                </div>

            </details>


            <a href="Encuestas.html">
                <i class="fa-solid fa-file-alt"></i>
                Encuestas
            </a>


            <a href="consultas.html">
                <i class="fa-solid fa-stethoscope"></i>
                Consultas
            </a>


            <a href="#">
                <i class="fa-solid fa-calendar-check"></i>
                Turnos
            </a>


            <a href="#">
                <i class="fa-solid fa-users"></i>
                Usuarios
            </a>


            <a href="index.html">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>

        </aside>


        <main class="contenido">

            <h2 class="titulo-panel">
                Nuevo paciente
            </h2>

            <p class="subtitulo-panel">
                Registrar los datos de una persona que todavía no está registrada.
            </p>


            <div class="tarjeta tarjeta-paciente">

                <h3>Datos del paciente</h3>

                <p>
                    Complete los siguientes datos para registrar al paciente.
                </p>


                <form action="nuevo_paciente.php" method="POST">


                    <div class="fila">

                        <div class="campo">

                            <label for="nombre">
                                Nombre
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ingrese el nombre"
                                required>

                        </div>


                        <div class="campo">

                            <label for="apellido">
                                Apellido
                            </label>

                            <input
                                type="text"
                                id="apellido"
                                name="apellido"
                                placeholder="Ingrese el apellido"
                                required>

                        </div>

                    </div>


                    <div class="fila">

                        <div class="campo">

                            <label for="cedula">
                                Cédula
                            </label>

                            <input
                                type="text"
                                id="cedula"
                                name="cedula"
                                placeholder="Ingrese la cédula"
                                inputmode="numeric"
                                required>

                        </div>


                        <div class="campo">

                            <label for="fecha-nacimiento">
                                Fecha de nacimiento
                            </label>

                            <input
                                type="date"
                                id="fecha-nacimiento"
                                name="nacimiento"
                                required>

                        </div>

                    </div>


                    <div class="fila">

                        <div class="campo">

                            <label for="telefono">
                                Teléfono
                            </label>

                            <input
                                type="tel"
                                id="telefono"
                                name="telefono"
                                placeholder="Ingrese el teléfono"
                                required>

                        </div>


                        <div class="campo">

                            <label for="direccion">
                                Dirección
                            </label>

                            <input
                                type="text"
                                id="direccion"
                                name="direccion"
                                placeholder="Ingrese la dirección"
                                required>

                        </div>

                    </div>


                    <div class="fila">

                        <div class="campo">

                            <label for="correo">
                                Correo
                            </label>

                            <input
                                type="text"
                                id="correo"
                                name="correo"
                                placeholder="Ingrese el correo"
                                required>

                        </div>

                    </div>


                    <div class="botones-consulta">

                        <button
                            type="reset"
                            class="boton-limpiar">

                            Limpiar

                        </button>


                        <button
                            type="submit"
                            class="boton-guardar">

                            Registrar paciente

                        </button>

                    </div>


                </form>

            </div>

        </main>

    </div>


    <footer>

        <p>
            &copy; 2026 Hospital de Clínicas.
            Todos los derechos reservados a DHC.
        </p>

    </footer>

</body>

</html>
```
