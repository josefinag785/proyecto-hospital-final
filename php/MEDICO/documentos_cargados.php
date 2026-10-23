<?php

session_start();

date_default_timezone_set('America/Montevideo');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../conexion.php';

$con->set_charset('utf8mb4');


// COMPROBAR QUIÉN INICIÓ SESIÓN

$id_usuario = filter_var(
    $_SESSION['id_usuario'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}


// BUSCAR AL MÉDICO Y COMPROBAR SU CUENTA

$sql = "SELECT Funcionario.ID_Funcionario,
               Persona.Nombre,
               Persona.Apellido
        FROM Funcionario
        INNER JOIN Usuario
            ON Funcionario.Cedula = Usuario.Cedula
        INNER JOIN Persona
            ON Funcionario.Cedula = Persona.Cedula
        WHERE Usuario.ID_Usuario = $id_usuario
          AND Usuario.Rol = 'Medico'
          AND Usuario.Estado = 'Activo'
          AND Funcionario.Tipo = 'Medico'";

$resultado_medico = $con->query($sql);

if (!$resultado_medico) {
    error_log($con->error);
    http_response_code(500);
    exit('No se pudo comprobar el acceso. Intente nuevamente.');
}

if ($resultado_medico->num_rows == 0) {
    http_response_code(403);
    exit('Esta página está disponible para médicos con una cuenta activa.');
}

$medico = $resultado_medico->fetch_assoc();

$id_medico = (int) $medico['ID_Funcionario'];

header('Cache-Control: no-store, private');


// ENLACE AL INICIO

$pagina_inicio = 'inicio.php';

if (is_file(__DIR__ . '/inicio_medico.php')) {
    $pagina_inicio = 'inicio_medico.php';
}


// COMPROBAR LOS FORMULARIOS

if (!isset($_SESSION['token_documentacion_medico'])) {
    $_SESSION['token_documentacion_medico'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_documentacion_medico'];

$token_enviado = $_POST['token'] ?? '';

$token_correcto = is_string($token_enviado)
    && hash_equals($token, $token_enviado);


// CERRAR SESIÓN

if (isset($_POST['cerrar_sesion'])) {

    if (!$token_correcto) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $_SESSION = [];

    session_destroy();

    header('Location: /prueba/php/login.php');

    exit;
}


// ABRIR UN PDF DEL PACIENTE

if (isset($_GET['ver_archivo'])) {

    $id_archivo = filter_var(
        $_GET['ver_archivo'],
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
            WHERE Archivo_Documento.ID_Archivo = $id_archivo
              AND Documento.ID_Funcionario = $id_medico
              AND Archivo_Documento.Archivo IS NOT NULL";

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

    $archivo_guardado = $resultado_archivo->fetch_assoc();

    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: inline; filename="documento_'
        . $id_archivo
        . '.pdf"'
    );

    header(
        'Content-Length: '
        . strlen($archivo_guardado['Archivo'])
    );

    header('X-Content-Type-Options: nosniff');

    echo $archivo_guardado['Archivo'];

    exit;
}


// BUSCAR POR NOMBRE O CÉDULA

$busqueda = '';

if (
    isset($_POST['buscar'])
    && is_string($_POST['busqueda'] ?? null)
) {
    $busqueda = trim($_POST['busqueda']);
}

$texto_busqueda = '%' . $busqueda . '%';

$mensaje = '';

$resultado_documentos = null;


// CONSULTAR LOS ARCHIVOS DE SUS PACIENTES

$sql = "SELECT Archivo_Documento.ID_Archivo,
               Archivo_Documento.Titulo,
               Archivo_Documento.Descripcion,
               Archivo_Documento.Fecha_Carga,
               Persona.Cedula,
               Persona.Nombre,
               Persona.Apellido
        FROM Archivo_Documento
        INNER JOIN Documento
            ON Archivo_Documento.ID_Documento = Documento.ID_Documento
        INNER JOIN Paciente
            ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona
            ON Paciente.Cedula = Persona.Cedula
        WHERE Documento.ID_Funcionario = ?
          AND Archivo_Documento.Archivo IS NOT NULL
          AND (
              CONCAT(Persona.Nombre, ' ', Persona.Apellido) LIKE ?
              OR Persona.Cedula LIKE ?
          )
        ORDER BY Archivo_Documento.Fecha_Carga DESC,
                 Archivo_Documento.ID_Archivo DESC";

$consulta = $con->prepare($sql);

if (!$consulta) {

    error_log($con->error);

    $mensaje = 'No se pudieron consultar los documentos. Intente nuevamente.';

} else {

    $consulta->bind_param(
        'iss',
        $id_medico,
        $texto_busqueda,
        $texto_busqueda
    );

    if (!$consulta->execute()) {

        error_log($consulta->error);

        $mensaje = 'No se pudieron consultar los documentos. Intente nuevamente.';

    } else {

        $resultado_documentos = $consulta->get_result();

        if (!$resultado_documentos) {

            error_log($consulta->error);

            $mensaje = 'No se pudieron consultar los documentos. Intente nuevamente.';

        }

    }

    $consulta->close();

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Documentos cargados | Hospital de Clínicas</title>

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

    <link rel="stylesheet"
          href="/prueba/css/style.css">

    <link rel="stylesheet"
          href="/prueba/css/medico.css">

    <link rel="stylesheet"
          href="/prueba/css/documentos_cargados.css">

</head>

<body class="inicio-medico pagina-documentos-cargados">


<header>

    <div class="encabezado">

        <img src="/prueba/img/LOGO PROYECTO DHC.png"
             alt="Logo del hospital"
             class="logo">

        <h1>HOSPITAL DE CLÍNICAS</h1>

    </div>

</header>


<div class="perfil-contenedor">

    <!-- MENÚ LATERAL -->

    <aside class="menu-lateral menu-medico">

        <div class="nombre-medico">

            <i class="fa-solid fa-user-doctor"
               aria-hidden="true"></i>

            <p>Médico</p>

            <strong>
                <?php
                echo htmlspecialchars(
                    $medico['Nombre'] . ' ' . $medico['Apellido'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

        </div>

        <h3>Menú</h3>


        <a href="<?php echo $pagina_inicio; ?>"
           class="opcion-medico">

            <i class="fa-solid fa-house"
               aria-hidden="true"></i>

            Inicio

        </a>


        <a href="mis_pacientes.php"
           class="opcion-medico">

            <i class="fa-solid fa-users"
               aria-hidden="true"></i>

            Mis pacientes

        </a>


        <a href="nueva_documentacion.php"
           class="opcion-medico">

            <i class="fa-solid fa-file-circle-plus"
               aria-hidden="true"></i>

            Nueva documentación

        </a>


        <a href="documentos_cargados.php"
           class="opcion-medico activo"
           aria-current="page">

            <i class="fa-solid fa-folder-open"
               aria-hidden="true"></i>

            Documentos cargados

        </a>


        <form method="POST"
              class="form-salir">

            <input type="hidden"
                   name="token"
                   value="<?php echo $token; ?>">

            <button type="submit"
                    name="cerrar_sesion"
                    class="salir-medico">

                <i class="fa-solid fa-right-from-bracket"
                   aria-hidden="true"></i>

                Cerrar sesión

            </button>

        </form>

    </aside>


    <!-- CONTENIDO -->

    <main class="contenido">

        <h2 class="titulo-panel">
            Documentos cargados
        </h2>

        <p class="subtitulo-panel">
            Consulte los archivos de sus pacientes asignados.
        </p>


        <!-- BUSCADOR -->

        <section class="tarjeta"
                 aria-labelledby="titulo-busqueda">

            <h3 id="titulo-busqueda">
                Buscar documentos de un paciente
            </h3>

            <form action="documentos_cargados.php#lista-documentos"
                  method="POST"
                  class="buscador-documentos">

                <div class="campo-busqueda-documentos">

                    <label for="busqueda">
                        Nombre o cédula del paciente
                    </label>

                    <input
                        type="search"
                        id="busqueda"
                        name="busqueda"
                        maxlength="100"
                        placeholder="Ej: Juan Pérez o su cédula"
                        value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">

                </div>


                <div class="botones-documentos">

                    <button type="submit"
                            name="buscar"
                            class="boton-documentos">

                        <i class="fa-solid fa-magnifying-glass"
                           aria-hidden="true"></i>

                        Buscar

                    </button>


                    <a href="documentos_cargados.php#lista-documentos"
                       class="boton-documentos boton-secundario">

                        <i class="fa-solid fa-list"
                           aria-hidden="true"></i>

                        Mostrar todos

                    </a>

                </div>

            </form>

        </section>


        <!-- LISTADO -->

        <section class="tarjeta"
                 id="lista-documentos"
                 aria-labelledby="titulo-listado">

            <h3 id="titulo-listado">
                Archivos guardados
            </h3>


            <?php if ($mensaje != '') { ?>

                <p class="error-documentos"
                   role="alert">

                    <?php
                    echo htmlspecialchars(
                        $mensaje,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

            <?php
            } elseif (
                $resultado_documentos
                && $resultado_documentos->num_rows > 0
            ) {
            ?>

                <p class="resumen-documentos">

                    Archivos encontrados:

                    <?php echo $resultado_documentos->num_rows; ?>

                </p>


                <div class="tabla-scroll">

                    <table class="big-table"
                           aria-labelledby="titulo-listado">

                        <thead>

                            <tr>
                                <th scope="col">Cédula</th>
                                <th scope="col">Paciente</th>
                                <th scope="col">Documento</th>
                                <th scope="col">Fecha de carga</th>
                                <th scope="col">Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php
                            while (
                                $documento = $resultado_documentos->fetch_assoc()
                            ) {
                            ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $documento['Cedula'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $documento['Nombre']
                                            . ' '
                                            . $documento['Apellido'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>


                                    <td class="columna-documento">

                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $documento['Titulo'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </strong>

                                        <?php
                                        if ($documento['Descripcion'] != '') {
                                        ?>

                                            <p class="detalle-archivo">
                                                <?php
                                                echo htmlspecialchars(
                                                    $documento['Descripcion'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </p>

                                        <?php } ?>

                                    </td>


                                    <td>
                                        <?php
                                        echo date(
                                            'd/m/Y H:i',
                                            strtotime($documento['Fecha_Carga'])
                                        );
                                        ?>
                                    </td>


                                    <td>

                                        <a href="documentos_cargados.php?ver_archivo=<?php echo (int) $documento['ID_Archivo']; ?>"
                                           class="boton-documentos"
                                           target="_blank"
                                           rel="noopener">

                                            <i class="fa-solid fa-file-pdf"
                                               aria-hidden="true"></i>

                                            Ver PDF

                                        </a>

                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>


            <?php } elseif ($busqueda != '') { ?>

                <p>
                    No se encontraron archivos para ese nombre o cédula.
                </p>

            <?php } else { ?>

                <p>
                    Todavía no hay archivos cargados para sus pacientes asignados.
                </p>

                <a href="nueva_documentacion.php"
                   class="boton-documentos">

                    <i class="fa-solid fa-upload"
                       aria-hidden="true"></i>

                    Cargar documento

                </a>

            <?php } ?>

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

<?php

$con->close();

?>