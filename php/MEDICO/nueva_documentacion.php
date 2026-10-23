<?php

session_start();

date_default_timezone_set('America/Montevideo');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../conexion.php';

$con->set_charset('utf8mb4');


// IDENTIFICAR AL MÉDICO QUE INICIÓ SESIÓN

$id_usuario = filter_var(
    $_SESSION['id_usuario'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id_usuario || $id_usuario < 1) {

    header('Location: /prueba/php/login.php');
    exit;

}

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


// ABRIR EL PDF DEL PACIENTE

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


// MENSAJES

$mensaje = '';

$mensaje_ok = $_SESSION['mensaje_carga_documento'] ?? '';

$id_archivo_subido = (int) (
    $_SESSION['archivo_cargado_medico'] ?? 0
);

unset($_SESSION['mensaje_carga_documento']);

unset($_SESSION['archivo_cargado_medico']);


// DATOS DEL FORMULARIO

$titulo = '';

$descripcion = '';

$id_documento = filter_var(
    $_GET['documento'] ?? null,
    FILTER_VALIDATE_INT
);

if (isset($_POST['subir_pdf'])) {

    $id_documento = filter_var(
        $_POST['id_documento'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (is_string($_POST['titulo'] ?? null)) {

        $titulo = trim($_POST['titulo']);

    }

    if (is_string($_POST['descripcion'] ?? null)) {

        $descripcion = trim($_POST['descripcion']);

    }

}


// SUGERENCIAS PARA EL TÍTULO DEL DOCUMENTO

$catalogo = [

    1 => 'Indicaciones de interrupción voluntaria del embarazo',

    2 => 'Prostatectomía radical (indicaciones e información para el paciente)',

    3 => 'Preparación para estudios imagenológicos',

    4 => 'Estudios diagnósticos con pertecneciato',

    5 => 'Centellograma de perfusión miocárdica',

    6 => 'Indicaciones ecocardiograma con dobutamina',

    7 => 'Indicaciones para pacientes en tratamiento con warfarina',

    8 => 'Indicaciones ecocardiograma transesofágico',

    9 => 'Indicaciones para ingreso a centro de nefrología y trasplante',

    10 => 'Plan de alta enfermería, Nefrología',

    11 => 'Indicaciones de enfermería para usuarios trasplantados',

    12 => 'Prevención de infecciones',

    13 => 'Encuesta de satisfacción del usuario trasplantado',

    14 => 'Pauta para pacientes ostomizados'

];


// BUSCAR LOS PACIENTES ASIGNADOS AL MÉDICO

$sql = "SELECT MIN(Documento.ID_Documento) AS ID_Documento,
               Persona.Cedula,
               Persona.Nombre,
               Persona.Apellido
        FROM Documento
        INNER JOIN Paciente
            ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona
            ON Paciente.Cedula = Persona.Cedula
        WHERE Documento.ID_Funcionario = $id_medico
        GROUP BY Documento.ID_Paciente,
                 Persona.Cedula,
                 Persona.Nombre,
                 Persona.Apellido
        ORDER BY Persona.Nombre,
                 Persona.Apellido,
                 Persona.Cedula";

$resultado_pacientes = $con->query($sql);

$pacientes = [];

$paciente_actual = null;

if (!$resultado_pacientes) {

    error_log($con->error);

    $mensaje = 'No se pudieron cargar los pacientes. Intente nuevamente.';

} else {

    while ($paciente = $resultado_pacientes->fetch_assoc()) {

        $pacientes[] = $paciente;

        if ($id_documento === (int) $paciente['ID_Documento']) {

            $paciente_actual = $paciente;

        }

    }

}


// RECIBIR Y COMPROBAR EL PDF

if (isset($_POST['subir_pdf'])) {

    $archivo = $_FILES['archivo'] ?? [];

    $limite_archivo = 2 * 1024 * 1024;


    if (!$token_correcto) {

        $mensaje = 'Recargue la página y vuelva a intentarlo.';

    } elseif (!$paciente_actual) {

        $mensaje = 'Seleccione un paciente asignado a usted.';

    } elseif (
        $titulo == ''
        || mb_strlen($titulo, 'UTF-8') > 100
    ) {

        $mensaje = 'Escriba un título de hasta 100 caracteres.';

    } elseif (mb_strlen($descripcion, 'UTF-8') > 255) {

        $mensaje = 'La descripción puede tener hasta 255 caracteres.';

    } elseif (
        !isset($archivo['error'])
        || !is_int($archivo['error'])
    ) {

        $mensaje = 'Seleccione un archivo PDF.';

    } elseif (
        $archivo['error'] == UPLOAD_ERR_INI_SIZE
        || $archivo['error'] == UPLOAD_ERR_FORM_SIZE
    ) {

        $mensaje = 'El PDF debe pesar como máximo 2 MB.';

    } elseif ($archivo['error'] != UPLOAD_ERR_OK) {

        $mensaje = 'No se pudo recibir el PDF. Selecciónelo nuevamente.';

    } elseif (
        !is_string($archivo['tmp_name'] ?? null)
        || !is_string($archivo['name'] ?? null)
        || !is_uploaded_file($archivo['tmp_name'])
    ) {

        $mensaje = 'No se pudo recibir el archivo seleccionado.';

    } elseif (
        $archivo['size'] < 1
        || $archivo['size'] > $limite_archivo
    ) {

        $mensaje = 'El PDF debe tener contenido y pesar como máximo 2 MB.';

    } elseif (
        strtolower(
            pathinfo($archivo['name'], PATHINFO_EXTENSION)
        ) != 'pdf'
    ) {

        $mensaje = 'Solo se pueden subir archivos PDF.';

    }


    // COMPROBAR EL TIPO REAL DEL ARCHIVO

    if ($mensaje == '') {

        $informacion = new finfo(FILEINFO_MIME_TYPE);

        $tipo_archivo = $informacion->file(
            $archivo['tmp_name']
        );

        if ($tipo_archivo != 'application/pdf') {

            $mensaje = 'Solo se pueden subir archivos PDF.';

        }

    }


    // LEER EL CONTENIDO DEL PDF

    if ($mensaje == '') {

        $contenido = file_get_contents(
            $archivo['tmp_name']
        );

        if ($contenido === false) {

            $mensaje = 'No se pudo leer el PDF. Selecciónelo nuevamente.';

        } elseif (substr($contenido, 0, 5) != '%PDF-') {

            $mensaje = 'El archivo seleccionado no es un PDF válido.';

        }

    }


    // GUARDAR EL ARCHIVO Y ACTUALIZAR EL ESTADO DEL ESPACIO

    if ($mensaje == '') {

        $guardado = false;

        $id_nuevo_archivo = 0;

        $fecha_carga = date('Y-m-d H:i:s');


        if ($con->begin_transaction()) {

            $sql = "INSERT INTO Archivo_Documento
                    (
                        ID_Documento,
                        ID_Funcionario,
                        Titulo,
                        Descripcion,
                        Archivo,
                        Fecha_Carga
                    )
                    SELECT ID_Documento, ?, ?, ?, ?, ?
                    FROM Documento
                    WHERE ID_Documento = ?
                      AND ID_Funcionario = ?";

            $consulta = $con->prepare($sql);


            if ($consulta) {

                $consulta->bind_param(
                    'issssii',
                    $id_medico,
                    $titulo,
                    $descripcion,
                    $contenido,
                    $fecha_carga,
                    $id_documento,
                    $id_medico
                );


                if (
                    $consulta->execute()
                    && $consulta->affected_rows == 1
                ) {

                    $id_nuevo_archivo = (int) $consulta->insert_id;

                    $sql = "UPDATE Documento
                            SET Estado = 'Con documentación'
                            WHERE ID_Documento = $id_documento
                              AND ID_Funcionario = $id_medico";


                    if (
                        $con->query($sql)
                        && $con->commit()
                    ) {

                        $guardado = true;

                    } else {

                        error_log($con->error);

                    }

                } elseif ($consulta->errno) {

                    error_log($consulta->error);

                }

                $consulta->close();

            } else {

                error_log($con->error);

            }


            if (!$guardado) {

                $con->rollback();

            }

        } else {

            error_log($con->error);

        }


        if ($guardado) {

            $_SESSION['mensaje_carga_documento'] =
                'Documento guardado correctamente. Puede elegir otro paciente.';

            $_SESSION['archivo_cargado_medico'] =
                $id_nuevo_archivo;

            header(
                'Location: nueva_documentacion.php#titulo-paciente',
                true,
                303
            );

            exit;

        }

        $mensaje = 'No se pudo guardar el PDF. Compruebe que el paciente siga asignado a usted.';

    }

} elseif (
    $_SERVER['REQUEST_METHOD'] == 'POST'
    && empty($_POST)
) {

    $mensaje = 'El envío supera el tamaño permitido por el servidor. Pruebe con un PDF de hasta 2 MB.';

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Nueva documentación | Hospital de Clínicas</title>

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

</head>

<body class="inicio-medico pagina-nueva-documentacion">


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
           class="opcion-medico activo"
           aria-current="page">

            <i class="fa-solid fa-file-circle-plus"
               aria-hidden="true"></i>

            Nueva documentación

        </a>


<a href="documentos_cargados.php"
   class="opcion-medico">

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
            Nueva documentación
        </h2>

        <p class="subtitulo-panel">
            Seleccione un paciente y adjunte el documento que le corresponde.
        </p>


        <!-- ELEGIR PACIENTE -->

        <section class="tarjeta tarjeta-carga"
                 aria-labelledby="titulo-paciente">

            <h3 id="titulo-paciente">
                1. Elegir paciente
            </h3>


            <?php if ($mensaje_ok != '') { ?>

                <p class="mensaje-carga exito-carga"
                   role="status">

                    <?php
                    echo htmlspecialchars(
                        $mensaje_ok,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                    <?php if ($id_archivo_subido > 0) { ?>

                        <br>

                        <a href="nueva_documentacion.php?ver_archivo=<?php echo $id_archivo_subido; ?>"
                           target="_blank"
                           rel="noopener">

                            <i class="fa-solid fa-eye"
                               aria-hidden="true"></i>

                            Ver archivo cargado

                        </a>

                    <?php } ?>

                </p>

            <?php } ?>


            <?php if ($mensaje != '') { ?>

                <p class="mensaje-carga error-carga"
                   role="alert">

                    <?php
                    echo htmlspecialchars(
                        $mensaje,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

            <?php } ?>


            <?php if (count($pacientes) == 0) { ?>

                <p>
                    No hay pacientes disponibles para seleccionar.
                </p>

            <?php } else { ?>

                <form action="nueva_documentacion.php#form-documentacion"
                      method="GET"
                      class="form-carga-documento">

                    <div class="campo-carga">

                        <label for="documento">
                            Paciente
                        </label>

                        <select id="documento"
                                name="documento"
                                required>

                            <option value="">
                                Seleccione un paciente
                            </option>

                            <?php foreach ($pacientes as $paciente) { ?>

                                <option
                                    value="<?php echo (int) $paciente['ID_Documento']; ?>"
                                    <?php
                                    if (
                                        $id_documento === (int) $paciente['ID_Documento']
                                    ) {
                                        echo 'selected';
                                    }
                                    ?>>

                                    <?php
                                    echo htmlspecialchars(
                                        $paciente['Nombre']
                                        . ' '
                                        . $paciente['Apellido']
                                        . ' — CI: '
                                        . $paciente['Cedula'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>

                                </option>

                            <?php } ?>

                        </select>

                    </div>


                    <button type="submit"
                            class="boton-cargar-documento">

                        <i class="fa-solid fa-arrow-right"
                           aria-hidden="true"></i>

                        Continuar

                    </button>

                </form>

            <?php } ?>

        </section>


        <!-- CARGAR DOCUMENTO -->

        <?php if ($paciente_actual) { ?>

            <section class="tarjeta tarjeta-carga"
                     id="form-documentacion"
                     aria-labelledby="titulo-documento">

                <h3 id="titulo-documento">
                    2. Cargar documento
                </h3>


                <p class="paciente-seleccionado">

                    <strong>Paciente:</strong>

                    <?php
                    echo htmlspecialchars(
                        $paciente_actual['Nombre']
                        . ' '
                        . $paciente_actual['Apellido']
                        . ' — CI: '
                        . $paciente_actual['Cedula'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>


                <form action="nueva_documentacion.php#form-documentacion"
                      method="POST"
                      enctype="multipart/form-data"
                      class="form-carga-documento">

                    <input type="hidden"
                           name="token"
                           value="<?php echo $token; ?>">

                    <input type="hidden"
                           name="id_documento"
                           value="<?php echo (int) $id_documento; ?>">

                    <input type="hidden"
                           name="MAX_FILE_SIZE"
                           value="2097152">


                    <div class="campo-carga">

                        <label for="titulo">
                            Título del documento
                        </label>

                        <input
                            type="text"
                            id="titulo"
                            name="titulo"
                            list="titulos-documentos"
                            maxlength="100"
                            required
                            placeholder="Ej: Prevención de infecciones"
                            value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>">


                        <datalist id="titulos-documentos">

                            <?php foreach ($catalogo as $nombre_documento) { ?>

                                <option value="<?php echo htmlspecialchars($nombre_documento, ENT_QUOTES, 'UTF-8'); ?>"></option>

                            <?php } ?>

                        </datalist>

                    </div>


                    <div class="campo-carga">

                        <label for="descripcion">
                            Descripción (opcional)
                        </label>

                        <textarea
                            id="descripcion"
                            name="descripcion"
                            rows="3"
                            maxlength="255"
                            placeholder="Agregue una breve descripción"><?php echo htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?></textarea>

                    </div>


                    <div class="campo-carga">

                        <label for="archivo">
                            Seleccionar archivo PDF
                        </label>

                        <input
                            type="file"
                            id="archivo"
                            name="archivo"
                            accept=".pdf,application/pdf"
                            aria-describedby="ayuda-archivo"
                            required>

                        <small id="ayuda-archivo">
                            Seleccione un PDF de hasta 2 MB.
                        </small>

                    </div>


                    <button type="submit"
                            name="subir_pdf"
                            class="boton-cargar-documento">

                        <i class="fa-solid fa-upload"
                           aria-hidden="true"></i>

                        Guardar documento

                    </button>

                </form>

            </section>

        <?php } ?>

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