<?php
session_start();
date_default_timezone_set('America/Montevideo');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: strict-origin-when-cross-origin');

$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);
if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

$mensaje = '';
$aviso = $_SESSION['aviso_conductor'] ?? '';
unset($_SESSION['aviso_conductor']);
$estados = ['En curso', 'En destino', 'Finalizado'];

try {
    // 1. COMPROBAR LA CUENTA Y BUSCAR EL CONDUCTOR
    $sql = "SELECT Funcionario.ID_Funcionario, Persona.Nombre, Persona.Apellido
            FROM Usuario
            INNER JOIN Funcionario ON Usuario.Cedula = Funcionario.Cedula
            INNER JOIN Persona ON Usuario.Cedula = Persona.Cedula
            WHERE Usuario.ID_Usuario = $id_usuario
              AND Usuario.Rol = 'Conductor' AND Usuario.Estado = 'Activo'
              AND Funcionario.Tipo = 'Conductor'";

    $conductor = $con->query($sql)->fetch_assoc();

    if (!$conductor) {
        http_response_code(403);
        exit('Esta página está disponible para conductores con una cuenta activa.');
    }

    $id_funcionario = (int) $conductor['ID_Funcionario'];

    // 2. COMPROBAR EL TOKEN ANTES DE GUARDAR O CERRAR SESIÓN
    if (!isset($_SESSION['token_conductor'])) {
        $_SESSION['token_conductor'] = bin2hex(random_bytes(32));
    }

    $token = $_SESSION['token_conductor'];

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!is_string($_POST['token'] ?? null)
            || !hash_equals($token, $_POST['token'])) {
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

    // 3. MOSTRAR SOLAMENTE SUS TRASLADOS ASIGNADOS
    $id_traslado = filter_var($_GET['traslado'] ?? 0, FILTER_VALIDATE_INT);

    if (isset($_GET['traslado']) && (!$id_traslado || $id_traslado < 1)) {
        http_response_code(404);
        exit('Traslado no disponible.');
    }

    $sql = "SELECT Traslado.*, Ambulancia.Numero_Ambulancia,
                   CONCAT(Persona.Nombre, ' ', Persona.Apellido) AS Nombre_Paciente,
                   (SELECT GROUP_CONCAT(CONCAT(P.Nombre, ' ', P.Apellido) SEPARATOR ', ')
                    FROM Acompanante
                    INNER JOIN Persona AS P ON Acompanante.Cedula = P.Cedula
                    WHERE Acompanante.ID_Traslado = Traslado.ID_Traslado) AS Acompanantes,
                   (SELECT GROUP_CONCAT(CONCAT(Carga.Tipo, ': ', Carga.Descripcon) SEPARATOR '; ')
                    FROM Carga
                    WHERE Carga.ID_Traslado = Traslado.ID_Traslado) AS Carga
            FROM Traslado
            LEFT JOIN Ambulancia ON Traslado.Matricula = Ambulancia.Matricula
            LEFT JOIN Paciente ON Traslado.ID_Paciente = Paciente.ID_Paciente
            LEFT JOIN Persona ON Paciente.Cedula = Persona.Cedula
            WHERE Traslado.ID_Funcionario = $id_funcionario";

    if ($id_traslado > 0) {
        $sql .= " AND Traslado.ID_Traslado = $id_traslado";
    }

    $sql .= " ORDER BY (Traslado.Estado = 'Finalizado'),
                      Traslado.Fecha DESC, Traslado.ID_Traslado DESC";

    $traslados = $con->query($sql);
    $traslado = $id_traslado > 0 ? $traslados->fetch_assoc() : null;

    if ($id_traslado > 0 && !$traslado) {
        http_response_code(404);
        exit('Traslado no disponible para esta cuenta.');
    }

    // 4. COMPLETAR LAS HORAS, EL ESTADO Y EL RECORRIDO
    $hora_salida = $traslado ? substr($traslado['Hora_Salida'], 0, 5) : '';
    $hora_llegada = $traslado ? substr($traslado['Hora_Llegada'] ?? '', 0, 5) : '';
    $estado = $traslado['Estado'] ?? '';
    $recorrido = $traslado['Recorrido'] ?? '[]';

    if (isset($_POST['guardar'])) {
        if (!$traslado) {
            http_response_code(404);
            exit('Seleccione uno de sus traslados.');
        }

        $hora_salida = is_string($_POST['hora_salida'] ?? null)
            ? trim($_POST['hora_salida']) : '';

        $hora_llegada = is_string($_POST['hora_llegada'] ?? null)
            ? trim($_POST['hora_llegada']) : '';

        $estado = is_string($_POST['estado'] ?? null) ? $_POST['estado'] : '';
        $formato_hora = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

        // El recorrido es una lista de puntos: latitud y longitud.
        $recorrido = is_string($_POST['recorrido'] ?? null)
            ? $_POST['recorrido'] : '';

        $puntos = strlen($recorrido) <= 30000
            ? json_decode($recorrido, true, 4) : null;

        $recorrido_valido = is_array($puntos) && array_is_list($puntos)
            && count($puntos) <= 500 && count($puntos) != 1;

        if ($recorrido_valido) {
            foreach ($puntos as $punto) {
                if (!is_array($punto) || !array_is_list($punto)
                    || count($punto) != 2
                    || !(is_int($punto[0]) || is_float($punto[0]))
                    || !(is_int($punto[1]) || is_float($punto[1]))
                    || abs($punto[0]) > 90 || abs($punto[1]) > 180) {
                    $recorrido_valido = false;
                    break;
                }
            }
        }

        if (!$recorrido_valido) {
            $recorrido = $traslado['Recorrido'] ?? '[]';
        }

        if (!preg_match($formato_hora, $hora_salida)
            || ($hora_llegada != '' && !preg_match($formato_hora, $hora_llegada))
            || !in_array($estado, $estados, true)) {
            $mensaje = 'Complete una hora de salida válida y seleccione el estado.';
        } elseif (!$recorrido_valido) {
            $mensaje = 'Recorrido inválido: marque de 2 a 500 puntos, o borre el recorrido para guardar sin puntos.';
        } elseif ($estado != 'En curso' && $hora_llegada == '') {
            $mensaje = 'Indique la hora de llegada al destino antes de guardar ese estado.';
        } else {
            $llegada_guardar = $hora_llegada == '' ? null : $hora_llegada;
            $recorrido_guardar = $puntos ? json_encode($puntos) : null;

            $consulta = $con->prepare(
                'UPDATE Traslado
                 SET Hora_Salida = ?, Hora_Llegada = ?, Estado = ?, Recorrido = ?
                 WHERE ID_Traslado = ? AND ID_Funcionario = ?'
            );

            $consulta->bind_param(
                'ssssii',
                $hora_salida,
                $llegada_guardar,
                $estado,
                $recorrido_guardar,
                $id_traslado,
                $id_funcionario
            );

            $consulta->execute();

            $_SESSION['aviso_conductor'] = $consulta->affected_rows == 1
                ? 'Datos del traslado guardados correctamente.'
                : 'Sin cambios: los datos ya estaban guardados o la asignación cambió.';

            header('Location: inicio_conductor.php?traslado=' . $id_traslado, true, 303);
            exit;
        }
    }
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo consultar o guardar el traslado. Intente nuevamente.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis traslados | Hospital de Clínicas</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <?php if ($traslado) { ?>
        <link rel="stylesheet"
              href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin="anonymous">

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin="anonymous" defer></script>

        <script src="/prueba/js/conductor.js" defer></script>
    <?php } ?>

    <link rel="stylesheet" href="/prueba/css/conductor.css">
</head>
<body>
<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital">
        <p>Hospital de Clínicas<br>Conductor</p>
    </div>
</header>

<main>
    <h1>Mis traslados</h1>

    <p>
        <?php echo htmlspecialchars(
            $conductor['Nombre'] . ' ' . $conductor['Apellido'],
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </p>

    <form action="inicio_conductor.php" method="POST">
        <input type="hidden" name="token" value="<?php echo $token; ?>">
        <button type="submit" name="cerrar_sesion" class="boton secundario">
            Cerrar sesión
        </button>
    </form>

    <?php if ($aviso != '') { ?>
        <p class="aviso" role="status">
            <?php echo htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php } ?>

    <?php if ($mensaje != '') { ?>
        <p class="aviso error" role="alert">
            <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php } ?>

    <?php if ($traslado) { ?>
        <section class="tarjeta">
            <h2>Traslado #<?php echo (int) $traslado['ID_Traslado']; ?></h2>

            <p><strong>Fecha:</strong>
                <?php echo date('d/m/Y', strtotime($traslado['Fecha'])); ?>
            </p>

            <p><strong>Ambulancia:</strong>
                A-<?php echo (int) $traslado['Numero_Ambulancia']; ?>
                · <?php echo htmlspecialchars($traslado['Matricula'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <p><strong>Origen:</strong>
                <?php echo htmlspecialchars($traslado['Origen'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <p><strong>Destino:</strong>
                <?php echo htmlspecialchars($traslado['Destino'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <?php if ($traslado['Nombre_Paciente']) { ?>
                <p><strong>Paciente:</strong>
                    <?php echo htmlspecialchars($traslado['Nombre_Paciente'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php } ?>

            <?php if ($traslado['Carga']) { ?>
                <p><strong>Carga:</strong>
                    <?php echo htmlspecialchars($traslado['Carga'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php } ?>

            <p><strong>Acompañante:</strong>
                <?php echo htmlspecialchars(
                    $traslado['Acompanantes'] ?: 'Sin acompañante',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>

            <form action="inicio_conductor.php?traslado=<?php echo $id_traslado; ?>" method="POST">
                <input type="hidden" name="token" value="<?php echo $token; ?>">

                <h3>Recorrido del traslado</h3>
                <p>
                    Acérquese a las calles y toque el mapa para marcar puntos
                    del recorrido. Luego pulse Guardar cambios.
                </p>

                <input type="hidden" id="recorrido" name="recorrido"
                       value="<?php echo htmlspecialchars($recorrido, ENT_QUOTES, 'UTF-8'); ?>">

                <div id="mapa-recorrido" class="mapa-recorrido"
                     role="region" aria-label="Mapa para marcar el recorrido"></div>

                <div class="acciones-recorrido">
                    <button type="button" id="deshacer-punto" class="boton secundario">
                        Quitar último punto
                    </button>

                    <button type="button" id="limpiar-recorrido" class="boton secundario">
                        Borrar recorrido
                    </button>
                </div>

                <p id="estado-recorrido" role="status" aria-live="polite">
                    Cargando mapa…
                </p>

                <div class="campo">
                    <label for="hora_salida">Hora de salida</label>
                    <input type="time" id="hora_salida" name="hora_salida"
                           value="<?php echo htmlspecialchars($hora_salida, ENT_QUOTES, 'UTF-8'); ?>"
                           required>
                </div>

                <div class="campo">
                    <label for="hora_llegada">Hora de llegada al destino</label>
                    <input type="time" id="hora_llegada" name="hora_llegada"
                           value="<?php echo htmlspecialchars($hora_llegada, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="campo">
                    <label for="estado">Estado del traslado</label>
                    <select id="estado" name="estado" required>
                        <?php foreach ($estados as $opcion) { ?>
                            <option value="<?php echo $opcion; ?>"
                                <?php if ($estado == $opcion) echo 'selected'; ?>>
                                <?php echo $opcion; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <p>
                    Marque Finalizado cuando haya terminado el traslado
                    y regresado al hospital.
                </p>

                <button type="submit" name="guardar" class="boton guardar">
                    Guardar cambios
                </button>
            </form>
        </section>

        <p>
            <a href="inicio_conductor.php" class="boton secundario">
                Volver a mis traslados
            </a>
        </p>

    <?php } elseif ($traslados->num_rows == 0) { ?>
        <section class="tarjeta">
            <p>No tiene traslados asignados.</p>
        </section>

    <?php } else { ?>
        <p>Seleccione un traslado para completar sus datos.</p>

        <?php while ($fila = $traslados->fetch_assoc()) { ?>
            <article class="tarjeta">
                <h2>Traslado #<?php echo (int) $fila['ID_Traslado']; ?></h2>

                <p>
                    <?php echo date('d/m/Y', strtotime($fila['Fecha'])); ?>
                    · Ambulancia A-<?php echo (int) $fila['Numero_Ambulancia']; ?>
                </p>

                <p><strong>Origen:</strong>
                    <?php echo htmlspecialchars($fila['Origen'], ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <p><strong>Destino:</strong>
                    <?php echo htmlspecialchars($fila['Destino'], ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <p><strong>Estado:</strong>
                    <?php echo htmlspecialchars($fila['Estado'], ENT_QUOTES, 'UTF-8'); ?>
                </p>

                <a href="inicio_conductor.php?traslado=<?php echo (int) $fila['ID_Traslado']; ?>"
                   class="boton">
                    Ver traslado
                </a>
            </article>
        <?php } ?>
    <?php } ?>
</main>
</body>
</html>
<?php $con->close(); ?>