<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$codigo = '';
$mensaje = '';
$paciente = null;
$archivos = null;
$transaccion = false;
$es_titular = false;
$encuesta_respondida = false;
$url = 'documentacion_paciente.php';

if (isset($_GET['codigo'])) {
    if (!is_string($_GET['codigo']) || !preg_match('/^[a-f0-9]{64}$/', $_GET['codigo'])) {
        http_response_code(404);
        exit('Documentación no disponible.');
    }
    $codigo = $_GET['codigo'];
    $url .= '?codigo=' . urlencode($codigo);
}

// 1. COMPROBAR LA CUENTA QUE INICIÓ SESIÓN
$id_usuario = 0;
if (isset($_SESSION['id_usuario'])) {
    $id_usuario = filter_var($_SESSION['id_usuario'], FILTER_VALIDATE_INT);
}
if (!$id_usuario || $id_usuario < 1) {
    $_SESSION['codigo_documentacion'] = $codigo;
    $archivo_qr = filter_var($_GET['archivo'] ?? 0, FILTER_VALIDATE_INT);
    $_SESSION['archivo_documentacion'] = $archivo_qr > 0 ? $archivo_qr : 0;
    header('Location: /prueba/php/login.php');
    exit;
}

try {
    $sql = "SELECT Cedula, Rol FROM Usuario
            WHERE ID_Usuario = $id_usuario AND Estado = 'Activo'";
    $resultado = $con->query($sql);
    if ($resultado->num_rows == 0) {
        $_SESSION = [];
        session_destroy();
        header('Location: /prueba/php/login.php');
        exit;
    }
    $usuario = $resultado->fetch_assoc();
    $cedula_sql = $con->real_escape_string($usuario['Cedula']);

    if (!isset($_SESSION['token_paciente'])) {
        $_SESSION['token_paciente'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['token_paciente'];

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_POST['token']) || !is_string($_POST['token'])
            || !hash_equals($token, $_POST['token'])) {
            http_response_code(403);
            exit('Recargue la página y vuelva a intentarlo.');
        }
    }

    if (isset($_POST['cerrar_sesion'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: /prueba/php/login.php', true, 303);
        exit;
    }

    // 2. BUSCAR SU PACIENTE Y SU ESPACIO DE DOCUMENTACIÓN
    if ($codigo == '') {
        $sql = "SELECT Paciente.ID_Paciente, Persona.Cedula,
                       Persona.Nombre, Persona.Apellido, Documento.ID_Documento
                FROM Paciente
                INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula
                LEFT JOIN Documento ON Paciente.ID_Paciente = Documento.ID_Paciente
                WHERE Paciente.Cedula = '$cedula_sql'
                  AND Paciente.Estado = 'Activo'
                ORDER BY Documento.ID_Documento DESC
                LIMIT 1";
    } else {
        $codigo_sql = $con->real_escape_string($codigo);
        $sql = "SELECT Paciente.ID_Paciente, Persona.Cedula,
                       Persona.Nombre, Persona.Apellido, Documento.ID_Documento
                FROM QR
                INNER JOIN Documento ON QR.ID_Documento = Documento.ID_Documento
                INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
                INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula
                WHERE QR.Codigo = '$codigo_sql'
                  AND Paciente.Estado = 'Activo'";

        // El administrador conserva la vista previa desde un QR.
        if ($usuario['Rol'] != 'Administrador') {
            $sql .= " AND Paciente.Cedula = '$cedula_sql'";
        }
    }

    $resultado = $con->query($sql);
    if ($resultado->num_rows > 0) {
        $paciente = $resultado->fetch_assoc();
        $id_paciente = (int) $paciente['ID_Paciente'];
        $id_documento = (int) $paciente['ID_Documento'];
        $es_titular = $paciente['Cedula'] == $usuario['Cedula'];
    } elseif ($codigo != '') {
        http_response_code(404);
        exit('Documentación no disponible para esta cuenta.');
    }

    // 3. ABRIR SOLAMENTE UN PDF DEL ESPACIO AUTORIZADO
    if (isset($_GET['archivo'])) {
        $id_archivo = filter_var($_GET['archivo'], FILTER_VALIDATE_INT);
        if (!$paciente || !$id_documento || !$id_archivo || $id_archivo < 1) {
            http_response_code(404);
            exit('Archivo no disponible.');
        }
        $sql = "SELECT Archivo FROM Archivo_Documento
                WHERE ID_Archivo = $id_archivo
                  AND ID_Documento = $id_documento
                  AND Archivo IS NOT NULL
                  AND Publicado = 1";
        $resultado = $con->query($sql);
        if ($resultado->num_rows == 0) {
            http_response_code(404);
            exit('Archivo no disponible.');
        }
        $archivo = $resultado->fetch_assoc();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="documento.pdf"');
        echo $archivo['Archivo'];
        $con->close();
        exit;
    }

    // COMPROBAR SI YA COMPLETÓ ESTA ENCUESTA
    if ($es_titular && $id_documento) {
        $sql_encuesta = "SELECT 1 FROM Encuesta
                        INNER JOIN Pregunta ON Pregunta.ID_Encuesta = Encuesta.ID_Encuesta
                        INNER JOIN Respuesta ON Respuesta.ID_Pregunta = Pregunta.ID_Pregunta
                        WHERE Encuesta.ID_Paciente = $id_paciente
                          AND Pregunta.Pregunta = '¿Cómo califica la información recibida?'
                        LIMIT 1";
        $encuesta_respondida = $con->query($sql_encuesta)->num_rows > 0;
    }

    // 4. GUARDAR LA ENCUESTA DEL PACIENTE QUE INGRESÓ
    if (isset($_POST['guardar_encuesta'])) {
        if (!$paciente || !$id_documento || !$es_titular) {
            http_response_code(403);
            exit('No se puede responder esta encuesta desde esta cuenta.');
        }
        $puntuacion = 0;
        if (isset($_POST['puntuacion'])) {
            $puntuacion = filter_var($_POST['puntuacion'], FILTER_VALIDATE_INT);
        }
        if ($encuesta_respondida) {
            $mensaje = 'La encuesta ya fue completada.';
        } elseif (!$puntuacion || $puntuacion < 1 || $puntuacion > 5) {
            $mensaje = 'Seleccione una opción de la encuesta.';
        } else {
            $con->begin_transaction();
            $transaccion = true;
            $con->query("SELECT ID_Paciente FROM Paciente
                         WHERE ID_Paciente = $id_paciente FOR UPDATE");
            if ($con->query($sql_encuesta . ' FOR UPDATE')->num_rows > 0) {
                $con->rollback();
                $transaccion = false;
                $_SESSION['mensaje_paciente'] = 'La encuesta ya fue completada.';
                header('Location: ' . $url . '#encuesta', true, 303);
                exit;
            }
            $sql = "INSERT INTO Encuesta (Fecha, ID_Paciente)
                    VALUES (CURDATE(), $id_paciente)";
            $con->query($sql);
            $id_encuesta = (int) $con->insert_id;

            $sql = "INSERT INTO Pregunta (Pregunta, ID_Encuesta)
                    VALUES ('¿Cómo califica la información recibida?', $id_encuesta)";
            $con->query($sql);
            $id_pregunta = (int) $con->insert_id;

            $sql = "INSERT INTO Respuesta (Respuesta, Puntuacion, Fecha, ID_Pregunta)
                    VALUES ('$puntuacion', $puntuacion, CURDATE(), $id_pregunta)";
            $con->query($sql);
            $con->commit();
            $transaccion = false;
            $_SESSION['mensaje_paciente'] = 'Gracias. Su respuesta fue guardada.';
            header('Location: ' . $url . '#encuesta', true, 303);
            exit;
        }
    }

    if (isset($_SESSION['mensaje_paciente'])) {
        $mensaje = $_SESSION['mensaje_paciente'];
        unset($_SESSION['mensaje_paciente']);
    }

    // 5. MOSTRAR LOS DOCUMENTOS DISPONIBLES
    if ($paciente && $id_documento) {
        $sql = "SELECT ID_Archivo, Titulo, Descripcion, Fecha_Carga
                FROM Archivo_Documento
                WHERE ID_Documento = $id_documento AND Archivo IS NOT NULL
                  AND Publicado = 1
                ORDER BY Fecha_Carga DESC, ID_Archivo DESC";
        $archivos = $con->query($sql);
    }
} catch (mysqli_sql_exception $error) {
    if ($transaccion) {
        $con->rollback();
    }
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo completar la operación. Intente nuevamente.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentación del paciente | Hospital de Clínicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/prueba/css/style.css">
</head>
<body class="pagina-documentacion">
<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital" class="logo">
        <h1>HOSPITAL DE CLÍNICAS</h1>
    </div>
</header>
<main class="contenido pagina-medico">
    <?php if ($es_titular || $codigo == '') { ?>
        <h2 class="titulo-panel">Mi documentación</h2>
    <?php } else { ?>
        <h2 class="titulo-panel">Documentación del paciente</h2>
    <?php } ?>

    <?php if ($paciente) { ?>
        <p class="subtitulo-panel"><?php echo htmlspecialchars($paciente['Nombre'] . ' ' . $paciente['Apellido'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php } ?>

    <form action="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
        <input type="hidden" name="token" value="<?php echo $token; ?>">
        <button type="submit" name="cerrar_sesion" class="boton-agendar">Cerrar sesión</button>
    </form>

    <div class="lista-documentacion">
        <section class="tarjeta">
            <h3>Documentos disponibles</h3>
            <?php if (!$archivos || $archivos->num_rows == 0) { ?>
                <p>Todavía no tiene documentación disponible. Cuando el hospital la agregue, podrá verla aquí.</p>
            <?php } else { ?>
                <div class="archivos-medico">
                    <?php while ($archivo = $archivos->fetch_assoc()) { ?>
                        <article class="archivo-medico">
                            <div>
                                <strong><?php echo htmlspecialchars($archivo['Titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($archivo['Descripcion'] != '') { ?>
                                    <p><?php echo htmlspecialchars($archivo['Descripcion'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php } ?>
                                <p>Fecha de carga: <?php echo htmlspecialchars($archivo['Fecha_Carga'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?><?php if ($codigo == '') { echo '?'; } else { echo '&amp;'; } ?>archivo=<?php echo (int) $archivo['ID_Archivo']; ?>"
                               class="ver-pdf-medico" target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Ver PDF
                            </a>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <?php if ($es_titular && $id_documento) { ?>
            <section class="tarjeta" id="encuesta">
                <h3><?php echo $encuesta_respondida ? 'Encuesta completada' : 'Encuesta de satisfacción'; ?></h3>
                <?php if ($mensaje != '') { ?>
                    <p class="aviso-medico" role="status"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php } ?>
                <?php if (!$encuesta_respondida) { ?>
                    <details>
                        <summary>Responder encuesta de satisfacción</summary>
                        <form action="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>#encuesta" method="POST" class="form-documentacion-medico">
                            <input type="hidden" name="token" value="<?php echo $token; ?>">
                            <div class="campo-pacientes">
                                <label for="puntuacion">¿Cómo califica la información recibida?</label>
                                <select id="puntuacion" name="puntuacion" required>
                                    <option value="">Seleccione una opción</option>
                                    <option value="1">1 — Muy mala</option>
                                    <option value="2">2 — Mala</option>
                                    <option value="3">3 — Regular</option>
                                    <option value="4">4 — Buena</option>
                                    <option value="5">5 — Muy buena</option>
                                </select>
                            </div>
                            <button type="submit" name="guardar_encuesta" class="boton-agendar">Enviar respuesta</button>
                        </form>
                    </details>
                <?php } else { ?>
                    <p>Ya completó esta encuesta. Gracias por su respuesta.</p>
                <?php } ?>
            </section>
        <?php } ?>
    </div>
</main>
<footer>
    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php
$con->close();
?>
