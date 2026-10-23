<?php

session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/conexion.php';

$con->set_charset('utf8mb4');


// 1. COMPROBAR QUE INGRESÓ UN ADMINISTRADOR

if (!isset($_SESSION['id_usuario'])) {
    header('Location: /prueba/php/login.php');
    exit;
}

$id_usuario = filter_var(
    $_SESSION['id_usuario'],
    FILTER_VALIDATE_INT
);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$sql = "SELECT ID_Usuario
        FROM Usuario
        WHERE ID_Usuario = $id_usuario
          AND Rol = 'Administrador'
          AND Estado = 'Activo'";

$resultado_usuario = $con->query($sql);

if ($resultado_usuario->num_rows == 0) {
    http_response_code(403);
    exit('Esta página está disponible para administradores con una cuenta activa.');
}

header('Cache-Control: no-store, private');


// 2. PREPARAR VARIABLES Y FORMULARIOS

if (!isset($_SESSION['token_pacientes'])) {
    $_SESSION['token_pacientes'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_pacientes'];

$mensaje = '';
$persona_modificar = null;
$paciente_accion = null;
$accion_actual = '';
$cedula = '';
$nombre = '';


// RECUPERAR EL MENSAJE DESPUÉS DE GUARDAR

if (isset($_SESSION['mensaje_pacientes'])) {

    $mensaje = $_SESSION['mensaje_pacientes'];

    unset($_SESSION['mensaje_pacientes']);

}
// ELEGIR ENTRE PACIENTES ACTIVOS E INACTIVOS

$estado_lista = 'Activo';

if (isset($_POST['estado_lista'])) {

    if ($_POST['estado_lista'] == 'Inactivo') {
        $estado_lista = 'Inactivo';
    }

} elseif (
    isset($_GET['estado'])
    && $_GET['estado'] == 'Inactivo'
) {

    $estado_lista = 'Inactivo';

}

if (isset($_POST['ver_inactivos'])) {
    $estado_lista = 'Inactivo';
}

if (isset($_POST['ver_activos'])) {
    $estado_lista = 'Activo';
}

$url_lista = 'ver_paciente.php?estado=' . $estado_lista;


// COMPROBAR EL CÓDIGO OCULTO DEL FORMULARIO

if (
    isset($_POST['eliminar'])
    || isset($_POST['confirmar_accion'])
    || isset($_POST['reactivar'])
    || isset($_POST['guardar_modificacion'])
    || isset($_POST['cerrar_sesion'])
) {

    if (
        !isset($_POST['token'])
        || !is_string($_POST['token'])
    ) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

    if (!hash_equals($token, $_POST['token'])) {
        http_response_code(403);
        exit('Recargue la página y vuelva a intentarlo.');
    }

}


// 3. CERRAR SESIÓN

if (isset($_POST['cerrar_sesion'])) {

    $_SESSION = [];

    session_destroy();

    header('Location: /prueba/php/login.php');

    exit;

}


try {

    // 4. REACTIVAR UN PACIENTE

    if (isset($_POST['reactivar'])) {

        $id_paciente = filter_var(
            $_POST['reactivar'],
            FILTER_VALIDATE_INT
        );

        if (!$id_paciente || $id_paciente < 1) {
            http_response_code(400);
            exit('Paciente no válido.');
        }

        $sql = "UPDATE Paciente
                SET Estado = 'Activo'
                WHERE ID_Paciente = $id_paciente
                  AND Estado = 'Inactivo'";

        $con->query($sql);

        if ($con->affected_rows > 0) {

            $_SESSION['mensaje_pacientes'] =
                'Paciente reactivado. Su cuenta de acceso conserva su estado anterior.';

        } else {

            $_SESSION['mensaje_pacientes'] =
                'El paciente ya está activo o no está registrado.';

        }

        header('Location: ver_paciente.php', true, 303);

        exit;

    }


    // 5. ELIMINAR O DAR DE BAJA

    if (
        isset($_POST['eliminar'])
        || isset($_POST['confirmar_accion'])
    ) {

        if (isset($_POST['confirmar_accion'])) {

            $id_paciente = filter_var(
                $_POST['confirmar_accion'],
                FILTER_VALIDATE_INT
            );

        } else {

            $id_paciente = filter_var(
                $_POST['eliminar'],
                FILTER_VALIDATE_INT
            );

        }

        if (!$id_paciente || $id_paciente < 1) {
            http_response_code(400);
            exit('Paciente no válido.');
        }


        // GUARDAR TODOS LOS CAMBIOS DE ESTA OPERACIÓN JUNTOS

        $con->begin_transaction();

        $sql = "SELECT ID_Paciente, Cedula
                FROM Paciente
                WHERE ID_Paciente = $id_paciente
                FOR UPDATE";

        $resultado_paciente = $con->query($sql);


        if ($resultado_paciente->num_rows == 0) {

            $con->rollback();

            $mensaje = 'El paciente ya no está registrado.';

        } else {

            $paciente_bloqueado = $resultado_paciente->fetch_assoc();

            $cedula_paciente = $con->real_escape_string(
                $paciente_bloqueado['Cedula']
            );


            // COORDINAR LOS CAMBIOS SOBRE ESTA PERSONA

            $sql = "SELECT Cedula
                    FROM Persona
                    WHERE Cedula = '$cedula_paciente'
                    FOR UPDATE";

            $con->query($sql);

            $sql = "SELECT ID_Paciente
                    FROM Paciente
                    WHERE Cedula = '$cedula_paciente'
                    FOR UPDATE";

            $con->query($sql);


            // BUSCAR LOS DATOS DEL PACIENTE

            $sql = "SELECT Paciente.ID_Paciente,
                           Paciente.Estado,
                           Persona.*
                    FROM Paciente
                    INNER JOIN Persona
                        ON Paciente.Cedula = Persona.Cedula
                    WHERE Paciente.ID_Paciente = $id_paciente";

            $resultado_paciente = $con->query($sql);

            $paciente_accion = $resultado_paciente->fetch_assoc();


            // CONTAR SUS TRASLADOS

            $sql = "SELECT COUNT(*) AS Total
                    FROM Traslado
                    WHERE ID_Paciente = $id_paciente";

            $resultado_conteo = $con->query($sql);

            $conteo = $resultado_conteo->fetch_assoc();

            $paciente_accion['Traslados'] = $conteo['Total'];


            // CONTAR SUS ENCUESTAS

            $sql = "SELECT COUNT(*) AS Total
                    FROM Encuesta
                    WHERE ID_Paciente = $id_paciente";

            $resultado_conteo = $con->query($sql);

            $conteo = $resultado_conteo->fetch_assoc();

            $paciente_accion['Encuestas'] = $conteo['Total'];


            // CONTAR SUS ESPACIOS DE DOCUMENTACIÓN

            $sql = "SELECT COUNT(*) AS Total
                    FROM Documento
                    WHERE ID_Paciente = $id_paciente";

            $resultado_conteo = $con->query($sql);

            $conteo = $resultado_conteo->fetch_assoc();

            $paciente_accion['Espacios'] = $conteo['Total'];


            // CONTAR SUS ARCHIVOS PDF

            $sql = "SELECT COUNT(*) AS Total
                    FROM Archivo_Documento
                    INNER JOIN Documento
                        ON Archivo_Documento.ID_Documento = Documento.ID_Documento
                    WHERE Documento.ID_Paciente = $id_paciente
                      AND Archivo_Documento.Archivo IS NOT NULL";

            $resultado_conteo = $con->query($sql);

            $conteo = $resultado_conteo->fetch_assoc();

            $paciente_accion['Archivos'] = $conteo['Total'];


            // DECIDIR SI CORRESPONDE ELIMINAR O DAR DE BAJA

            if (
                $paciente_accion['Traslados'] > 0
                || $paciente_accion['Encuestas'] > 0
            ) {

                $accion_actual = 'baja';

            } else {

                $accion_actual = 'eliminar';

            }


            if (isset($_POST['confirmar_accion'])) {

                $accion_enviada = '';

                if (
                    isset($_POST['accion'])
                    && is_string($_POST['accion'])
                ) {
                    $accion_enviada = $_POST['accion'];
                }


                // COMPROBAR SI CAMBIÓ EL HISTORIAL

                if ($accion_enviada != $accion_actual) {

                    $con->commit();

                    $mensaje =
                        'El historial del paciente cambió. Revise y confirme la acción nuevamente.';

                } else {

                    $cedula_paciente = $con->real_escape_string(
                        $paciente_accion['Cedula']
                    );


                    if ($accion_actual == 'baja') {

                        // MARCAR AL PACIENTE COMO INACTIVO

                        $sql = "UPDATE Paciente
                                SET Estado = 'Inactivo'
                                WHERE ID_Paciente = $id_paciente";

                        $con->query($sql);


                        // COMPROBAR SI TIENE OTRO REGISTRO ACTIVO

                        $sql = "SELECT ID_Paciente
                                FROM Paciente
                                WHERE Cedula = '$cedula_paciente'
                                  AND Estado = 'Activo'";

                        $otros_activos = $con->query($sql);


                        // DESACTIVAR ÚNICAMENTE LAS CUENTAS DE PACIENTE

                        if ($otros_activos->num_rows == 0) {

                            $sql = "UPDATE Usuario
                                    SET Estado = 'Inactivo'
                                    WHERE Cedula = '$cedula_paciente'
                                      AND Rol = 'Paciente'";

                            $con->query($sql);

                        }

                        $mensaje =
                            'Paciente dado de baja. Se conservó su historial.';

                    } else {

                        // BUSCAR LOS ESPACIOS DEL PACIENTE

                        $sql = "SELECT ID_Documento
                                FROM Documento
                                WHERE ID_Paciente = $id_paciente
                                FOR UPDATE";

                        $documentos_eliminar = $con->query($sql);


                        // BORRAR ARCHIVOS Y QR DE CADA ESPACIO

                        while (
                            $documento_eliminar =
                            $documentos_eliminar->fetch_assoc()
                        ) {

                            $id_documento =
                                (int) $documento_eliminar['ID_Documento'];

                            $sql = "DELETE FROM Archivo_Documento
                                    WHERE ID_Documento = $id_documento";

                            $con->query($sql);

                            $sql = "DELETE FROM QR
                                    WHERE ID_Documento = $id_documento";

                            $con->query($sql);

                        }


                        // BORRAR LOS ESPACIOS

                        $sql = "DELETE FROM Documento
                                WHERE ID_Paciente = $id_paciente";

                        $con->query($sql);


                        // BORRAR EL PACIENTE

                        $sql = "DELETE FROM Paciente
                                WHERE ID_Paciente = $id_paciente";

                        $con->query($sql);


                        // COMPROBAR SI QUEDA OTRO REGISTRO DE PACIENTE

                        $sql = "SELECT ID_Paciente
                                FROM Paciente
                                WHERE Cedula = '$cedula_paciente'";

                        $otros_pacientes = $con->query($sql);


                        // BORRAR SOLO LAS CUENTAS DEL PERFIL PACIENTE

                        if ($otros_pacientes->num_rows == 0) {

                            $sql = "DELETE FROM Usuario
                                    WHERE Cedula = '$cedula_paciente'
                                      AND Rol = 'Paciente'";

                            $con->query($sql);

                        }


                        // COMPROBAR SI LA PERSONA TIENE OTROS REGISTROS

                        $sql = "SELECT ID_Usuario
                                FROM Usuario
                                WHERE Cedula = '$cedula_paciente'";

                        $usuarios = $con->query($sql);

                        $sql = "SELECT ID_Funcionario
                                FROM Funcionario
                                WHERE Cedula = '$cedula_paciente'";

                        $funcionarios = $con->query($sql);

                        $sql = "SELECT Cedula
                                FROM Acompanante
                                WHERE Cedula = '$cedula_paciente'";

                        $acompanantes = $con->query($sql);


                        // BORRAR LA PERSONA SOLO SI YA NO SE USA

                        if (
                            $otros_pacientes->num_rows == 0
                            && $usuarios->num_rows == 0
                            && $funcionarios->num_rows == 0
                            && $acompanantes->num_rows == 0
                        ) {

                            $sql = "DELETE FROM Persona
                                    WHERE Cedula = '$cedula_paciente'";

                            $con->query($sql);

                        }

                        $mensaje =
                            'Paciente y documentación eliminados correctamente. Los datos utilizados por otros perfiles se conservaron.';

                    }


                    // CONFIRMAR TODOS LOS CAMBIOS

                    $con->commit();

                    $_SESSION['mensaje_pacientes'] = $mensaje;

                    header('Location: ' . $url_lista, true, 303);

                    exit;

                }

            } else {

                // EL PRIMER CLIC SOLO MUESTRA LA CONFIRMACIÓN

                $con->commit();

            }

        }

    }


    // 6. GUARDAR LOS DATOS MODIFICADOS

    if (isset($_POST['guardar_modificacion'])) {

        $id_paciente = 0;

        if (isset($_POST['id_paciente'])) {

            $id_paciente = filter_var(
                $_POST['id_paciente'],
                FILTER_VALIDATE_INT
            );

        }


        // REVISAR LOS CAMPOS DEL FORMULARIO

        $campos = [
            'nombre',
            'apellido',
            'nacimiento',
            'direccion',
            'correo',
            'telefono'
        ];

        foreach ($campos as $campo) {

            if (
                !isset($_POST[$campo])
                || !is_string($_POST[$campo])
            ) {
                $_POST[$campo] = '';
            }

        }


        // PREPARAR LOS TEXTOS PARA SQL

        $nombre_nuevo = $con->real_escape_string(
            trim($_POST['nombre'])
        );

        $apellido = $con->real_escape_string(
            trim($_POST['apellido'])
        );

        $direccion = $con->real_escape_string(
            trim($_POST['direccion'])
        );

        $correo = $con->real_escape_string(
            trim($_POST['correo'])
        );

        $telefono = $con->real_escape_string(
            trim($_POST['telefono'])
        );


        // PERMITIR QUE LA FECHA QUEDE VACÍA

        $nacimiento = 'NULL';

        if ($_POST['nacimiento'] != '') {

            $nacimiento = "'" .
                $con->real_escape_string($_POST['nacimiento']) .
                "'";

        }


        if (
            !$id_paciente
            || $id_paciente < 1
            || $nombre_nuevo == ''
            || $apellido == ''
        ) {

            $mensaje =
                'Seleccione un paciente e indique su nombre y apellido.';

        } else {

            $sql = "SELECT Cedula
                    FROM Paciente
                    WHERE ID_Paciente = $id_paciente";

            $resultado_paciente = $con->query($sql);

            $persona = $resultado_paciente->fetch_assoc();


            if (!$persona) {

                $mensaje = 'El paciente ya no está registrado.';

            } else {

                $cedula_paciente = $con->real_escape_string(
                    $persona['Cedula']
                );

                $sql = "UPDATE Persona
                        SET Nombre = '$nombre_nuevo',
                            Apellido = '$apellido',
                            Fecha_Nacimiento = $nacimiento,
                            Direccion = '$direccion',
                            Correo = '$correo',
                            Telefono = '$telefono'
                        WHERE Cedula = '$cedula_paciente'";

                $con->query($sql);

                $_SESSION['mensaje_pacientes'] =
                    'Paciente modificado correctamente.';

                header('Location: ' . $url_lista, true, 303);

                exit;

            }

        }

    }


    // 7. ABRIR EL FORMULARIO PARA MODIFICAR

    if (isset($_POST['modificar'])) {

        $id_paciente = filter_var(
            $_POST['modificar'],
            FILTER_VALIDATE_INT
        );

        if ($id_paciente && $id_paciente > 0) {

            $sql = "SELECT Persona.*,
                           Paciente.ID_Paciente
                    FROM Persona
                    INNER JOIN Paciente
                        ON Persona.Cedula = Paciente.Cedula
                    WHERE Paciente.ID_Paciente = $id_paciente";

            $resultado_modificar = $con->query($sql);

            $persona_modificar =
                $resultado_modificar->fetch_assoc();

        }

    }

} catch (mysqli_sql_exception $error) {

    // DESHACER LA ELIMINACIÓN O BAJA SI FALLÓ

    $con->rollback();

    error_log($error->getMessage());

    if ($error->getCode() == 1451) {

        $mensaje =
            'Hay otros registros relacionados con este paciente. No se realizó ningún cambio.';

    } else {

        $mensaje =
            'No se pudo completar la operación. No se guardaron cambios de la eliminación o baja.';

    }

    $paciente_accion = null;

}


// 8. BUSCAR Y MOSTRAR PACIENTES

if (isset($_POST['buscar'])) {

    if (
        isset($_POST['cedula'])
        && is_string($_POST['cedula'])
    ) {
        $cedula = trim($_POST['cedula']);
    }

    if (
        isset($_POST['nombre'])
        && is_string($_POST['nombre'])
    ) {
        $nombre = trim($_POST['nombre']);
    }

}

$buscar_cedula = $con->real_escape_string($cedula);

$buscar_nombre = $con->real_escape_string($nombre);

$resultado = null;

$pacientes = [];


try {

    // BUSCAR SEGÚN ESTADO, CÉDULA Y NOMBRE

    $sql = "SELECT Persona.*,
                   Paciente.ID_Paciente,
                   Paciente.Estado
            FROM Persona
            INNER JOIN Paciente
                ON Persona.Cedula = Paciente.Cedula
            WHERE Paciente.Estado = '$estado_lista'
              AND Persona.Cedula LIKE '%$buscar_cedula%'
              AND CONCAT(Persona.Nombre, ' ', Persona.Apellido)
                  LIKE '%$buscar_nombre%'
            ORDER BY Persona.Apellido,
                     Persona.Nombre,
                     Paciente.ID_Paciente";

    $resultado = $con->query($sql);


    while ($persona = $resultado->fetch_assoc()) {

        $id_paciente_lista = (int) $persona['ID_Paciente'];


        // CONTAR SUS TRASLADOS

        $sql = "SELECT COUNT(*) AS Total
                FROM Traslado
                WHERE ID_Paciente = $id_paciente_lista";

        $resultado_conteo = $con->query($sql);

        $conteo = $resultado_conteo->fetch_assoc();

        $persona['Traslados'] = $conteo['Total'];


        // CONTAR SUS ENCUESTAS

        $sql = "SELECT COUNT(*) AS Total
                FROM Encuesta
                WHERE ID_Paciente = $id_paciente_lista";

        $resultado_conteo = $con->query($sql);

        $conteo = $resultado_conteo->fetch_assoc();

        $persona['Encuestas'] = $conteo['Total'];


        // GUARDAR LA FILA PARA MOSTRARLA EN LA TABLA

        $pacientes[] = $persona;

    }

} catch (mysqli_sql_exception $error) {

    error_log($error->getMessage());

    $resultado = null;

    $pacientes = [];

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>HOSPITAL DE CLINICA</title>

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

<body class="pagina-documentacion">

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

    <aside class="menu-lateral menu-medico">

        <h3>Menú</h3>

        <a href="/prueba/panelprincipal.html">

            <i class="fa-solid fa-house"></i>

            Inicio

        </a>


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

                <a href="/prueba/php/registro_ambulancias.html">

                    <i class="fa-solid fa-list"></i>

                    Ver registros

                </a>

            </div>

        </details>


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

                    Lista de usuarios

                </a>

            </div>

        </details>


        <form method="POST" class="form-salir">

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

    <main class="contenido pagina-medico">

        <h2 class="titulo-panel">
            Pacientes registrados
        </h2>

        <p class="subtitulo-panel">
            Busque pacientes, consulte sus datos y gestione su baja o eliminación.
        </p>


        <div class="lista-documentacion">

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


            <!-- CONFIRMACIÓN -->

            <?php if ($paciente_accion !== null) { ?>

                <section class="tarjeta" id="confirmar-paciente">

                    <h3>

                        <?php
                        if ($accion_actual == 'baja') {
                            echo 'Dar de baja al paciente';
                        } else {
                            echo 'Eliminar al paciente';
                        }
                        ?>

                    </h3>

                    <p>

                        <strong>Paciente:</strong>

                        <?php
                        echo htmlspecialchars(
                            $paciente_accion['Nombre'] . ' ' .
                            $paciente_accion['Apellido'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </p>

                    <p>

                        <strong>Cédula:</strong>

                        <?php
                        echo htmlspecialchars(
                            $paciente_accion['Cedula'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </p>


                    <?php if ($accion_actual == 'baja') { ?>

                        <p>

                            Tiene

                            <?php
                            echo (int) $paciente_accion['Traslados'];
                            ?>

                            traslado(s) y

                            <?php
                            echo (int) $paciente_accion['Encuestas'];
                            ?>

                            encuesta(s).

                        </p>

                        <p>
                            Quedará inactivo para nuevos registros.
                            Se conservarán su historial y sus documentos.
                        </p>

                    <?php } else { ?>

                        <p>

                            Se eliminarán el paciente,

                            <?php
                            echo (int) $paciente_accion['Espacios'];
                            ?>

                            espacio(s) de documentación y

                            <?php
                            echo (int) $paciente_accion['Archivos'];
                            ?>

                            archivo(s) PDF.

                        </p>

                        <p>
                            La eliminación es definitiva.
                            Las cuentas de otros perfiles se conservan.
                        </p>

                    <?php } ?>


                    <form
                        action="<?php echo htmlspecialchars($url_lista, ENT_QUOTES, 'UTF-8'); ?>"
                        method="POST"
                        class="acciones-pacientes">

                        <input type="hidden"
                               name="token"
                               value="<?php echo $token; ?>">

                        <input type="hidden"
                               name="estado_lista"
                               value="<?php echo $estado_lista; ?>">

                        <input type="hidden"
                               name="accion"
                               value="<?php echo $accion_actual; ?>">

                        <button
                            type="submit"
                            name="confirmar_accion"
                            value="<?php echo (int) $paciente_accion['ID_Paciente']; ?>"
                            class="eliminar">

                            <i class="fa-solid fa-check"
                               aria-hidden="true"></i>

                            <?php
                            if ($accion_actual == 'baja') {
                                echo 'Confirmar baja';
                            } else {
                                echo 'Confirmar eliminación';
                            }
                            ?>

                        </button>

                        <a
                            href="<?php echo htmlspecialchars($url_lista, ENT_QUOTES, 'UTF-8'); ?>"
                            class="ver-pdf-medico">

                            Cancelar

                        </a>

                    </form>

                </section>

            <?php } ?>


            <!-- BUSCADOR -->

            <section class="tarjeta">

                <h3>Buscar paciente</h3>

                <form action="ver_paciente.php" method="POST">

                    <input type="hidden"
                           name="estado_lista"
                           value="<?php echo $estado_lista; ?>">

                    <div class="fila-pacientes">

                        <div class="campo-pacientes">

                            <label for="cedula">
                                Cédula
                            </label>

                            <input
                                type="text"
                                id="cedula"
                                name="cedula"
                                placeholder="Ej: 4.123.456-7"
                                value="<?php echo htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8'); ?>">

                        </div>

                        <div class="campo-pacientes">

                            <label for="nombre">
                                Nombre o apellido
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Juan"
                                value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>">

                        </div>

                    </div>


                    <div class="botones-busqueda-documentacion">

                        <button type="submit"
                                name="buscar"
                                class="boton-agendar">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            Buscar

                        </button>

                        <button type="submit"
                                name="mostrar_todos"
                                class="boton-agendar">

                            <i class="fa-solid fa-list"></i>

                            Mostrar todos

                        </button>


                        <?php if ($estado_lista == 'Activo') { ?>

                            <button type="submit"
                                    name="ver_inactivos"
                                    class="boton-agendar">

                                <i class="fa-solid fa-user-slash"></i>

                                Ver inactivos

                            </button>

                        <?php } else { ?>

                            <button type="submit"
                                    name="ver_activos"
                                    class="boton-agendar">

                                <i class="fa-solid fa-users"></i>

                                Ver activos

                            </button>

                        <?php } ?>

                    </div>

                </form>

            </section>


            <!-- MODIFICAR PACIENTE -->

            <?php if ($persona_modificar) { ?>

                <section class="tarjeta" id="modificar-paciente">

                    <h3>Modificar paciente</h3>

                    <form action="ver_paciente.php"
                          method="POST"
                          class="form-modificar">

                        <input type="hidden"
                               name="token"
                               value="<?php echo $token; ?>">

                        <input
                            type="hidden"
                            name="id_paciente"
                            value="<?php echo (int) $persona_modificar['ID_Paciente']; ?>">

                        <input type="hidden"
                               name="estado_lista"
                               value="<?php echo $estado_lista; ?>">


                        <div class="campos-modificar">

                            <div>

                                <label>Nombre</label>

                                <input
                                    type="text"
                                    name="nombre"
                                    maxlength="50"
                                    required
                                    value="<?php echo htmlspecialchars($persona_modificar['Nombre'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                            <div>

                                <label>Apellido</label>

                                <input
                                    type="text"
                                    name="apellido"
                                    maxlength="50"
                                    required
                                    value="<?php echo htmlspecialchars($persona_modificar['Apellido'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                            <div>

                                <label>Fecha de nacimiento</label>

                                <input
                                    type="date"
                                    name="nacimiento"
                                    value="<?php echo htmlspecialchars((string) $persona_modificar['Fecha_Nacimiento'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                            <div>

                                <label>Dirección</label>

                                <input
                                    type="text"
                                    name="direccion"
                                    maxlength="150"
                                    value="<?php echo htmlspecialchars((string) $persona_modificar['Direccion'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                            <div>

                                <label>Correo</label>

                                <input
                                    type="email"
                                    name="correo"
                                    maxlength="100"
                                    value="<?php echo htmlspecialchars((string) $persona_modificar['Correo'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                            <div>

                                <label>Teléfono</label>

                                <input
                                    type="text"
                                    name="telefono"
                                    maxlength="20"
                                    value="<?php echo htmlspecialchars((string) $persona_modificar['Telefono'], ENT_QUOTES, 'UTF-8'); ?>">

                            </div>

                        </div>


                        <button
                            type="submit"
                            name="guardar_modificacion"
                            class="boton-guardar-modificacion">

                            <i class="fa-solid fa-check"></i>

                            Guardar cambios

                        </button>

                    </form>

                </section>

            <?php } ?>


            <!-- LISTA DE PACIENTES -->

            <section class="tarjeta">

                <h3>

                    <?php
                    if ($estado_lista == 'Activo') {
                        echo 'Pacientes activos';
                    } else {
                        echo 'Pacientes dados de baja';
                    }
                    ?>

                </h3>


                <?php if (!$resultado) { ?>

                    <p>
                        No se pudieron consultar los pacientes.
                    </p>

                <?php } elseif ($resultado->num_rows == 0) { ?>

                    <p>
                        No hay pacientes para mostrar con esta selección.
                    </p>

                <?php } else { ?>

                    <div class="tabla-scroll">

                        <table class="big-table">

                            <thead>

                                <tr>

                                    <th>Cédula</th>
                                    <th>Nombre</th>
                                    <th>Apellido</th>
                                    <th>Teléfono</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($pacientes as $persona) { ?>

                                    <tr>

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $persona['Cedula'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $persona['Nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                $persona['Apellido'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>

                                        <td>

                                            <?php
                                            echo htmlspecialchars(
                                                (string) $persona['Telefono'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>

                                        </td>

                                        <td>

                                            <span class="estado-documentacion">

                                                <?php
                                                echo htmlspecialchars(
                                                    $persona['Estado'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <td class="acciones-pacientes">

                                            <!-- MODIFICAR -->

                                            <form
                                                action="ver_paciente.php#modificar-paciente"
                                                method="POST">

                                                <input
                                                    type="hidden"
                                                    name="estado_lista"
                                                    value="<?php echo $estado_lista; ?>">

                                                <button
                                                    type="submit"
                                                    name="modificar"
                                                    value="<?php echo (int) $persona['ID_Paciente']; ?>"
                                                    class="editar"
                                                    title="Modificar paciente"
                                                    aria-label="Modificar paciente">

                                                    <i class="fa-solid fa-pen"></i>

                                                </button>

                                            </form>


                                            <!-- REACTIVAR -->

                                            <?php
                                            if ($persona['Estado'] == 'Inactivo') {
                                            ?>

                                                <form
                                                    action="ver_paciente.php"
                                                    method="POST">

                                                    <input
                                                        type="hidden"
                                                        name="token"
                                                        value="<?php echo $token; ?>">

                                                    <button
                                                        type="submit"
                                                        name="reactivar"
                                                        value="<?php echo (int) $persona['ID_Paciente']; ?>"
                                                        class="editar">

                                                        <i class="fa-solid fa-user-check"></i>

                                                        Reactivar

                                                    </button>

                                                </form>

                                            <?php } ?>


                                            <!-- ELIMINAR O DAR DE BAJA -->

                                            <?php
                                            if (
                                                $persona['Estado'] == 'Activo'
                                                || (
                                                    (int) $persona['Traslados'] == 0
                                                    && (int) $persona['Encuestas'] == 0
                                                )
                                            ) {
                                            ?>

                                                <form
                                                    action="<?php echo htmlspecialchars($url_lista, ENT_QUOTES, 'UTF-8'); ?>#confirmar-paciente"
                                                    method="POST">

                                                    <input
                                                        type="hidden"
                                                        name="token"
                                                        value="<?php echo $token; ?>">

                                                    <input
                                                        type="hidden"
                                                        name="estado_lista"
                                                        value="<?php echo $estado_lista; ?>">

                                                    <button
                                                        type="submit"
                                                        name="eliminar"
                                                        value="<?php echo (int) $persona['ID_Paciente']; ?>"
                                                        class="eliminar">

                                                        <?php
                                                        if (
                                                            (int) $persona['Traslados'] > 0
                                                            || (int) $persona['Encuestas'] > 0
                                                        ) {
                                                        ?>

                                                            <i class="fa-solid fa-user-slash"></i>

                                                            Dar de baja

                                                        <?php } else { ?>

                                                            <i class="fa-solid fa-trash"></i>

                                                            Eliminar

                                                        <?php } ?>

                                                    </button>

                                                </form>

                                            <?php } ?>

                                        </td>

                                    </tr>

                                <?php } ?>

                            </tbody>

                        </table>

                    </div>

                <?php } ?>

            </section>

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