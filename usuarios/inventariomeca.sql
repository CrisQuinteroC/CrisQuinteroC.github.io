-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-03-2026 a las 23:01:01
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
-- Base de datos: `inventariomeca`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos`
--

CREATE TABLE `activos` (
  `ID_Activo` int(15) NOT NULL,
  `Num_Marbete` varchar(25) NOT NULL,
  `Activo_Desc` varchar(150) NOT NULL,
  `Estado` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `Matricula_Alumno` int(15) NOT NULL,
  `Nombre_Alumno` varchar(150) NOT NULL,
  `Carrera` varchar(50) NOT NULL,
  `Grupo` varchar(20) NOT NULL,
  `Contacto_Alumno` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `Matricula_Docente` int(15) NOT NULL,
  `Nombre_Docente` varchar(150) NOT NULL,
  `Contacto_Docente` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `ID_Prestamo` int(15) NOT NULL,
  `ID_Activo` int(15) NOT NULL,
  `Matricula_Alumno` int(15) DEFAULT NULL,
  `Matricula_Docente` int(15) DEFAULT NULL,
  `Fecha_Inicio` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `Fecha_Regreso` datetime(6) NOT NULL,
  `ID_Lab` int(10) NOT NULL,
  `Comentarios` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones`
--

CREATE TABLE `ubicaciones` (
  `ID_Lab` int(10) NOT NULL,
  `Numero_Lab` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `Matricula_Uss` int(15) NOT NULL,
  `Nombre_Uss` varchar(150) NOT NULL,
  `Rol_Uss` varchar(30) NOT NULL,
  `Pswd_Uss` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`Matricula_Uss`, `Nombre_Uss`, `Rol_Uss`, `Pswd_Uss`) VALUES
(24306012, 'Elisa Haro', 'Administrador', '$2a$10$6m8xYM0RSXW4IXKkou6PXOIpNo3g6uR.LKGpWU6KRi6fOAzhEb1HG');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activos`
--
ALTER TABLE `activos`
  ADD PRIMARY KEY (`ID_Activo`);

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`Matricula_Alumno`),
  ADD KEY `Matricula_Alumno` (`Matricula_Alumno`),
  ADD KEY `Matricula_Alumno_2` (`Matricula_Alumno`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`Matricula_Docente`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`ID_Prestamo`),
  ADD KEY `fk_id_lab` (`ID_Lab`),
  ADD KEY `fk_id_activo` (`ID_Activo`),
  ADD KEY `fk_matricula_alumno` (`Matricula_Alumno`),
  ADD KEY `fk_matricula_docente` (`Matricula_Docente`);

--
-- Indices de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  ADD PRIMARY KEY (`ID_Lab`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activos`
--
ALTER TABLE `activos`
  MODIFY `ID_Activo` int(15) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `ID_Prestamo` int(15) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  MODIFY `ID_Lab` int(10) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `fk_id_activo` FOREIGN KEY (`ID_Activo`) REFERENCES `activos` (`ID_Activo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_id_lab` FOREIGN KEY (`ID_Lab`) REFERENCES `ubicaciones` (`ID_Lab`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_matricula_alumno` FOREIGN KEY (`Matricula_Alumno`) REFERENCES `alumnos` (`Matricula_Alumno`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_matricula_docente` FOREIGN KEY (`Matricula_Docente`) REFERENCES `docentes` (`Matricula_Docente`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
