<?php
// Const_Sql.php

// 1. Columnas generales de las tablas
const sql_clave = "clave";
const sql_cedula = "cedula";
const sql_usuario_completo = "datos_completados";
const sql_tipo = "tipo";
const sql_nombre = "nombre";
const sql_apellido = "apellido";

// Columnas específicas para Logs
const sql_id = "id";
const sql_fecha = "fecha";
const sql_cedula_usuario = "cedula_usuario";
const sql_tipo_log = "tipo_log";
const sql_tipo_modelo = "tipo_modelo";
const sql_texto = "texto";
const sql_query = "query";
const sql_parametros = "parametros";

// 2. Nombres de las TABLAS en la BD
const sql_tabla_usuario = "usuario";
const sql_tabla_trabajador = "trabajador";
const sql_tabla_soli_usuario = "solicitud_usuario";
const sql_tabla_operador = "operador_camion";
const sql_tabla_admin = "admin_sistemas";
const sql_tabla_muni_operador = "admin_municipal_operador";
const sql_tabla_muni_general = "admin_municipal_general";
const sql_tabla_camion = "camion";
const sql_tabla_cuadrilla = "cuadrilla";
const sql_tabla_camion_cuadrilla = "camion_cuadrilla";
const sql_tabla_usa_trayecto = "usa_trayecto";
const sql_tabla_gestiona_trayecto = "gestiona_trayecto";
const sql_tabla_ejecucion_contenedor = "ejecucion_contenedor";
const sql_tabla_ejecucion_pausa = "ejecucion_pausa";

// Tablas de Logs
const sql_tabla_log_user = "log_user";
const sql_tabla_log_sql = "log_sql";

// 3. Valores permitidos para el ENUM 'tipo'
const enum_tipo_vecino = "vecino";
const enum_tipo_operario = "operador camion";
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

// =====================================================================
// CONTENEDORES (CRUD contenedor, estado, llenado, vaciado e incidentes)
// =====================================================================

// Tablas
const sql_tabla_contenedor = "contenedor";
const sql_tabla_almacenado = "almacenado";
const sql_tabla_estado_contenedor = "estado_contenedor";
const sql_tabla_llenado = "llenado";
const sql_tabla_vaciado = "vaciado";
const sql_tabla_incidente = "incidente";

// Columnas
const sql_ubicacion = "ubicacion";
const sql_en_funcion = "en_funcion";
const sql_id_contenedor = "id_contenedor";
const sql_tipo_basuras = "tipo_basuras";
const sql_cantidad = "cantidad";
const sql_condicion = "condicion";
const sql_basura = "basura";
const sql_llenado = "llenado";
const sql_tipos = "tipos";
// Incidente: nombres propios para no chocar con sql_tipo (TYPEUSER) en el traductor
const sql_tipo_incidente = "tipo_incidente";
const sql_estado_incidente = "estado_incidente";
const sql_descripcion = "descripcion";

// Claves compuestas de los arrays que arma Model_Contenedor / Controller_Contenedor
// (no son columnas, pero tambien se traducen a JSON con Util_Translator)
const sql_estado_actual = "estado_actual";
const sql_llenado_actual = "llenado_actual";
const sql_historial_estado = "historial_estado";
const sql_historial_llenado = "historial_llenado";

// Enum: condicion del estado (el estado "Normal" es exclusivo)
const enum_cond_normal = "Normal";
const enum_cond_vandalizado = "Vandalizado";
const enum_cond_roto = "Roto";
const enum_cond_quemado = "Quemado";
const enum_cond_reparacion = "Reparacion";
const sql_estado_condicion = [
    enum_cond_normal,
    enum_cond_vandalizado,
    enum_cond_roto,
    enum_cond_quemado,
    enum_cond_reparacion
];

// Enum: basura del estado (el estado "Limpio" es exclusivo)
const enum_basura_afuera = "Basura_afuera";
const enum_basura_sobrellenado = "Sobre_llenado";
const enum_basura_limpio = "Limpio";
const sql_estado_basura = [
    enum_basura_afuera,
    enum_basura_sobrellenado,
    enum_basura_limpio
];

// Enum: tipos de basura que puede guardar un almacenado
const enum_residuo_plastico = "Plástico";
const enum_residuo_vidrio = "Vidrio";
const enum_residuo_papel = "Papel y cartón";
const enum_residuo_metales = "Metales";
const enum_residuo_biodegradable = "Biodegradable";
const sql_tipo_basura = [
    enum_residuo_plastico,
    enum_residuo_vidrio,
    enum_residuo_papel,
    enum_residuo_metales,
    enum_residuo_biodegradable
];

// Llenado: 0 = vacio, 1 a 10 = nivel, 11 = sobrellenado
const llenado_vacio = 0;
const llenado_sobrellenado = 11;

// Incidentes
const enum_incidente_grave = "Grave";
const enum_incidente_moderado = "Moderado";
const enum_incidente_leve = "Leve";
const sql_incidente_tipo = [
    enum_incidente_grave,
    enum_incidente_moderado,
    enum_incidente_leve
];

