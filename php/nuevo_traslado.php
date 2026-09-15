<?php

require_once 'conexion.php';

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nuevo paciente - Hospital de Clínicas</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="/prueba/css/style.css">
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

     <aside class="menu-lateral">

        <h3>Menú</h3>


        <!-- INICIO -->

        <a href="/prueba/panelprincipal.html">

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

                <a href="/prueba/php/registro_ambulancias.html">

                    <i class="fa-solid fa-list"></i>

                    Ver registros

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

        <a href="/prueba/index.html">

            <i class="fa-solid fa-right-from-bracket"></i>

            Cerrar sesión

        </a>

    </aside>

    <main class="contenido">

        <section class="transporte">

            <h2>Asignar Transporte</h2>

            <p class="texto-transporte">
                Complete la siguiente información para registrar un nuevo transporte.
            </p>

            <div class="tarjeta">

                <form class="form-transporte" method="POST" action="transporte.php">

                    <div class="fila">

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-user"></i>
                                Paciente
                            </label>

                            <input
                                type="text"
                                name="paciente"
                                placeholder="Ingrese el paciente">
                        </div>

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-truck-medical"></i>
                                Ambulancia
                            </label>

                            <select name="matricula">

                                <option value="">
                                    Seleccione una ambulancia
                                </option>

                                <?php

                                $sqlAmbulancias = "SELECT Matricula, Numero_Ambulancia, Modelo, Estado
                                                    FROM Ambulancia";

                                $resultadoAmbulancias = $con->query($sqlAmbulancias);

                                if ($resultadoAmbulancias) {

                                    while ($ambulancia = $resultadoAmbulancias->fetch_assoc()) {

                                        ?>

                                        <option value="<?php echo $ambulancia['Matricula']; ?>">

                                            <?php
                                            echo "A-" . $ambulancia['Numero_Ambulancia']
                                                . " - "
                                                . $ambulancia['Modelo']
                                                . " - "
                                                . $ambulancia['Estado'];
                                            ?>

                                        </option>

                                        <?php

                                    }

                                }

                                ?>

                            </select>
                        </div>

                    </div>

                    <div class="fila">

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-id-badge"></i>
                                Conductor
                            </label>

                            <select name="conductor">

                                <option value="">
                                    Seleccione un conductor
                                </option>

                            </select>
                        </div>

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-user-doctor"></i>
                                Funcionario acompañante
                            </label>

                            <select name="id_funcionario">

                                <option value="">
                                    Seleccione un funcionario
                                </option>

                            </select>
                        </div>

                    </div>

                    <div class="fila">

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-location-dot"></i>
                                Origen
                            </label>

                            <input
                                type="text"
                                name="origen">
                        </div>

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-map-location-dot"></i>
                                Destino
                            </label>

                            <input
                                type="text"
                                name="destino">
                        </div>

                    </div>

                    <div class="fila">

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-calendar-days"></i>
                                Fecha
                            </label>

                            <input
                                type="date"
                                name="fecha">
                        </div>

                        <div class="campo">
                            <label>
                                <i class="fa-solid fa-clock"></i>
                                Hora
                            </label>

                            <input
                                type="time"
                                name="hora_salida">
                        </div>

                    </div>

                    <div class="campo">

                        <label>
                            <i class="fa-solid fa-file-lines"></i>
                            Observaciones
                        </label>

                        <textarea name="observaciones"></textarea>

                    </div>

                    <button type="submit">
                        Registrar Transporte
                    </button>

                </form>

            </div>





        </section>

    </main>

</div>

<footer>

    <p>&copy; 2026 Hospital de Clínicas. Todos los derechos reservados a DHC.</p>

</footer>

</body>
</html>