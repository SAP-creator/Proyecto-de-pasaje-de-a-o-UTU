CREATE DATABASE DB;
USE DB;

CREATE TABLE usuario (
    cedula INT(9) NOT NULL,
    datos_completados BOOLEAN NOT NULL,
    clave text(255),
    tipo ENUM('vecino', 'operario', 'admin operador','admin general', 'admin sistema'),
    PRIMARY KEY (cedula)
);

CREATE TABLE solicitud_usuario (
    cedula INT(9) NOT NULL,
    clave text(255),
    tipo ENUM('vecino', 'operario', 'admin operador','admin general', 'admin sistema'),
    PRIMARY KEY (cedula)
);

CREATE TABLE trabajador (
    nombre VARCHAR(100),
    apellido VARCHAR(100),
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario(cedula) ON DELETE CASCADE
);

CREATE TABLE vecino (
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES usuario(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_sistemas (
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE operador (
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_municipal_operador (
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE admin_municipal_general (
    cedula INT(9) NOT NULL,
    PRIMARY KEY (cedula),
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE
);

CREATE TABLE log_user (
    ci INT AUTO_INCREMENT PRIMARY KEY,
    tipo_log VARCHAR(50),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    texto TEXT,
    cedula_usuario INT(9) NOT NULL
);

CREATE TABLE log_sql (
    ci INT AUTO_INCREMENT PRIMARY KEY,
    tipo_modelo VARCHAR(50),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    texto TEXT
);

CREATE TABLE incidente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_incidente ENUM('Grave', 'Moderado', 'Leve'),
    cedula_vecino INT(9),
    FOREIGN KEY (cedula_vecino) REFERENCES vecino(cedula)
);

CREATE TABLE mes_llenado (
    mes VARCHAR(20),
    anio INT,
    PRIMARY KEY (mes, anio)
);

CREATE TABLE aviso_llenado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes VARCHAR(20),
    anio INT,
    cantidad INT(1),
    FOREIGN KEY (mes, anio) REFERENCES mes_llenado(mes, anio) ON DELETE CASCADE
);

CREATE TABLE contenedor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ubicacion VARCHAR(255),
    tipo_residuos ENUM('Orgánico', 'Plástico', 'Vidrio', 'Papel', 'General'),
    id_aviso INT,
    FOREIGN KEY (id_aviso) REFERENCES aviso_llenado(id)
);

CREATE TABLE centro_almacenamiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_residuos ENUM('Orgánico', 'Plástico', 'Vidrio', 'Papel', 'General')
);

CREATE TABLE ruta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_contenedor INT,
    id_centro_inicio INT,
    id_centro_final INT,
    FOREIGN KEY (id_contenedor) REFERENCES contenedor(id) ON DELETE CASCADE,
    FOREIGN KEY (id_centro_inicio) REFERENCES centro_almacenamiento(id) ON DELETE CASCADE,
    FOREIGN KEY (id_centro_final) REFERENCES centro_almacenamiento(id) ON DELETE CASCADE
);

CREATE TABLE trayecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hora_aproximada TIME,
    dia_semana VARCHAR(15),
    id_ruta INT,
    FOREIGN KEY (id_ruta) REFERENCES ruta(id) ON DELETE CASCADE
);

CREATE TABLE cuadrilla (
    id INT AUTO_INCREMENT PRIMARY KEY
);

CREATE TABLE camionero (
    cedula INT(9) NOT NULL PRIMARY KEY,
    id_cuadrilla INT,
    FOREIGN KEY (cedula) REFERENCES trabajador(cedula) ON DELETE CASCADE,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE CASCADE
);

CREATE TABLE camion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_residuos ENUM('Orgánico', 'Plástico', 'Vidrio', 'Papel', 'General')
);

-- Tablas N:M con nombres corregidos (guion bajo en lugar de comillas)

CREATE TABLE camion_cuadrilla (
    id_camion INT,
    id_cuadrilla INT,
    PRIMARY KEY (id_camion, id_cuadrilla),
    FOREIGN KEY (id_camion) REFERENCES camion(id) ON DELETE CASCADE,
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE CASCADE
);

CREATE TABLE usa_trayecto (
    id_cuadrilla INT,
    id_trayecto INT,
    PRIMARY KEY (id_cuadrilla, id_trayecto),
    FOREIGN KEY (id_cuadrilla) REFERENCES cuadrilla(id) ON DELETE CASCADE,
    FOREIGN KEY (id_trayecto) REFERENCES trayecto(id) ON DELETE CASCADE
);

CREATE TABLE gestiona_trayecto (
    cedula_operativo INT(9),
    id_trayecto INT,
    PRIMARY KEY (cedula_operativo, id_trayecto),
    FOREIGN KEY (cedula_operativo) REFERENCES trabajador(cedula) ON DELETE CASCADE,
    FOREIGN KEY (id_trayecto) REFERENCES trayecto(id) ON DELETE CASCADE
);