// ASUNCION: la tabla incidente.estado es VARCHAR libre; se limita a estos valores.
const enum_incidente_pendiente = "Pendiente";
const enum_incidente_en_proceso = "En proceso";
const enum_incidente_resuelto = "Resuelto";
const sql_incidente_estado = [
    enum_incidente_pendiente,
    enum_incidente_en_proceso,
    enum_incidente_resuelto
];


// =====================================================================
// CENTROS DE ACOPIO, RUTAS Y TRAYECTOS
// =====================================================================

// Tablas
const sql_tabla_centro = "centro_almacenamiento";
const sql_tabla_almacenamiento_centro = "almacenamiento_centro";
const sql_tabla_retiro_centro = "retiro_centro";
const sql_tabla_ruta = "ruta";
const sql_tabla_ruta_contenedor = "ruta_contenedor";
const sql_tabla_trayecto = "trayecto";
const sql_tabla_ejecucion = "ejecucion";

// Columnas
const sql_id_centro = "id_centro";
const sql_id_almacenamiento = "id_almacenamiento";
const sql_volumen_actual = "volumen_actual";
const sql_volumen = "volumen";
const sql_id_ruta = "id_ruta";
const sql_id_centro_inicio = "id_centro_inicio";
const sql_id_centro_final = "id_centro_final";
const sql_matricula = "matricula";
const sql_capacidad = "capacidad";
const sql_estado = "estado";
const sql_estado_ejecucion_alias = "estado_ejecucion";
const sql_tipo_residuos = "tipo_residuos";
const sql_dia_semana_columna = "dia_semana";
const sql_hora = "hora";
const sql_fecha_especial = "fecha_especial";
const sql_inicio_automatico = "inicio_automatico";
const sql_id_camion_auto = "id_camion_auto";
const sql_cedula_operario_auto = "cedula_operario_auto";
const sql_id_trayecto = "id_trayecto";
const sql_fecha_programada = "fecha_programada";
const sql_cedula_aprobador = "cedula_aprobador";
const sql_fecha_aprobacion = "fecha_aprobacion";
const sql_fecha_inicio = "fecha_inicio";
const sql_fecha_final = "fecha_final";
const sql_notas = "notas";
const sql_parado = "parado";
const sql_cedula_operario = "cedula_operario";
const sql_id_camion = "id_camion";
const sql_llenado_camion = "llenado_camion";
const sql_id_ejecucion = "id_ejecucion";
const sql_vaciado = "vaciado";
const sql_fecha_pausa = "fecha_pausa";
const sql_fecha_reanudacion = "fecha_reanudacion";
const sql_pausas = "pausas";
const sql_orden = "orden";
const sql_estado_ejecucion = "estado"; // ejecucion.estado

// Claves compuestas de los arrays que arman Model_Centro y Model_Ruta
// (no son columnas, pero tambien se traducen a JSON con Util_Translator)
const sql_almacenamientos = "almacenamientos";
const sql_contenedores = "contenedores";
const sql_slot = "slot";
const sql_tipos_basura = "tipos_basura";
const sql_cantidad_contenedores = "cantidad_contenedores";
const sql_advertencias = "advertencias";
const sql_origen = "origen";
const sql_faltan = "faltan";
const sql_todos = "todos";
const sql_incompleta = "incompleta";
const sql_retiros = "retiros";

// Dias de la semana de un trayecto
const enum_dia_lunes = "lunes";
const enum_dia_martes = "martes";
const enum_dia_miercoles = "miercoles";
const enum_dia_jueves = "jueves";
const enum_dia_viernes = "viernes";
const enum_dia_sabado = "sabado";
const enum_dia_domingo = "domingo";
const sql_dia_semana = [
    enum_dia_lunes,
    enum_dia_martes,
    enum_dia_miercoles,
    enum_dia_jueves,
    enum_dia_viernes,
    enum_dia_sabado,
    enum_dia_domingo
];

// Estados de una ejecucion
const enum_ejecucion_aprobada = "aprobada";
const enum_ejecucion_en_curso = "en_curso";
const enum_ejecucion_pausada = "pausada";
const enum_ejecucion_finalizada = "finalizada";
const enum_ejecucion_cancelada = "cancelada";
const enum_ejecucion_rechazada = "rechazada";
const enum_ejecucion_vencida = "vencida";
const sql_ejecucion_estado = [
    enum_ejecucion_aprobada,
    enum_ejecucion_en_curso,
    enum_ejecucion_pausada,
    enum_ejecucion_finalizada,
    enum_ejecucion_cancelada,
    enum_ejecucion_rechazada,
    enum_ejecucion_vencida
];
// La ruta "se esta ejecutando" cuando alguna ejecucion esta en curso o pausada
const sql_ejecucion_en_curso_o_pausada = [enum_ejecucion_en_curso, enum_ejecucion_pausada];
// "Abierta" incluye la que esta aprobada y todavia no empezo
const sql_ejecucion_abierta = [enum_ejecucion_aprobada, enum_ejecucion_en_curso, enum_ejecucion_pausada];

// Estados de un camion
const enum_camion_disponible = "disponible";
const enum_camion_no_disponible = "no disponible";
const enum_camion_en_trayecto = "en trayecto";
const sql_camion_estado = [
    enum_camion_disponible,
    enum_camion_no_disponible,
    enum_camion_en_trayecto
];
