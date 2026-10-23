<?php

require_once 'conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);


// AGREGAR AMBULANCIA
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['agregar'])) {

        $matricula = $_POST["matricula"];
        $modelo = $_POST["modelo"];
        $estado = $_POST["estado"];

        // El ID se genera automaticamente
        $sql = "INSERT INTO Ambulancia
                (Matricula, Modelo, Estado)
                VALUES
                ('$matricula', '$modelo', '$estado')";

        if (!$con->query($sql)) {
            die("ERROR AL AGREGAR: " . $con->error);
        }
    }


    // ELIMINAR
    elseif (isset($_POST['eliminar'])) {

        $numero_ambulancia = $_POST['eliminar'];

        $sql = "DELETE FROM Ambulancia
                WHERE Numero_Ambulancia = '$numero_ambulancia'";

        if (!$con->query($sql)) {
            die("ERROR AL ELIMINAR: " . $con->error);
        }
    }


    // MOSTRAR FORMULARIO PARA MODIFICAR
    elseif (isset($_POST['modificar'])) {

        $numero_ambulancia = $_POST['modificar'];

        $sql = "SELECT *
                FROM Ambulancia
                WHERE Numero_Ambulancia = '$numero_ambulancia'";

        $resultado_modificar = $con->query($sql);

        $ambulancia_modificar = $resultado_modificar->fetch_assoc();
    }


    // GUARDAR MODIFICACIÓN
    elseif (isset($_POST['guardar_modificacion'])) {

        $numero_ambulancia = $_POST['numero_ambulancia'];
        $matricula = $_POST['matricula'];
        $modelo = $_POST['modelo'];
        $estado = $_POST['estado'];

        $sql = "UPDATE Ambulancia SET
                Matricula = '$matricula',
                Modelo = '$modelo',
                Estado = '$estado'
                WHERE Numero_Ambulancia = '$numero_ambulancia'";

        if (!$con->query($sql)) {
            die("ERROR AL MODIFICAR: " . $con->error);
        }
    }

}


// MOSTRAR TODAS LAS AMBULANCIAS

$sql = "SELECT *
        FROM Ambulancia
        ORDER BY Numero_Ambulancia";

