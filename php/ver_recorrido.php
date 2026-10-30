<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/conexion.php';
$con->set_charset('utf8mb4');
header('Cache-Control: no-store, private');
header('Referrer-Policy: strict-origin-when-cross-origin');

$id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: /prueba/php/login.php');
    exit;
}

try {
    // COMPROBAR QUE SEA UN ADMINISTRADOR ACTIVO
    $sql = "SELECT ID_Usuario FROM Usuario
            WHERE ID_Usuario = $id_usuario
              AND Rol = 'Administrador' AND Estado = 'Activo'";

    if ($con->query($sql)->num_rows != 1) {
        http_response_code(403);
        exit('Esta página está disponible para administradores con una cuenta activa.');
    }

    $id_traslado = filter_var($_GET['traslado'] ?? null, FILTER_VALIDATE_INT);

    if (!$id_traslado || $id_traslado < 1) {
        http_response_code(404);
        exit('Traslado no disponible.');
    }

    // CONSULTAR EL RECORRIDO GUARDADO POR EL CONDUCTOR
    $consulta = $con->prepare(
        'SELECT ID_Traslado, Fecha, Origen, Destino, Estado, Recorrido
         FROM Traslado WHERE ID_Traslado = ?'
    );

    $consulta->bind_param('i', $id_traslado);
    $consulta->execute();
    $traslado = $consulta->get_result()->fetch_assoc();

    if (!$traslado) {
        http_response_code(404);
        exit('Traslado no disponible.');
    }

    $recorrido = $traslado['Recorrido'] ?? '[]';
    $puntos = json_decode($recorrido, true);

    $hay_recorrido = is_array($puntos)
        && array_is_list($puntos)
        && count($puntos) >= 2;

} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('No se pudo consultar el recorrido. Intente nuevamente.');
}

$con->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recorrido del traslado | Hospital de Clínicas</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <?php if ($hay_recorrido) { ?>
        <link rel="stylesheet"
              href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin="anonymous">

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin="anonymous" defer></script>

        <script src="/prueba/js/ver_recorrido.js" defer></script>
    <?php } ?>

    <link rel="stylesheet" href="/prueba/css/conductor.css">
</head>
<body>

<header>
    <div class="encabezado">
        <img src="/prueba/img/LOGO PROYECTO DHC.png" alt="Logo del hospital">
        <p>Hospital de Clínicas<br>Administrador</p>
    </div>
</header>

<main>
    <h1>
        Recorrido del traslado #<?php echo (int) $traslado['ID_Traslado']; ?>
    </h1>

    <section class="tarjeta">
        <p><strong>Fecha:</strong>
            <?php echo date('d/m/Y', strtotime($traslado['Fecha'])); ?>
        </p>

        <p><strong>Origen:</strong>
            <?php echo htmlspecialchars($traslado['Origen'], ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <p><strong>Destino:</strong>
            <?php echo htmlspecialchars($traslado['Destino'], ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <p><strong>Estado:</strong>
            <?php echo htmlspecialchars($traslado['Estado'], ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <?php if ($hay_recorrido) { ?>
            <input type="hidden" id="recorrido-guardado"
                   value="<?php echo htmlspecialchars($recorrido, ENT_QUOTES, 'UTF-8'); ?>">

            <div id="mapa-recorrido" class="mapa-recorrido"
                 role="region" aria-label="Recorrido guardado por el conductor">
            </div>

            <p id="estado-recorrido" role="status">Cargando mapa…</p>
        <?php } else { ?>
            <p>El conductor todavía no ha guardado un recorrido para este traslado.</p>
        <?php } ?>
    </section>

    <p>
        <a href="/prueba/php/ver_traslados.php" class="boton secundario">
            Volver a traslados
        </a>
    </p>
</main>

</body>
</html>