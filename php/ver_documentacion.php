<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/conexion.php';

$con->set_charset('utf8mb4');


// COMPROBAR QUE INGRESÓ UN ADMINISTRADOR ACTIVO

$id_usuario = filter_var(
    $_SESSION['id_usuario'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$sql = "SELECT ID_Usuario
        FROM Usuario
        WHERE ID_Usuario = $id_usuario
          AND Rol = 'Administrador'
          AND Estado = 'Activo'";

$resultado_usuario = $con->query($sql);

if (!$resultado_usuario) {
    error_log($con->error);
    http_response_code(500);
    exit('No se pudo comprobar el acceso. Intente nuevamente.');
}

if ($resultado_usuario->num_rows == 0) {
    http_response_code(403);
    exit('Esta página está disponible para administradores con una cuenta activa.');
}

header('Cache-Control: no-store, private');


// PREPARAR EL CIERRE DE SESIÓN

if (!isset($_SESSION['token_ver_documentacion_admin'])) {
    $_SESSION['token_ver_documentacion_admin'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_ver_documentacion_admin'];


// CERRAR SESIÓN

if (isset($_POST['cerrar_sesion'])) {

    $token_enviado = $_POST['token'] ?? '';

    if (
        !is_string($token_enviado)
        || !hash_equals($token, $token_enviado)
    ) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $_SESSION = [];

    session_destroy();

    header('Location: /prueba/php/login.php');
    exit;
}


// AL ENTRAR DIRECTAMENTE SE MUESTRAN TODOS LOS DOCUMENTOS
// SI SE RECIBE UN ESPACIO, SE FILTRA POR ESE ESPACIO

$id_documento = 0;

if (isset($_GET['documento'])) {

    $id_documento = filter_var(
        $_GET['documento'],
        FILTER_VALIDATE_INT
    );

    if (!$id_documento || $id_documento < 1) {
        http_response_code(404);
        exit('Espacio de documentación no disponible.');
    }
}


// ABRIR UN PDF

if (isset($_GET['archivo'])) {

    $id_archivo = filter_var(
        $_GET['archivo'],
        FILTER_VALIDATE_INT
    );

    if (!$id_archivo || $id_archivo < 1) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }

    $sql = "SELECT Archivo_Documento.Archivo
            FROM Archivo_Documento
            INNER JOIN Documento
                ON Archivo_Documento.ID_Documento = Documento.ID_Documento
            INNER JOIN Paciente
                ON Documento.ID_Paciente = Paciente.ID_Paciente
            INNER JOIN Persona
                ON Paciente.Cedula = Persona.Cedula
            WHERE Archivo_Documento.ID_Archivo = $id_archivo
              AND Archivo_Documento.Archivo IS NOT NULL";

    if ($id_documento > 0) {
        $sql .= " AND Documento.ID_Documento = $id_documento";
    }

    $resultado_archivo = $con->query($sql);

    if (!$resultado_archivo) {
        error_log($con->error);
        http_response_code(500);
        exit('No se pudo abrir el archivo.');
    }

    if ($resultado_archivo->num_rows == 0) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }

    $pdf = $resultado_archivo->fetch_assoc();

    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: inline; filename="documento_'
        . $id_archivo
        . '.pdf"'
    );

    header('Content-Length: ' . strlen($pdf['Archivo']));

    header('X-Content-Type-Options: nosniff');

    echo $pdf['Archivo'];

    exit;
}


// ELIMINAR UN ARCHIVO

$url_documentacion = 'ver_documentacion.php';

if ($id_documento > 0) {
    $url_documentacion .= '?documento=' . $id_documento;
}

$archivo_eliminar = null;

$aviso = $_SESSION['aviso_documentacion_admin'] ?? '';

unset($_SESSION['aviso_documentacion_admin']);

if (
    isset($_POST['eliminar'])
    || isset($_POST['confirmar_eliminacion'])
) {

    $token_enviado = $_POST['token'] ?? '';

    if (
        !is_string($token_enviado)
        || !hash_equals($token, $token_enviado)
    ) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $id_archivo = filter_var(
        $_POST['confirmar_eliminacion'] ?? $_POST['eliminar'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$id_archivo || $id_archivo < 1) {
        http_response_code(400);
        exit('Archivo no válido.');
    }

    // BUSCAR EL ARCHIVO QUE SE QUIERE ELIMINAR

    $sql = "SELECT Archivo_Documento.ID_Archivo,
                   Archivo_Documento.ID_Documento,
                   Archivo_Documento.Titulo,
                   Persona.Nombre,
                   Persona.Apellido
            FROM Archivo_Documento
            INNER JOIN Documento
                ON Archivo_Documento.ID_Documento = Documento.ID_Documento
            INNER JOIN Paciente
                ON Documento.ID_Paciente = Paciente.ID_Paciente
            INNER JOIN Persona
                ON Paciente.Cedula = Persona.Cedula
            WHERE Archivo_Documento.ID_Archivo = $id_archivo
              AND Archivo_Documento.Archivo IS NOT NULL";

    if ($id_documento > 0) {
        $sql .= " AND Documento.ID_Documento = $id_documento";
    }

    $resultado_eliminar = $con->query($sql);

    if (!$resultado_eliminar) {
        error_log($con->error);
        http_response_code(500);
        exit('No se pudo consultar el archivo.');
    }

    if ($resultado_eliminar->num_rows == 0) {
        http_response_code(404);
        exit('Archivo no disponible.');
    }

    $archivo_eliminar = $resultado_eliminar->fetch_assoc();

    // ELIMINAR DESPUÉS DE CONFIRMAR

    if (isset($_POST['confirmar_eliminacion'])) {

        $id_espacio = (int) $archivo_eliminar['ID_Documento'];

        $correcto = $con->begin_transaction();

        // BLOQUEAR EL ESPACIO MIENTRAS SE ACTUALIZA

        if ($correcto) {

            $sql = "SELECT ID_Documento
                    FROM Documento
                    WHERE ID_Documento = $id_espacio
                    FOR UPDATE";

            $bloqueo = $con->query($sql);

            $correcto = $bloqueo && $bloqueo->num_rows > 0;
        }

        // BORRAR EL ARCHIVO ELEGIDO

        if ($correcto) {

            $consulta_eliminar = $con->prepare(
                'DELETE FROM Archivo_Documento
                 WHERE ID_Archivo = ?
                   AND ID_Documento = ?
                   AND Archivo IS NOT NULL'
            );

            $correcto = false;

            if ($consulta_eliminar) {

                $consulta_eliminar->bind_param(
                    'ii',
                    $id_archivo,
                    $id_espacio
                );

                $correcto = $consulta_eliminar->execute()
                    && $consulta_eliminar->affected_rows == 1;
            }
        }

        // ACTUALIZAR EL ESTADO SEGÚN LOS PDF QUE QUEDEN

        if ($correcto) {

            $sql = "UPDATE Documento
                    SET Estado = CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM Archivo_Documento
                            WHERE ID_Documento = $id_espacio
                              AND Archivo IS NOT NULL
                        ) THEN 'Con documentación'
                        ELSE 'Pendiente'
                    END
                    WHERE ID_Documento = $id_espacio";

            $correcto = $con->query($sql);
        }

        if ($correcto) {
            $correcto = $con->commit();
        }

        if (!$correcto) {
            error_log('No se pudo eliminar la documentación: ' . $con->error);
            $con->rollback();
            http_response_code(500);
            exit('No se pudo eliminar el archivo. Intente nuevamente.');
        }

        $_SESSION['aviso_documentacion_admin'] =
            'Documentación eliminada correctamente.';

        header('Location: ' . $url_documentacion, true, 303);
        exit;
    }
}


// RECIBIR LA BÚSQUEDA

$cedula = '';
$nombre = '';
$mensaje = '';

if (isset($_POST['buscar'])) {

    if (is_string($_POST['cedula'] ?? null)) {
        $cedula = trim($_POST['cedula']);
    }

    if (is_string($_POST['nombre'] ?? null)) {
        $nombre = trim($_POST['nombre']);
    }
}


// BUSCAR LOS DOCUMENTOS DE TODOS LOS PACIENTES

$sql = "SELECT Archivo_Documento.ID_Archivo,
               Archivo_Documento.ID_Documento,
               Archivo_Documento.Titulo,
               Archivo_Documento.Descripcion,
               Archivo_Documento.Fecha_Carga,
               Persona.Cedula,
               Persona.Nombre,
               Persona.Apellido,
               Persona_Medico.Nombre AS Nombre_Medico,
               Persona_Medico.Apellido AS Apellido_Medico
        FROM Archivo_Documento
        INNER JOIN Documento
            ON Archivo_Documento.ID_Documento = Documento.ID_Documento
        INNER JOIN Paciente
            ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona
            ON Paciente.Cedula = Persona.Cedula
        LEFT JOIN Funcionario
            ON Documento.ID_Funcionario = Funcionario.ID_Funcionario
        LEFT JOIN Persona AS Persona_Medico
            ON Funcionario.Cedula = Persona_Medico.Cedula
        WHERE Archivo_Documento.Archivo IS NOT NULL
          AND Persona.Cedula LIKE ?
          AND CONCAT(Persona.Nombre, ' ', Persona.Apellido) LIKE ?";

if ($id_documento > 0) {
    $sql .= " AND Documento.ID_Documento = $id_documento";
}

$sql .= " ORDER BY Archivo_Documento.Fecha_Carga DESC,
                   Archivo_Documento.ID_Archivo DESC";

$buscar_cedula = '%' . $cedula . '%';

$buscar_nombre = '%' . $nombre . '%';

$resultado = false;

$consulta = $con->prepare($sql);

if ($consulta) {

    $consulta->bind_param(
        'ss',
        $buscar_cedula,
        $buscar_nombre
    );

    if ($consulta->execute()) {
        $resultado = $consulta->get_result();
    } else {
        error_log($consulta->error);
    }

} else {
    error_log($con->error);
}

if (!$resultado) {
    $mensaje = 'No se pudieron consultar los archivos. Intente nuevamente.';
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Ver documentación | Hospital de Clínicas</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/prueba/css/style.css">

</head>

<body class="pagina-documentacion">

<header>

    <div class="encabezado">

        <img src="/prueba/img/LOGO PROYECTO DHC.png"
             alt="Logo del hospital"
             class="logo">

        <h1>HOSPITAL DE CLÍNICAS</h1>

    </div>

</header>


<div class="perfil-contenedor">

    <!-- MENÚ LATERAL DEL ADMINISTRADOR -->

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

    <main class="contenido pagina-medico">

        <h2 class="titulo-panel">Ver documentación</h2>

        <p class="subtitulo-panel">
            Consulte la documentación cargada para todos los pacientes.
        </p>

        <div class="lista-documentacion">

            <!-- MENSAJE DESPUÉS DE ELIMINAR -->

            <?php if ($aviso != '') { ?>

                <p class="aviso-medico" role="status">
                    <?php echo htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8'); ?>
                </p>

            <?php } ?>


            <!-- CONFIRMAR LA ELIMINACIÓN -->

            <?php if ($archivo_eliminar !== null) { ?>

                <div class="tarjeta" id="confirmar-eliminacion">

                    <h3>Eliminar documentación</h3>

                    <p>
                        <strong>Paciente:</strong>

                        <?php
                        echo htmlspecialchars(
                            $archivo_eliminar['Nombre']
                            . ' '
                            . $archivo_eliminar['Apellido'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Documento:</strong>

                        <?php
                        echo htmlspecialchars(
                            $archivo_eliminar['Titulo'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </p>

                    <p>
                        El archivo se eliminará definitivamente.
                        ¿Desea continuar?
                    </p>

                    <form
                        action="<?php echo htmlspecialchars($url_documentacion, ENT_QUOTES, 'UTF-8'); ?>"
                        method="POST"
                        class="acciones-pacientes">

                        <input
                            type="hidden"
                            name="token"
                            value="<?php echo $token; ?>">

                        <button
                            type="submit"
                            name="confirmar_eliminacion"
                            value="<?php echo (int) $archivo_eliminar['ID_Archivo']; ?>"
                            class="eliminar">

                            <i class="fa-solid fa-trash" aria-hidden="true"></i>

                            Sí, eliminar

                        </button>

                        <a
                            href="<?php echo htmlspecialchars($url_documentacion, ENT_QUOTES, 'UTF-8'); ?>"
                            class="ver-pdf-medico">

                            Cancelar

                        </a>

                    </form>

                </div>

            <?php } ?>


            <!-- BUSCADOR -->

            <div class="tarjeta">

                <h3>Buscar documentación de un paciente</h3>

                <form action="ver_documentacion.php" method="POST">

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
                                placeholder="Ej: Juan Pérez"
                                value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>">

                        </div>

                    </div>

                    <div class="botones-busqueda-documentacion">

                        <button
                            type="submit"
                            name="buscar"
                            class="boton-agendar">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            Buscar

                        </button>

                        <button
                            type="submit"
                            name="mostrar_todos"
                            class="boton-agendar">

                            <i class="fa-solid fa-list"></i>

                            Mostrar todos

                        </button>

                    </div>

                </form>

            </div>


            <!-- LISTA DE DOCUMENTOS -->

            <div class="tarjeta">

                <h3>Documentación cargada</h3>

                <?php if ($mensaje != '') { ?>

                    <p class="aviso-medico" role="alert">
                        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                <?php } elseif ($resultado->num_rows == 0) { ?>

                    <p>
                        No hay documentos cargados para mostrar con esta selección.
                    </p>

                <?php } else { ?>

                    <p>
                        Documentos encontrados:
                        <?php echo $resultado->num_rows; ?>
                    </p>

                    <div class="tabla-scroll">

                        <table class="big-table">

                            <thead>

                                <tr>
                                    <th scope="col">Paciente</th>
                                    <th scope="col">Cédula</th>
                                    <th scope="col">Documento</th>
                                    <th scope="col">Médico responsable</th>
                                    <th scope="col">Fecha de carga</th>
                                    <th scope="col">Acciones</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php
                                while ($archivo = $resultado->fetch_assoc()) {
                                ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo htmlspecialchars(
                                                $archivo['Nombre'] . ' ' . $archivo['Apellido'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo htmlspecialchars(
                                                $archivo['Cedula'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>

                                        <td>

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $archivo['Titulo'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </strong>

                                            <?php if ($archivo['Descripcion'] != '') { ?>

                                                <p>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $archivo['Descripcion'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    );
                                                    ?>
                                                </p>

                                            <?php } ?>

                                        </td>

                                        <td>

                                            <?php

                                            $nombre_medico = trim(
                                                ($archivo['Nombre_Medico'] ?? '')
                                                . ' '
                                                . ($archivo['Apellido_Medico'] ?? '')
                                            );

                                            if ($nombre_medico == '') {
                                                echo 'Sin médico asignado';
                                            } else {
                                                echo htmlspecialchars(
                                                    $nombre_medico,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            }

                                            ?>

                                        </td>

                                        <td>
                                            <?php
                                            echo date(
                                                'd/m/Y H:i',
                                                strtotime($archivo['Fecha_Carga'])
                                            );
                                            ?>
                                        </td>

                                        <!-- VER Y ELIMINAR -->

                                        <td>

                                            <a
                                                href="ver_documentacion.php?documento=<?php echo (int) $archivo['ID_Documento']; ?>&amp;archivo=<?php echo (int) $archivo['ID_Archivo']; ?>"
                                                class="ver-pdf-medico"
                                                target="_blank"
                                                rel="noopener">

                                                <i class="fa-solid fa-file-pdf"
                                                   aria-hidden="true"></i>

                                                Ver PDF

                                            </a>

                                            <form
                                                action="<?php echo htmlspecialchars($url_documentacion, ENT_QUOTES, 'UTF-8'); ?>#confirmar-eliminacion"
                                                method="POST"
                                                class="acciones-pacientes">

                                                <input
                                                    type="hidden"
                                                    name="token"
                                                    value="<?php echo $token; ?>">

                                                <button
                                                    type="submit"
                                                    name="eliminar"
                                                    value="<?php echo (int) $archivo['ID_Archivo']; ?>"
                                                    class="eliminar"
                                                    title="Eliminar documentación">

                                                    <i class="fa-solid fa-trash"
                                                       aria-hidden="true"></i>

                                                    Eliminar

                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                <?php } ?>

            </div>

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