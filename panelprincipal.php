<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);
if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

try {
    require_once __DIR__ . '/php/conexion.php';
    $con->set_charset('utf8mb4');

    // COMPROBAR QUE ES UN ADMINISTRADOR ACTIVO
    $acceso = $con->query("SELECT ID_Usuario FROM Usuario
        WHERE ID_Usuario = $id_usuario AND Rol = 'Administrador' AND Estado = 'Activo'");
    if ($acceso->num_rows == 0) {
        http_response_code(403);
        exit('Esta página está disponible para administradores con una cuenta activa.');
    }

    // PROTEGER EL CIERRE DE SESIÓN
    if (!isset($_SESSION['token_panel'])) {
        $_SESSION['token_panel'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['token_panel'];
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!is_string($_POST['token'] ?? null) || !hash_equals($token, $_POST['token'])) {
            http_response_code(403);
            exit('Recargue la página y vuelva a intentarlo.');
        }
        if (isset($_POST['cerrar_sesion'])) {
            $_SESSION = [];
            session_destroy();
            header('Location: /prueba/php/login.php', true, 303);
            exit;
        }
    }

    // CONSULTAR SI HAY PENDIENTES
    $solicitudes = $con->query("SELECT Usuario.ID_Usuario FROM Usuario
        INNER JOIN Persona ON Usuario.Cedula = Persona.Cedula
        WHERE (Usuario.Rol = 'Pendiente' AND Usuario.Estado = 'Pendiente')
           OR (Usuario.Rol = 'Paciente' AND Usuario.Estado = 'Activo'
               AND Usuario.Solicitud_Funcionario = 1)
        LIMIT 1");

    $documentos = $con->query("SELECT Archivo_Documento.ID_Archivo FROM Archivo_Documento
        INNER JOIN Documento ON Archivo_Documento.ID_Documento = Documento.ID_Documento
        INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
        WHERE Archivo_Documento.Publicado = 0
          AND Archivo_Documento.Archivo IS NOT NULL AND Paciente.Estado = 'Activo'
        LIMIT 1");

    $hay_solicitudes = $solicitudes->num_rows > 0;
    $hay_documentos = $documentos->num_rows > 0;
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo cargar el panel. Intente nuevamente.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal | Hospital de Clínicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/prueba/css/style.css">
</head>
<body>
<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital" class="logo">
        <h1>HOSPITAL DE CLÍNICAS</h1>
    </div>
</header>

<div class="perfil-contenedor">
    <aside class="menu-lateral">
        <h3>Menú</h3>
        <a href="/prueba/panelprincipal.php" aria-current="page">
            <i class="fa-solid fa-house" aria-hidden="true"></i> Inicio
        </a>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Pacientes</summary>
            <div class="submenu">
                <a href="/prueba/php/nuevo_paciente.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo paciente</a>
                <a href="/prueba/php/ver_paciente.php"><i class="fa-solid fa-list" aria-hidden="true"></i> Ver pacientes</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-truck-medical" aria-hidden="true"></i> Ambulancias</summary>
            <div class="submenu">
                <a href="/prueba/php/nuevo_traslado.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nuevo registro</a>
                <a href="/prueba/php/ver_traslados.php"><i class="fa-solid fa-list" aria-hidden="true"></i> Ver registros</a>
                <a href="/prueba/php/Gestion_Ambulancia.php"><i class="fa-solid fa-cogs" aria-hidden="true"></i> Gestión de Ambulancias</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-file-alt" aria-hidden="true"></i> Documentación</summary>
            <div class="submenu">
                <a href="/prueba/php/documentacion.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nueva documentación</a>
                <a href="/prueba/php/ver_documentacion.php"><i class="fa-solid fa-list" aria-hidden="true"></i> Ver documentación</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Usuarios</summary>
            <div class="submenu">
                <a href="/prueba/php/panel_admin.php"><i class="fa-solid fa-plus" aria-hidden="true"></i> Solicitudes</a>
                <a href="/prueba/php/lista_usuarios.php"><i class="fa-solid fa-list" aria-hidden="true"></i> Lista de Usuarios</a>
            </div>
        </details>

        <form action="/prueba/panelprincipal.php" method="POST" class="form-salir">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <button type="submit" name="cerrar_sesion" class="salir-medico">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Cerrar sesión
            </button>
        </form>
    </aside>

    <main class="contenido">
    <h2 class="titulo-panel">Panel principal</h2>
    <p class="subtitulo-panel">
        Revise los pendientes y consulte el orden de trabajo.
    </p>

    <div class="panel-fila">
        <section class="panel-contenedor">
            <div class="panel-titulo">
                <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                <h2>Solicitudes de funcionarios</h2>
            </div>

            <?php if ($hay_solicitudes) { ?>
                <p>Hay solicitudes de funcionarios por revisar.</p>
                <a href="/prueba/php/panel_admin.php#solicitudes" class="ver-pdf-medico">
                    Revisar solicitudes
                </a>
            <?php } else { ?>
                <p>No hay solicitudes pendientes.</p>
            <?php } ?>
        </section>

        <section class="panel-contenedor">
            <div class="panel-titulo">
                <i class="fa-solid fa-file-medical" aria-hidden="true"></i>
                <h2>Documentos por publicar</h2>
            </div>

            <?php if ($hay_documentos) { ?>
                <p>Hay documentos médicos por revisar y publicar.</p>
                <a href="/prueba/php/ver_documentacion.php#documentos" class="ver-pdf-medico">
                    Revisar documentos
                </a>
            <?php } else { ?>
                <p>No hay documentos pendientes de publicación.</p>
            <?php } ?>
        </section>
    </div>

    <div class="panel-fila">
        <section class="panel-contenedor">
            <div class="panel-titulo">
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                <h2>Documentación: paso a paso</h2>
            </div>

            <ol>
                <li>Registrar al paciente, crear su espacio y asignar un médico.</li>
                <li>El médico carga los archivos PDF.</li>
                <li>El administrador revisa y publica los documentos.</li>
                <li>El paciente los consulta desde su cuenta o mediante el QR.</li>
            </ol>
        </section>

        <section class="panel-contenedor">
            <div class="panel-titulo">
                <i class="fa-solid fa-truck-medical" aria-hidden="true"></i>
                <h2>Traslados: paso a paso</h2>
            </div>

            <ol>
                <li>Registrar el traslado y asignar una ambulancia y un conductor.</li>
                <li>El conductor completa las horas y actualiza el estado desde el celular.</li>
                <li>El conductor marca «Finalizado» al completar el traslado y el regreso al hospital.</li>
                <li>El administrador consulta los cambios en «Ver registros».</li>
            </ol>
        </section>
    </div>
</main>
</div>

<footer>
    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php $con->close(); ?>
