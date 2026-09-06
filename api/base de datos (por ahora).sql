CREATE DATABASE IF NOT EXISTS DB;
USE DB;

-- -----------------------------------------------------
-- USUARIOS Y AUTENTICACIÓN
-- -----------------------------------------------------

CREATE TABLE usuario (
    cedula VARCHAR(20) NOT NULL,
    datos_completados BOOLEAN NOT NULL DEFAULT FALSE,
    clave TEXT NOT NULL,
    tipo ENUM('vecino', 'operador camion', 'admin operador', 'admin planificador', 'admin sistema') NOT NULL,
    PRIMARY KEY (cedula)
);

CREATE TABLE solicitud_usuario (
    cedula VARCHAR(20) NOT NULL,
    clave TEXT NOT NULL,
    tipo ENUM('vecino', 'operador camion', 'admin operador', 'admin planificador', 'admin sistema') NOT NULL,
    PRIMARY KEY (cedula)
);

CREATE TABLE trabajador (
    cedula VARCHAR(20) NOT NULL,
    nombre VARCHAR(100),
    apellido VARCHAR(100),
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario(cedula) ON DELETE CASCADE
);

CREATE TABLE vecino ( 
    cedula VARCHAR(20) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_sistemas (
    cedula VARCHAR(20) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_municipal_operador (
    cedula VARCHAR(20) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_municipal_planificador (
    cedula VARCHAR(20) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

-- -----------------------------------------------------
-- LOGS Y AUDITORÍA
-- -----------------------------------------------------

CREATE TABLE log_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_log VARCHAR(50),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    texto TEXT,
    cedula_usuario VARCHAR(20) NOT NULL,
    FOREIGN KEY (cedula_usuario) REFERENCES usuario(cedula) ON DELETE CASCADE
);

CREATE TABLE log_sql (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_modelo VARCHAR(50),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    texto TEXT
);

-- -----------------------------------------------------
-- INFRAESTRUCTURA DE RECOLECCIÓN (CONTENEDORES Y CENTROS)
-- -----------------------------------------------------

CREATE TABLE contenedor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ubicacion VARCHAR(255) NOT NULL,
    en_funcion BOOLEAN DEFAULT TRUE,
    estado ENUM(
        'Normal', 
        'Dañado', 
        'Vandalizado', 
        'En reparación/Reemplazo', 
        'Basura en los alrededores', 
        'Fuera de lugar', 
        'Sobrepeso'
    ) DEFAULT 'Normal',
    tipo_residuos ENUM(
        'Plástico', 
        'Vidrio', 
        'Papel y cartón', 
        'Metales', 
        'Biodegradable'
    ) NOT NULL,
    volumen_maximo INT NOT NULL, -- En cm³ según req. 1.1
    observaciones TEXT
);

CREATE TABLE mes_llenado (
    mes VARCHAR(20) NOT NULL,
    anio INT NOT NULL,
    PRIMARY KEY (mes, anio)
);

-- Corregida la relación 1:N (Contenedor tiene N Avisos/Historial de Llenados)
CREATE TABLE aviso_llenado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_contenedor INT NOT NULL,
    mes VARCHAR(20) NOT NULL,
    anio INT NOT NULL,
    cantidad INT NOT NULL CHECK (cantidad BETWEEN 1 AND 10), 
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id) ON DELETE CASCADE,
    FOREIGN KEY (mes, anio) REFERENCES mes_llenado(mes, anio) ON DELETE CASCADE
);

CREATE TABLE incidente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo ENUM('Grave', 'Moderado', 'Leve') NOT NULL,
    estado VARCHAR(50) DEFAULT 'Pendiente',
    descripcion TEXT,
    id_contenedor INT NOT NULL,
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id) ON DELETE CASCADE
);

CREATE TABLE centro_almacenamiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ubicacion VARCHAR(255) NOT NULL,
    tipo_residuos ENUM(
        'Plástico', 
        'Vidrio', 
        'Papel y cartón', 
        'Metales', 
        'Biodegradable'
    ) NOT NULL
);

-- -----------------------------------------------------
-- OPERACIÓN, RUTAS Y LOGÍSTICA
-- -----------------------------------------------------

CREATE TABLE camion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula VARCHAR(20) UNIQUE NOT NULL,
    capacidad INT NOT NULL,
    estado VARCHAR(50) DEFAULT 'Disponible',
    ubicacion VARCHAR(255),
    tipo_residuos ENUM(
        'Plástico', 
        'Vidrio', 
        'Papel y cartón', 
        'Metales', 
        'Biodegradable'
    ) NOT NULL
);

CREATE TABLE cuadrilla (
    id INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE camionero (
    cedula VARCHAR(20) NOT NULL PRIMARY KEY,
    id_cuadrilla INT,
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE SET NULL
);

CREATE TABLE ruta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_semana VARCHAR(15) NOT NULL,
    hora TIME NOT NULL,
    id_centro_inicio INT NOT NULL,
    id_centro_final INT NOT NULL,
    FOREIGN KEY (id_centro_inicio) REFERENCES centro_almacenamiento(id) ON DELETE CASCADE,
    FOREIGN KEY (id_centro_final) REFERENCES centro_almacenamiento(id) ON DELETE CASCADE
);

-- Secuencia de contenedores dentro de una ruta
CREATE TABLE ruta_contenedor (
    id_ruta INT NOT NULL,
    id_contenedor INT NOT NULL,
    orden INT NOT NULL,
    PRIMARY KEY (id_ruta, id_contenedor),
    FOREIGN KEY (id_ruta) REFERENCES ruta(id) ON DELETE CASCADE,
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id) ON DELETE CASCADE
);

CREATE TABLE trayecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_ruta INT NOT NULL,
    fecha_ejecucion DATETIME NOT NULL,
    estado ENUM('Planificado', 'En proceso', 'Pausado', 'Cancelado', 'Finalizado') DEFAULT 'Planificado',
    cedula_camionero VARCHAR(20),
    FOREIGN KEY (id_ruta) REFERENCES ruta(id) ON DELETE CASCADE,
    FOREIGN KEY (cedula_camionero) REFERENCES camionero(cedula) ON DELETE SET NULL
);

-- -----------------------------------------------------
-- TABLAS DE RELACIÓN (N:M)
-- -----------------------------------------------------

CREATE TABLE camion_cuadrilla (
    id_camion INT NOT NULL,
    id_cuadrilla INT NOT NULL,
    PRIMARY KEY (id_camion, id_cuadrilla),
    FOREIGN KEY (id_camion) REFERENCES camion(id) ON DELETE CASCADE,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE CASCADE
);

CREATE TABLE usa_trayecto (
    id_cuadrilla INT NOT NULL,
    id_trayecto INT NOT NULL,
    PRIMARY KEY (id_cuadrilla, id_trayecto),
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE CASCADE,
    FOREIGN KEY (id_trayecto) REFERENCES trayecto(id) ON DELETE CASCADE
);

CREATE TABLE gestiona_trayecto (
    cedula_operativo VARCHAR(20) NOT NULL,
    id_trayecto INT NOT NULL,
    PRIMARY KEY (cedula_operativo, id_trayecto),
    FOREIGN KEY (cedula_operativo) REFERENCES admin_municipal_operador(cedula) ON DELETE CASCADE,
    FOREIGN KEY (id_trayecto) REFERENCES trayecto(id) ON DELETE CASCADE
);