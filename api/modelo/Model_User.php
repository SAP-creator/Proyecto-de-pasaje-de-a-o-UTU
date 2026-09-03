<?php
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

class Model_User
{
    private const model_log = "USER MODEL";


    public static function get_users(string $type = ""): ?array
    {
        $db = new Util_DbConnection();

        
        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_usuario;
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::model_log, "Obtener todos los usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::model_log, "Obtener usuarios filtrados por tipo: {$type}");
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
            $sql = "SELECT * FROM " . sql_tabla_soli_usuario;
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::model_log, "Obtener todas las solicitudes de usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::model_log, "Obtener solicitudes filtradas por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_user(int $ci): ?array 
    {
        $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE cedula = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        
        Model_Log::add_log_sql(self::model_log, "Consultar usuario por cédula: {$ci}");
        
        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function get_request_user(int $ci): ?array
    {
        $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::model_log, "Consultar solicitud por cédula: {$ci}");

        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function has_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function has_request_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function create_request_user(int $ci, string $clave, string $type): ?bool
    {
        $sql = "INSERT INTO ". sql_tabla_soli_usuario . " (cedula, clave, tipo) VALUES (?, ?, ?)";
        $db = new Util_DbConnection();
        $result = $db->executeQuery($sql, "iss", $ci, $clave, $type);
        
        Model_Log::add_log_sql(self::model_log, "Crear solicitud de usuario CI: {$ci} con tipo: {$type}");

        return $result->success;
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
        
        if (!array_key_exists($tipo, self::sql_accept_user)) {
            return false;
        }

        $db = new Util_DbConnection();

        foreach (self::sql_accept_user[$tipo] as $sql) {
            $result = str_contains($sql, "usuario") 
                ? $db->executeQuery($sql, "is", $ci, $clave)
                : $db->executeQuery($sql, "i", $ci);

            if (!$result->success) {
                return false;
            }
        }

        $sql_delete = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        $result_delete = $db->executeQuery($sql_delete, "i", $ci);

        Model_Log::add_log_sql(self::model_log, "Solicitud aceptada y usuario migrado CI: {$ci} como tipo: {$tipo}");

        return $result_delete->success;
    }

    private const sql_accept_user = [
        enum_tipo_vecino => [
            "INSERT INTO ".sql_tabla_usuario." (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_vecino."', true)",
            "INSERT INTO ".enum_tipo_vecino." (cedula) VALUES (?)"
        ],
        enum_tipo_operario => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_operario."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_operador." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_operador => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_operador."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_muni_operador." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_general => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_general."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_muni_general." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_sistema => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_sistema."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_admin." (cedula) VALUES (?)"
        ]
    ];


    public static function change_data(int $ci, string $table, string $collum, mixed $new_value): ?bool
    {
        if (! array_key_exists($table, self::permitted_tables)) {
            return null;
        }

        if (! in_array($collum, self::permitted_tables[$table], true)) {
            return null;
        }

        if (self::has_user($ci) !== true) {
            return null;
        }

        $sql = "UPDATE " . $table . "
                SET " . $collum . " = ?
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

        return $result_query->success;
    }

    const permitted_tables = [
        sql_tabla_usuario => [sql_clave],
        sql_tabla_trabajador => [sql_nombre,sql_apellido],
        sql_tabla_muni_general => [],
        sql_tabla_muni_operador => [],
        sql_tabla_admin => []
    ];
}