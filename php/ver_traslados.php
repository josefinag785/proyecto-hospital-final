<?php

require_once 'conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);


/* =========================================================
   VARIABLES
   ========================================================= */

$resultado = null;
$traslado_modificar = null;


/* =========================================================
   FUNCIONES
   ========================================================= */

function obtener_traslados($con, $condicion = "")
{
    $sql = "SELECT
                Traslado.ID_Traslado,
                Traslado.Fecha,
                Traslado.Hora_Salida,
                Traslado.Hora_Llegada,
                Traslado.Origen,
                Traslado.Destino,
                Traslado.Estado,
                Traslado.Retorno,

                Ambulancia.Matricula,
                Ambulancia.Numero_Ambulancia,
                Ambulancia.Modelo,

                Persona_Conductor.Nombre AS Nombre_Conductor,
                Persona_Conductor.Apellido AS Apellido_Conductor,
                Persona_Conductor.Cedula AS Cedula_Conductor,

                Persona_Paciente.Nombre AS Nombre_Paciente,
                Persona_Paciente.Apellido AS Apellido_Paciente,
                Persona_Paciente.Cedula AS Cedula_Paciente,

                Persona_Acompanante.Nombre AS Nombre_Acompanante,
                Persona_Acompanante.Apellido AS Apellido_Acompanante,

                Carga.Tipo AS Tipo_Carga,
                Carga.Descripcon AS Descripcion_Carga

            FROM Traslado

            LEFT JOIN Ambulancia
                ON Traslado.Matricula = Ambulancia.Matricula

            LEFT JOIN Funcionario
                ON Traslado.ID_Funcionario = Funcionario.ID_Funcionario

            LEFT JOIN Persona AS Persona_Conductor
                ON Funcionario.Cedula = Persona_Conductor.Cedula

            LEFT JOIN Paciente
                ON Traslado.ID_Paciente = Paciente.ID_Paciente

            LEFT JOIN Persona AS Persona_Paciente
                ON Paciente.Cedula = Persona_Paciente.Cedula

            LEFT JOIN Acompanante
                ON Traslado.ID_Traslado = Acompanante.ID_Traslado

            LEFT JOIN Persona AS Persona_Acompanante
                ON Acompanante.Cedula = Persona_Acompanante.Cedula

            LEFT JOIN Carga
                ON Traslado.ID_Traslado = Carga.ID_Traslado

            $condicion

            ORDER BY Traslado.ID_Traslado DESC";

    return $con->query($sql);
}


