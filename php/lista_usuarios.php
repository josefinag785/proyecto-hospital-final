<?php

require_once 'conexion.php';


// CAMBIAR ESTADO DEL USUARIO

if (isset($_GET['accion']) && isset($_GET['id'])) {

    $id = $_GET['id'];
    $accion = $_GET['accion'];

    if ($accion == "desactivar") {

        $sql_estado = "UPDATE Usuario 
                       SET Estado = 'Inactivo' 
                       WHERE ID_Usuario = '$id'";

        $con->query($sql_estado);

    } elseif ($accion == "activar") {

        $sql_estado = "UPDATE Usuario 
                       SET Estado = 'Activo' 
                       WHERE ID_Usuario = '$id'";

        $con->query($sql_estado);
    }

    header("Location: lista_usuarios.php");
    exit();
}


// BUSCAR USUARIOS ACTIVOS

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
        WHERE Usuario.Estado = 'Activo'";

$resultado = $con->query($sql);


// BUSCAR USUARIOS DESACTIVADOS

$sql_desactivados = "SELECT Usuario.ID_Usuario,
                            Usuario.Cedula,
                            Persona.Nombre,
                            Persona.Apellido,
                            Persona.correo,
                            Usuario.Rol,
                            Usuario.Estado
                     FROM Usuario
                     INNER JOIN Persona
                     ON Usuario.Cedula = Persona.Cedula
                     WHERE Usuario.Estado = 'Inactivo'";

$resultado_desactivados = $con->query($sql_desactivados);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Usuarios - Hospital de Clínicas</title>


    <!-- Normalize CSS -->

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">


    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">


    <!-- Font Awesome -->

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">


    <link rel="stylesheet"
          href="/prueba/css/style.css">

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

        <a href="/prueba/panelprincipal.html">

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

                <a href="/prueba/php/nuevo_paciente.php">

                    <i class="fa-solid fa-plus"></i>

                    Nuevo paciente

                </a>

                <a href="/prueba/php/ver_paciente.php">

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

                <a href="/prueba/php/nuevo_traslado.php">

                    <i class="fa-solid fa-plus"></i>

                    Nuevo registro

                </a>

                <a href="/prueba/php/registro_ambulancias.html">

                    <i class="fa-solid fa-list"></i>

                    Ver registros

                </a>

            </div>

        </details>


        <!-- USUARIOS -->

        <details class="menu-desplegable">

            <summary>

                <i class="fa-solid fa-user-plus"></i>

                Usuarios

            </summary>

            <div class="submenu">

                <a href="/prueba/php/panel_admin.php">

                    <i class="fa-solid fa-plus"></i>

                    Solicitudes

                </a>

                <a href="/prueba/php/lista_usuarios.php">

                    <i class="fa-solid fa-list"></i>

                    Lista de Usuarios

                </a>

            </div>

        </details>


        <!-- CERRAR SESIÓN -->

        <a href="/prueba/index.html">

            <i class="fa-solid fa-right-from-bracket"></i>

            Cerrar sesión

        </a>

    </aside>


    <!-- CONTENIDO -->

    <main class="contenido">


        <h2 class="titulo-panel">

            Usuarios registrados

        </h2>


        <p class="subtitulo-panel">

            Lista de usuarios con cuentas activas y desactivadas.

        </p>



        <!-- ===================================== -->
        <!-- USUARIOS ACTIVOS -->
        <!-- ===================================== -->

        <section class="panel-contenedor">


            <div class="panel-titulo">

                <i class="fa-solid fa-users"></i>

                <h2>Usuarios activos</h2>

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

                            <th>Estado</th>

                            <th>Acciones</th>

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

                                <?php echo $usuario["Rol"]; ?>

                            </td>


                            <td>

                                <?php echo $usuario["Estado"]; ?>

                            </td>


                            <td>

                                <a href="lista_usuarios.php?accion=desactivar&id=<?php echo $usuario['ID_Usuario']; ?>"
                                   onclick="return confirm('¿Seguro que quieres desactivar este usuario?');">

                                    Desactivar

                                </a>

                            </td>


                        </tr>


                    <?php

                        }

                    } else {

                    ?>


                        <tr>

                            <td colspan="7">

                                No hay usuarios activos.

                            </td>

                        </tr>


                    <?php

                    }

                    ?>


                    </tbody>

                </table>

            </div>


        </section>



        <!-- ===================================== -->
        <!-- USUARIOS DESACTIVADOS -->
        <!-- ===================================== -->

        <section class="panel-contenedor">


            <div class="panel-titulo">

                <i class="fa-solid fa-user-slash"></i>

                <h2>Usuarios desactivados</h2>

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

                            <th>Estado</th>

                            <th>Acciones</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if ($resultado_desactivados->num_rows > 0) {

                        while ($usuario = $resultado_desactivados->fetch_assoc()) {

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

                                <?php echo $usuario["Rol"]; ?>

                            </td>


                            <td>

                                <?php echo $usuario["Estado"]; ?>

                            </td>


                            <td>

                                <a href="lista_usuarios.php?accion=activar&id=<?php echo $usuario['ID_Usuario']; ?>"
                                   onclick="return confirm('¿Seguro que quieres activar este usuario?');">

                                    Activar

                                </a>

                            </td>


                        </tr>


                    <?php

                        }

                    } else {

                    ?>


                        <tr>

                            <td colspan="7">

                                No hay usuarios desactivados.

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

