<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

if (!isset($_SESSION['token_registro_funcionario'])) {
    $_SESSION['token_registro_funcionario'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_registro_funcionario'];
$mensaje = $_SESSION['mensaje_registro_funcionario'] ?? '';
unset($_SESSION['mensaje_registro_funcionario']);
$cedula = '';
$correo = '';
$tiene_cuenta = false;
$activar = false;
$transaccion = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. COMPROBAR EL FORMULARIO Y SUS DATOS
    if (!is_string($_POST['token'] ?? null) || !hash_equals($token, $_POST['token'])) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }
    $cedula = is_string($_POST['cedula'] ?? null) ? trim($_POST['cedula']) : '';
    $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
    $contrasena = is_string($_POST['contrasena'] ?? null) ? $_POST['contrasena'] : '';
    $confirmar = is_string($_POST['confirmar_contrasena'] ?? null) ? $_POST['confirmar_contrasena'] : '';
    $tiene_cuenta = ($_POST['tiene_cuenta'] ?? '') === '1';

    if ($cedula == '' || strlen($cedula) > 20 || !filter_var($correo, FILTER_VALIDATE_EMAIL)
        || $contrasena == '' || strpos($contrasena, "\0") !== false) {
        $mensaje = 'Complete una cédula, un correo y una contraseña válidos.';
    } elseif ($contrasena !== $confirmar) {
        $mensaje = 'Las contraseñas no coinciden.';
    } else {
        try {
            $con->begin_transaction();
            $transaccion = true;
            $enviada = false;

            // 2. LA PERSONA DEBE ESTAR REGISTRADA PREVIAMENTE EN EL HOSPITAL
            $consulta = $con->prepare('SELECT Cedula FROM Persona WHERE Cedula = ? AND correo = ? FOR UPDATE');
            $consulta->bind_param('ss', $cedula, $correo);
            $consulta->execute();
            $persona = $consulta->get_result()->fetch_assoc();

            if (!$persona) {
                $mensaje = 'La cédula, el correo o la contraseña no son correctos.';
            } else {
                $consulta = $con->prepare(
                    'SELECT ID_Usuario, Contraseña, Rol, Estado, Solicitud_Funcionario
                     FROM Usuario WHERE Cedula = ? FOR UPDATE'
                );
                $consulta->bind_param('s', $cedula);
                $consulta->execute();
                $usuario = $consulta->get_result()->fetch_assoc();

                if (!$usuario) {
                    // 3. CREAR UNA CUENTA PENDIENTE SOLO SI TODAVÍA NO EXISTE
                    // El mínimo se exige solamente al crear una contraseña nueva.
                    if (preg_match('/^.{8,}$/us', $contrasena) !== 1) {
                        $mensaje = 'La contraseña nueva debe tener al menos 8 caracteres.';
                    } elseif (strlen($contrasena) > 72) {
                        $mensaje = 'La contraseña nueva es demasiado larga. Pruebe con una más corta.';
                    } else {
                        $protegida = password_hash($contrasena, PASSWORD_DEFAULT);
                        $consulta = $con->prepare(
                            "INSERT INTO Usuario (Cedula, Contraseña, Rol, Estado, Solicitud_Funcionario)
                             VALUES (?, ?, 'Pendiente', 'Pendiente', 1)"
                        );
                        $consulta->bind_param('ss', $cedula, $protegida);
                        $consulta->execute();
                        $enviada = true;
                    }
                } elseif ($usuario['Rol'] == 'Paciente' && $usuario['Estado'] == 'Sin activar') {
                    $mensaje = 'Primero active su cuenta de paciente y cree su contraseña. Después podrá enviar esta solicitud.';
                    $activar = true;
                } elseif (!password_verify($contrasena, $usuario['Contraseña'])) {
                    $mensaje = 'La cédula, el correo o la contraseña no son correctos.';
                } elseif ($usuario['Rol'] == 'Paciente' && $usuario['Estado'] == 'Activo') {
                    // 4. USAR LA CUENTA ACTUAL DEL PACIENTE SIN CAMBIARLA
                    if ((int) $usuario['Solicitud_Funcionario'] == 1) {
                        $mensaje = 'Ya tiene una solicitud pendiente. Espere la respuesta del administrador.';
                    } else {
                        $id_usuario = (int) $usuario['ID_Usuario'];
                        $consulta = $con->prepare(
                            "UPDATE Usuario SET Solicitud_Funcionario = 1
                             WHERE ID_Usuario = ? AND Rol = 'Paciente' AND Estado = 'Activo'
                               AND Solicitud_Funcionario = 0"
                        );
                        $consulta->bind_param('i', $id_usuario);
                        $consulta->execute();
                        $enviada = $consulta->affected_rows == 1;
                        if (!$enviada) {
                            $mensaje = 'No se pudo enviar la solicitud. Recargue la página y vuelva a intentarlo.';
                        }
                    }
                } elseif ($usuario['Rol'] == 'Pendiente' && $usuario['Estado'] == 'Pendiente') {
                    $mensaje = 'Su solicitud ya está pendiente de aprobación.';
                } elseif ($usuario['Estado'] != 'Activo') {
                    $mensaje = 'Su cuenta no está activa. Consulte a un administrador.';
                } else {
                    $mensaje = 'Ya tiene una cuenta para el personal del hospital. Inicie sesión con sus datos habituales.';
                }
            }

            if ($enviada) {
                $con->commit();
                $transaccion = false;
                $_SESSION['mensaje_registro_funcionario'] =
                    'Solicitud enviada. Un administrador revisará su acceso como funcionario.';
                header('Location: registrarse.php', true, 303);
                exit;
            }
            $con->rollback();
            $transaccion = false;
        } catch (mysqli_sql_exception $error) {
            if ($transaccion) {
                $con->rollback();
            }
            error_log($error->getMessage());
            $mensaje = 'No se pudo enviar la solicitud. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar acceso como funcionario | Hospital de Clínicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/prueba/css/style.css">
    <script src="/prueba/js/registro.js" defer></script>
</head>
<body>
<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital" class="logo">
        <h1>HOSPITAL DE CLINICA</h1>
    </div>
    <div class="topnav">
        <div class="nav-links">
            <a class="active" href="registrarse.php">Registrarse</a>
            <a href="/prueba/index.html">Volver atrás</a>
        </div>
    </div>
</header>
<main>
    <section class="hero">
        <div class="registro">
            <h2>Solicitar acceso como funcionario</h2>
            <p class="texto-registro">Complete la cédula y el correo registrados en el hospital.</p>
            <div class="aviso-medico">
                <p>
                    <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                    <strong>Ya tiene cuenta de paciente</strong><br>
                    Marque la casilla «Ya tengo cuenta de paciente» e ingrese su contraseña actual en ambos campos.
                </p>
                <p>
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    <strong>Es su primer registro</strong><br>
                    Elija una contraseña de al menos 8 caracteres y repítala para confirmarla.
                </p>
            </div>

            <?php if ($mensaje != '') { ?>
                <p class="aviso-medico" role="status"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>
            <?php if ($activar) { ?>
                <p><a href="/prueba/php/PACIENTE/activar_cuenta.php">Activar mi cuenta de paciente</a></p>
            <?php } ?>

            <form id="form-registro" class="form-registro" action="registrarse.php" method="POST">
                <input type="hidden" name="token" value="<?php echo $token; ?>">
                <label>
                    <input type="checkbox" id="tiene_cuenta" name="tiene_cuenta" value="1" <?php if ($tiene_cuenta) { echo 'checked'; } ?>>
                    Ya tengo cuenta de paciente
                </label>
                <div class="fila">
                    <div class="campo">
                        <label for="cedula"><i class="fa-solid fa-id-card" aria-hidden="true"></i> Cédula</label>
                        <input type="text" id="cedula" name="cedula" maxlength="20" autocomplete="username"
                               value="<?php echo htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="campo">
                        <label for="correo"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Correo electrónico</label>
                        <input type="email" id="correo" name="correo" autocomplete="email"
                               value="<?php echo htmlspecialchars($correo, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                <div class="fila">
                    <div class="campo">
                        <label for="contrasena"><i class="fa-solid fa-lock" aria-hidden="true"></i> Contraseña</label>
                        <input type="password" id="contrasena" name="contrasena" required>
                    </div>
                    <div class="campo">
                        <label for="confirmar_contrasena"><i class="fa-solid fa-lock" aria-hidden="true"></i> Confirmar contraseña</label>
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
                    </div>
                </div>
                <button type="submit">Enviar solicitud</button>
                <p>¿Ya tiene una cuenta? <a href="login.php">Iniciar sesión</a></p>
            </form>
        </div>
    </section>
</main>
<footer>
    <p>&copy; 2026 Hospital de Clínica. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php $con->close(); ?>
