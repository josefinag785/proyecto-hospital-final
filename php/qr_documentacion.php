<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

// COMPROBAR QUE INGRESÓ UN ADMINISTRADOR
$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);
if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}
$sql = "SELECT ID_Usuario FROM Usuario
        WHERE ID_Usuario = $id_usuario
          AND Rol = 'Administrador' AND Estado = 'Activo'";
$resultado = $con->query($sql);
if (!$resultado) {
    error_log($con->error);
    http_response_code(500);
    exit('No se pudo comprobar el acceso. Intente nuevamente.');
}
if ($resultado->num_rows == 0) {
    http_response_code(403);
    exit('Esta página está disponible para administradores con una cuenta activa.');
}

// CERRAR SESIÓN
if (!isset($_SESSION['token_qr'])) {
    $_SESSION['token_qr'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_qr'];
if (isset($_POST['cerrar_sesion'])) {
    $token_enviado = $_POST['token'] ?? '';
    if (!is_string($token_enviado) || !hash_equals($token, $token_enviado)) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: /prueba/php/login.php', true, 303);
    exit;
}

// 1. BUSCAR SOLAMENTE PDF PUBLICADOS DE PACIENTES ACTIVOS
$id_archivo = filter_var($_GET['archivo'] ?? 0, FILTER_VALIDATE_INT);
if (isset($_GET['archivo']) && (!$id_archivo || $id_archivo < 1)) {
    http_response_code(404);
    exit('Documento no disponible.');
}

$sql = "SELECT Archivo_Documento.ID_Archivo, Archivo_Documento.ID_Documento,
               Archivo_Documento.Titulo, Persona.Nombre, Persona.Apellido
        FROM Archivo_Documento
        INNER JOIN Documento ON Archivo_Documento.ID_Documento = Documento.ID_Documento
        INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula
        WHERE Archivo_Documento.Publicado = 1
          AND Archivo_Documento.Archivo IS NOT NULL
          AND Paciente.Estado = 'Activo'";
if ($id_archivo > 0) {
    $sql .= " AND Archivo_Documento.ID_Archivo = $id_archivo";
}
$sql .= " ORDER BY Persona.Apellido, Persona.Nombre, Archivo_Documento.Titulo";
$documentos = $con->query($sql);
if (!$documentos) {
    error_log($con->error);
    http_response_code(500);
    exit('No se pudieron consultar los documentos. Intente nuevamente.');
}
$archivo = null;
if ($id_archivo > 0) {
    $archivo = $documentos->fetch_assoc();
    if (!$archivo) {
        http_response_code(404);
        exit('Documento no disponible.');
    }
}

// 2. ARMAR EL ENLACE CON EL NÚMERO DEL PDF ELEGIDO
// Esta es la dirección que ya probamos desde el celular.
// Si cambia la IP de la computadora o se publica el sistema, actualizar esta línea.
$direccion_sistema = 'http://192.168.1.23/prueba';
$enlace = $direccion_sistema . '/php/PACIENTE/documentacion_paciente.php?archivo=' . $id_archivo;
$imagen_qr = 'qr_documentacion.php?archivo=' . $id_archivo . '&imagen=1';
$hay_generador = is_file(__DIR__ . '/qrcode.php');
$hay_gd = function_exists('imagepng');

// 3. LA LIBRERÍA QRCODE CREA LA IMAGEN A PARTIR DEL ENLACE
if (isset($_GET['imagen'])) {
    if (!$archivo) {
        http_response_code(404);
        exit('Elija un documento publicado.');
    }
    if (!$hay_generador || !$hay_gd) {
        http_response_code(500);
        exit('Se necesita qrcode.php y la extensión GD de PHP para generar el QR.');
    }
    require_once __DIR__ . '/qrcode.php';
    $qr = QRCode::getMinimumQRCode($enlace, QR_ERROR_CORRECT_LEVEL_M);
    $imagen = $qr->createImage(8, 32); // Cuadrados de 8 píxeles y borde blanco de 32.
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    if (isset($_GET['descargar'])) {
        header('Content-Disposition: attachment; filename="qr_documento_' . $id_archivo . '.png"');
    }
    imagepng($imagen);
    imagedestroy($imagen);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR del documento | Hospital de Clínicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/prueba/css/style.css">
</head>
<body class="pagina-documentacion pagina-qr">
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
    <main class="contenido pagina-documentacion">
        <h2 class="titulo-panel no-imprimir">QR del documento</h2>
        <p class="subtitulo-panel no-imprimir">
            Cada PDF publicado tiene su propio QR. El paciente ingresa con su cuenta para abrirlo.
        </p>

        <?php if (!$archivo) { ?>
            <section class="tarjeta">
                <h3>Elegir documento</h3>
                <?php if ($documentos->num_rows > 0) { ?>
                    <form action="qr_documentacion.php" method="GET" class="form-documentacion-medico">
                        <div class="campo-pacientes">
                            <label for="archivo">Paciente y documento publicado</label>
                            <select id="archivo" name="archivo" required>
                                <option value="">Seleccione un documento</option>
                                <?php while ($opcion = $documentos->fetch_assoc()) { ?>
                                    <option value="<?php echo (int) $opcion['ID_Archivo']; ?>">
                                        <?php echo htmlspecialchars($opcion['Apellido'] . ', ' . $opcion['Nombre'] . ' — ' . $opcion['Titulo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <button type="submit" class="boton-agendar">Mostrar QR</button>
                    </form>
                <?php } else { ?>
                    <p>No hay documentos publicados de pacientes activos.</p>
                    <p>Publique un PDF desde Ver documentación para generar su QR.</p>
                <?php } ?>
            </section>
        <?php } else { ?>
            <section class="tarjeta tarjeta-qr">
                <h3>Hospital de Clínicas</h3>
                <p><strong><?php echo htmlspecialchars($archivo['Titulo'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                <p>Paciente: <?php echo htmlspecialchars($archivo['Nombre'] . ' ' . $archivo['Apellido'], ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if ($hay_generador && $hay_gd) { ?>
                    <img src="<?php echo htmlspecialchars($imagen_qr, ENT_QUOTES, 'UTF-8'); ?>" class="imagen-qr"
                         alt="QR para abrir este documento" width="320" height="320">
                    <p>Escanee el código e ingrese con su cédula y contraseña para abrir este PDF.</p>
                    <p class="no-imprimir">
                        <a href="<?php echo htmlspecialchars($imagen_qr . '&descargar=1', ENT_QUOTES, 'UTF-8'); ?>" class="ver-pdf-medico">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> Descargar QR
                        </a>
                    </p>
                    <p class="no-imprimir">Para imprimir esta tarjeta, use <strong>Ctrl + P</strong>.</p>
                <?php } elseif (!$hay_generador) { ?>
                    <p>Agregue el archivo qrcode.php en la carpeta php del proyecto.</p>
                <?php } else { ?>
                    <p>Se necesita activar la extensión GD de PHP para generar la imagen.</p>
                <?php } ?>
            </section>

            <p class="no-imprimir">
                <a href="ver_documentacion.php?documento=<?php echo (int) $archivo['ID_Documento']; ?>&amp;archivo=<?php echo $id_archivo; ?>" class="ver-pdf-medico" target="_blank" rel="noopener noreferrer">Ver PDF</a>
            </p>
            <p class="no-imprimir"><a href="qr_documentacion.php">Elegir otro documento</a></p>
        <?php } ?>
        <p class="no-imprimir"><a href="ver_documentacion.php">Volver a Ver documentación</a></p>
    </main>
</div>
<footer>
    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php
$con->close();
?>
