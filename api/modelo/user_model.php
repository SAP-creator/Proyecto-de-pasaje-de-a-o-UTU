<?php
include_once __DIR__ . "/../constantes/sql_constantes.php";
include_once __DIR__ . "/../utils/db_connection.php";
include_once __DIR__ . "/../modelo/log_model.php";

class UserModel
{
    private const model_log = "USER MODEL";

    public static function get_users(string $type = ""): ?array
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_usuario;
            $query_result = $db->executeQuery($sql); 
            LogModel::add_log_sql(self::model_log, "Obtener todos los usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            LogModel::add_log_sql(self::model_log, "Obtener usuarios filtrados por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_request_users(string $type = ""): ?array
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_soli_usuario;
            $query_result = $db->executeQuery($sql); 
            LogModel::add_log_sql(self::model_log, "Obtener todas las solicitudes de usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            LogModel::add_log_sql(self::model_log, "Obtener solicitudes filtradas por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_user(int $ci): ?array 
    {
        $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE cedula = ?";
        
        $db = new DatabaseConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        LogModel::add_log_sql(self::model_log, "Consultar usuario por cédula: {$ci}");

        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function get_request_user(int $ci): ?array
    {
        $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        
        $db = new DatabaseConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        LogModel::add_log_sql(self::model_log, "Consultar solicitud por cédula: {$ci}");

        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function has_user(int $ci, string $type = ""): ?bool
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            LogModel::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        LogModel::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function has_request_user(int $ci, string $type = ""): ?bool
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            LogModel::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        LogModel::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function create_request_user(int $ci, string $clave, string $type): ?bool
    {
        $sql = "INSERT INTO ". sql_tabla_soli_usuario . " (cedula, clave, tipo) VALUES (?, ?, ?)";
        $db = new DatabaseConnection();
        $result = $db->executeQuery($sql, "iss", $ci, $clave, $type);
        
        LogModel::add_log_sql(self::model_log, "Crear solicitud de usuario CI: {$ci} con tipo: {$type}");

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

        $db = new DatabaseConnection();

        foreach (self::sql_accept_user[$tipo] as $sql) {
            $result = str_contains($sql, "usuario (") 
                ? $db->executeQuery($sql, "is", $ci, $clave)
                : $db->executeQuery($sql, "i", $ci);

            if (!$result->success) {
                return false;
            }
        }

        $sql_delete = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        $result_delete = $db->executeQuery($sql_delete, "i", $ci);

        LogModel::add_log_sql(self::model_log, "Solicitud aceptada y usuario migrado CI: {$ci} como tipo: {$tipo}");

        return $result_delete->success;
    }

    private const sql_accept_user = [
        "vecino" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'vecino', true)",
            "INSERT INTO vecino (cedula) VALUES (?)"
        ],
        "operario" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'operario', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO operador (cedula) VALUES (?)"
        ],
        "admin operador" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin operador', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_municipal_operador (cedula) VALUES (?)"
        ],
        "admin general" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin general', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_municipal_general (cedula) VALUES (?)"
        ],
        "admin sistema" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin sistema', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_sistemas (cedula) VALUES (?)"
        ]
    ];
}