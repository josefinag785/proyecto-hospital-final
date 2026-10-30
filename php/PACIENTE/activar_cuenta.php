<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$mensaje = '';
$transaccion = false;

if (!isset($_SESSION['token_activar_paciente'])) {
    $_SESSION['token_activar_paciente'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token_activar_paciente'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['token']) || !is_string($_POST['token'])
        || !hash_equals($token, $_POST['token'])) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    try {
        // 1. COMPROBAR LOS DATOS Y EL CÓDIGO ENTREGADO POR EL HOSPITAL
        if (isset($_POST['verificar_codigo'])) {
            unset($_SESSION['activacion_paciente']);
            $cedula = '';
            $correo = '';
            $codigo = '';

            if (isset($_POST['cedula']) && is_string($_POST['cedula'])) {
                $cedula = trim($_POST['cedula']);
            }
            if (isset($_POST['correo']) && is_string($_POST['correo'])) {
                $correo = trim($_POST['correo']);
            }
            if (isset($_POST['codigo']) && is_string($_POST['codigo'])) {
                $codigo = strtoupper(trim($_POST['codigo']));
            }

            $mensaje = 'No se pudo verificar el ingreso. Revise sus datos y el código. Si venció, solicite otro en el hospital.';

            if ($cedula != '' && strlen($cedula) <= 20
                && strlen($correo) <= 100 && filter_var($correo, FILTER_VALIDATE_EMAIL)
                && preg_match('/^[A-F0-9]{32}$/', $codigo)) {

                $cedula_sql = $con->real_escape_string($cedula);
                $correo_sql = $con->real_escape_string($correo);

                $sql = "SELECT Usuario.ID_Usuario, Usuario.Codigo_Activacion
                        FROM Usuario
                        INNER JOIN Persona ON Usuario.Cedula = Persona.Cedula
                        INNER JOIN Paciente ON Usuario.Cedula = Paciente.Cedula
                        WHERE Usuario.Cedula = '$cedula_sql'
                          AND Persona.Correo = '$correo_sql'
                          AND Usuario.Rol = 'Paciente'
                          AND Usuario.Estado = 'Sin activar'
                          AND Paciente.Estado = 'Activo'
                          AND Usuario.Vence_Activacion > NOW()
                        LIMIT 1";
                $resultado = $con->query($sql);

                if ($resultado->num_rows > 0) {
                    $usuario = $resultado->fetch_assoc();

                    if (is_string($usuario['Codigo_Activacion'])
                        && password_verify($codigo, $usuario['Codigo_Activacion'])) {
                        session_regenerate_id(true);
                        $_SESSION['activacion_paciente'] = [
                            'id_usuario' => (int) $usuario['ID_Usuario'],
                            'codigo_protegido' => $usuario['Codigo_Activacion']
                        ];
                        header('Location: activar_cuenta.php', true, 303);
                        exit;
                    }
                }
            }
        }

        // 2. CREAR LA CONTRASEÑA Y DEJAR EL CÓDIGO SIN EFECTO
        elseif (isset($_POST['guardar_contrasena'])) {
            $contrasena = '';
            $repetir = '';
            if (isset($_POST['contrasena']) && is_string($_POST['contrasena'])) {
                $contrasena = $_POST['contrasena'];
            }
            if (isset($_POST['repetir']) && is_string($_POST['repetir'])) {
                $repetir = $_POST['repetir'];
            }

            if (!isset($_SESSION['activacion_paciente'])) {
                $mensaje = 'Primero debe verificar sus datos y su código.';
            } elseif (mb_strlen($contrasena, 'UTF-8') < 15) {
                $mensaje = 'La contraseña debe tener al menos 15 caracteres.';
            } elseif (strlen($contrasena) > 72 || str_contains($contrasena, "\0")) {
                $mensaje = 'La contraseña es demasiado larga o contiene un carácter no permitido. Use una frase más corta.';
            } elseif ($contrasena != $repetir) {
                $mensaje = 'Las contraseñas no coinciden.';
            } else {
                $id_usuario = (int) $_SESSION['activacion_paciente']['id_usuario'];
                $codigo_protegido = $_SESSION['activacion_paciente']['codigo_protegido'];

                $con->begin_transaction();
                $transaccion = true;

                // Se vuelve a comprobar por si el código venció o fue renovado.
                $sql = "SELECT Usuario.Codigo_Activacion
                        FROM Usuario
                        INNER JOIN Paciente ON Usuario.Cedula = Paciente.Cedula
                        WHERE Usuario.ID_Usuario = $id_usuario
                          AND Usuario.Rol = 'Paciente'
                          AND Usuario.Estado = 'Sin activar'
                          AND Paciente.Estado = 'Activo'
                          AND Usuario.Vence_Activacion > NOW()
                        FOR UPDATE";
                $resultado = $con->query($sql);
                $valido = false;

                if ($resultado->num_rows > 0) {
                    $usuario = $resultado->fetch_assoc();
                    if (is_string($usuario['Codigo_Activacion'])
                        && hash_equals($codigo_protegido, $usuario['Codigo_Activacion'])) {
                        $valido = true;
                    }
                }

                if (!$valido) {
                    $con->rollback();
                    $transaccion = false;
                    unset($_SESSION['activacion_paciente']);
                    $mensaje = 'El código ya no está disponible. Solicite uno nuevo en el hospital y vuelva a verificar sus datos.';
                } else {
                    $contrasena_protegida = password_hash($contrasena, PASSWORD_DEFAULT);
                    $contrasena_sql = $con->real_escape_string($contrasena_protegida);

                    $sql = "UPDATE Usuario
                            SET Contraseña = '$contrasena_sql',
                                Estado = 'Activo',
                                Codigo_Activacion = NULL,
                                Vence_Activacion = NULL
                            WHERE ID_Usuario = $id_usuario";
                    $con->query($sql);
                    $con->commit();
                    $transaccion = false;
                    unset($_SESSION['activacion_paciente']);

                    header('Location: /prueba/php/login.php?mensaje=cuenta_activada', true, 303);
                    exit;
                }
            }
        } elseif (isset($_POST['volver'])) {
            unset($_SESSION['activacion_paciente']);
            header('Location: activar_cuenta.php', true, 303);
            exit;
        }
    } catch (mysqli_sql_exception $error) {
        if ($transaccion) {
            $con->rollback();
        }
        error_log($error->getMessage());
        $mensaje = 'No se pudo completar la operación. Intente nuevamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primer ingreso | Hospital de Clínicas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/prueba/css/style.css">
</head>
<body>
<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital" class="logo">
        <h1>HOSPITAL DE CLÍNICAS</h1>
    </div>
    <div class="topnav">
        <div class="nav-links">
            <a href="/prueba/php/login.php">Volver al ingreso</a>
        </div>
    </div>
</header>
<main>
    <section class="hero">
        <div class="login">
            <h2>Primer ingreso del paciente</h2>
            <?php if ($mensaje != '') { ?>
                <p role="alert"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>

            <?php if (!isset($_SESSION['activacion_paciente'])) { ?>
                <p class="texto-login">Ingrese los datos registrados y el código que le entregó el hospital.</p>
                <form action="activar_cuenta.php" method="POST" class="form-login">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <label for="cedula">Cédula</label>
                    <input type="text" id="cedula" name="cedula" maxlength="20" autocomplete="username" required>
                    <label for="correo">Correo registrado</label>
                    <input type="email" id="correo" name="correo" maxlength="100" autocomplete="email" required>
                    <label for="codigo">Código de activación</label>
                    <input type="text" id="codigo" name="codigo" minlength="32" maxlength="32" autocomplete="off" required>
                    <button type="submit" name="verificar_codigo">Verificar datos</button>
                </form>
            <?php } else { ?>
                <p class="texto-login">Elija una contraseña de al menos 15 caracteres. Puede usar una frase que recuerde.</p>
                <form action="activar_cuenta.php" method="POST" class="form-login">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <label for="contrasena">Nueva contraseña</label>
                    <input type="password" id="contrasena" name="contrasena" minlength="15" autocomplete="new-password" required>
                    <label for="repetir">Repetir contraseña</label>
                    <input type="password" id="repetir" name="repetir" minlength="15" autocomplete="new-password" required>
                    <button type="submit" name="guardar_contrasena">Crear contraseña</button>
                </form>
                <form action="activar_cuenta.php" method="POST" class="form-login">
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <button type="submit" name="volver">Volver a verificar mis datos</button>
                </form>
            <?php } ?>
        </div>
    </section>
</main>
<footer>
    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>
</footer>
</body>
</html>
<?php
$con->close();
?>
