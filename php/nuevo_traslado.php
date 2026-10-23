<?php

require_once 'conexion.php';

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nuevo traslado - Hospital de Clínicas</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" crossorigin>

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


        <main class="contenido">

            <section class="transporte">

                <h2>Registrar Traslado</h2>

                <p class="texto-transporte">
                    Complete la siguiente información para registrar un nuevo transporte.
                </p>

                <div class="tarjeta">

                    <form class="form-transporte" method="POST" action="transporte.php">


 <!-- TIPO DE TRASLADO -->
<div class="fila">
    <div class="campo">
        <label for="tipo_traslado">
            <i class="fa-solid fa-box"></i> Tipo de traslado
        </label>

        <select name="tipo_traslado" id="tipo_traslado" onchange="mostrarTipoTraslado()" required>
            <option value="">Seleccione una opción</option>
            <option value="Persona">Persona</option>
            <option value="Equipamiento">Equipamiento</option>
            <option value="Insumos">Insumos</option>
        </select>
    </div>
</div>


<!-- PACIENTE Y AMBULANCIA -->
<div class="fila">

    <div class="campo" id="campo-paciente">
        <label for="id_paciente">
            <i class="fa-solid fa-user"></i> Paciente
        </label>

        <select name="id_paciente" id="id_paciente">
            <option value="">Seleccione un paciente</option>

            <?php
            $sqlPacientes = "SELECT Paciente.ID_Paciente, Persona.Nombre, Persona.Apellido, Persona.Cedula
                             FROM Paciente
                             INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula";

            $resultadoPacientes = $con->query($sqlPacientes);

            while ($paciente = $resultadoPacientes->fetch_assoc()) {
                echo "<option value='" . $paciente['ID_Paciente'] . "'>"
                    . $paciente['Nombre'] . " "
                    . $paciente['Apellido'] . " - CI: "
                    . $paciente['Cedula']
                    . "</option>";
            }
            ?>
        </select>
    </div>

    <div class="campo" id="campo-elemento" style="display: none;">
        <label for="elemento_trasladar">
            <i class="fa-solid fa-box"></i> Elemento a trasladar
        </label>

        <input type="text" name="elemento_trasladar" id="elemento_trasladar">
    </div>


    <div class="campo">
        <label for="matricula">
            <i class="fa-solid fa-truck-medical"></i> Ambulancia
        </label>

        <select name="matricula" id="matricula" required>
            <option value="">Seleccione una ambulancia</option>

            <?php
            $sqlAmbulancias = "SELECT Matricula, Numero_Ambulancia, Modelo, Estado
                               FROM Ambulancia";

            $resultadoAmbulancias = $con->query($sqlAmbulancias);

            while ($ambulancia = $resultadoAmbulancias->fetch_assoc()) {
                echo "<option value='" . $ambulancia['Matricula'] . "'>"
                    . "A-" . $ambulancia['Numero_Ambulancia']
                    . " - " . $ambulancia['Modelo']
                    . " - " . $ambulancia['Estado']
                    . "</option>";
            }
            ?>
        </select>
    </div>

</div>


<!-- CONDUCTOR Y FUNCIONARIO ACOMPAÑANTE -->
<div class="fila">

    <div class="campo">
        <label for="id_funcionario">
            <i class="fa-solid fa-id-card"></i> Conductor
        </label>

        <select name="id_funcionario" id="id_funcionario" required>
            <option value="">Seleccione un conductor</option>

            <?php
$sqlConductores = "SELECT Funcionario.ID_Funcionario, Persona.Nombre, Persona.Apellido, Persona.Cedula
                   FROM Funcionario
                   INNER JOIN Persona ON Funcionario.Cedula = Persona.Cedula
                   INNER JOIN Usuario ON Funcionario.Cedula = Usuario.Cedula
                   WHERE Funcionario.Tipo = 'Conductor'
                   AND Usuario.Estado = 'Activo'";

            $resultadoConductores = $con->query($sqlConductores);

            while ($conductor = $resultadoConductores->fetch_assoc()) {
                echo "<option value='" . $conductor['ID_Funcionario'] . "'>"
                    . $conductor['Nombre'] . " "
                    . $conductor['Apellido'] . " - CI: "
                    . $conductor['Cedula']
                    . "</option>";
            }
            ?>
        </select>
    </div>


    <div class="campo">
        <label for="acompanante">
            <i class="fa-solid fa-user-group"></i> Acompañante
        </label>

        <select name="acompanante" id="acompanante">
            <option value="">Seleccione un acompañante</option>

            <?php
