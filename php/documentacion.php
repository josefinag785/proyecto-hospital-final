<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Montevideo');
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');

// COMPROBAR QUE INGRESÓ UN ADMINISTRADOR ACTIVO

$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$sql = "SELECT ID_Usuario, Cedula
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

$administrador = $resultado_usuario->fetch_assoc();
$cedula_admin_sql = $con->real_escape_string($administrador['Cedula']);

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');


// PREPARAR EL CIERRE DE SESIÓN

if (!isset($_SESSION['token_documentacion_admin'])) {
    $_SESSION['token_documentacion_admin'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_documentacion_admin'];

if (isset($_POST['cerrar_sesion'])) {
    $token_enviado = $_POST['token'] ?? '';

    if (!is_string($token_enviado) || !hash_equals($token, $token_enviado)) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $_SESSION = [];
    session_destroy();

    header('Location: /prueba/php/login.php');
    exit;
}


// VARIABLES INICIALES
$mensaje = '';
$id_modificar = 0;
$id_documento = 0;
$id_paciente = 0;
$id_funcionario = 0;
$cedula = '';
$nombre = '';
$accion = $_GET['accion'] ?? '';
if (isset($_POST['crear_documentacion'])) $accion = 'crear';
if (isset($_POST['subir_pdf'])) $accion = 'cargar';
if (isset($_POST['buscar'])) $accion = 'buscar';

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
            $sql = "SELECT Documento.ID_Documento
                    FROM Documento
                    INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
                    WHERE Documento.ID_Documento = $id_documento
                      AND Paciente.Estado = 'Activo' ";
            $resultadoDocumento = $con->query($sql);
            if (!$resultadoDocumento) {
                $mensaje = 'Error al modificar el médico responsable.';
                error_log($con->error);
            } elseif ($resultadoDocumento->num_rows == 0) {
                $mensaje = 'El espacio no está disponible o el paciente está dado de baja.';
            } else {

                // Guardar el nuevo médico.
                $sql = "UPDATE Documento
                        SET Aviso_Leido = IF(ID_Funcionario = $id_funcionario, Aviso_Leido, 0),
                            ID_Funcionario = $id_funcionario
                        WHERE ID_Documento = $id_documento
                          AND EXISTS (
                              SELECT 1 FROM Paciente
                              WHERE Paciente.ID_Paciente = Documento.ID_Paciente
                                AND Paciente.Estado = 'Activo'
                          )";
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

// CREAR UN ESPACIO DE DOCUMENTACIÓN
if (isset($_POST['crear_documentacion'])) {
    $paciente_activo = false;
    if ($id_paciente > 0) {
        $consulta_paciente = $con->query("SELECT ID_Paciente FROM Paciente WHERE ID_Paciente = $id_paciente AND Estado = 'Activo'");
        $paciente_activo = $consulta_paciente && $consulta_paciente->num_rows == 1;
    }
    if ($id_paciente < 1 || !$paciente_activo) {
        $mensaje = 'Debe seleccionar un paciente activo.';
    } elseif ($id_funcionario < 1) {
        $mensaje = 'Debe seleccionar un médico.';
    } else {
        // Comprobar si el paciente ya tiene un espacio, sin importar el médico.
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
                        (Fecha_Emision, Tipo, Descripcion,
                         ID_Paciente, ID_Funcionario, Estado)
                        SELECT '$fecha', 'Documentación médica',
                               'Espacio de documentación del paciente.',
                               Paciente.ID_Paciente, $id_funcionario, 'Pendiente'
                        FROM Paciente
                        WHERE Paciente.ID_Paciente = $id_paciente
                          AND Paciente.Estado = 'Activo'";

                if ($con->query($sql) && $con->affected_rows == 1) {
                    header(
                        'Location: documentacion.php?mensaje=documentacion_creada',
                        true,
                        303
                    );
                    exit;
                } elseif ($con->errno == 1062) {
                    // La base puede impedir también los espacios duplicados.
                    $mensaje = 'Este paciente ya tiene un espacio de documentación.';
                } else {
                    $mensaje = 'Error al crear el espacio. Compruebe que el paciente siga registrado.';
                    error_log($con->error);
                }
            }
        }
    }
}

$aviso_carga = $_SESSION['aviso_carga_documentacion'] ?? '';
unset($_SESSION['aviso_carga_documentacion']);

// CARGAR UN PDF EN UN ESPACIO EXISTENTE

$error_carga = '';
$titulo_pdf = '';
$descripcion_pdf = '';
$id_espacio_carga = 0;
$espacios = [];

$sql = "SELECT Documento.ID_Documento, Persona.Nombre,
               Persona.Apellido, Persona.Cedula
        FROM Documento
        INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula
        WHERE Paciente.Estado = 'Activo'";
$sql .= ' ORDER BY Persona.Apellido, Persona.Nombre';
$resultado_espacios = $con->query($sql);
if (!$resultado_espacios) {
    error_log($con->error);
    $error_carga = 'No se pudieron consultar los espacios de documentación.';
} else {
    while ($espacio = $resultado_espacios->fetch_assoc()) {
        $espacios[] = $espacio;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    $error_carga = 'No se pudo recibir el formulario. Seleccione un PDF de hasta 2 MB.';
}

if (isset($_POST['subir_pdf'])) {
    $token_enviado = $_POST['token'] ?? '';
    if (!is_string($token_enviado) || !hash_equals($token, $token_enviado)) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    $id_espacio_carga = filter_var($_POST['id_espacio_carga'] ?? null, FILTER_VALIDATE_INT);
    if (is_string($_POST['titulo_pdf'] ?? null)) {
        $titulo_pdf = trim($_POST['titulo_pdf']);
    }
    if (is_string($_POST['descripcion_pdf'] ?? null)) {
        $descripcion_pdf = trim($_POST['descripcion_pdf']);
    }
    $espacio_valido = false;
    foreach ($espacios as $espacio) {
        if ($id_espacio_carga === (int) $espacio['ID_Documento']) {
            $espacio_valido = true;
        }
    }
    $archivo = $_FILES['archivo'] ?? [];
    $limite_archivo = 2 * 1024 * 1024;

    if (!$espacio_valido) {
        $error_carga = 'Seleccione un espacio de documentación de un paciente activo.';
    } elseif ($titulo_pdf == '' || mb_strlen($titulo_pdf, 'UTF-8') > 100) {
        $error_carga = 'Escriba un título de hasta 100 caracteres.';
    } elseif (mb_strlen($descripcion_pdf, 'UTF-8') > 255) {
        $error_carga = 'La descripción puede tener hasta 255 caracteres.';
    } elseif (!isset($archivo['error']) || !is_int($archivo['error'])) {
        $error_carga = 'Seleccione un archivo PDF.';
    } elseif ($archivo['error'] == UPLOAD_ERR_INI_SIZE || $archivo['error'] == UPLOAD_ERR_FORM_SIZE) {
        $error_carga = 'El PDF debe pesar como máximo 2 MB.';
    } elseif ($archivo['error'] != UPLOAD_ERR_OK) {
        $error_carga = 'No se pudo recibir el PDF. Selecciónelo nuevamente.';
    } elseif (!is_string($archivo['tmp_name'] ?? null)
        || !is_string($archivo['name'] ?? null)
        || !is_uploaded_file($archivo['tmp_name'])) {
        $error_carga = 'No se pudo recibir el archivo seleccionado.';
    } elseif ($archivo['size'] < 1 || $archivo['size'] > $limite_archivo) {
        $error_carga = 'El PDF debe tener contenido y pesar como máximo 2 MB.';
    } elseif (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) != 'pdf') {
        $error_carga = 'Solo se pueden subir archivos PDF.';
    }

    if ($error_carga == '') {
        $informacion = new finfo(FILEINFO_MIME_TYPE);
        if ($informacion->file($archivo['tmp_name']) != 'application/pdf') {
            $error_carga = 'Solo se pueden subir archivos PDF.';
        }
    }
    if ($error_carga == '') {
        $contenido = file_get_contents($archivo['tmp_name']);
        if ($contenido === false || substr($contenido, 0, 5) != '%PDF-') {
            $error_carga = 'No se pudo leer un PDF válido.';
        }
    }

    // GUARDAR EL ARCHIVO Y SU AUTOR JUNTOS
    if ($error_carga == '') {
        $correcto = $con->begin_transaction();
        if ($correcto) {
            $sql = "SELECT Cedula FROM Persona
                    WHERE Cedula = '$cedula_admin_sql' FOR UPDATE";
            $bloqueo_admin = $con->query($sql);
            $correcto = $bloqueo_admin && $bloqueo_admin->num_rows == 1;
        }
        if ($correcto) {
            $sql = "SELECT Documento.ID_Documento
                    FROM Paciente
                    INNER JOIN Documento ON Paciente.ID_Paciente = Documento.ID_Paciente
                    WHERE Documento.ID_Documento = $id_espacio_carga
                      AND Paciente.Estado = 'Activo'
                    FOR UPDATE";
            $bloqueo = $con->query($sql);
            $correcto = $bloqueo && $bloqueo->num_rows == 1;
        }

        // El autor es el administrador que ingresó, sin cambiar el médico responsable.
        if ($correcto) {
            $sql = "SELECT ID_Funcionario FROM Funcionario
                    WHERE Cedula = '$cedula_admin_sql'
                    ORDER BY ID_Funcionario LIMIT 1";
            $resultado_funcionario = $con->query($sql);
            $correcto = $resultado_funcionario !== false;
            if ($correcto && $resultado_funcionario->num_rows > 0) {
                $funcionario = $resultado_funcionario->fetch_assoc();
                $id_autor = (int) $funcionario['ID_Funcionario'];
            } elseif ($correcto) {
                $sql = "INSERT INTO Funcionario (Cedula, Tipo)
                        VALUES ('$cedula_admin_sql', 'Administrador')";
                $correcto = $con->query($sql);
                $id_autor = (int) $con->insert_id;
            }
        }
        if ($correcto) {
            $fecha_carga = date('Y-m-d H:i:s');
            $sql = "INSERT INTO Archivo_Documento
                    (ID_Documento, ID_Funcionario, Titulo, Descripcion, Archivo, Fecha_Carga, Publicado)
                    VALUES (?, ?, ?, ?, ?, ?, 1)";
            $consulta_carga = $con->prepare($sql);
            $correcto = false;
            if ($consulta_carga) {
                $consulta_carga->bind_param('iissss', $id_espacio_carga, $id_autor,
                    $titulo_pdf, $descripcion_pdf, $contenido, $fecha_carga);
                $correcto = $consulta_carga->execute() && $consulta_carga->affected_rows == 1;
                $consulta_carga->close();
            }
        }
        if ($correcto) {
            $sql = "UPDATE Documento SET Estado = 'Con documentación'
                    WHERE ID_Documento = $id_espacio_carga";
            $correcto = $con->query($sql);
        }
        if ($correcto) {
            $correcto = $con->commit();
        }
        if ($correcto) {
            $_SESSION['aviso_carga_documentacion'] = 'PDF cargado correctamente. Ya está disponible para el paciente.';
            header('Location: documentacion.php?accion=cargar#acciones-documentacion', true, 303);
            exit;
        }
        error_log('No se pudo cargar la documentación: ' . $con->error);
        $con->rollback();
        $error_carga = 'No se pudo guardar el PDF. Intente nuevamente.';
    }
}

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
                   AND Paciente.Estado = 'Activo'
                 ORDER BY Persona.Apellido, Persona.Nombre";
$resultadoPacientes = $con->query($sqlPacientes);

// OBTENER MÉDICOS ACTIVOS
$sqlMedicos = "SELECT
                  Funcionario.ID_Funcionario,
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
$sqlDocumentos = "SELECT Documento.ID_Documento, Documento.Fecha_Emision,
                        Documento.Tipo, Documento.Descripcion, Documento.Estado,
                        Documento.ID_Funcionario, Paciente.ID_Paciente,
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
if ($error_carga != '' || $aviso_carga != '') {
    $accion = 'cargar';
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


        <!-- INICIO -->

        <a href="/prueba/panelprincipal.php">

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

        <form method="POST" class="form-salir">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <button type="submit" name="cerrar_sesion" class="salir-medico">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                Cerrar sesión
            </button>
        </form>

    </aside>

    <!-- CONTENIDO -->
    <main class="contenido pagina-documentacion">

        <h2 class="titulo-panel">Documentación de pacientes</h2>
        <p class="subtitulo-panel">Seleccione una acción o consulte los espacios de documentación.</p>

        <form action="documentacion.php#acciones-documentacion" method="GET"
              class="botones-busqueda-documentacion" id="acciones-documentacion">
            <button type="submit" name="accion" value="crear" class="boton-agendar">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Crear espacio
            </button>
            <button type="submit" name="accion" value="cargar" class="boton-agendar">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Cargar PDF
            </button>
            <button type="submit" formaction="qr_documentacion.php" class="boton-agendar">
                <i class="fa-solid fa-qrcode" aria-hidden="true"></i> QR para pacientes
            </button>
        </form>

        <?php
        if ($mensaje != '') {
            echo '<p class="subtitulo-panel" role="status">'
                . htmlspecialchars($mensaje)
                . '</p>';
        }
        ?>

        <?php if ($accion === 'crear') { ?>
        <!-- CREAR DOCUMENTACIÓN -->
        <div class="tarjeta" id="crear-espacio">

            <h3>Crear espacio de documentación</h3>

            <form action="documentacion.php?accion=crear#acciones-documentacion" method="POST">
                <div class="fila-pacientes">
                    <div class="campo-pacientes">
                        <label for="id_paciente">
                            Paciente
                        </label>
                        <select
                            id="id_paciente"
                            name="id_paciente"
                            required>
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
                        <select
                            id="id_funcionario"
                            name="id_funcionario"
                            required>
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
                <div class="botones-busqueda-documentacion">
                    <button
                        type="submit"
                        name="crear_documentacion"
                        class="boton-agendar">
                        <i class="fa-solid fa-plus"></i>
                        Crear espacio
                    </button>
                    <a href="documentacion.php#acciones-documentacion" class="ver-pdf-medico">Cancelar</a>
                </div>
            </form>
        </div>
        <?php } ?>

        <?php if ($accion === 'cargar') { ?>
        <!-- CARGAR UN PDF EN EL ESPACIO DE UN PACIENTE -->
        <div class="tarjeta" id="cargar-documentacion">
            <h3>Cargar PDF</h3>
            <p>Seleccione el espacio de un paciente y adjunte el PDF que desea dejar disponible.</p>

            <?php if ($aviso_carga != '') { ?>
                <p class="aviso-medico" role="status"><?php echo htmlspecialchars($aviso_carga, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>

            <?php if ($error_carga != '') { ?>
                <p class="aviso-medico" role="alert"><?php echo htmlspecialchars($error_carga, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>

            <?php if (count($espacios) == 0) { ?>
                <p>No hay espacios de pacientes activos disponibles para cargar archivos.</p>
                <a href="documentacion.php?accion=crear#acciones-documentacion" class="ver-pdf-medico">Crear un espacio</a>
            <?php } else { ?>
                <form action="documentacion.php?accion=cargar#acciones-documentacion"
                      method="POST" enctype="multipart/form-data" class="form-documentacion-medico">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">

                    <div class="campo-pacientes">
                        <label for="id_espacio_carga">Espacio de documentación del paciente</label>
                        <select id="id_espacio_carga" name="id_espacio_carga" required>
                            <option value="">Seleccione un paciente</option>
                            <?php foreach ($espacios as $espacio) { ?>
                                <option value="<?php echo (int) $espacio['ID_Documento']; ?>"
                                    <?php if ($id_espacio_carga === (int) $espacio['ID_Documento']) { echo 'selected'; } ?>>
                                    <?php echo htmlspecialchars($espacio['Nombre'] . ' ' . $espacio['Apellido'] . ' — C.I. ' . $espacio['Cedula'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="campo-pacientes">
                        <label for="titulo_pdf">Título del documento</label>
                        <input type="text" id="titulo_pdf" name="titulo_pdf" maxlength="100"
                               list="titulos_pdf" placeholder="Escriba un título o elija una sugerencia"
                               value="<?php echo htmlspecialchars($titulo_pdf, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <datalist id="titulos_pdf">
                            <?php foreach ($catalogo as $sugerencia) { ?>
                                <option value="<?php echo htmlspecialchars($sugerencia, ENT_QUOTES, 'UTF-8'); ?>"></option>
                            <?php } ?>
                        </datalist>
                    </div>

                    <div class="campo-pacientes">
                        <label for="descripcion_pdf">Descripción (opcional)</label>
                        <textarea id="descripcion_pdf" name="descripcion_pdf" maxlength="255" rows="3"><?php echo htmlspecialchars($descripcion_pdf, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="campo-pacientes">
                        <label for="archivo">Archivo PDF (hasta 2 MB)</label>
                        <input type="file" id="archivo" name="archivo" accept=".pdf,application/pdf" required>
                    </div>

                    <div class="botones-busqueda-documentacion">
                    <button type="submit" name="subir_pdf" class="boton-agendar">
                            <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i> Cargar PDF
                        </button>
                    <a href="documentacion.php#acciones-documentacion" class="ver-pdf-medico">Cancelar</a>
                </div>
                </form>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- ESPACIOS DE DOCUMENTACIÓN -->
        <div class="tarjeta espacios-documentacion" id="espacios-documentacion">

            <h3>Espacios de documentación</h3>

            <?php if ($accion === 'buscar') { ?>
            <!-- BUSCADOR COMO EL DE PACIENTES -->
            <form action="documentacion.php#espacios-documentacion"
                  method="POST" class="busqueda-documentacion">
                <div class="fila-pacientes">
                    <div class="campo-pacientes">
                        <label for="cedula">Cédula</label>
                        <input type="text" id="cedula" name="cedula"
                               placeholder="Ej: 4.123.456-7"
                               value="<?php echo htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="campo-pacientes">
                        <label for="nombre">Nombre o apellido</label>
                        <input type="text" id="nombre" name="nombre"
                               placeholder="Ej: Juan"
                               value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="botones-busqueda-documentacion">
                    <button type="submit" name="buscar" class="boton-agendar">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Buscar
                    </button>
                    <button type="submit" name="mostrar_todos" class="boton-agendar">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        Mostrar todos
                    </button>
                    <a href="documentacion.php#espacios-documentacion" class="ver-pdf-medico">Cancelar</a>
                </div>
            </form>
            <?php } else { ?>
                <form action="documentacion.php#espacios-documentacion" method="GET" class="busqueda-documentacion">
                    <button type="submit" name="accion" value="buscar" class="boton-agendar">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar paciente
                    </button>
                </form>
            <?php } ?>
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
                        <details class="tarjeta-documentacion"
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
                                <form action="documentacion.php#documentacion_<?php echo (int) $documento['ID_Documento']; ?>"
                                      method="POST" class="acciones-pacientes">
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
                                    <form action="documentacion.php" method="POST" class="form-medico-documentacion">
                                        <input
                                            type="hidden"
                                            name="id_documento"
                                            value="<?php echo (int) $documento['ID_Documento']; ?>">
                                        <label
                                            for="medico_<?php echo (int) $documento['ID_Documento']; ?>">
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
                                <form action="ver_documentacion.php" method="GET">
                                    <button type="submit"
                                            name="documento"
                                            value="<?php echo (int) $documento['ID_Documento']; ?>"
                                            class="ver-documentacion">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        Ver documentación
                                    </button>
                                </form>
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