/* =========================================================
   BUSCAR
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] == "POST") {


    /* -----------------------------------------------------
       BUSCAR TRASLADO
       ----------------------------------------------------- */

    if (isset($_POST['buscar'])) {

        $busqueda = $_POST['busqueda'] ?? '';
        $estado_busqueda = $_POST['estado_busqueda'] ?? '';

        $busqueda = $con->real_escape_string($busqueda);
        $estado_busqueda = $con->real_escape_string($estado_busqueda);

        $condiciones = array();


        if ($busqueda != "") {

            $condiciones[] = "(
                Persona_Paciente.Nombre LIKE '%$busqueda%'
                OR Persona_Paciente.Apellido LIKE '%$busqueda%'
                OR Persona_Paciente.Cedula LIKE '%$busqueda%'
                OR Traslado.Destino LIKE '%$busqueda%'
                OR Ambulancia.Matricula LIKE '%$busqueda%'
                OR Ambulancia.Numero_Ambulancia LIKE '%$busqueda%'
                OR Persona_Conductor.Nombre LIKE '%$busqueda%'
                OR Persona_Conductor.Apellido LIKE '%$busqueda%'
                OR Carga.Descripcon LIKE '%$busqueda%'
            )";
        }


        if ($estado_busqueda != "") {

            $condiciones[] = "Traslado.Estado = '$estado_busqueda'";

        }


        $condicion = "";

        if (count($condiciones) > 0) {

            $condicion = "WHERE " . implode(" AND ", $condiciones);

        }


        $resultado = obtener_traslados($con, $condicion);

    }


    /* -----------------------------------------------------
       MOSTRAR TODOS
       ----------------------------------------------------- */

    elseif (isset($_POST['mostrar_todos'])) {

        $resultado = obtener_traslados($con);

    }


    /* -----------------------------------------------------
       ELIMINAR
       ----------------------------------------------------- */

    elseif (isset($_POST['eliminar'])) {

        $id_traslado = $_POST['eliminar'];


        // Primero eliminar la carga relacionada

        $sql = "DELETE FROM Carga
                WHERE ID_Traslado = '$id_traslado'";

        if (!$con->query($sql)) {

            die("ERROR CARGA: " . $con->error);

        }


        // Después eliminar el acompañante

        $sql = "DELETE FROM Acompanante
                WHERE ID_Traslado = '$id_traslado'";

        if (!$con->query($sql)) {

            die("ERROR ACOMPAÑANTE: " . $con->error);

        }


        // Finalmente eliminar el traslado

        $sql = "DELETE FROM Traslado
                WHERE ID_Traslado = '$id_traslado'";

        if (!$con->query($sql)) {

            die("ERROR TRASLADO: " . $con->error);

        }


        // Mostrar nuevamente todos

        $resultado = obtener_traslados($con);

    }


    /* -----------------------------------------------------
       MOSTRAR FORMULARIO PARA MODIFICAR
       ----------------------------------------------------- */

    elseif (isset($_POST['modificar'])) {

        $id_traslado = $_POST['modificar'];


        $sql = "SELECT
                    Traslado.*,
                    Ambulancia.Numero_Ambulancia,
                    Ambulancia.Matricula,
                    Ambulancia.Modelo
                FROM Traslado
                INNER JOIN Ambulancia
                    ON Traslado.Matricula = Ambulancia.Matricula
                WHERE Traslado.ID_Traslado = '$id_traslado'";


        $resultado_modificar = $con->query($sql);


        if ($resultado_modificar) {

            $traslado_modificar = $resultado_modificar->fetch_assoc();

        }


        $resultado = obtener_traslados($con);

    }


    /* -----------------------------------------------------
       GUARDAR MODIFICACIÓN
       ----------------------------------------------------- */

    elseif (isset($_POST['guardar_modificacion'])) {

        $id_traslado = $_POST['id_traslado'];
        $fecha = $_POST['fecha'];
        $hora_salida = $_POST['hora_salida'];
        $hora_llegada = $_POST['hora_llegada'];
        $origen = $_POST['origen'];
        $destino = $_POST['destino'];
        $estado = $_POST['estado'];
        $matricula = $_POST['matricula'];


        if ($hora_llegada == "") {

            $hora_llegada = null;

        }


        $sql = "UPDATE Traslado SET

                    Fecha = ?,
                    Hora_Salida = ?,
                    Hora_Llegada = ?,
                    Origen = ?,
                    Destino = ?,
                    Estado = ?,
                    Matricula = ?

                WHERE ID_Traslado = ?";


        $stmt = $con->prepare($sql);


        if (!$stmt) {

            die("ERROR AL PREPARAR MODIFICACIÓN: " . $con->error);

        }


        $stmt->bind_param(
            "sssssssi",
            $fecha,
            $hora_salida,
            $hora_llegada,
            $origen,
            $destino,
            $estado,
            $matricula,
            $id_traslado
        );


        if (!$stmt->execute()) {

            die("ERROR AL MODIFICAR TRASLADO: " . $stmt->error);

        }


        $stmt->close();


        // Mostrar nuevamente todos

        $resultado = obtener_traslados($con);

    }


} else {

    /* =====================================================
       AL ENTRAR A LA PÁGINA
       ===================================================== */

    $resultado = obtener_traslados($con);

}

?>


<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>HOSPITAL DE CLINICA</title>


    <!-- Normalize CSS -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css"
    >


    <!-- Fuente Poppins -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >


    <!-- CSS -->

    <link
        rel="stylesheet"href="/prueba/css/style.css">

</head>


<body>


<header>

    <div class="encabezado">

        <img
            src="/prueba/img/LOGO PROYECTO DHC.png"alt="Logo"class="logo">

        <h1>HOSPITAL DE CLINICA</h1>

    </div>

