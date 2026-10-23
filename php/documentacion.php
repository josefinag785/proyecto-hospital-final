<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Montevideo');

mysqli_report(MYSQLI_REPORT_OFF);

require_once 'conexion.php';

// VARIABLES INICIALES
$mensaje = '';
$id_modificar = 0;
$id_documento = 0;
$id_paciente = 0;
$id_funcionario = 0;
$cedula = '';
$nombre = '';

// RECIBIR LOS DATOS DE LOS FORMULARIOS
if (isset($_POST['modificar'])) {
    $id_modificar = filter_var(
        $_POST['modificar'],
        FILTER_VALIDATE_INT
    );
}

if (isset($_POST['id_documento'])) {
    $id_documento = filter_var(
        $_POST['id_documento'],
        FILTER_VALIDATE_INT
    );
}

if (isset($_POST['id_paciente'])) {
    $id_paciente = filter_var(
        $_POST['id_paciente'],
        FILTER_VALIDATE_INT
    );
}

if (isset($_POST['id_funcionario'])) {
    $id_funcionario = filter_var(
        $_POST['id_funcionario'],
        FILTER_VALIDATE_INT
    );
}

// MOSTRAR LOS MENSAJES
if (isset($_GET['mensaje'])) {

    if ($_GET['mensaje'] == 'medico_actualizado') {
        $mensaje = 'Médico responsable actualizado correctamente.';
    }

    if ($_GET['mensaje'] == 'documentacion_creada') {
        $mensaje = 'Espacio de documentación creado correctamente.';
    }
}

// MODIFICAR EL MÉDICO
if (isset($_POST['modificar_medico'])) {

    $id_modificar = $id_documento;

    if ($id_documento < 1 || $id_funcionario < 1) {

        $mensaje = 'Debe seleccionar un médico y un espacio válido.';

    } else {

        // Comprobar que el médico exista y esté activo.
        $sql = "SELECT Funcionario.ID_Funcionario
                FROM Funcionario
                INNER JOIN Usuario
                    ON Funcionario.Cedula = Usuario.Cedula
                WHERE Funcionario.ID_Funcionario = $id_funcionario
                  AND Funcionario.Tipo = 'Medico'
                  AND Usuario.Estado = 'Activo'";

        $resultadoMedico = $con->query($sql);

        if (!$resultadoMedico) {

            $mensaje = 'Error al modificar el médico responsable.';
            error_log($con->error);

        } elseif ($resultadoMedico->num_rows == 0) {

            $mensaje = 'El médico seleccionado no existe o está inactivo.';

        } else {

            // Comprobar que el espacio exista.
            $sql = "SELECT ID_Documento
                    FROM Documento
                    WHERE ID_Documento = $id_documento";

            $resultadoDocumento = $con->query($sql);

            if (!$resultadoDocumento) {

                $mensaje = 'Error al modificar el médico responsable.';
                error_log($con->error);

            } elseif ($resultadoDocumento->num_rows == 0) {

                $mensaje = 'El espacio de documentación ya no existe.';

            } else {

                // Guardar el nuevo médico.
                $sql = "UPDATE Documento
                        SET ID_Funcionario = $id_funcionario
                        WHERE ID_Documento = $id_documento";

                if ($con->query($sql)) {

                    header(
                        'Location: documentacion.php?mensaje=medico_actualizado',
                        true,
                        303
                    );

                    exit;

                } else {

                    $mensaje = 'Error al modificar el médico responsable.';
                    error_log($con->error);
                }
            }
        }
    }
}

