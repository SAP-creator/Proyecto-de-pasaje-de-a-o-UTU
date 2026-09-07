<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

class Model_User
{
    private const MODEL_LOG = "USER MODEL";

    public static function get_users(string $type = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT u." . sql_cedula . ", u." . sql_tipo . " FROM " . sql_tabla_usuario . " u";
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::MODEL_LOG, "Obtener todos los usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT u." . sql_cedula . ", u." . sql_tipo . " FROM " . sql_tabla_usuario . " u WHERE u." . sql_tipo . " = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::MODEL_LOG, "Obtener usuarios filtrados por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_request_users(string $type = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT s." . sql_cedula . ", s." . sql_tipo . " FROM " . sql_tabla_soli_usuario . " s";
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::MODEL_LOG, "Obtener todas las solicitudes de usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT s." . sql_cedula . ", s." . sql_tipo . " FROM " . sql_tabla_soli_usuario . " s WHERE s." . sql_tipo . " = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::MODEL_LOG, "Obtener solicitudes filtradas por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_user(int $ci): ?array 
    {
        $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE " . sql_cedula . " = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        
        Model_Log::add_log_sql(self::MODEL_LOG, "Consultar usuario por cédula: {$ci}");
        
        if (!$query_result->success) { 
            return null; 
        }

        return $query_result->data->fetch_assoc(); 
    }

    public static function get_request_user(int $ci): ?array
    {
        $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE " . sql_cedula . " = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Consultar solicitud por cédula: {$ci}");

        if (!$query_result->success) { 
            return null; 
        }

        return $query_result->data->fetch_assoc(); 
    }

    public static function has_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE " . sql_cedula . " = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::MODEL_LOG, "Verificar existencia de usuario CI: {$ci}");

            if (!$query_result->success) { 
                return null; 
            }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE " . sql_cedula . " = ? AND " . sql_tipo . " = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::MODEL_LOG, "Verificar existencia de usuario CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { 
            return null; 
        }
        return $query_result->data->num_rows > 0;
    }

    public static function has_request_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE " . sql_cedula . " = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::MODEL_LOG, "Verificar existencia de solicitud CI: {$ci}");

            if (!$query_result->success) { 
                return null; 
            }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE " . sql_cedula . " = ? AND " . sql_tipo . " = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::MODEL_LOG, "Verificar existencia de solicitud CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { 
            return null; 
        }
        return $query_result->data->num_rows > 0;
    }

    public static function create_request_user(int $ci, string $clave, string $type): ?bool
    {
        $sql = "INSERT INTO " . sql_tabla_soli_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ") VALUES (?, ?, ?)";
        $db = new Util_DbConnection();
        $result = $db->executeQuery($sql, "iss", $ci, $clave, $type);
        
        Model_Log::add_log_sql(self::MODEL_LOG, "Crear solicitud de usuario CI: {$ci} con tipo: {$type}");

        return $result->success;
    }

    private static function get_accept_user_queries(): array
    {
        return [
            enum_tipo_vecino => [
                "INSERT INTO " . sql_tabla_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ", " . sql_usuario_completo . ") VALUES (?, ?, '" . enum_tipo_vecino . "', true)",
                "INSERT INTO " . sql_tabla_vecino . " (" . sql_cedula . ") VALUES (?)"
            ],
            enum_tipo_operario => [
                "INSERT INTO " . sql_tabla_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ", " . sql_usuario_completo . ") VALUES (?, ?, '" . enum_tipo_operario . "', false)",
                "INSERT INTO " . sql_tabla_trabajador . " (" . sql_cedula . ") VALUES (?)",
                "INSERT INTO " . sql_tabla_operador . " (" . sql_cedula . ") VALUES (?)"
            ],
            enum_tipo_admin_operador => [
                "INSERT INTO " . sql_tabla_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ", " . sql_usuario_completo . ") VALUES (?, ?, '" . enum_tipo_admin_operador . "', false)",
                "INSERT INTO " . sql_tabla_trabajador . " (" . sql_cedula . ") VALUES (?)",
                "INSERT INTO " . sql_tabla_muni_operador . " (" . sql_cedula . ") VALUES (?)"
            ],
            enum_tipo_admin_general => [
                "INSERT INTO " . sql_tabla_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ", " . sql_usuario_completo . ") VALUES (?, ?, '" . enum_tipo_admin_general . "', false)",
                "INSERT INTO " . sql_tabla_trabajador . " (" . sql_cedula . ") VALUES (?)",
                "INSERT INTO " . sql_tabla_muni_general . " (" . sql_cedula . ") VALUES (?)"
            ],
            enum_tipo_admin_sistema => [
                "INSERT INTO " . sql_tabla_usuario . " (" . sql_cedula . ", " . sql_clave . ", " . sql_tipo . ", " . sql_usuario_completo . ") VALUES (?, ?, '" . enum_tipo_admin_sistema . "', false)",
                "INSERT INTO " . sql_tabla_trabajador . " (" . sql_cedula . ") VALUES (?)",
                "INSERT INTO " . sql_tabla_admin . " (" . sql_cedula . ") VALUES (?)"
            ]
        ];
    }

    public static function accept_request_user(int $ci): ?bool
    {
        $has_request = self::has_request_user($ci);
        if ($has_request !== true) { 
            return $has_request; 
        }

        $user_request = self::get_request_user($ci);
        if (!$user_request) {
            return false;
        }

        $tipo = $user_request[sql_tipo];
        $clave = $user_request[sql_clave];
        $accept_queries = self::get_accept_user_queries();
        
        if (!array_key_exists($tipo, $accept_queries)) {
            return false;
        }

        $db = new Util_DbConnection();

        foreach ($accept_queries[$tipo] as $sql) {
            $result = str_contains($sql, sql_tabla_usuario) 
                ? $db->executeQuery($sql, "is", $ci, $clave)
                : $db->executeQuery($sql, "i", $ci);

            if (!$result->success) {
                return false;
            }
        }

        $sql_delete = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE " . sql_cedula . " = ?";
        $result_delete = $db->executeQuery($sql_delete, "i", $ci);

        Model_Log::add_log_sql(self::MODEL_LOG, "Solicitud aceptada y usuario migrado CI: {$ci} como tipo: {$tipo}");

        return $result_delete->success;
    }

    public static function change_data(int $ci, string $table, string $column, mixed $new_value): ?bool
    {
        if (self::has_user($ci) !== true) {
            return null;
        }

        $sql = "UPDATE " . $table . "
                SET " . $column . " = ?
                WHERE " . sql_cedula . " = ?";

        $type = "";

        if (is_double($new_value)) {
            $type = "d";
        } elseif (is_int($new_value)) {
            $type = "i";
        } elseif (is_string($new_value)) {
            $type = "s";
        } else {
            return null;
        }

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, $type . "i", $new_value, $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Actualizar campo '{$column}' en la tabla '{$table}' para la CI: {$ci}");

        self::set_complete_user($ci);

        return $result_query->success;
    }

    private static function get_user_complete_queries(): array
    {
        $worker_select = "SELECT
            IF(t." . sql_nombre . " IS NULL, TRUE, FALSE) AS trabajador__" . sql_nombre . ",
            IF(t." . sql_apellido . " IS NULL, TRUE, FALSE) AS trabajador__" . sql_apellido . "
        FROM " . sql_tabla_usuario . " u 
        LEFT JOIN " . sql_tabla_trabajador . " t ON u." . sql_cedula . " = t." . sql_cedula . " 
        WHERE u." . sql_cedula . " = ?";

        return [
            enum_tipo_vecino => null,
            enum_tipo_operario => $worker_select,
            enum_tipo_admin_operador => $worker_select,
            enum_tipo_admin_general => $worker_select,
            enum_tipo_admin_sistema => $worker_select
        ];
    }

    private static function set_complete_user(int $ci): void
    {
        if (!self::has_user($ci)) {
            return;
        }

        $db = new Util_DbConnection();

        $user = Model_User::get_user($ci);
        if (!$user) {
            return;
        }

        $typeuser = $user[sql_tipo] ?? null;
        $complete_queries = self::get_user_complete_queries();

        if (!array_key_exists($typeuser, $complete_queries) || $complete_queries[$typeuser] === null) {
            return;
        }

        $sql = $complete_queries[$typeuser];

        $result_query_user_com = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Evaluación interna de estado completo para CI: {$ci}");

        if ($result_query_user_com->success != true) {
            return;
        }

        $data = $result_query_user_com->data->fetch_assoc();

        if (empty($data)) {
            return;
        }

        $completo = true;
        foreach ($data as $columna => $valor_bool) {
            if ($valor_bool) {
                $completo = false;
                break;
            }
        }

        $val_completo = $completo ? 1 : 0;

        $sql_complete = "UPDATE " . sql_tabla_usuario . " SET " . sql_usuario_completo . " = ? WHERE " . sql_cedula . " = ?";
        
        $db->executeQuery($sql_complete, "ii", $val_completo, $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Estado de perfil completo actualizado a ({$val_completo}) para CI: {$ci}");
    }

    public static function find_incomplete_data(string $typeuser, int $ci): bool|null|array
    {
        if (!in_array($typeuser, sql_usuario_tipo)) {
            return null;
        }
        
        $complete_queries = self::get_user_complete_queries();
        $sql = $complete_queries[$typeuser] ?? null;

        if ($sql == null) {
            return true;
        }

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Consulta de datos incompletos del usuario CI: {$ci} con tipo: {$typeuser}");

        if ($result_query->success != true) {
            return null;
        }
        
        $data = $result_query->data->fetch_assoc();
        if ($data == null) {
            return null;
        }

        if (empty($data)) {
            return true;
        }

        return $data;
    }

    public static function user_is_complete(int $ci): ?bool
    {
        $sql = "SELECT " . sql_usuario_completo . " FROM " . sql_tabla_usuario . " WHERE " . sql_cedula . " = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Consulta de indicador de usuario completo para CI: {$ci}");

        if ($result_query->success != true) {
            return null;
        }
        
        $data = $result_query->data->fetch_assoc();

        if ($data == null) {
            return null;
        }

        return (bool) $data[sql_usuario_completo];
    }

    public static function get_user_data(int $ci): ?array
    {
        $user_base = self::get_user($ci);

        if (!$user_base) {
            return null;
        }

        $typeuser = $user_base[sql_tipo] ?? null;

        $joins_by_type = [
            enum_tipo_vecino => [
                sql_tabla_vecino
            ],
            enum_tipo_operario => [
                sql_tabla_trabajador,
                sql_tabla_operador
            ],
            enum_tipo_admin_operador => [
                sql_tabla_trabajador,
                sql_tabla_muni_operador
            ],
            enum_tipo_admin_general => [
                sql_tabla_trabajador,
                sql_tabla_muni_general
            ],
            enum_tipo_admin_sistema => [
                sql_tabla_trabajador,
                sql_tabla_admin
            ]
        ];

        $tables_to_join = $joins_by_type[$typeuser] ?? [];

        $sql = "SELECT u." . sql_cedula . ", u." . sql_tipo . ", u." . sql_usuario_completo;
        $joins_sql = "";

        foreach ($tables_to_join as $index => $tabla) {
            $alias = "t" . ($index + 1);
            $sql .= ", {$alias}.*";
            $joins_sql .= " LEFT JOIN {$tabla} {$alias} ON u." . sql_cedula . " = {$alias}." . sql_cedula;
        }

        $sql .= " FROM " . sql_tabla_usuario . " u{$joins_sql} WHERE u." . sql_cedula . " = ?";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);

        Model_Log::add_log_sql(self::MODEL_LOG, "Consulta administrativa de datos completos para CI: {$ci}");

        if (!$query_result->success) {
            return null;
        }

        $data = $query_result->data->fetch_assoc();

        if (!$data) {
            return null;
        }

        unset($data[sql_clave]); 

        return $data;
    }

    public static function delete_user(int $ci): ?bool
    {
        if (!self::has_user($ci)) {
            return null;
        }

        $sql = "DELETE FROM " . sql_tabla_usuario . " WHERE " . sql_cedula . " = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Eliminar usuario CI: {$ci}");

        return $result_query->success;
    }

    public static function delete_user_request(int $ci): ?bool
    {
        if (!self::has_request_user($ci)) {
            return null;
        }

        $sql = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE " . sql_cedula . " = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::MODEL_LOG, "Eliminar solicitud de usuario CI: {$ci}");

        return $result_query->success;
    }
}