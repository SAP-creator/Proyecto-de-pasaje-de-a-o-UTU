<?php
// Const_Modificable.php
//
// Que datos puede modificar cada tipo de usuario. Se arma por piezas
// (columna -> tabla -> perfil) para no repetir lo mismo en cada rol:
// agregar una columna modificable es definir su pieza y sumarla a los perfiles.
//
// Forma:  tipo de usuario => [ tabla => [ clave JSON => ['tipo' => ..., 'cantidad' => ...] ] ]
//   - tipo:     dato_string, dato_int o dato_double
//   - cantidad: longitud maxima del valor (se mide con Util_VerifyData::comprobar_longitud)

include_once __DIR__ . "/Const_Json.php";
include_once __DIR__ . "/Const_Sql.php";

// Tipos de dato
const dato_string = 's';
const dato_int = 'i';
const dato_double = 'd';

// El admin puede modificar todo. No esta en sql_usuario_tipo y el tipo de
// usuario sale siempre del token (o de esta constante), nunca del cliente.
const tipo_modificador_admin = "ADMIN";

// Columnas
const col_password = [
    json_password => ['tipo' => dato_string, 'cantidad' => 128]
];

const col_nombre_apellido = [
    json_first_name => ['tipo' => dato_string, 'cantidad' => 100],
    json_last_name  => ['tipo' => dato_string, 'cantidad' => 100]
];

// Perfiles
const perm_vecino = [
    sql_tabla_usuario => col_password
];

const perm_trabajador = [
    sql_tabla_usuario    => col_password,
    sql_tabla_trabajador => col_nombre_apellido
];

const perm_admin = [
    sql_tabla_usuario       => col_password,
    sql_tabla_trabajador    => col_nombre_apellido,
    sql_tabla_operador      => [],
    sql_tabla_muni_operador => [],
    sql_tabla_muni_general  => [],
    sql_tabla_admin         => []
];

// Diccionario final
const modificable_usuario = [
    tipo_modificador_admin   => perm_admin,
    enum_tipo_vecino         => perm_vecino,
    enum_tipo_operario       => perm_trabajador,
    enum_tipo_admin_operador => perm_trabajador,
    enum_tipo_admin_general  => perm_trabajador,
    enum_tipo_admin_sistema  => perm_trabajador
];
