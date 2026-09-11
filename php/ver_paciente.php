<?php

require_once 'conexion.php';


// BUSCAR PACIENTE
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['buscar'])) {

        $cedula = $_POST['cedula'];
        $nombre = $_POST['nombre'];

        if ($cedula != "") {

            $sql = "SELECT * FROM Persona
                    WHERE Cedula = '$cedula'";

        } elseif ($nombre != "") {

            $sql = "SELECT * FROM Persona
                    WHERE Nombre LIKE '%$nombre%'";

        } else {

            $sql = "SELECT * FROM Persona";

        }

        $resultado = $con->query($sql);

    }

elseif (isset($_POST['mostrar_todos'])) {
     $sql = "SELECT * FROM Persona";
      $resultado = $con->query($sql); }

    // ELIMINAR
    elseif (isset($_POST['eliminar'])) {

        $cedula = $_POST['eliminar'];

        $sql = "DELETE FROM Paciente
                WHERE Cedula = '$cedula'";

        $con->query($sql);

        $sql = "DELETE FROM Persona
                WHERE Cedula = '$cedula'";

        $con->query($sql);

        $sql = "SELECT * FROM Persona";

        $resultado = $con->query($sql);

    }


    // MOSTRAR FORMULARIO PARA MODIFICAR
    elseif (isset($_POST['modificar'])) {

        $cedula = $_POST['modificar'];

        $sql = "SELECT * FROM Persona
                WHERE Cedula = '$cedula'";

        $resultado_modificar = $con->query($sql);

        $persona_modificar = $resultado_modificar->fetch_assoc();

        $sql = "SELECT * FROM Persona";

        $resultado = $con->query($sql);

    }


    // GUARDAR MODIFICACIÓN
    elseif (isset($_POST['guardar_modificacion'])) {

        $cedula = $_POST['cedula'];
        $nombre = $_POST['nombre'];
        $apellido = $_POST['apellido'];
        $nacimiento = $_POST['nacimiento'];
        $direccion = $_POST['direccion'];
        $correo = $_POST['correo'];
        $telefono = $_POST['telefono'];

        $sql = "UPDATE Persona SET
                Nombre = '$nombre',
                Apellido = '$apellido',
                Fecha_Nacimiento = '$nacimiento',
                Direccion = '$direccion',
                Correo = '$correo',
                Telefono = '$telefono'
                WHERE Cedula = '$cedula'";

        $con->query($sql);

        $sql = "SELECT * FROM Persona";

        $resultado = $con->query($sql);

    }

} else {

    // MOSTRAR TODOS LOS PACIENTES AL ENTRAR
    $sql = "SELECT * FROM Persona";

    $resultado = $con->query($sql);

}

?>


