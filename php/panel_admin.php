
<?php

require_once 'conexion.php';

$mensaje = "";


/* ACEPTAR SOLICITUD */

if (isset($_POST["aceptar"])) {

    $id_usuario = $_POST["id_usuario"];
    $rol = $_POST["rol"];

    $sql = "UPDATE Usuario
            SET Rol = '$rol',
                Estado = 'Activo'
            WHERE ID_Usuario = '$id_usuario'";

    if ($con->query($sql) === TRUE) {

        $mensaje = "Solicitud aceptada correctamente.";

    } else {

        $mensaje = "Error al aceptar la solicitud: " . $con->error;

    }
}


/* BUSCAR SOLICITUDES PENDIENTES */

$sql = "SELECT Usuario.ID_Usuario,
               Usuario.Cedula,
               Persona.Nombre,
               Persona.Apellido,
               Persona.correo,
               Usuario.Rol,
               Usuario.Estado
        FROM Usuario
        INNER JOIN Persona
        ON Usuario.Cedula = Persona.Cedula
        WHERE Usuario.Estado = 'Pendiente'";

$resultado = $con->query($sql);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Panel Administrador - Hospital de Clínicas</title>

    <!-- Normalize CSS -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

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

</header>


<div class="perfil-contenedor">


    <!-- MENÚ LATERAL -->

    <aside class="menu-lateral">

        <h3>Menú</h3>


        <!-- INICIO -->

        <a href="panelprincipal.html">

            <i class="fa-solid fa-house"></i>

            Inicio

        </a>


        <!-- PACIENTES -->

        <details class="menu-desplegable">

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


        <!-- AMBULANCIAS -->

        <details class="menu-desplegable">

            <summary>

                <i class="fa-solid fa-truck-medical"></i>

                Ambulancias

            </summary>

            <div class="submenu">

                <a href="transporte.html">

                    <i class="fa-solid fa-plus"></i>

                    Nuevo registro

                </a>

                <a href="registro_ambulancias.html">

                    <i class="fa-solid fa-list"></i>

                    Ver registros

                </a>

            </div>

        </details>


        <details class="menu-desplegable">

            <summary>

                <i class="fa-solid fa-user-plus"></i>

                Usuaruios

            </summary>

            <div class="submenu">

                <a href="panel_admin.php">

                    <i class="fa-solid fa-plus"></i>

                   Solicitudes

                </a>

                <a href="lista_usuarios.php">

                    <i class="fa-solid fa-list"></i>

                    Lista de Usuarios

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

        <a href="index.html">
            <i class="fa-solid fa-right-from-bracket"></i>
            Cerrar sesión
        </a>

    </aside>


    <!-- CONTENIDO -->

    <main class="contenido">

        <h2 class="titulo-panel">

            Panel de Administrador

        </h2>

        <p class="subtitulo-panel">

            Solicitudes de registro pendientes de aprobación.

        </p>


        <?php

        if ($mensaje != "") {

            echo "<p>" . $mensaje . "</p>";

        }

        ?>


        <!-- SOLICITUDES -->

        <section class="panel-contenedor">

            <div class="panel-titulo">

                <i class="fa-solid fa-user-clock"></i>

                <h2>Solicitudes pendientes</h2>

            </div>


            <div class="tabla-scroll">

                <table class="tabla-panel">

                    <thead>

                        <tr>

                            <th>Cédula</th>

                            <th>Nombre</th>

                            <th>Apellido</th>

                            <th>Correo</th>

                            <th>Rol</th>

                            <th>Acción</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php

                    if ($resultado->num_rows > 0) {

                        while ($usuario = $resultado->fetch_assoc()) {

                    ?>

                        <tr>

                            <td>
                                <?php echo $usuario["Cedula"]; ?>
                            </td>

                            <td>
                                <?php echo $usuario["Nombre"]; ?>
                            </td>

                            <td>
                                <?php echo $usuario["Apellido"]; ?>
                            </td>

                            <td>
                                <?php echo $usuario["correo"]; ?>
                            </td>

                            <td>

                                <form method="POST" action="panel_admin.php">

                                    <input type="hidden"
                                           name="id_usuario"
                                           value="<?php echo $usuario["ID_Usuario"]; ?>">

                                    <select name="rol" required>

                                        <option value="">
                                            Seleccionar
                                        </option>

                                        <option value="Medico">
                                            Médico
                                        </option>

                                        <option value="Funcionario">
                                            Funcionario
                                        </option>

                                        <option value="Administrador">
                                            Administrador
                                        </option>

                                    </select>

                            </td>

                            <td>

                                    <button type="submit"
                                            name="aceptar">

                                        <i class="fa-solid fa-check"></i>

                                        Aceptar

                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php

                        }

                    } else {

                    ?>

                        <tr>

                            <td colspan="6">

                                No hay solicitudes pendientes.

                            </td>

                        </tr>

                    <?php

                    }

                    ?>

                    </tbody>

                </table>

            </div>

        </section>


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

