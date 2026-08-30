<?php

// 1. Columnas generales de las tablas
const sql_clave = "clave";
const sql_cedula = "cedula";
const sql_usuario_completo = "datos_completados";
const sql_tipo = "tipo";

// 2. Nombres de las TABLAS en la BD
const sql_tabla_usuario = "usuario";
const sql_tabla_soli_usuario = "solicitud_usuario";
const sql_tabla_vecino = "vecino";
const sql_tabla_operador = "operador";
const sql_tabla_admin = "admin_sistemas";
const sql_tabla_muni_operador = "admin_municipal_operador";
const sql_tabla_muni_general = "admin_municipal_general";

// 3. Valores permitidos para el ENUM 'tipo'
const enum_tipo_vecino = "vecino";
const enum_tipo_operario = "operario";
const enum_tipo_admin_operador = "admin operador";
const enum_tipo_admin_general = "admin general";
const enum_tipo_admin_sistema = "admin sistema";

// 4. Arrays con los valores del ENUM
const sql_usuario_tipo = [
    enum_tipo_vecino, 
    enum_tipo_operario, 
    enum_tipo_admin_operador, 
    enum_tipo_admin_general, 
    enum_tipo_admin_sistema
];

const sql_trabajador_tipo = [
    enum_tipo_operario, 
    enum_tipo_admin_operador, 
    enum_tipo_admin_general, 
    enum_tipo_admin_sistema
];