</header>


<div class="perfil-contenedor">


    <!-- =================================================
         MENÚ LATERAL
         ================================================= -->

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


    <!-- =================================================
         CONTENIDO
         ================================================= -->

    <main class="contenido">


        <h2 class="titulo-panel">

            Traslados registrados

        </h2>


        <p class="subtitulo-panel">

            Busque, consulte y gestione los traslados registrados.

        </p>


        <!-- =================================================
             BUSCADOR
             ================================================= -->

        <div class="tarjeta">

            <h3>

                Buscar traslado

            </h3>


            <form
                action="ver_traslados.php"
                method="POST"
            >


                <div class="fila-pacientes">


                    <div class="campo-pacientes">

                        <label for="busqueda">

                            Buscar

                        </label>


                        <input
                            type="text"
                            id="busqueda"
                            name="busqueda"
                            placeholder="Paciente, destino, matrícula..."
                        >

                    </div>


                    <div class="campo-pacientes">

                        <label for="estado_busqueda">

                            Estado

                        </label>


                        <select
                            id="estado_busqueda"
                            name="estado_busqueda"
                        >

                            <option value="">

                                Todos los estados

                            </option>


                            <option value="En curso">

                                En curso

                            </option>


                            <option value="En destino">

                                En destino

                            </option>


                            <option value="Finalizado">

                                Finalizado

                            </option>

                        </select>

                    </div>


                </div>


                <button
                    type="submit"
                    name="buscar"
                    class="boton-agendar"
                >

                    <i class="fa-solid fa-magnifying-glass"></i>

                    Buscar

                </button>


                <button
                    type="submit"
                    name="mostrar_todos"
                    class="boton-agendar"
                >

                    <i class="fa-solid fa-list"></i>

                    Mostrar todos

                </button>


            </form>

        </div>

