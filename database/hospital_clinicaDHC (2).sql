-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 11-09-2026 a las 15:36:10
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hospital_clinicaDHC`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Acompanante`
--

CREATE TABLE `Acompanante` (
  `Cedula` varchar(20) NOT NULL,
  `ID_Traslado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ambulancia`
--

CREATE TABLE `Ambulancia` (
  `Matricula` varchar(20) NOT NULL,
  `Numero_Ambulancia` int(11) NOT NULL,
  `Modelo` varchar(50) NOT NULL,
  `Estado` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Carga`
--

CREATE TABLE `Carga` (
  `ID_Carga` int(11) NOT NULL,
  `Descripcon` varchar(255) NOT NULL,
  `Tipo` varchar(50) NOT NULL,
  `ID_Traslado` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Documento`
--

CREATE TABLE `Documento` (
  `ID_Documento` int(11) NOT NULL,
  `Fecha_Emision` date NOT NULL,
  `Tipo` varchar(100) NOT NULL,
  `Descripcion` varchar(255) DEFAULT NULL,
  `ID_Paciente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Encuesta`
--

CREATE TABLE `Encuesta` (
  `ID_Encuesta` int(11) NOT NULL,
  `Fecha` date NOT NULL,
  `ID_Paciente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Funcionario`
--

CREATE TABLE `Funcionario` (
  `ID_Funcionario` int(11) NOT NULL,
  `Cedula` varchar(20) NOT NULL,
  `Tipo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Paciente`
--

CREATE TABLE `Paciente` (
  `ID_Paciente` int(11) NOT NULL,
  `Cedula` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `Paciente`
--

INSERT INTO `Paciente` (`ID_Paciente`, `Cedula`) VALUES
(35, '56838677'),
(40, '57759698');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Persona`
--

CREATE TABLE `Persona` (
  `Cedula` varchar(20) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Apellido` varchar(50) NOT NULL,
  `Fecha_Nacimiento` date DEFAULT NULL,
  `Direccion` varchar(150) DEFAULT NULL,
  `Correo` varchar(100) DEFAULT NULL,
  `Telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `Persona`
--

INSERT INTO `Persona` (`Cedula`, `Nombre`, `Apellido`, `Fecha_Nacimiento`, `Direccion`, `Correo`, `Telefono`) VALUES
('56838677', 'josefina1', 'garrasino1', '2007-10-09', 'larrañaga 1345', 'josefinagarrasino2@gmail.com', '098775906'),
('57759698', 'matias', 'moreira', '2009-01-13', 'narnia', 'matiasmoreira1301@gmail.com', '098765322');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Pregunta`
--

CREATE TABLE `Pregunta` (
  `ID_Pregunta` int(11) NOT NULL,
  `Pregunta` varchar(255) NOT NULL,
  `ID_Encuesta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `QR`
--

CREATE TABLE `QR` (
  `Codigo` varchar(255) NOT NULL,
  `Fecha_Generacion` date NOT NULL,
  `ID_Documento` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Respuesta`
--

CREATE TABLE `Respuesta` (
  `ID_Respuesta` int(11) NOT NULL,
  `Respuesta` varchar(255) NOT NULL,
  `Puntuacion` int(11) DEFAULT NULL,
  `Fecha` date NOT NULL,
  `ID_Pregunta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Traslado`
--

CREATE TABLE `Traslado` (
  `ID_Traslado` int(11) NOT NULL,
  `Fecha` date NOT NULL,
  `Hora_Salida` time NOT NULL,
  `Hora_Llegada` time DEFAULT NULL,
  `Origen` varchar(150) NOT NULL,
  `Destino` varchar(150) NOT NULL,
  `Ruta` varchar(255) DEFAULT NULL,
  `Estado` varchar(50) NOT NULL,
  `Retorno` tinyint(1) DEFAULT 0,
  `Matricula` varchar(20) NOT NULL,
  `ID_Paciente` int(11) DEFAULT NULL,
  `ID_Funcionario` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Usuario`
--

CREATE TABLE `Usuario` (
  `ID_Usuario` int(11) NOT NULL,
  `Cedula` varchar(20) NOT NULL,
  `Contraseña` varchar(255) NOT NULL,
  `Rol` varchar(50) NOT NULL,
  `Estado` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `Usuario`
--

INSERT INTO `Usuario` (`ID_Usuario`, `Cedula`, `Contraseña`, `Rol`, `Estado`) VALUES
(1, '56838677', '$2y$10$d9114/8SDQ0bE7YKymvKMu8OTW5k42crdteAQixmHf2OWL0gVZCxO', 'Medico', 'Activo'),
(2, '57759698', '$2y$10$hXETzxb7nqO2l82R1jkG4uCoJGMtwN9DdNwo6KkgQz8GieLVNxF5i', 'Administrador', 'Activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `Acompanante`
--
ALTER TABLE `Acompanante`
  ADD PRIMARY KEY (`Cedula`,`ID_Traslado`),
  ADD KEY `ID_Traslado` (`ID_Traslado`);

--
-- Indices de la tabla `Ambulancia`
--
ALTER TABLE `Ambulancia`
  ADD PRIMARY KEY (`Matricula`);

--
-- Indices de la tabla `Carga`
--
ALTER TABLE `Carga`
  ADD PRIMARY KEY (`ID_Carga`),
  ADD KEY `ID_Traslado` (`ID_Traslado`);

--
-- Indices de la tabla `Documento`
--
ALTER TABLE `Documento`
  ADD PRIMARY KEY (`ID_Documento`),
  ADD KEY `ID_Paciente` (`ID_Paciente`);

--
-- Indices de la tabla `Encuesta`
--
ALTER TABLE `Encuesta`
  ADD PRIMARY KEY (`ID_Encuesta`),
  ADD KEY `ID_Paciente` (`ID_Paciente`);

--
-- Indices de la tabla `Funcionario`
--
ALTER TABLE `Funcionario`
  ADD PRIMARY KEY (`ID_Funcionario`),
  ADD KEY `Cedula` (`Cedula`);

--
-- Indices de la tabla `Paciente`
--
ALTER TABLE `Paciente`
  ADD PRIMARY KEY (`ID_Paciente`),
  ADD KEY `Cedula` (`Cedula`);

--
-- Indices de la tabla `Persona`
--
ALTER TABLE `Persona`
  ADD PRIMARY KEY (`Cedula`);

--
-- Indices de la tabla `Pregunta`
--
ALTER TABLE `Pregunta`
  ADD PRIMARY KEY (`ID_Pregunta`),
  ADD KEY `ID_Encuesta` (`ID_Encuesta`);

--
-- Indices de la tabla `QR`
--
ALTER TABLE `QR`
  ADD PRIMARY KEY (`Codigo`),
  ADD KEY `ID_Documento` (`ID_Documento`);

--
-- Indices de la tabla `Respuesta`
--
ALTER TABLE `Respuesta`
  ADD PRIMARY KEY (`ID_Respuesta`),
  ADD KEY `ID_Pregunta` (`ID_Pregunta`);

--
-- Indices de la tabla `Traslado`
--
ALTER TABLE `Traslado`
  ADD PRIMARY KEY (`ID_Traslado`),
  ADD KEY `Matricula` (`Matricula`),
  ADD KEY `ID_Paciente` (`ID_Paciente`),
  ADD KEY `ID_Funcionario` (`ID_Funcionario`);

--
-- Indices de la tabla `Usuario`
--
ALTER TABLE `Usuario`
  ADD PRIMARY KEY (`ID_Usuario`),
  ADD UNIQUE KEY `Cedula` (`Cedula`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `Carga`
--
ALTER TABLE `Carga`
  MODIFY `ID_Carga` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Documento`
--
ALTER TABLE `Documento`
  MODIFY `ID_Documento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Encuesta`
--
ALTER TABLE `Encuesta`
  MODIFY `ID_Encuesta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Funcionario`
--
ALTER TABLE `Funcionario`
  MODIFY `ID_Funcionario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Paciente`
--
ALTER TABLE `Paciente`
  MODIFY `ID_Paciente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `Pregunta`
--
ALTER TABLE `Pregunta`
  MODIFY `ID_Pregunta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Respuesta`
--
ALTER TABLE `Respuesta`
  MODIFY `ID_Respuesta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Traslado`
--
ALTER TABLE `Traslado`
  MODIFY `ID_Traslado` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Usuario`
--
ALTER TABLE `Usuario`
  MODIFY `ID_Usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `Acompanante`
--
ALTER TABLE `Acompanante`
  ADD CONSTRAINT `Acompanante_ibfk_1` FOREIGN KEY (`Cedula`) REFERENCES `Persona` (`Cedula`),
  ADD CONSTRAINT `Acompanante_ibfk_2` FOREIGN KEY (`ID_Traslado`) REFERENCES `Traslado` (`ID_Traslado`);

--
-- Filtros para la tabla `Carga`
--
ALTER TABLE `Carga`
  ADD CONSTRAINT `Carga_ibfk_1` FOREIGN KEY (`ID_Traslado`) REFERENCES `Traslado` (`ID_Traslado`);

--
-- Filtros para la tabla `Documento`
--
ALTER TABLE `Documento`
  ADD CONSTRAINT `Documento_ibfk_1` FOREIGN KEY (`ID_Paciente`) REFERENCES `Paciente` (`ID_Paciente`);

--
-- Filtros para la tabla `Encuesta`
--
ALTER TABLE `Encuesta`
  ADD CONSTRAINT `Encuesta_ibfk_1` FOREIGN KEY (`ID_Paciente`) REFERENCES `Paciente` (`ID_Paciente`);

--
-- Filtros para la tabla `Funcionario`
--
ALTER TABLE `Funcionario`
  ADD CONSTRAINT `Funcionario_ibfk_1` FOREIGN KEY (`Cedula`) REFERENCES `Persona` (`Cedula`);

--
-- Filtros para la tabla `Paciente`
--
ALTER TABLE `Paciente`
  ADD CONSTRAINT `Paciente_ibfk_1` FOREIGN KEY (`Cedula`) REFERENCES `Persona` (`Cedula`);

--
-- Filtros para la tabla `Pregunta`
--
ALTER TABLE `Pregunta`
  ADD CONSTRAINT `Pregunta_ibfk_1` FOREIGN KEY (`ID_Encuesta`) REFERENCES `Encuesta` (`ID_Encuesta`);

--
-- Filtros para la tabla `QR`
--
ALTER TABLE `QR`
  ADD CONSTRAINT `QR_ibfk_1` FOREIGN KEY (`ID_Documento`) REFERENCES `Documento` (`ID_Documento`);

--
-- Filtros para la tabla `Respuesta`
--
ALTER TABLE `Respuesta`
  ADD CONSTRAINT `Respuesta_ibfk_1` FOREIGN KEY (`ID_Pregunta`) REFERENCES `Pregunta` (`ID_Pregunta`);

--
-- Filtros para la tabla `Traslado`
--
ALTER TABLE `Traslado`
  ADD CONSTRAINT `Traslado_ibfk_1` FOREIGN KEY (`Matricula`) REFERENCES `Ambulancia` (`Matricula`),
  ADD CONSTRAINT `Traslado_ibfk_2` FOREIGN KEY (`ID_Paciente`) REFERENCES `Paciente` (`ID_Paciente`),
  ADD CONSTRAINT `Traslado_ibfk_3` FOREIGN KEY (`ID_Funcionario`) REFERENCES `Funcionario` (`ID_Funcionario`);

--
-- Filtros para la tabla `Usuario`
--
ALTER TABLE `Usuario`
  ADD CONSTRAINT `Usuario_ibfk_1` FOREIGN KEY (`Cedula`) REFERENCES `Persona` (`Cedula`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