/// CREAR UN ESPACIO DE DOCUMENTACIÓN
if (isset($_POST['crear_documentacion'])) {

    if ($id_paciente < 1) {

        $mensaje = 'Debe seleccionar un paciente.';

    } elseif ($id_funcionario < 1) {

        $mensaje = 'Debe seleccionar un médico.';

    } else {

        // Comprobar si el paciente ya tiene un espacio.
        $sql = "SELECT ID_Documento
                FROM Documento
                WHERE ID_Paciente = $id_paciente
                LIMIT 1";

        $resultadoEspacio = $con->query($sql);

        if (!$resultadoEspacio) {

            $mensaje = 'No se pudo comprobar si el paciente ya tiene un espacio.';
            error_log($con->error);

        } elseif ($resultadoEspacio->num_rows > 0) {

            $mensaje = 'Este paciente ya tiene un espacio de documentación. Puede modificar su médico desde el botón del lápiz.';

        } else {

            // Comprobar que el médico exista y esté activo.
            $sql = "SELECT Funcionario.ID_Funcionario
                    FROM Funcionario
                    INNER JOIN Usuario
                        ON Funcionario.Cedula = Usuario.Cedula
                    WHERE Funcionario.ID_Funcionario = $id_funcionario
                      AND Funcionario.Tipo = 'Medico'
                      AND Usuario.Estado = 'Activo'";

            $resultadoMedico = $con->query($sql);

            if (!$resultadoMedico) {

                $mensaje = 'Error al crear el espacio. Compruebe que el paciente siga registrado.';
                error_log($con->error);

            } elseif ($resultadoMedico->num_rows == 0) {

                $mensaje = 'El médico seleccionado no existe o está inactivo.';

            } else {

                $fecha = date('Y-m-d');

                $sql = "INSERT INTO Documento
                        (
                            Fecha_Emision,
                            Tipo,
                            Descripcion,
                            ID_Paciente,
                            ID_Funcionario,
                            Estado
                        )
                        VALUES
                        (
                            '$fecha',
                            'Documentación médica',
                            'Espacio de documentación del paciente.',
                            $id_paciente,
                            $id_funcionario,
                            'Pendiente'
                        )";

                if ($con->query($sql)) {

                    header(
                        'Location: documentacion.php?mensaje=documentacion_creada',
                        true,
                        303
                    );

                    exit;

                } elseif ($con->errno == 1062) {

                    $mensaje = 'Este paciente ya tiene un espacio de documentación.';

                } else {

                    $mensaje = 'Error al crear el espacio. Compruebe que el paciente siga registrado.';
                    error_log($con->error);
                }
            }
        }
    }
}
// OBTENER PACIENTES SIN ESPACIO DE DOCUMENTACIÓN
$sqlPacientes = "SELECT Paciente.ID_Paciente,
                        Persona.Nombre,
                        Persona.Apellido,
                        Persona.Cedula
                 FROM Paciente
                 INNER JOIN Persona
                     ON Paciente.Cedula = Persona.Cedula
                 LEFT JOIN Documento
                     ON Paciente.ID_Paciente = Documento.ID_Paciente
                 WHERE Documento.ID_Documento IS NULL
                 ORDER BY Persona.Apellido, Persona.Nombre";

$resultadoPacientes = $con->query($sqlPacientes);

// OBTENER MÉDICOS ACTIVOS
$sqlMedicos = "SELECT Funcionario.ID_Funcionario,
                     Persona.Nombre,
                     Persona.Apellido,
                     Persona.Cedula
              FROM Funcionario
              INNER JOIN Persona
                  ON Funcionario.Cedula = Persona.Cedula
              INNER JOIN Usuario
                  ON Funcionario.Cedula = Usuario.Cedula
              WHERE Funcionario.Tipo = 'Medico'
                AND Usuario.Estado = 'Activo'
              ORDER BY Persona.Apellido, Persona.Nombre";

$resultadoMedicos = $con->query($sqlMedicos);

// OBTENER DOCUMENTACIONES
$sqlDocumentos = "SELECT Documento.ID_Documento,
                        Documento.Fecha_Emision,
                        Documento.Tipo,
                        Documento.Descripcion,
                        Documento.Estado,
                        Documento.ID_Funcionario,
                        Paciente.ID_Paciente,
                        Persona_Paciente.Nombre AS Nombre_Paciente,
                        Persona_Paciente.Apellido AS Apellido_Paciente,
                        Persona_Paciente.Cedula AS Cedula_Paciente,
                        Persona_Medico.Nombre AS Nombre_Medico,
                        Persona_Medico.Apellido AS Apellido_Medico,
                        Persona_Medico.Cedula AS Cedula_Medico
                 FROM Documento
                 INNER JOIN Paciente
                     ON Documento.ID_Paciente = Paciente.ID_Paciente
                 INNER JOIN Persona AS Persona_Paciente
                     ON Paciente.Cedula = Persona_Paciente.Cedula
                 LEFT JOIN Funcionario
                     ON Documento.ID_Funcionario = Funcionario.ID_Funcionario
                 LEFT JOIN Persona AS Persona_Medico
                     ON Funcionario.Cedula = Persona_Medico.Cedula";

