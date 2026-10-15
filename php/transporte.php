<?php

require_once 'conexion.php';


// Recibir los datos del formulario

$id_paciente = $_POST['id_paciente'] ?? null;
$tipo_traslado = $_POST['tipo_traslado'] ?? '';
$elemento_trasladar = $_POST['elemento_trasladar'] ?? '';

$matricula = $_POST['matricula'] ?? '';
$id_funcionario = $_POST['id_funcionario'] ?? '';
$acompanante = $_POST['acompanante'] ?? '';

$origen = $_POST['origen'] ?? '';
$destino = $_POST['destino'] ?? '';

$fecha = $_POST['fecha'] ?? '';
$hora_salida = $_POST['hora_salida'] ?? '';
$hora_llegada = $_POST['hora_llegada'] ?? '';
$estado = $_POST['estado'] ?? '';


// Como todas las ambulancias deben regresar al Hospital,
// el retorno queda automáticamente en 1.

$retorno = 1;


// Verificar los datos obligatorios

if (empty($tipo_traslado)) {
    die("Falta el tipo de traslado.");
}

if (empty($matricula)) {
    die("Falta seleccionar la ambulancia.");
}

if (empty($id_funcionario)) {
    die("Falta seleccionar el conductor.");
}

if (empty($origen)) {
    die("Falta el origen.");
}

if (empty($destino)) {
    die("Falta el destino.");
}

if (empty($fecha)) {
    die("Falta la fecha.");
}

if (empty($hora_salida)) {
    die("Falta la hora de salida.");
}

if (empty($hora_llegada)) {
    die("Falta la hora de llegada.");
}

if (empty($estado)) {
    die("Falta el estado.");
}


// Verificar si es Persona

if ($tipo_traslado == "Persona") {

    if (empty($id_paciente)) {
        die("Debe seleccionar un paciente.");
    }

} else {

    // Si no es persona, debe existir una descripción de la carga

    if (empty($elemento_trasladar)) {
        die("Debe indicar qué elemento se va a trasladar.");
    }

    // En este caso no hay paciente

    $id_paciente = null;
}


// Iniciar una transacción

$con->begin_transaction();


try {


    // Insertar el traslado

 $sql = "INSERT INTO Traslado
        (
            Fecha,
            Hora_Salida,
            Hora_Llegada,
            Origen,
            Destino,
            Estado,
            Retorno,
            Matricula,
            ID_Paciente,
            ID_Funcionario
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )";

$stmt = $con->prepare($sql);

if (!$stmt) {
    throw new Exception("Error al preparar el traslado: " . $con->error);
}


$stmt->bind_param(
    "ssssssisii",
    $fecha,
    $hora_salida,
    $hora_llegada,
    $origen,
    $destino,
    $estado,
    $retorno,
    $matricula,
    $id_paciente,
    $id_funcionario
);

if (!$stmt->execute()) {
    throw new Exception("Error al registrar el traslado: " . $stmt->error);
}


    // Obtener el ID del traslado recién creado

    $id_traslado = $con->insert_id;


    $stmt->close();


    // Si el traslado es Equipamiento o Insumos,
    // guardar también la información en Carga.

    if ($tipo_traslado == "Equipamiento" || $tipo_traslado == "Insumos") {


        $sqlCarga = "INSERT INTO Carga
                     (
                         Descripcon,
                         Tipo,
                         ID_Traslado
                     )
                     VALUES
                     (
                         ?,
                         ?,
                         ?
                     )";


        $stmtCarga = $con->prepare($sqlCarga);


        if (!$stmtCarga) {
            throw new Exception("Error al preparar la carga: " . $con->error);
        }


        $stmtCarga->bind_param(
            "ssi",
            $elemento_trasladar,
            $tipo_traslado,
            $id_traslado
        );


        if (!$stmtCarga->execute()) {
            throw new Exception("Error al registrar la carga: " . $stmtCarga->error);
        }


        $stmtCarga->close();

    }


    // Si se seleccionó un acompañante,
    // guardarlo en la tabla Acompanante

    if (!empty($acompanante)) {


        $sqlAcompanante = "INSERT INTO Acompanante
                           (
                               Cedula,
                               ID_Traslado
                           )
                           VALUES
                           (
                               ?,
                               ?
                           )";


        $stmtAcompanante = $con->prepare($sqlAcompanante);


        if (!$stmtAcompanante) {
            throw new Exception("Error al preparar el acompañante: " . $con->error);
        }


        $stmtAcompanante->bind_param(
            "si",
            $acompanante,
            $id_traslado
        );


        if (!$stmtAcompanante->execute()) {
            throw new Exception("Error al registrar el acompañante: " . $stmtAcompanante->error);
        }


        $stmtAcompanante->close();

    }


    // Confirmar todos los cambios

    $con->commit();


    echo "<h2>Traslado registrado correctamente.</h2>";
    echo "<p>ID del traslado: " . $id_traslado . "</p>";
    echo '<a href="nuevo_traslado.php">Registrar otro traslado</a>';


} catch (Exception $e) {


    // Si algo falla, deshacer los cambios

    $con->rollback();

    echo "Error al registrar el traslado: " . $e->getMessage();

}


$con->close();

?>

