<?php

session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/conexion.php';

$con->set_charset('utf8mb4');


// COMPROBAR EL ADMINISTRADOR

$id_usuario = 0;

if (isset($_SESSION['id_usuario'])) {
    $id_usuario = filter_var(
        $_SESSION['id_usuario'],
        FILTER_VALIDATE_INT
    );
}

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$sql = "SELECT ID_Usuario
        FROM Usuario
        WHERE ID_Usuario = $id_usuario
          AND Rol = 'Administrador'
          AND Estado = 'Activo'";

$cuenta = $con->query($sql);

if ($cuenta->num_rows == 0) {
    http_response_code(403);
    exit('Acceso disponible para administradores activos.');
}

header('Cache-Control: no-store, private');


// PREPARAR FORMULARIOS Y MENSAJES

if (!isset($_SESSION['token_nuevo_paciente'])) {
    $_SESSION['token_nuevo_paciente'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_nuevo_paciente'];

$mensaje = '';

$acceso = null;

if (isset($_SESSION['mensaje_nuevo_paciente'])) {
    $mensaje = $_SESSION['mensaje_nuevo_paciente'];
    unset($_SESSION['mensaje_nuevo_paciente']);
}

if (isset($_SESSION['acceso_nuevo_paciente'])) {
    $acceso = $_SESSION['acceso_nuevo_paciente'];
    unset($_SESSION['acceso_nuevo_paciente']);
}


// REGISTRAR AL PACIENTE O PREPARAR SU ACCESO

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (
        !isset($_POST['token'])
        || !is_string($_POST['token'])
        || !hash_equals($token, $_POST['token'])
    ) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }


    // REVISAR LOS CAMPOS RECIBIDOS

    $campos = [
        'cedula',
        'nombre',
        'apellido',
        'nacimiento',
        'direccion',
        'correo',
        'telefono',
        'cedula_acceso'
    ];

    foreach ($campos as $campo) {
        if (
            !isset($_POST[$campo])
            || !is_string($_POST[$campo])
        ) {
            $_POST[$campo] = '';
        }
    }


    // SABER CUÁL DE LOS DOS FORMULARIOS SE ENVIÓ

    $preparar_acceso = isset($_POST['preparar_acceso']);

    if ($preparar_acceso) {
        $cedula = trim($_POST['cedula_acceso']);
    } else {
        $cedula = trim($_POST['cedula']);
    }

    $nombre = trim($_POST['nombre']);

    $apellido = trim($_POST['apellido']);

    $correo = trim($_POST['correo']);


    if ($cedula == '') {

        $mensaje = 'Ingrese la cédula del paciente.';

    } elseif (
        !$preparar_acceso
        && (
            $nombre == ''
            || $apellido == ''
            || !filter_var($correo, FILTER_VALIDATE_EMAIL)
        )
    ) {

        $mensaje =
            'Complete el nombre, el apellido y un correo válido.';

    } else {

        try {

            $con->begin_transaction();

            $cedula_sql = $con->real_escape_string($cedula);


            // BUSCAR SI LA PERSONA YA EXISTE

            $sql = "SELECT Cedula, Nombre, Apellido, Correo
                    FROM Persona
                    WHERE Cedula = '$cedula_sql'
                    FOR UPDATE";

            $resultado_persona = $con->query($sql);

            $persona = $resultado_persona->fetch_assoc();


            // BUSCAR SI YA ESTÁ REGISTRADA COMO PACIENTE

            $sql = "SELECT ID_Paciente, Estado
                    FROM Paciente
                    WHERE Cedula = '$cedula_sql'
                    FOR UPDATE";

            $resultado_paciente = $con->query($sql);

            $paciente = $resultado_paciente->fetch_assoc();


            if ($paciente && $paciente['Estado'] == 'Inactivo') {

                $con->rollback();

                $mensaje =
                    'Este paciente está dado de baja. Puede reactivarlo desde Ver pacientes, en Ver inactivos.';

            } elseif ($preparar_acceso && !$paciente) {

                $con->rollback();

                $mensaje =
                    'Primero debe registrar al paciente en el hospital.';

            } elseif (!$preparar_acceso && $paciente) {

                $con->rollback();

                $mensaje =
                    'Este paciente ya está registrado. Use el formulario de acceso para pacientes registrados.';

            } else {

                // GUARDAR LA PERSONA SI TODAVÍA NO EXISTE

                if (!$persona) {

                    $nombre_sql = $con->real_escape_string($nombre);

                    $apellido_sql = $con->real_escape_string($apellido);

                    $direccion_sql = $con->real_escape_string(
                        trim($_POST['direccion'])
                    );

                    $correo_sql = $con->real_escape_string($correo);

                    $telefono_sql = $con->real_escape_string(
                        trim($_POST['telefono'])
                    );

                    $nacimiento_sql = 'NULL';

                    if ($_POST['nacimiento'] != '') {
                        $nacimiento_sql = "'" .
                            $con->real_escape_string($_POST['nacimiento']) .
                            "'";
                    }

                    $sql = "INSERT INTO Persona
                                (
                                    Cedula,
                                    Nombre,
                                    Apellido,
                                    Fecha_Nacimiento,
                                    Direccion,
                                    Correo,
                                    Telefono
                                )
                            VALUES
                                (
                                    '$cedula_sql',
                                    '$nombre_sql',
                                    '$apellido_sql',
                                    $nacimiento_sql,
                                    '$direccion_sql',
                                    '$correo_sql',
                                    '$telefono_sql'
                                )";

                    $con->query($sql);

                    $persona = [
                        'Cedula' => $cedula,
                        'Nombre' => $nombre,
                        'Apellido' => $apellido,
                        'Correo' => $correo
                    ];

                }


                // GUARDAR AL PACIENTE SIN DUPLICARLO

                if (!$paciente) {

                    $sql = "INSERT INTO Paciente
                                (Cedula, Estado)
                            VALUES
                                ('$cedula_sql', 'Activo')";

                    $con->query($sql);

                }


                // BUSCAR SI ESTA PERSONA YA TIENE UNA CUENTA

                $sql = "SELECT ID_Usuario, Rol, Estado
                        FROM Usuario
                        WHERE Cedula = '$cedula_sql'
                        FOR UPDATE";

                $resultado_usuario = $con->query($sql);

                $usuario = $resultado_usuario->fetch_assoc();

                $crear_codigo = false;

                if (!$usuario) {

                    $crear_codigo = true;

                } elseif (
                    $usuario['Rol'] == 'Paciente'
                    && $usuario['Estado'] == 'Sin activar'
                ) {

                    $crear_codigo = true;

                }


                if ($crear_codigo) {

                    // USAR EL CORREO GUARDADO EN PERSONA

                    if (
                        !filter_var(
                            $persona['Correo'],
                            FILTER_VALIDATE_EMAIL
                        )
                    ) {

                        $con->rollback();

                        $mensaje =
                            'El correo registrado no es válido. Actualice los datos personales antes de preparar el acceso.';

                    } else {

                        // GENERAR EL CÓDIGO Y GUARDARLO PROTEGIDO

                        $codigo = strtoupper(
                            bin2hex(random_bytes(16))
                        );

                        $codigo_protegido = password_hash(
                            $codigo,
                            PASSWORD_DEFAULT
                        );

                        $codigo_sql = $con->real_escape_string(
                            $codigo_protegido
                        );


                        if (!$usuario) {

                            // CREAR LA CUENTA PENDIENTE

                            $sql = "INSERT INTO Usuario
                                        (
                                            Cedula,
                                            Contraseña,
                                            Rol,
                                            Estado,
                                            Codigo_Activacion,
                                            Vence_Activacion
                                        )
                                    VALUES
                                        (
                                            '$cedula_sql',
                                            '',
                                            'Paciente',
                                            'Sin activar',
                                            '$codigo_sql',
                                            DATE_ADD(NOW(), INTERVAL 1 DAY)
                                        )";

                            $con->query($sql);

                            $id_cuenta = (int) $con->insert_id;

                        } else {

                            // RENOVAR EL CÓDIGO DE UNA CUENTA PENDIENTE

                            $id_cuenta = (int) $usuario['ID_Usuario'];

                            $sql = "UPDATE Usuario
                                    SET Codigo_Activacion = '$codigo_sql',
                                        Vence_Activacion =
                                            DATE_ADD(NOW(), INTERVAL 1 DAY)
                                    WHERE ID_Usuario = $id_cuenta
                                      AND Rol = 'Paciente'
                                      AND Estado = 'Sin activar'";

                            $con->query($sql);

                        }


                        // RECUPERAR EL VENCIMIENTO

                        $sql = "SELECT Vence_Activacion
                                FROM Usuario
                                WHERE ID_Usuario = $id_cuenta";

                        $resultado_vencimiento = $con->query($sql);

                        $vencimiento =
                            $resultado_vencimiento->fetch_assoc();


                        // PREPARAR LOS DATOS QUE SE ENTREGAN AL PACIENTE

                        $acceso = [
                            'Nombre' =>
                                $persona['Nombre'] . ' ' .
                                $persona['Apellido'],

                            'Cedula' => $persona['Cedula'],

                            'Correo' => $persona['Correo'],

                            'Codigo' => $codigo,

                            'Vence' =>
                                $vencimiento['Vence_Activacion']
                        ];

                        $con->commit();

                        $_SESSION['mensaje_nuevo_paciente'] =
                            'Paciente registrado y cuenta preparada. Su acceso queda pendiente de activación.';

                        $_SESSION['acceso_nuevo_paciente'] = $acceso;

                        header(
                            'Location: nuevo_paciente.php#acceso-paciente',
                            true,
                            303
                        );

                        exit;

                    }

                } else {

                    // CONSERVAR LAS CUENTAS QUE YA EXISTÍAN

                    $con->commit();

                    if ($preparar_acceso) {

                        $_SESSION['mensaje_nuevo_paciente'] =
                            'El paciente ya tiene una cuenta en el sistema. Se conservaron su contraseña, su rol y su estado.';

                    } else {

                        $_SESSION['mensaje_nuevo_paciente'] =
                            'Paciente registrado. Ya tenía una cuenta y se conservaron su contraseña, su rol y su estado.';

                    }

                    header('Location: nuevo_paciente.php', true, 303);

                    exit;

                }

            }

        } catch (mysqli_sql_exception $error) {

            $con->rollback();

            error_log($error->getMessage());

            $mensaje =
                'No se pudo completar el registro o preparar el acceso. No se guardaron cambios.';

            $acceso = null;

        }

    }

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Nuevo paciente - Hospital de Clínicas</title>

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

</head>

<body>

<header>

    <div class="encabezado">

        <img src="/prueba/img/LOGO PROYECTO DHC.png"
             alt="Logo Hospital de Clínicas"
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


    <main class="contenido">

        <!-- MENSAJES -->

        <?php if ($mensaje != '') { ?>

            <p class="aviso-medico" role="status">

                <?php
                echo htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </p>

        <?php } ?>


        <!-- DATOS QUE SE ENTREGAN AL PACIENTE -->

        <?php if ($acceso !== null) { ?>

            <section class="tarjeta" id="acceso-paciente">

                <h3>Acceso del paciente</h3>

                <p>

                    <strong>Paciente:</strong>

                    <?php
                    echo htmlspecialchars(
                        $acceso['Nombre'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

                <p>

                    <strong>Cédula:</strong>

                    <?php
                    echo htmlspecialchars(
                        $acceso['Cedula'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

                <p>

                    <strong>Correo:</strong>

                    <?php
                    echo htmlspecialchars(
                        $acceso['Correo'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

                <p>

                    <strong>Código de activación:</strong>

                    <code>
                        <?php
                        echo htmlspecialchars(
                            $acceso['Codigo'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </code>

                </p>

                <p>

                    <strong>Válido hasta:</strong>

                    <?php
                    echo htmlspecialchars(
                        $acceso['Vence'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </p>

                <p>
                    Guarde y entregue este código al paciente.
                    Lo necesitará para crear su contraseña.
                </p>

                <p>
                    Si generó otro código para esta cuenta,
                    el anterior dejó de servir.
                </p>

            </section>

        <?php } ?>


        <h2 class="titulo-panel">
            Nuevo paciente
        </h2>

        <p class="subtitulo-panel">
            Registre al paciente y prepare su cuenta para el primer ingreso.
        </p>


        <!-- FORMULARIO DE REGISTRO -->

        <div class="tarjeta tarjeta-paciente">

            <h3>Datos del paciente</h3>

            <p>
                Complete los datos confirmados durante el registro
                presencial en el hospital.
            </p>

            <form action="nuevo_paciente.php" method="POST">

                <input type="hidden"
                       name="token"
                       value="<?php echo $token; ?>">

                <div class="fila">

                    <div class="campo">

                        <label for="nombre">Nombre</label>

                        <input type="text"
                               id="nombre"
                               name="nombre"
                               placeholder="Ingrese el nombre"
                               required>

                    </div>

                    <div class="campo">

                        <label for="apellido">Apellido</label>

                        <input type="text"
                               id="apellido"
                               name="apellido"
                               placeholder="Ingrese el apellido"
                               required>

                    </div>

                </div>


                <div class="fila">

                    <div class="campo">

                        <label for="cedula">Cédula</label>

                        <input type="text"
                               id="cedula"
                               name="cedula"
                               placeholder="Ingrese la cédula"
                               inputmode="numeric"
                               required>

                    </div>

                    <div class="campo">

                        <label for="fecha-nacimiento">
                            Fecha de nacimiento
                        </label>

                        <input type="date"
                               id="fecha-nacimiento"
                               name="nacimiento"
                               required>

                    </div>

                </div>


                <div class="fila">

                    <div class="campo">

                        <label for="telefono">Teléfono</label>

                        <input type="tel"
                               id="telefono"
                               name="telefono"
                               placeholder="Ingrese el teléfono"
                               required>

                    </div>

                    <div class="campo">

                        <label for="direccion">Dirección</label>

                        <input type="text"
                               id="direccion"
                               name="direccion"
                               placeholder="Ingrese la dirección"
                               required>

                    </div>

                </div>


                <div class="fila">

                    <div class="campo">

                        <label for="correo">Correo</label>

                        <input type="email"
                               id="correo"
                               name="correo"
                               placeholder="Ingrese el correo"
                               required>

                    </div>

                </div>


                <div class="botones-consulta">

                    <button type="reset"
                            class="boton-limpiar">

                        Limpiar

                    </button>

                    <button type="submit"
                            class="boton-guardar">

                        Registrar paciente

                    </button>

                </div>

            </form>

        </div>


        <!-- ACCESO PARA PACIENTES QUE YA ESTABAN REGISTRADOS -->

        <section class="tarjeta">

            <h3>Acceso para un paciente ya registrado</h3>

            <p>
                Prepare el primer acceso o renueve un código vencido.
                Las cuentas existentes conservan su contraseña y su estado.
            </p>

            <form action="nuevo_paciente.php" method="POST">

                <input type="hidden"
                       name="token"
                       value="<?php echo $token; ?>">

                <div class="campo">

                    <label for="cedula_acceso">
                        Cédula del paciente
                    </label>

                    <input type="text"
                           id="cedula_acceso"
                           name="cedula_acceso"
                           maxlength="20"
                           required>

                </div>

                <button type="submit"
                        name="preparar_acceso"
                        class="boton-guardar">

                    Generar código de activación

                </button>

            </form>

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