$sqlAcompanantes = "SELECT Funcionario.Cedula, Persona.Nombre, Persona.Apellido
                    FROM Funcionario
                    INNER JOIN Persona ON Funcionario.Cedula = Persona.Cedula
                    INNER JOIN Usuario ON Funcionario.Cedula = Usuario.Cedula
                    WHERE Usuario.Estado = 'Activo'";

            $resultadoAcompanantes = $con->query($sqlAcompanantes);

            while ($acompanante = $resultadoAcompanantes->fetch_assoc()) {
                echo "<option value='" . $acompanante['Cedula'] . "'>"
                    . $acompanante['Nombre'] . " "
                    . $acompanante['Apellido'] . " - CI: "
                    . $acompanante['Cedula']
                    . "</option>";
            }
            ?>
        </select>
    </div>

</div>


<!-- ORIGEN Y DESTINO -->
<div class="fila">

    <div class="campo">
        <label for="origen">
            <i class="fa-solid fa-location-dot"></i> Origen
        </label>

        <input type="text"
               name="origen"
               id="origen"
               value="Hospital de Clínicas de Montevideo"
               readonly
               required>
    </div>


    <div class="campo">
        <label for="destino">
            <i class="fa-solid fa-location-crosshairs"></i> Destino
        </label>

        <input type="text"
               name="destino"
               id="destino"
               required>
    </div>

</div>


<!-- FECHA Y HORA -->
<div class="fila">

    <div class="campo">
        <label for="fecha">
            <i class="fa-solid fa-calendar"></i> Fecha
        </label>

        <input type="date"
               name="fecha"
               id="fecha"
               value="<?php echo date('Y-m-d'); ?>"
               required>
    </div>


    <div class="campo">
        <label for="hora_salida">
            <i class="fa-solid fa-clock"></i> Hora de salida
        </label>

        <input type="time"
               name="hora_salida"
               id="hora_salida"
               value="<?php echo date('H:i'); ?>"
               required>
    </div>

        <div class="campo">
        <label for="hora_llegada">
            <i class="fa-solid fa-clock"></i> Hora de llegada
        </label>

        <input type="time"
               name="hora_llegada"
               id="hora_llegada"
               value="<?php echo date('H:i'); ?>"
               required>
    </div>
</div>


<!-- ESTADO -->
<div class="fila">

    <div class="campo">
        <label for="estado">
            <i class="fa-solid fa-road"></i> Estado
        </label>

        <select name="estado" id="estado" required>
            <option value="En curso">En curso</option>
            <option value="En destino">En destino</option>
            <option value="Finalizado">Finalizado</option>
        </select>
    </div>

</div>





<button type="submit">
    <i class="fa-solid fa-plus"></i> Registrar Transporte
</button>


<script>
function mostrarTipoTraslado() {

    const tipo = document.getElementById("tipo_traslado").value;

    const campoPaciente = document.getElementById("campo-paciente");
    const campoElemento = document.getElementById("campo-elemento");

    const paciente = document.getElementById("id_paciente");
    const elemento = document.getElementById("elemento_trasladar");

    if (tipo === "Persona") {

        campoPaciente.style.display = "block";
        campoElemento.style.display = "none";

        paciente.required = true;
        elemento.required = false;

    } else if (tipo === "Equipamiento" || tipo === "Insumos") {

        campoPaciente.style.display = "none";
        campoElemento.style.display = "block";

        paciente.required = false;
        elemento.required = true;

    } else {

        campoPaciente.style.display = "none";
        campoElemento.style.display = "none";

        paciente.required = false;
        elemento.required = false;
    }
}
</script>

</body>

</html>