-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 02, 2026 at 07:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `DB`
--
CREATE DATABASE IF NOT EXISTS `DB` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `DB`;

-- --------------------------------------------------------

--
-- Table structure for table `usuario`
--
CREATE TABLE `usuario` (
  `cedula` varchar(20) NOT NULL,
  `datos_completados` tinyint(1) NOT NULL DEFAULT 0,
  `clave` text NOT NULL,
  `tipo` enum('vecino','operador camion','admin operador','admin general','admin sistema') NOT NULL,
  PRIMARY KEY (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trabajador`
--
CREATE TABLE `trabajador` (
  `cedula` varchar(20) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`cedula`),
  CONSTRAINT `trabajador_ibfk_1` FOREIGN KEY (`cedula`) REFERENCES `usuario` (`cedula`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_municipal_general`
--
CREATE TABLE `admin_municipal_general` (
  `cedula` varchar(20) NOT NULL,
  PRIMARY KEY (`cedula`),
  CONSTRAINT `admin_municipal_general_ibfk_1` FOREIGN KEY (`cedula`) REFERENCES `trabajador` (`cedula`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_municipal_operador`
--
CREATE TABLE `admin_municipal_operador` (
  `cedula` varchar(20) NOT NULL,
  PRIMARY KEY (`cedula`),
  CONSTRAINT `admin_municipal_operador_ibfk_1` FOREIGN KEY (`cedula`) REFERENCES `trabajador` (`cedula`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_sistemas`
--
CREATE TABLE `admin_sistemas` (
  `cedula` varchar(20) NOT NULL,
  PRIMARY KEY (`cedula`),
  CONSTRAINT `admin_sistemas_ibfk_1` FOREIGN KEY (`cedula`) REFERENCES `trabajador` (`cedula`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operador_camion`
--
CREATE TABLE `operador_camion` (
  `cedula` varchar(20) NOT NULL,
  PRIMARY KEY (`cedula`),
  CONSTRAINT `operador_camion_ibfk_1` FOREIGN KEY (`cedula`) REFERENCES `trabajador` (`cedula`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `centro_almacenamiento`
--
CREATE TABLE `centro_almacenamiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ubicacion` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contenedor`
--
CREATE TABLE `contenedor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ubicacion` varchar(255) NOT NULL,
  `en_funcion` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `almacenado`
--
CREATE TABLE `almacenado` (
  `id_contenedor` int(11) NOT NULL,
  `tipo_basuras` text NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_contenedor`),
  CONSTRAINT `almacenado_ibfk_1` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `almacenamiento_centro`
--
CREATE TABLE `almacenamiento_centro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_centro` int(11) NOT NULL,
  `tipo_basuras` text NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `volumen_actual` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `id_centro` (`id_centro`),
  CONSTRAINT `almacenamiento_centro_ibfk_1` FOREIGN KEY (`id_centro`) REFERENCES `centro_almacenamiento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `camion`
--
CREATE TABLE `camion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matricula` varchar(20) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `estado` enum('disponible','no disponible','en trayecto') NOT NULL DEFAULT 'disponible',
  `tipo_basuras` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matricula` (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ruta`
--
CREATE TABLE `ruta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_centro_inicio` int(11) DEFAULT NULL,
  `id_centro_final` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_centro_inicio` (`id_centro_inicio`),
  KEY `id_centro_final` (`id_centro_final`),
  CONSTRAINT `ruta_ibfk_1` FOREIGN KEY (`id_centro_inicio`) REFERENCES `centro_almacenamiento` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ruta_ibfk_2` FOREIGN KEY (`id_centro_final`) REFERENCES `centro_almacenamiento` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trayecto`
--
CREATE TABLE `trayecto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ruta` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') DEFAULT NULL,
  `hora` time NOT NULL,
  `fecha_especial` date DEFAULT NULL,
  `inicio_automatico` tinyint(1) NOT NULL DEFAULT 0,
  `id_camion_auto` int(11) DEFAULT NULL,
  `cedula_operario_auto` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_ruta` (`id_ruta`),
  CONSTRAINT `trayecto_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `ruta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ejecucion`
--
CREATE TABLE `ejecucion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_trayecto` int(11) NOT NULL,
  `id_ruta` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') DEFAULT NULL,
  `hora` time NOT NULL,
  `estado` enum('aprobada','en_curso','pausada','finalizada','cancelada','rechazada','vencida') NOT NULL,
  `fecha_programada` date NOT NULL,
  `cedula_aprobador` varchar(20) DEFAULT NULL,
  `fecha_aprobacion` datetime NOT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_final` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `parado` tinyint(1) NOT NULL DEFAULT 0,
  `cedula_operario` varchar(20) NOT NULL,
  `id_camion` int(11) NOT NULL,
  `llenado_camion` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ejecucion_estado_ruta` (`estado`,`id_ruta`),
  KEY `idx_ejecucion_trayecto` (`id_trayecto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ejecucion_contenedor`
--
CREATE TABLE `ejecucion_contenedor` (
  `id_ejecucion` int(11) NOT NULL,
  `id_contenedor` int(11) NOT NULL,
  `vaciado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_ejecucion`,`id_contenedor`),
  CONSTRAINT `ejecucion_contenedor_ibfk_1` FOREIGN KEY (`id_ejecucion`) REFERENCES `ejecucion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ejecucion_pausa`
--
CREATE TABLE `ejecucion_pausa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ejecucion` int(11) NOT NULL,
  `fecha_pausa` datetime NOT NULL,
  `fecha_reanudacion` datetime DEFAULT NULL,
  `notas` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_ejecucion` (`id_ejecucion`),
  CONSTRAINT `ejecucion_pausa_ibfk_1` FOREIGN KEY (`id_ejecucion`) REFERENCES `ejecucion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estado_contenedor`
--
CREATE TABLE `estado_contenedor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_contenedor` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `condicion` text NOT NULL,
  `basura` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado_actual` (`id_contenedor`,`fecha`),
  CONSTRAINT `estado_contenedor_ibfk_1` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gestiona_trayecto`
--
CREATE TABLE `gestiona_trayecto` (
  `cedula_operativo` varchar(20) NOT NULL,
  `id_trayecto` int(11) NOT NULL,
  PRIMARY KEY (`cedula_operativo`,`id_trayecto`),
  KEY `id_trayecto` (`id_trayecto`),
  CONSTRAINT `gestiona_trayecto_ibfk_1` FOREIGN KEY (`cedula_operativo`) REFERENCES `admin_municipal_operador` (`cedula`) ON DELETE CASCADE,
  CONSTRAINT `gestiona_trayecto_ibfk_2` FOREIGN KEY (`id_trayecto`) REFERENCES `trayecto` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidente`
--
CREATE TABLE `incidente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `tipo_incidente` enum('Grave','Moderado','Leve') NOT NULL,
  `estado_incidente` varchar(50) DEFAULT 'Pendiente',
  `descripcion` text DEFAULT NULL,
  `id_contenedor` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_contenedor` (`id_contenedor`),
  CONSTRAINT `incidente_ibfk_1` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `llenado`
--
CREATE TABLE `llenado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_contenedor` int(11) NOT NULL,
  `llenado` tinyint(4) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_llenado_actual` (`id_contenedor`,`fecha`),
  CONSTRAINT `llenado_ibfk_1` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_user`
--

CREATE TABLE IF NOT EXISTS `log_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula_usuario` varchar(20) NOT NULL,
  `tipo_log` varchar(100) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `texto` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_user_ci_fecha` (`cedula_usuario`, `fecha`),
  KEY `idx_log_user_tipo_fecha` (`tipo_log`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `log_sql`
--
CREATE TABLE `log_sql` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_modelo` varchar(50) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `texto` text DEFAULT NULL,
  `query` text DEFAULT NULL,
  `parametros` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parametros`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `retiro_centro`
--
CREATE TABLE `retiro_centro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_centro` int(11) NOT NULL,
  `id_almacenamiento` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `volumen` decimal(10,2) NOT NULL,
  `cedula_usuario` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_centro` (`id_centro`),
  CONSTRAINT `retiro_centro_ibfk_1` FOREIGN KEY (`id_centro`) REFERENCES `centro_almacenamiento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ruta_contenedor`
--
CREATE TABLE `ruta_contenedor` (
  `id_ruta` int(11) NOT NULL,
  `id_contenedor` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  PRIMARY KEY (`id_ruta`,`id_contenedor`),
  UNIQUE KEY `uq_ruta_orden` (`id_ruta`,`orden`),
  CONSTRAINT `ruta_contenedor_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `ruta` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `solicitud_usuario`
--
CREATE TABLE `solicitud_usuario` (
  `cedula` varchar(20) NOT NULL,
  `clave` text NOT NULL,
  `tipo` enum('vecino','operador camion','admin operador','admin general','admin sistema') NOT NULL,
  PRIMARY KEY (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaciado`
--
CREATE TABLE `vaciado` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_contenedor` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `tipos` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_contenedor` (`id_contenedor`),
  CONSTRAINT `vaciado_ibfk_1` FOREIGN KEY (`id_contenedor`) REFERENCES `contenedor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;


