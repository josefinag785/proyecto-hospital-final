<?php

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../conexion.php';

$con->set_charset('utf8mb4');


// COMPROBAR QUIÉN INICIÓ SESIÓN

$id_usuario = filter_var(
    $_SESSION['id_usuario'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}


// BUSCAR AL MÉDICO Y COMPROBAR SU CUENTA

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

$resultado = $con->query($sql);

if (!$resultado) {

    error_log($con->error);

    http_response_code(500);

    exit('No se pudo comprobar el acceso. Intente nuevamente.');

}

if ($resultado->num_rows == 0) {

    http_response_code(403);

    exit('Esta página está disponible para médicos con una cuenta activa.');

}

$medico = $resultado->fetch_assoc();

$id_medico = (int) $medico['ID_Funcionario'];

header('Cache-Control: no-store, private');


// PREPARAR LA COMPROBACIÓN DE LOS FORMULARIOS

if (!isset($_SESSION['token_documentacion_medico'])) {

    $_SESSION['token_documentacion_medico'] = bin2hex(random_bytes(32));

}

$token = $_SESSION['token_documentacion_medico'];

if (
    isset($_POST['cerrar_sesion'])
    || isset($_POST['leer_aviso'])
) {

    $token_enviado = $_POST['token'] ?? '';

    if (
        !is_string($token_enviado)
        || !hash_equals($token, $token_enviado)
    ) {

        http_response_code(403);

        exit('Recargue la página y vuelva a intentarlo.');

    }

}


// CERRAR SESIÓN

if (isset($_POST['cerrar_sesion'])) {

    $_SESSION = [];

    session_destroy();

    header('Location: /prueba/php/login.php');

    exit;

}


// MARCAR EL AVISO COMO LEÍDO

$error_aviso = '';

if (isset($_POST['leer_aviso'])) {

    $sql = "UPDATE Documento
            SET Aviso_Leido = 1
            WHERE ID_Funcionario = $id_medico
              AND Aviso_Leido = 0";

    if ($con->query($sql)) {

        header(
            'Location: ' . basename(__FILE__),
            true,
            303
        );

        exit;

    }

    error_log($con->error);

    $error_aviso = 'No se pudo marcar el aviso como leído. Intente nuevamente.';

}


// CONTAR LOS PACIENTES ASIGNADOS

$sql = "SELECT COUNT(DISTINCT ID_Paciente) AS Total
        FROM Documento
        WHERE ID_Funcionario = $id_medico";

$resultado_pacientes = $con->query($sql);

$total_pacientes = null;

if ($resultado_pacientes) {

    $fila = $resultado_pacientes->fetch_assoc();

    $total_pacientes = (int) $fila['Total'];

} else {

    error_log($con->error);

}


// BUSCAR LOS PACIENTES NUEVOS

$sql = "SELECT DISTINCT Paciente.ID_Paciente,
                        Persona.Nombre,
                        Persona.Apellido
        FROM Documento
        INNER JOIN Paciente
            ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona
            ON Paciente.Cedula = Persona.Cedula
        WHERE Documento.ID_Funcionario = $id_medico
          AND Documento.Aviso_Leido = 0
        ORDER BY Persona.Nombre,
                 Persona.Apellido,
                 Paciente.ID_Paciente";

$pacientes_nuevos = $con->query($sql);

if (!$pacientes_nuevos) {

    error_log($con->error);

    $error_aviso = 'No se pudieron consultar los avisos. Intente nuevamente.';

}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Inicio del médico | Hospital de Clínicas</title>

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

<body class="inicio-medico">


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


        <a href="<?php echo basename(__FILE__); ?>"
           class="opcion-medico activo"
           aria-current="page">

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
           class="opcion-medico">

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


    <!-- CONTENIDO DEL INICIO -->

    <main class="contenido">

        <h2 class="titulo-panel">
            Inicio
        </h2>

        <p class="subtitulo-panel">
            Bienvenido a su panel médico.
        </p>


        <!-- MOSTRAR ERRORES DEL AVISO -->

        <?php if ($error_aviso != '') { ?>

            <section class="tarjeta aviso-pacientes"
                     role="alert">

                <p>
                    <?php
                    echo htmlspecialchars(
                        $error_aviso,
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>

            </section>

        <?php } ?>


        <!-- AVISO DE PACIENTES NUEVOS -->

        <?php
        if (
            $pacientes_nuevos
            && $pacientes_nuevos->num_rows > 0
        ) {
        ?>

            <section class="tarjeta aviso-pacientes"
                     aria-labelledby="titulo-aviso">

                <h3 id="titulo-aviso">

                    <i class="fa-solid fa-bell"
                       aria-hidden="true"></i>

                    Pacientes nuevos:
                    <?php echo $pacientes_nuevos->num_rows; ?>

                </h3>

                <p>
                    Se le asignaron los siguientes pacientes:
                </p>


                <ul>

                    <?php
                    while ($nuevo = $pacientes_nuevos->fetch_assoc()) {
                    ?>

                        <li>
                            <?php
                            echo htmlspecialchars(
                                $nuevo['Nombre'] . ' ' . $nuevo['Apellido'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </li>

                    <?php } ?>

                </ul>


                <form method="POST">

                    <input type="hidden"
                           name="token"
                           value="<?php echo $token; ?>">

                    <a href="mis_pacientes.php"
                       class="boton-ver-lista">

                        Ver pacientes

                    </a>

                    <button type="submit"
                            name="leer_aviso"
                            class="boton-ver-lista">

                        Marcar aviso como leído

                    </button>

                </form>

            </section>

        <?php } ?>


        <!-- TOTAL DE PACIENTES ASIGNADOS -->

        <section class="tarjeta resumen-medico"
                 aria-labelledby="titulo-pacientes">

            <i class="fa-solid fa-users icono-resumen"
               aria-hidden="true"></i>

            <h3 id="titulo-pacientes">
                Pacientes asignados
            </h3>

            <?php if ($total_pacientes !== null) { ?>

                <p class="numero-pacientes"><?php echo $total_pacientes; ?></p>

                <p>
                    Total de pacientes a su cargo.
                </p>

            <?php } else { ?>

                <p>
                    No se pudo consultar la cantidad de pacientes.
                    Intente nuevamente.
                </p>

            <?php } ?>

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