// BUSCAR POR CÉDULA, NOMBRE O APELLIDO
if (isset($_POST['buscar'])) {

    if (isset($_POST['cedula']) && is_string($_POST['cedula'])) {
        $cedula = trim($_POST['cedula']);
    }

    if (isset($_POST['nombre']) && is_string($_POST['nombre'])) {
        $nombre = trim($_POST['nombre']);
    }

    // Preparar los textos antes de usarlos en SQL.
    $cedula_sql = $con->real_escape_string($cedula);
    $nombre_sql = $con->real_escape_string($nombre);

    if ($cedula != '') {

        $sqlDocumentos .= " WHERE Persona_Paciente.Cedula = '$cedula_sql'";

    } elseif ($nombre != '') {

        $sqlDocumentos .= " WHERE Persona_Paciente.Nombre LIKE '%$nombre_sql%'
                           OR Persona_Paciente.Apellido LIKE '%$nombre_sql%'";

    }
}

// Al entrar o pulsar Mostrar todos, no se agrega un filtro.
$sqlDocumentos .= " ORDER BY Documento.ID_Documento DESC";

$resultadoDocumentos = $con->query($sqlDocumentos);

if (!$resultadoDocumentos) {
    error_log($con->error);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Documentación - Hospital de Clínicas</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

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

        <a href="/prueba/panelprincipal.html">
            <i class="fa-solid fa-house"></i>
            Inicio
        </a>

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

        <details class="menu-desplegable">

            <summary>
                <i class="fa-solid fa-truck-medical"></i>
                Ambulancias
            </summary>

            <div class="submenu">

                <a href="/prueba/php/nuevo_traslado.php">
                    <i class="fa-solid fa-plus"></i>
                    Nuevo traslado
                </a>

                <a href="/prueba/php/ver_traslados.php">
                    <i class="fa-solid fa-list"></i>
                    Ver traslados
                </a>

                <a href="/prueba/php/Gestion_Ambulancia.php">
                    <i class="fa-solid fa-ambulance"></i>
                    Gestión de ambulancias
                </a>

            </div>

        </details>

        <a href="/prueba/php/documentacion.php">
            <i class="fa-solid fa-file-medical"></i>
            Documentación
        </a>

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

        <a href="/prueba/index.html">
            <i class="fa-solid fa-right-from-bracket"></i>
            Cerrar sesión
        </a>

    </aside>

    <!-- CONTENIDO -->
    <main class="contenido pagina-documentacion">

        <h2 class="titulo-panel">
            Documentación de pacientes
        </h2>

        <?php
        if ($mensaje != '') {
            echo '<p class="subtitulo-panel" role="status">'
                . htmlspecialchars($mensaje)
                . '</p>';
        }
        ?>

        <p class="subtitulo-panel">
            Cree y gestione los espacios de documentación de los pacientes.
        </p>

        <!-- CREAR DOCUMENTACIÓN -->
        <div class="tarjeta">

            <h3>Crear espacio de documentación</h3>

            <form action="documentacion.php" method="POST">

                <div class="fila-pacientes">

                    <div class="campo-pacientes">

                        <label for="id_paciente">
                            Paciente
                        </label>

                        <select id="id_paciente" name="id_paciente" required>

                            <option value="">
                                Seleccione un paciente
                            </option>

                            <?php
                            if ($resultadoPacientes) {
                                while ($paciente = $resultadoPacientes->fetch_assoc()) {
                            ?>

                                    <option value="<?php echo (int) $paciente['ID_Paciente']; ?>">
                                        <?php
                                        echo htmlspecialchars(
                                            $paciente['Nombre'] . ' '
                                            . $paciente['Apellido'] . ' - CI '
                                            . $paciente['Cedula']
                                        );
                                        ?>
                                    </option>

                            <?php
                                }
                            }
                            ?>

                        </select>

                    </div>

                    <div class="campo-pacientes">

                        <label for="id_funcionario">
                            Médico responsable
                        </label>

                        <select id="id_funcionario" name="id_funcionario" required>

                            <option value="">
                                Seleccione un médico
                            </option>

                            <?php
                            if ($resultadoMedicos) {
                                while ($medico = $resultadoMedicos->fetch_assoc()) {
                            ?>

                                    <option value="<?php echo (int) $medico['ID_Funcionario']; ?>">
                                        <?php
                                        echo htmlspecialchars(
                                            $medico['Nombre'] . ' '
                                            . $medico['Apellido'] . ' - CI '
                                            . $medico['Cedula']
                                        );
                                        ?>
                                    </option>

                            <?php
                                }
                            }
                            ?>

                        </select>

                    </div>

                </div>

                <button
                    type="submit"
                    name="crear_documentacion"
                    class="boton-agendar">

                    <i class="fa-solid fa-plus"></i>
                    Crear espacio

                </button>

            </form>

        </div>

        <!-- ESPACIOS DE DOCUMENTACIÓN -->
        <div class="tarjeta espacios-documentacion" id="espacios-documentacion">

            <h3>Espacios de documentación</h3>

            <!-- BUSCADOR COMO EL DE PACIENTES -->
            <form
                action="documentacion.php#espacios-documentacion"
                method="POST"
                class="busqueda-documentacion">

                <div class="fila-pacientes">

                    <div class="campo-pacientes">

                        <label for="cedula">Cédula</label>

                        <input
                            type="text"
                            id="cedula"
                            name="cedula"
                            placeholder="Ej: 4.123.456-7"
                            value="<?php echo htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8'); ?>">

                    </div>

                    <div class="campo-pacientes">

                        <label for="nombre">Nombre o apellido</label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            placeholder="Ej: Juan"
                            value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>">

                    </div>

                </div>
<br>
                <div class="botones-busqueda-documentacion">

                    <button
                        type="submit"
                        name="buscar"
                        class="boton-agendar">

                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Buscar

                    </button>

                    <button
                        type="submit"
                        name="mostrar_todos"
                        class="boton-agendar">

                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        Mostrar todos

                    </button>

                </div>
<br>
            </form>

            <?php
            if (!$resultadoDocumentos) {
            ?>

                <p>No se pudieron cargar los espacios de documentación.</p>

            <?php
            } elseif ($resultadoDocumentos->num_rows > 0) {
            ?>

                <div class="lista-documentacion">

                    <?php
                    while ($documento = $resultadoDocumentos->fetch_assoc()) {
                    ?>

                        <details
                            class="tarjeta-documentacion"
                            id="documentacion_<?php echo (int) $documento['ID_Documento']; ?>"
                            <?php
                            if ($id_modificar == $documento['ID_Documento']) {
                                echo 'open';
                            }
                            ?>>

                            <!-- NOMBRE VISIBLE CON LA TARJETA CERRADA -->
                            <summary>

                                <?php
                                echo htmlspecialchars(
                                    $documento['Nombre_Paciente'] . ' '
                                    . $documento['Apellido_Paciente']
                                );
                                ?>

                            </summary>

                            <!-- INFORMACIÓN AL ABRIR LA TARJETA -->
                            <div class="encabezado-documentacion">

                                <div>

                                    <strong>
                                        Documentación #<?php echo (int) $documento['ID_Documento']; ?>
                                    </strong>

                                    <span class="tipo-documentacion">
                                        <?php echo htmlspecialchars($documento['Tipo']); ?>
                                    </span>

                                </div>

                                <span class="estado-documentacion">
                                    <?php echo htmlspecialchars($documento['Estado']); ?>
                                </span>

                            </div>

                            <div class="informacion-documentacion">

                                <div class="dato-documentacion">

                                    <i class="fa-solid fa-user"></i>

                                    <div>

                                        <strong>Paciente</strong>

                                        <p>
                                            <?php
                                            echo htmlspecialchars(
                                                $documento['Nombre_Paciente'] . ' '
                                                . $documento['Apellido_Paciente']
                                            );
                                            ?>

                                            <br>

                                            CI:
                                            <?php echo htmlspecialchars($documento['Cedula_Paciente']); ?>
                                        </p>

                                    </div>

                                </div>

                                <div class="dato-documentacion">

                                    <i class="fa-solid fa-user-doctor"></i>

                                    <div>

                                        <strong>Médico responsable</strong>

                                        <p>
                                            <?php
                                            if ($documento['Nombre_Medico'] === null) {
                                                echo 'Sin médico asignado';
                                            } else {
                                                echo htmlspecialchars(
                                                    $documento['Nombre_Medico'] . ' '
                                                    . $documento['Apellido_Medico']
                                                );
                                            }
                                            ?>
                                        </p>

                                    </div>

                                </div>

                                <div class="dato-documentacion">

                                    <i class="fa-solid fa-calendar"></i>

                                    <div>

                                        <strong>Fecha de creación</strong>

                                        <p>
                                            <?php
                                            echo date(
                                                'd/m/Y',
                                                strtotime($documento['Fecha_Emision'])
                                            );
                                            ?>
                                        </p>

                                    </div>

                                </div>

                                <div class="dato-documentacion">

                                    <i class="fa-solid fa-file-medical"></i>

                                    <div>

                                        <strong>Estado</strong>

                                        <p>
                                            <?php echo htmlspecialchars($documento['Estado']); ?>
                                        </p>

                                    </div>

                                </div>

                            </div>

                            <div class="acciones-documentacion">

                                <!-- BOTÓN DEL LÁPIZ -->
                                <form
                                    action="documentacion.php#documentacion_<?php echo (int) $documento['ID_Documento']; ?>"
                                    method="POST"
                                    class="acciones-pacientes">

                                    <button
                                        type="submit"
                                        name="modificar"
                                        value="<?php echo (int) $documento['ID_Documento']; ?>"
                                        class="editar"
                                        title="Modificar documentación"
                                        aria-label="Modificar documentación">

                                        <i class="fa-solid fa-pen"></i>

                                    </button>

                                </form>

                                <?php
                                if ($id_modificar == $documento['ID_Documento']) {
                                ?>

                                    <!-- FORMULARIO PARA CAMBIAR EL MÉDICO -->
                                    <form
                                        action="documentacion.php"
                                        method="POST"
                                        class="form-medico-documentacion">

                                        <input
                                            type="hidden"
                                            name="id_documento"
                                            value="<?php echo (int) $documento['ID_Documento']; ?>">

                                        <label for="medico_<?php echo (int) $documento['ID_Documento']; ?>">
                                            Médico responsable
                                        </label>

                                        <select
                                            id="medico_<?php echo (int) $documento['ID_Documento']; ?>"
                                            name="id_funcionario"
                                            required>

                                            <option value="">
                                                Seleccione un médico
                                            </option>

                                            <?php
                                            $resultadoMedicosModificar = $con->query($sqlMedicos);

                                            if ($resultadoMedicosModificar) {
                                                while ($medico = $resultadoMedicosModificar->fetch_assoc()) {
                                            ?>

                                                    <option
                                                        value="<?php echo (int) $medico['ID_Funcionario']; ?>"
                                                        <?php
                                                        if (
                                                            $medico['ID_Funcionario']
                                                            == $documento['ID_Funcionario']
                                                        ) {
                                                            echo 'selected';
                                                        }
                                                        ?>>

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $medico['Nombre'] . ' '
                                                            . $medico['Apellido'] . ' - CI '
                                                            . $medico['Cedula']
                                                        );
                                                        ?>

                                                    </option>

                                            <?php
                                                }
                                            }
                                            ?>

                                        </select>

                                        <button
                                            type="submit"
                                            name="modificar_medico"
                                            class="editar">

                                            <i class="fa-solid fa-check"></i>
                                            Guardar cambios

                                        </button>

                                    </form>

                                <?php
                                }
                                ?>

                                <button
                                    type="button"
                                    class="ver-documentacion">

                                    <i class="fa-solid fa-eye"></i>
                                    Ver documentación

                                </button>

                            </div>

                        </details>

                    <?php
                    }
                    ?>

                </div>

            <?php
            } else {
            ?>

                <p>
                    <?php
                    if ($cedula != '' || $nombre != '') {
                        echo 'No se encontraron espacios para esa búsqueda.';
                    } else {
                        echo 'No hay espacios de documentación registrados.';
                    }
                    ?>
                </p>

            <?php
            }
            ?>

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

<?php
$con->close();
?>