$resultado = $con->query($sql);

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

                <a href="/prueba/php/ver_traslados.php">

                    <i class="fa-solid fa-list"></i>

                    Ver registros

                </a>
                <a href="/prueba/php/Gestion_Ambulancia.php">
                   <i class="fa-solid fa-cogs"></i>
                    Gestión de Ambulancias
                </a>

            </div>

        </details>

             <!-- Documentación -->

       <details class="menu-desplegable">

            <summary>

                <i class="fa-solid fa-file-alt"></i>

                Documentación

            </summary>

            <div class="submenu">

                <a href="/prueba/php/documentacion.php">

                    <i class="fa-solid fa-plus"></i>

                    Nueva documentación

                </a>

                <a href="/prueba/php/ver_documentacion.php">

                    <i class="fa-solid fa-list"></i>

                    Ver documentación

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


        <!-- TÍTULO Y BOTÓN + -->

        <div class="titulo-ambulancias">

            <h1>Gestión de Ambulancias</h1>


            <!-- BOTÓN + -->

            <details class="agregar-unidad">

                <summary>+</summary>


                <!-- FORMULARIO AGREGAR -->

                <div class="formulario-unidad">

                    <h3>Agregar nueva ambulancia</h3>


                    <form
                        id="formAmbulancia"
                        method="POST"
                        action="Gestion_Ambulancia.php">


                        <div>

                            <label for="matricula">
                                Matrícula
                            </label>

                            <input
                                type="text"
                                id="matricula"
                                name="matricula"
                                placeholder="Ej: SAM 4567"
                                required>

                        </div>


                        <div>

                            <label for="modelo">
                                Modelo
                            </label>

                            <input
                                type="text"
                                id="modelo"
                                name="modelo"
                                placeholder="Ej: Mercedes Benz"
                                required>

                        </div>


                        <div>

                            <label for="estado">
                                Estado
                            </label>

                            <select
                                id="estado"
                                name="estado"
                                required>

                                <option value="">
                                    Seleccionar
                                </option>

                                <option value="En servicio">
                                    En servicio
                                </option>

                                <option value="Disponible">
                                    Disponible
                                </option>

                                <option value="Fuera de servicio">
                                    Fuera de servicio
                                </option>

                            </select>

                        </div>


                        <div class="botones-unidad">

                            <button
                                type="reset"
                                class="boton-cancelar">

                                Limpiar

                            </button>


                            <button
                                type="submit"
                                name="agregar"
                                class="boton-agregar">

                                Agregar unidad

                            </button>

                        </div>


                    </form>

                </div>

            </details>

        </div>


        <!-- FORMULARIO DE MODIFICACIÓN -->

        <?php

        if (isset($ambulancia_modificar)) {

        ?>

        <div class="tarjeta">

            <h3>Modificar ambulancia</h3>


            <form
                action="Gestion_Ambulancia.php"
                method="POST"
                class="form-modificar">


                <div class="campos-modificar">


                    <!-- ID -->

                    <div>

                        <label>ID:</label>

                        <input
                            type="text"
                            value="<?php echo $ambulancia_modificar['Numero_Ambulancia']; ?>"
                            readonly>

                    </div>


                    <!-- MATRÍCULA -->

                    <div>

                        <label>Matrícula:</label>

                        <input
                            type="text"
                            name="matricula"
                            value="<?php echo $ambulancia_modificar['Matricula']; ?>"
                            required>

                    </div>


                    <!-- MODELO -->

                    <div>

                        <label>Modelo:</label>

                        <input
                            type="text"
                            name="modelo"
                            value="<?php echo $ambulancia_modificar['Modelo']; ?>"
                            required>

                    </div>


                    <!-- ESTADO -->

                    <div>

                        <label>Estado:</label>

                        <select
                            name="estado"
                            required>


                            <option
                                value="En servicio"
                                <?php

                                if ($ambulancia_modificar['Estado'] == 'En servicio') {

                                    echo 'selected';

                                }

                                ?>>

                                En servicio

                            </option>


                            <option
                                value="Disponible"
                                <?php

                                if ($ambulancia_modificar['Estado'] == 'Disponible') {

                                    echo 'selected';

                                }

                                ?>>

                                Disponible

                            </option>


                            <option
                                value="Fuera de servicio"
                                <?php

                                if ($ambulancia_modificar['Estado'] == 'Fuera de servicio') {

                                    echo 'selected';

                                }

                                ?>>

                                Fuera de servicio

                            </option>


                        </select>

                    </div>


                </div>


                <!-- ID OCULTO -->

                <input
                    type="hidden"
                    name="numero_ambulancia"
                    value="<?php echo $ambulancia_modificar['Numero_Ambulancia']; ?>">


                <!-- GUARDAR -->

                <button
                    type="submit"
                    name="guardar_modificacion"
                    class="boton-guardar-modificacion">

                    <i class="fa-solid fa-check"></i>

                    Guardar cambios

                </button>


            </form>

        </div>

        <?php

        }

        ?>


        <!-- TABLA -->

        <div class="tarjeta">


            <h3>Lista de ambulancias</h3>


            <div class="tabla-scroll">


                <table class="big-table">


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Matrícula</th>

                            <th>Modelo</th>

                            <th>Estado</th>

                            <th>Acciones</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php

                    if ($resultado && $resultado->num_rows > 0) {

                        while ($ambulancia = $resultado->fetch_assoc()) {

                    ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?php echo $ambulancia['Numero_Ambulancia']; ?>

                        </td>


                        <!-- MATRÍCULA -->

                        <td>

                            <?php echo $ambulancia['Matricula']; ?>

                        </td>


                        <!-- MODELO -->

                        <td>

                            <?php echo $ambulancia['Modelo']; ?>

                        </td>


                        <!-- ESTADO -->

                        <td>

                            <?php echo $ambulancia['Estado']; ?>

                        </td>


                        <!-- ACCIONES -->

                        <td class="acciones-pacientes">


                            <!-- MODIFICAR -->

                            <form
                                action="Gestion_Ambulancia.php"
                                method="POST">

                                <button
                                    type="submit"
                                    name="modificar"
                                    value="<?php echo $ambulancia['Numero_Ambulancia']; ?>"
                                    class="editar"
                                    title="Modificar ambulancia">

                                    <i class="fa-solid fa-pen"></i>

                                </button>

                            </form>


                            <!-- ELIMINAR -->

                            <form
                                action="Gestion_Ambulancia.php"
                                method="POST"
                                onsubmit="return confirmarEliminacion();">

                                <button
                                    type="submit"
                                    name="eliminar"
                                    value="<?php echo $ambulancia['Numero_Ambulancia']; ?>"
                                    class="eliminar"
                                    title="Eliminar ambulancia">

                                    <i class="fa-solid fa-trash"></i>

                                </button>

                            </form>


                        </td>


                    </tr>


                    <?php

                        }

                    } else {

                    ?>


                    <tr>

                        <td colspan="5">

                            No hay ambulancias registradas.

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


<!-- CONFIRMACIÓN DE ELIMINAR -->

<script>

function confirmarEliminacion() {

    return confirm(
        "¿Seguro que quieres eliminar esta ambulancia?"
    );

}

</script>


</body>

</html>
