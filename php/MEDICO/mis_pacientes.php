<?php

session_start();
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/../conexion.php';
$con->set_charset('utf8mb4');

// 1. IDENTIFICAR AL MÉDICO QUE INICIÓ SESIÓN
$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: ../prueba/php/login.php');
    exit;
}

$sql = "SELECT Funcionario.ID_Funcionario, Persona.Nombre, Persona.Apellido
        FROM Funcionario
        INNER JOIN Usuario ON Funcionario.Cedula = Usuario.Cedula
        INNER JOIN Persona ON Funcionario.Cedula = Persona.Cedula
        WHERE Usuario.ID_Usuario = $id_usuario
          AND Usuario.Rol = 'Medico'
          AND Usuario.Estado = 'Activo'
          AND Funcionario.Tipo = 'Medico'";

$resultado_medico = $con->query($sql);

if (!$resultado_medico) {
    error_log($con->error);
    http_response_code(500);
    exit('No se pudo comprobar el acceso. Intente nuevamente.');
}

if ($resultado_medico->num_rows == 0) {
    http_response_code(403);
    exit('Esta página está disponible para médicos con una cuenta activa.');
}

$medico = $resultado_medico->fetch_assoc();
$id_medico = (int) $medico['ID_Funcionario'];

header('Cache-Control: no-store, private');

// 2. CERRAR SESIÓN
if (!isset($_SESSION['token_documentacion_medico'])) {
    $_SESSION['token_documentacion_medico'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_documentacion_medico'];

if (isset($_POST['cerrar_sesion'])) {

    $token_enviado = $_POST['token'] ?? '';

    if (
        !is_string($token_enviado)
        || !hash_equals($token, $token_enviado)
    ) {

        http_response_code(403);

        exit('Recargue la página y vuelva a intentarlo.');

    }

    $_SESSION = [];

    session_destroy();

    header('Location: /prueba/php/login.php');

    exit;

}

// 3. RECIBIR LOS CAMPOS DE BÚSQUEDA
$cedula = '';
$nombre = '';

if (isset($_POST['buscar'])) {
    if (isset($_POST['cedula']) && is_string($_POST['cedula'])) {
        $cedula = trim($_POST['cedula']);
    }

    if (isset($_POST['nombre']) && is_string($_POST['nombre'])) {
        $nombre = trim($_POST['nombre']);
    }
}

// 4. BUSCAR SOLAMENTE PACIENTES ASIGNADOS A ESTE MÉDICO
// DISTINCT evita repetir un paciente si tiene más de un espacio antiguo.
$sql = "SELECT DISTINCT Paciente.ID_Paciente,
                        Persona.Cedula,
                        Persona.Nombre,
                        Persona.Apellido,
                        Persona.Fecha_Nacimiento,
                        Persona.Direccion,
                        Persona.Correo,
                        Persona.Telefono
        FROM Documento
        INNER JOIN Paciente ON Documento.ID_Paciente = Paciente.ID_Paciente
        INNER JOIN Persona ON Paciente.Cedula = Persona.Cedula
        WHERE Documento.ID_Funcionario = $id_medico";

if ($cedula != '') {
    $cedula_sql = $con->real_escape_string($cedula);
    $sql .= " AND Persona.Cedula = '$cedula_sql'";
}

if ($nombre != '') {
    $nombre_sql = $con->real_escape_string($nombre);
    $sql .= " AND CONCAT(Persona.Nombre, ' ', Persona.Apellido)
              LIKE '%$nombre_sql%'";
}

$sql .= " ORDER BY Persona.Nombre, Persona.Apellido,
                  Persona.Cedula, Paciente.ID_Paciente";

$resultado = $con->query($sql);

if (!$resultado) {
    error_log($con->error);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pacientes | Hospital de Clínicas</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/prueba/css/style.css">
    <link rel="stylesheet" href="/prueba/css/medico.css">
</head>

<body class="inicio-medico pagina-mis-pacientes">

<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital" class="logo">
        <h1>HOSPITAL DE CLÍNICAS</h1>
    </div>
</header>

<div class="perfil-contenedor">

    <!-- MENÚ LATERAL -->
    <aside class="menu-lateral menu-medico">

        <div class="nombre-medico">
            <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
            <p>Médico</p>
            <strong><?php echo htmlspecialchars($medico['Nombre'] . ' ' . $medico['Apellido'], ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <h3>Menú</h3>

        <a href="/prueba/php/MEDICO/inicio_medico.php" class="opcion-medico">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            Inicio
        </a>

        <a href="mis_pacientes.php" class="opcion-medico activo" aria-current="page">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            Mis pacientes
        </a>

 <a href="/prueba/php/MEDICO/nueva_documentacion.php"
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

    <main class="contenido">

        <h2 class="titulo-panel">Mis pacientes</h2>
        <p class="subtitulo-panel">Busque y consulte la información de sus pacientes asignados.</p>

        <!-- BUSCADOR -->
        <section class="tarjeta" aria-labelledby="titulo-buscador">
            <h3 id="titulo-buscador">Buscar paciente</h3>

            <form action="mis_pacientes.php#lista-pacientes" method="POST">
                <div class="fila-pacientes">

                    <div class="campo-pacientes">
                        <label for="cedula">Cédula</label>
                        <input type="text" id="cedula" name="cedula" maxlength="20" placeholder="Ej: 56838677" value="<?php echo htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="campo-pacientes">
                        <label for="nombre">Nombre o apellido</label>
                        <input type="text" id="nombre" name="nombre" maxlength="101" placeholder="Ej: Juan Pérez" value="<?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                </div>

                <div class="botones-buscador">
                    <button type="submit" name="buscar" class="boton-medico">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Buscar
                    </button>

                    <button type="submit" name="mostrar_todos" class="boton-medico">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        Mostrar todos
                    </button>
                </div>
            </form>
        </section>

        <!-- LISTA DE PACIENTES -->
        <section class="tarjeta" id="lista-pacientes" aria-labelledby="titulo-lista">
            <h3 id="titulo-lista">Lista de pacientes</h3>

            <?php if (!$resultado) { ?>
                <p>No se pudo cargar la lista de pacientes. Intente nuevamente.</p>
            <?php } elseif ($resultado->num_rows == 0) { ?>
                <p>No hay pacientes para mostrar con esta búsqueda.</p>
            <?php } else { ?>

                <p class="detalle-inicio">Resultados: <?php echo $resultado->num_rows; ?></p>

                <div class="tabla-scroll">
                    <table class="big-table">
                        <thead>
                            <tr>
                                <th scope="col">Cédula</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Apellido</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Información</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php while ($paciente = $resultado->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($paciente['Cedula'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($paciente['Nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($paciente['Apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($paciente['Telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>

                                    <td>
                                        <details class="datos-paciente">
                                            <summary>
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                Ver datos
                                            </summary>

                                            <div class="informacion-paciente">
                                                <p><strong>Fecha de nacimiento:</strong><br>
                                                    <?php
                                                    if (!empty($paciente['Fecha_Nacimiento'])) {
                                                        echo date('d/m/Y', strtotime($paciente['Fecha_Nacimiento']));
                                                    } else {
                                                        echo 'No registrada';
                                                    }
                                                    ?>
                                                </p>

                                                <p><strong>Dirección:</strong><br><?php echo htmlspecialchars($paciente['Direccion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><strong>Correo:</strong><br><?php echo htmlspecialchars($paciente['Correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            <?php } ?>

                        </tbody>
                    </table>
                </div>

            <?php } ?>
        </section>

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