<br><br>
        <!-- =================================================
             FORMULARIO DE MODIFICACIÓN
             ================================================= -->

        <?php

        if (isset($traslado_modificar)) {

        ?>


        <div class="tarjeta">

            <h3>

                Modificar traslado #<?php echo $traslado_modificar['ID_Traslado']; ?>

            </h3>


            <form
                action="ver_traslados.php"
                method="POST"
                class="form-modificar"
            >


                <div class="campos-modificar">


                    <div>

                        <label>

                            Fecha:

                        </label>


                        <input
                            type="date"
                            name="fecha"
                            value="<?php echo $traslado_modificar['Fecha']; ?>"
                            required
                        >

                    </div>


                    <div>

                        <label>

                            Hora de salida:

                        </label>


                        <input
                            type="time"
                            name="hora_salida"
                            value="<?php echo $traslado_modificar['Hora_Salida']; ?>"
                            required
                        >

                    </div>


                    <div>

                        <label>

                            Hora de llegada:

                        </label>


                        <input
                            type="time"
                            name="hora_llegada"
                            value="<?php echo $traslado_modificar['Hora_Llegada']; ?>"
                        >

                    </div>


                    <div>

                        <label>

                            Origen:

                        </label>


                        <input
                            type="text"
                            name="origen"
                            value="<?php echo $traslado_modificar['Origen']; ?>"
                            required
                        >

                    </div>


                    <div>

                        <label>

                            Destino:

                        </label>


                        <input
                            type="text"
                            name="destino"
                            value="<?php echo $traslado_modificar['Destino']; ?>"
                            required
                        >

                    </div>


                    <div>

                        <label>

                            Estado:

                        </label>


                        <select
                            name="estado"
                            required
                        >

                            <option
                                value="En curso"
                                <?php
                                if ($traslado_modificar['Estado'] == 'En curso') {
                                    echo 'selected';
                                }
                                ?>
                            >

                                En curso

                            </option>


                            <option
                                value="En destino"
                                <?php
                                if ($traslado_modificar['Estado'] == 'En destino') {
                                    echo 'selected';
                                }
                                ?>
                            >

                                En destino

                            </option>


                            <option
                                value="Finalizado"
                                <?php
                                if ($traslado_modificar['Estado'] == 'Finalizado') {
                                    echo 'selected';
                                }
                                ?>
                            >

                                Finalizado

                            </option>

                        </select>

                    </div>


                    <div>

                        <label>

                            Ambulancia:

                        </label>


                        <select
                            name="matricula"
                            required
                        >

                            <?php

                            $sqlAmbulancias = "SELECT
                                                    Matricula,
                                                    Numero_Ambulancia,
                                                    Modelo,
                                                    Estado
                                                FROM Ambulancia
                                                ORDER BY Numero_Ambulancia";

                            $resultadoAmbulancias = $con->query($sqlAmbulancias);


                            while ($ambulancia = $resultadoAmbulancias->fetch_assoc()) {

                            ?>

                                <option
                                    value="<?php echo $ambulancia['Matricula']; ?>"
                                    <?php

                                    if (
                                        $ambulancia['Matricula']
                                        ==
                                        $traslado_modificar['Matricula']
                                    ) {

                                        echo 'selected';

                                    }

                                    ?>
                                >

                                    Ambulancia
                                    <?php echo $ambulancia['Numero_Ambulancia']; ?>
                                    -
                                    <?php echo $ambulancia['Matricula']; ?>
                                    -
                                    <?php echo $ambulancia['Modelo']; ?>

                                </option>

                            <?php

                            }

                            ?>

                        </select>

                    </div>


                </div>


                <input
                    type="hidden"
                    name="id_traslado"
                    value="<?php echo $traslado_modificar['ID_Traslado']; ?>"
                >


                <button
                    type="submit"
                    name="guardar_modificacion"
                    class="boton-guardar-modificacion"
                >

                    <i class="fa-solid fa-check"></i>

                    Guardar cambios

                </button>


            </form>

        </div>


        <?php

        }

        ?>


        <!-- =================================================
             LISTA DE TRASLADOS
             ================================================= -->

        <div class="tarjeta">

            <h3>

                Traslados registrados

            </h3>


            <div class="lista-traslados">


                <?php

                if ($resultado && $resultado->num_rows > 0) {


                    while ($traslado = $resultado->fetch_assoc()) {


                        /* -------------------------------------
                           DETERMINAR QUÉ SE TRASLADA
                           ------------------------------------- */

                        if (!empty($traslado['Nombre_Paciente'])) {

                            $tipo = "Persona";

                            $trasladado =
                                $traslado['Nombre_Paciente']
                                . " "
                                . $traslado['Apellido_Paciente'];

                            $cedula =
                                $traslado['Cedula_Paciente'];

                        } elseif (!empty($traslado['Descripcion_Carga'])) {

                            $tipo = $traslado['Tipo_Carga'];

                            $trasladado =
                                $traslado['Descripcion_Carga'];

                            $cedula = "";

                        } else {

                            $tipo = "Sin especificar";

                            $trasladado = "Sin información";

                            $cedula = "";

                        }


                        /* -------------------------------------
                           ESTADO
                           ------------------------------------- */

                        $clase_estado = "";

                        if ($traslado['Estado'] == "Finalizado") {

                            $clase_estado = "estado-finalizado";

                        } elseif ($traslado['Estado'] == "En destino") {

                            $clase_estado = "estado-destino";

                        } else {

                            $clase_estado = "estado-curso";

                        }


                ?>


                <div class="tarjeta-traslado">


                    <div class="encabezado-traslado">


                        <div>

                            <strong>

                                Traslado #<?php echo $traslado['ID_Traslado']; ?>

                            </strong>


                            <span class="tipo-traslado">

                                <?php echo $tipo; ?>

                            </span>

                        </div>


                        <span class="estado-traslado <?php echo $clase_estado; ?>">

                            <?php echo $traslado['Estado']; ?>

                        </span>


                    </div>


                    <div class="informacion-traslado">


                        <!-- TRASLADADO -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-user"></i>


                            <div>

                                <strong>

                                    Trasladado

                                </strong>


                                <p>

                                    <?php echo $trasladado; ?>


                                    <?php

                                    if ($cedula != "") {

                                        echo " — CI " . $cedula;

                                    }

                                    ?>

                                </p>

                            </div>

                        </div>


                        <!-- AMBULANCIA -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-truck-medical"></i>


                            <div>

                                <strong>

                                    Ambulancia

                                </strong>


                                <p>

                                    A-<?php echo $traslado['Numero_Ambulancia']; ?>

                                    ·

                                    <?php echo $traslado['Matricula']; ?>

                                    ·

                                    <?php echo $traslado['Modelo']; ?>

                                </p>

                            </div>

                        </div>


                        <!-- CONDUCTOR -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-id-card"></i>


                            <div>

                                <strong>

                                    Conductor

                                </strong>


                                <p>

                                    <?php

                                    echo $traslado['Nombre_Conductor']
                                        . " "
                                        . $traslado['Apellido_Conductor'];

                                    ?>

                                </p>

                            </div>

                        </div>


                        <!-- ACOMPAÑANTE -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-user-group"></i>


                            <div>

                                <strong>

                                    Acompañante

                                </strong>


                                <p>

                                    <?php

                                    if (!empty($traslado['Nombre_Acompanante'])) {

                                        echo $traslado['Nombre_Acompanante']
                                            . " "
                                            . $traslado['Apellido_Acompanante'];

                                    } else {

                                        echo "Sin acompañante";

                                    }

                                    ?>

                                </p>

                            </div>

                        </div>


                        <!-- ORIGEN -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-location-dot"></i>


                            <div>

                                <strong>

                                    Origen

                                </strong>


                                <p>

                                    <?php echo $traslado['Origen']; ?>

                                </p>

                            </div>

                        </div>


                        <!-- DESTINO -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-location-crosshairs"></i>


                            <div>

                                <strong>

                                    Destino

                                </strong>


                                <p>

                                    <?php echo $traslado['Destino']; ?>

                                </p>

                            </div>

                        </div>


                        <!-- FECHA -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-calendar"></i>


                            <div>

                                <strong>

                                    Fecha

                                </strong>


                                <p>

                                    <?php

                                    echo date(
                                        "d/m/Y",
                                        strtotime($traslado['Fecha'])
                                    );

                                    ?>

                                </p>

                            </div>

                        </div>


                        <!-- HORARIOS -->

                        <div class="dato-traslado">

                            <i class="fa-solid fa-clock"></i>


                            <div>

                                <strong>

                                    Horario

                                </strong>


                                <p>

                                    Salida:

                                    <?php echo $traslado['Hora_Salida']; ?>


                                    <?php

                                    if (!empty($traslado['Hora_Llegada'])) {

                                        echo " — Llegada: "
                                            . $traslado['Hora_Llegada'];

                                    }

                                    ?>

                                </p>

                            </div>

                        </div>


                    </div>


                    <!-- ACCIONES -->

                    <div class="acciones-traslado">


                        <!-- MODIFICAR -->

                        <form
                            action="ver_traslados.php"
                            method="POST"
                        >

                            <button
                                type="submit"
                                name="modificar"
                                value="<?php echo $traslado['ID_Traslado']; ?>"
                                class="editar"
                                title="Modificar traslado"
                            >

                                <i class="fa-solid fa-pen"></i>

                                Modificar

                            </button>

                        </form>


                        <!-- ELIMINAR -->

                        <form
                            action="ver_traslados.php"
                            method="POST"
                            onsubmit="return confirm('¿Seguro que quieres eliminar este traslado?');"
                        >

                            <button
                                type="submit"
                                name="eliminar"
                                value="<?php echo $traslado['ID_Traslado']; ?>"
                                class="eliminar"
                                title="Eliminar traslado"
                            >

                                <i class="fa-solid fa-trash"></i>

                                Eliminar

                            </button>

                        </form>


                    </div>


                </div>


                <?php

                    }

                } else {

                ?>


                    <p>

                        No hay traslados registrados.

                    </p>


                <?php

                }

                ?>


            </div>

        </div>


    </main>

</div>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer>

    <p>

        &copy; 2026 Hospital de Clínicas.

        Todos los derechos reservados a DHC.

    </p>

</footer>


</body>

</html>