<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>HOSPITAL DE CLINICA</title>


    <!-- Normalize CSS -->

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">


    <!-- Fuente Poppins -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">


    <!-- Font Awesome -->

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">


    <!-- CSS -->

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


        <a href="panelprincipal.html">

            <i class="fa-solid fa-house"></i>

            Inicio

        </a>


        <!-- PACIENTES -->

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


        <!-- AMBULANCIAS -->

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


                <a href="Gestión_Ambulancias.html">

                    <i class="fa-solid fa-cogs"></i>

                    Gestión de Ambulancias

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


    <!-- CONTENIDO -->

    <main class="contenido">


        <h2 class="titulo-panel">

            Pacientes registrados

        </h2>


        <p class="subtitulo-panel">

            Busque y consulte la información de los pacientes registrados.

        </p>


        <!-- BUSCADOR -->

        <div class="tarjeta">

            <h3>Buscar paciente</h3>


            <form action="ver_paciente.php" method="POST">


                <div class="fila-pacientes">


                    <div class="campo-pacientes">

                        <label for="cedula">

                            Cédula

                        </label>


                        <input
                            type="text"
                            id="cedula"
                            name="cedula"
                            placeholder="Ej: 4.123.456-7">


                    </div>


                    <div class="campo-pacientes">

                        <label for="nombre">

                            Nombre

                        </label>


                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            placeholder="Ej: Juan">


                    </div>


                </div>


                <button
                    type="submit"
                    name="buscar"
                    class="boton-agendar">

                    <i class="fa-solid fa-magnifying-glass"></i>

                    Buscar

                </button>
                <button type="submit" 
                name="mostrar_todos" 
                class="boton-agendar"> 
                <i class="fa-solid fa-list"></i> Mostrar todos 
            </button>


            </form>

        </div>


        <!-- FORMULARIO DE MODIFICACIÓN -->

        <?php

        if (isset($persona_modificar)) {

        ?>

        <div class="tarjeta">
             <h3>Modificar paciente</h3> 
             <form action="ver_paciente.php" method="POST" class="form-modificar"> 
                <div class="campos-modificar"> 
                    <div> 
                        <label>Nombre:</label> 
                <input type="text" name="nombre" value="<?php echo $persona_modificar['Nombre']; ?>">
             </div> 
             <div> 
                <label>Apellido:</label> 
                <input type="text" name="apellido" value="<?php echo $persona_modificar['Apellido']; ?>"> 
            </div> 
            <div> 
                <label>Fecha Nacimiento:</label> 
                <input type="date" name="nacimiento" value="<?php echo $persona_modificar['Fecha_Nacimiento']; ?>">
             </div> 
             <div> 
                <label>Dirección:</label>
                 <input type="text" name="direccion" value="<?php echo $persona_modificar['Direccion']; ?>">
             </div> 
             <div> 
                <label>Correo:</label>
                 <input type="text" name="correo" value="<?php echo $persona_modificar['Correo']; ?>">
                 </div>
                  <div>
                     <label>Teléfono:</label>
                      <input type="text" name="telefono" value="<?php echo $persona_modificar['Telefono']; ?>">
                     </div>
                     </div>
                      <input type="hidden" name="cedula" value="<?php echo $persona_modificar['Cedula']; ?>">
                       <button type="submit" name="guardar_modificacion" class="boton-guardar-modificacion">
                         <i class="fa-solid fa-check"></i> Guardar cambios </button>
                         </form> 
                        </div>

        <?php

        }

        ?>


        <!-- TABLA -->

        <div class="tarjeta">


            <h3>Lista de pacientes</h3>


            <div class="tabla-scroll">


                <table class="big-table">


                    <thead>

                        <tr>

                            <th>Cédula</th>

                            <th>Nombre</th>

                            <th>Apellido</th>

                            <th>Teléfono</th>

                            <th>Acciones</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    while ($persona = $resultado->fetch_assoc()) {

                    ?>


                    <tr>


                        <td>

                            <?php echo $persona['Cedula']; ?>

                        </td>


                        <td>

                            <?php echo $persona['Nombre']; ?>

                        </td>


                        <td>

                            <?php echo $persona['Apellido']; ?>

                        </td>


                        <td>

                            <?php echo $persona['Telefono']; ?>

                        </td>


                        <td class="acciones-pacientes">


                            <!-- MODIFICAR -->

                            <form action="ver_paciente.php" method="POST">

                                <button
                                    name="modificar"
                                    value="<?php echo $persona['Cedula']; ?>"
                                    class="editar"
                                    title="Editar paciente">

                                    <i class="fa-solid fa-pen"></i>

                                </button>

                            </form>


                            <!-- ELIMINAR -->

                            <form action="ver_paciente.php" method="POST">

                                <button
                                    name="eliminar"
                                    value="<?php echo $persona['Cedula']; ?>"
                                    class="eliminar"
                                    title="Eliminar paciente">

                                    <i class="fa-solid fa-trash"></i>

                                </button>

                            </form>


                        </td>


                    </tr>


                    <?php

                    }

                    ?>


                    </tbody>


                </table>


            </div>


        </div>


    </main>


</div>


<!-- FOOTER -->

<footer>

    <p>

        &copy; 2026 Hospital de Clínicas.

        Todos los derechos reservados a DHC.

    </p>

</footer>


</body>

</html>
