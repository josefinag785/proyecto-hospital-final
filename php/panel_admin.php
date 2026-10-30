<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$id_admin = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);
if (!$id_admin || $id_admin < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$roles = ['Medico' => 'Médico', 'Conductor' => 'Conductor', 'Administrador' => 'Administrador'];
$mensaje = $_SESSION['aviso_solicitudes'] ?? '';
unset($_SESSION['aviso_solicitudes']);
$transaccion = false;

try {
    // 1. COMPROBAR QUE INGRESÓ UN ADMINISTRADOR ACTIVO
    $sql = "SELECT ID_Usuario FROM Usuario
            WHERE ID_Usuario = $id_admin
              AND Rol = 'Administrador' AND Estado = 'Activo'";
    if ($con->query($sql)->num_rows == 0) {
        http_response_code(403);
        exit('Esta página está disponible para administradores con una cuenta activa.');
    }

    // 2. PROTEGER LOS FORMULARIOS Y EL CIERRE DE SESIÓN
    if (!isset($_SESSION['token_solicitudes'])) {
        $_SESSION['token_solicitudes'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['token_solicitudes'];
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

    // 3. ACEPTAR O RECHAZAR UNA SOLICITUD PENDIENTE
    if (isset($_POST['aceptar']) || isset($_POST['rechazar'])) {
        $id_usuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
        $rol = is_string($_POST['rol'] ?? null) ? $_POST['rol'] : '';
        $aceptar = isset($_POST['aceptar']);
        if (!$id_usuario || $id_usuario < 1 || ($aceptar && !isset($roles[$rol]))) {
            http_response_code(400);
            exit('Seleccione una solicitud y un rol válidos.');
        }

        $con->begin_transaction();
        $transaccion = true;
        $consulta = $con->prepare(
            'SELECT Cedula, Rol, Estado, Solicitud_Funcionario
             FROM Usuario WHERE ID_Usuario = ? FOR UPDATE'
        );
        $consulta->bind_param('i', $id_usuario);
        $consulta->execute();
        $usuario = $consulta->get_result()->fetch_assoc();

        $nueva = $usuario && $usuario['Rol'] == 'Pendiente' && $usuario['Estado'] == 'Pendiente';
        $paciente = $usuario && $usuario['Rol'] == 'Paciente'
            && $usuario['Estado'] == 'Activo' && (int) $usuario['Solicitud_Funcionario'] == 1;
        if (!$nueva && !$paciente) {
            $con->rollback();
            $transaccion = false;
            $_SESSION['aviso_solicitudes'] = 'La solicitud ya fue resuelta o no está disponible.';
            header('Location: panel_admin.php#solicitudes', true, 303);
            exit;
        }

        if ($aceptar) {
            // CREAR O ACTUALIZAR EL FUNCIONARIO CONSERVANDO SUS REGISTROS
            $cedula = $usuario['Cedula'];
            $consulta = $con->prepare('SELECT ID_Funcionario FROM Funcionario WHERE Cedula = ? FOR UPDATE');
            $consulta->bind_param('s', $cedula);
            $consulta->execute();
            $existe = $consulta->get_result()->num_rows > 0;
            if ($existe) {
                $consulta = $con->prepare('UPDATE Funcionario SET Tipo = ? WHERE Cedula = ?');
                $consulta->bind_param('ss', $rol, $cedula);
            } else {
                $consulta = $con->prepare('INSERT INTO Funcionario (Cedula, Tipo) VALUES (?, ?)');
                $consulta->bind_param('ss', $cedula, $rol);
            }
            $consulta->execute();

            // CAMBIAR EL ROL DE LA MISMA CUENTA Y QUITAR LA SOLICITUD
            $consulta = $con->prepare(
                "UPDATE Usuario SET Rol = ?, Estado = 'Activo', Solicitud_Funcionario = 0
                 WHERE ID_Usuario = ?"
            );
            $consulta->bind_param('si', $rol, $id_usuario);
            $consulta->execute();
            $aviso = 'Solicitud aprobada. Rol asignado: ' . $roles[$rol] . '.';
        } else {
            // UN PACIENTE CONSERVA SU CUENTA ACTIVA SI SE RECHAZA LA SOLICITUD
            $estado_cuenta = $paciente ? 'Activo' : 'Inactivo';
            $consulta = $con->prepare(
                'UPDATE Usuario SET Solicitud_Funcionario = 0, Estado = ? WHERE ID_Usuario = ?'
            );
            $consulta->bind_param('si', $estado_cuenta, $id_usuario);
            $consulta->execute();
            $aviso = $paciente
                ? 'Solicitud rechazada. El paciente conserva su cuenta activa.'
                : 'Solicitud rechazada. La cuenta nueva quedó inactiva.';
        }

        $con->commit();
        $transaccion = false;
        $_SESSION['aviso_solicitudes'] = $aviso;
        header('Location: panel_admin.php#solicitudes', true, 303);
        exit;
    }

    // 4. LISTAR CUENTAS NUEVAS Y SOLICITUDES DE PACIENTES ACTIVOS
    $sql = "SELECT Usuario.ID_Usuario, Usuario.Cedula, Usuario.Rol,
                   Persona.Nombre, Persona.Apellido, Persona.correo
            FROM Usuario
            INNER JOIN Persona ON Usuario.Cedula = Persona.Cedula
            WHERE (Usuario.Rol = 'Pendiente' AND Usuario.Estado = 'Pendiente')
               OR (Usuario.Rol = 'Paciente' AND Usuario.Estado = 'Activo'
                   AND Usuario.Solicitud_Funcionario = 1)
            ORDER BY Usuario.ID_Usuario";
    $resultado = $con->query($sql);
} catch (mysqli_sql_exception $error) {
    if ($transaccion) {
        $con->rollback();
    }
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo consultar o resolver la solicitud. Intente nuevamente.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de funcionarios | Hospital de Clínicas</title>
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
        <h1>HOSPITAL DE CLINICA</h1>
    </div>
</header>
<div class="perfil-contenedor">
    <aside class="menu-lateral">
        <h3>Menú</h3>
        <a href="/prueba/panelprincipal.php"><i class="fa-solid fa-house"></i> Inicio</a>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-user-plus"></i> Pacientes</summary>
            <div class="submenu">
                <a href="/prueba/php/nuevo_paciente.php"><i class="fa-solid fa-plus"></i> Nuevo paciente</a>
                <a href="/prueba/php/ver_paciente.php"><i class="fa-solid fa-list"></i> Ver pacientes</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-truck-medical"></i> Ambulancias</summary>
            <div class="submenu">
                <a href="/prueba/php/nuevo_traslado.php"><i class="fa-solid fa-plus"></i> Nuevo registro</a>
                <a href="/prueba/php/ver_traslados.php"><i class="fa-solid fa-list"></i> Ver registros</a>
                <a href="/prueba/php/Gestion_Ambulancia.php"><i class="fa-solid fa-cogs"></i> Gestión de Ambulancias</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-file-alt"></i> Documentación</summary>
            <div class="submenu">
                <a href="/prueba/php/documentacion.php"><i class="fa-solid fa-plus"></i> Nueva documentación</a>
                <a href="/prueba/php/ver_documentacion.php"><i class="fa-solid fa-list"></i> Ver documentación</a>
            </div>
        </details>

        <details class="menu-desplegable">
            <summary><i class="fa-solid fa-user-plus"></i> Usuarios</summary>
            <div class="submenu">
                <a href="/prueba/php/panel_admin.php" aria-current="page"><i class="fa-solid fa-plus"></i> Solicitudes</a>
                <a href="/prueba/php/lista_usuarios.php"><i class="fa-solid fa-list"></i> Lista de Usuarios</a>
            </div>
        </details>

        <form action="panel_admin.php" method="POST" class="form-salir">
            <input type="hidden" name="token" value="<?php echo $token; ?>">
            <button type="submit" name="cerrar_sesion" class="salir-medico">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Cerrar sesión
            </button>
        </form>
    </aside>

    <main class="contenido">
        <h2 class="titulo-panel">Solicitudes de funcionarios</h2>
        <p class="subtitulo-panel">Revise las solicitudes y seleccione el rol que corresponde a cada funcionario.</p>
        <?php if ($mensaje != '') { ?>
            <p class="aviso-medico" role="status"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>

        <section class="panel-contenedor" id="solicitudes">
            <div class="panel-titulo">
                <i class="fa-solid fa-user-clock" aria-hidden="true"></i>
                <h2>Solicitudes pendientes</h2>
            </div>
            <div class="tabla-scroll">
                <table class="tabla-panel">
                    <thead>
                        <tr>
                            <th scope="col">Cédula</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Apellido</th>
                            <th scope="col">Correo</th>
                            <th scope="col">Cuenta actual</th>
                            <th scope="col">Rol y acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado->num_rows == 0) { ?>
                            <tr><td colspan="6">No hay solicitudes pendientes.</td></tr>
                        <?php } else { ?>
                            <?php while ($usuario = $resultado->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($usuario['Cedula'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['Apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['correo'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $usuario['Rol'] == 'Paciente' ? 'Paciente activa' : 'Cuenta nueva'; ?></td>
                                    <td>
                                        <form action="panel_admin.php#solicitudes" method="POST" class="acciones-pacientes">
                                            <input type="hidden" name="token" value="<?php echo $token; ?>">
                                            <input type="hidden" name="id_usuario" value="<?php echo (int) $usuario['ID_Usuario']; ?>">
                                            <select name="rol" aria-label="Rol a asignar" required>
                                                <option value="">Seleccione un rol</option>
                                                <?php foreach ($roles as $valor => $nombre) { ?>
                                                    <option value="<?php echo $valor; ?>"><?php echo $nombre; ?></option>
                                                <?php } ?>
                                            </select>
                                            <button type="submit" name="aceptar" class="boton-agendar">Aceptar</button>
                                            <button type="submit" name="rechazar" class="eliminar" formnovalidate>Rechazar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<footer>
    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php $con->close(); ?>

