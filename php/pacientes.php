<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';



echo "Conexión correcta<br>";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
 
if (isset($_POST['eliminar'])) {

    $cedula = $_POST['eliminar'];

    $sql = "DELETE FROM Paciente WHERE Cedula = '$cedula'";
    $con->query($sql);

    $sql = "DELETE FROM Persona WHERE Cedula = '$cedula'";
    $con->query($sql);

  }
  elseif(isset($_POST['modificar'])){
   
  $cedula = $_POST['modificar'];
  $sql = "SELECT * FROM Persona WHERE Cedula = '$cedula'";
  $resultado = $con->query($sql);
  $persona = $resultado->fetch_assoc();

  
?>
  <form action="pacientes.php" method="POST">
  <label>Nombre:</label>
  <input type="text" name="nombre" value="<?php echo $persona['Nombre']; ?>">
  <label>Apellido:</label>
  <input type="text" name="apellido" value="<?php echo $persona['Apellido']; ?>"> 
  <label>Fecha Nacimiento:</label>
  <input type="text" name="nacimiento" value="<?php echo $persona['Fecha_Nacimiento']; ?>">
  <label>Direccion:</label>
  <input type="text" name="direccion" value="<?php echo $persona['Direccion']; ?>">
  <label>Correo:</label>
  <input type="text" name="correo" value="<?php echo $persona['Correo']; ?>">
  <label>Telefono:</label>
   <input type="text" name="telefono" value="<?php echo $persona['Telefono']; ?>">

  
  <input type="hidden" name="cedula" value="<?php echo $persona['Cedula']; ?>">
  
  <button name="guardar_modificacion">Guardar cambios</button>

  </form>
  <?php
  }

elseif (isset($_POST['guardar_modificacion'])){
  $cedula = $_POST['cedula'];
  $nombre = $_POST['nombre'];
  $apellido = $_POST['apellido'];
  $nacimiento = $_POST['nacimiento'];
  $direccion = $_POST['direccion'];
  $correo = $_POST['correo'];
  $telefono = $_POST['telefono'];

  $sql = "UPDATE Persona SET Nombre = '$nombre', Apellido = '$apellido', Fecha_Nacimiento = '$nacimiento', Direccion = '$direccion', Correo = '$correo', Telefono = '$telefono' WHERE Cedula = '$cedula'";

  $con->query($sql);

  }


  else{
  $cedula = $_POST['cedula'];
  $nombre = $_POST['nombre'];
  $apellido = $_POST['apellido'];
 $nacimiento = $_POST['nacimiento'];
  $direccion = $_POST['direccion'];
  $correo = $_POST['correo'];
  $telefono = $_POST['telefono'];


  
$sql = "INSERT INTO Persona (Cedula, Nombre, Apellido, Fecha_Nacimiento, Direccion, Correo, Telefono)
VALUES ('$cedula', '$nombre', '$apellido', '$nacimiento', '$direccion', '$correo', '$telefono')";

$con->query($sql);

$sql = "INSERT INTO Paciente (Cedula)
VALUES ('$cedula')";

$con->query($sql);
}
}

$sql = "SELECT * FROM Persona";

$resultado = $con->query($sql);
?>
<table border="1">

<tr>
  <th>Cedula</th>
  <th>Nombre</th>
  <th>Apellido</th>
  <th>Fecha Nacimiento</th>
    <th>Direccion</th>
  <th>Correo</th>
  <th>Telefono</th>
  <th>Accion</th>
</tr>

<?php
while ($persona = $resultado->fetch_assoc()) {
?>
<tr>
<td><?php echo $persona['Cedula'] ; ?></td>
<td><?php echo $persona['Nombre'] ; ?></td>
<td><?php echo $persona['Apellido'] ; ?></td>
<td><?php echo $persona['Fecha_Nacimiento'] ; ?></td>
<td><?php echo $persona['Direccion'] ; ?></td>
<td><?php echo $persona['Correo'] ; ?></td>
<td><?php echo $persona['Telefono'] ; ?></td>

<td>
  <form action="pacientes.php" method="POST">
<button name="eliminar" value="<?php echo $persona['Cedula'];?>">Eliminar</button>
</form>
<form action="pacientes.php" method="POST"> 
  <button name="modificar" value="<?php echo $persona['Cedula'];?>">Modificar</button>
</form>
</td>
</tr>
<?php
}
?>
</table>
<html>
<body>
<header>
  <h1>Registrar Paciente </h1>
</header>
<form action="pacientes.php" method="POST">

<label for="cedula">Cedula:</label><br>
<input type="text" id="cedula" name="cedula"><br>

<label for="nombre">Nombre:</label><br>
<input type="text" id="nombre" name="nombre"><br>

<label for="apellido">Apellido:</label><br>
<input type="text" id="apellido" name="apellido"><br>

<label for="Fecha_Nacimiento">Fecha de Nacimiento:</label><br>
<input type="date" id="nacimiento" name="nacimiento">
<br>
<label for="direccion">Direccion:</label><br>
<input type="text" id="direccion" name="direccion"><br>

<label for="correo">Correo:</label><br>
<input type="text" id="correo" name="correo"><br>

<label for="telefono">Telefono:</label><br>
<input type="text" id="telefono" name="telefono"><br>



  <input type="submit" value="Enviar">

</form>

</body>
</html>
