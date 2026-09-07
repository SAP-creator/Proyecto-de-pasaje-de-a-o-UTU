<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";

class Model_Log
{
    public static function add_log_user(int $ci, string $type_log, string $text): ?bool
    {
        $sql = "INSERT INTO log_user (cedula_usuario, tipo_log, texto) VALUES (?, ?, ?)";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "iss", $ci, $type_log, $text);

        return $query_result->success;
    }

    public static function add_log_sql(string $model, string $text): ?bool
    {
        
        $sql = "INSERT INTO log_sql (tipo_modelo, texto) VALUES (?, ?)";
        
        $db = new Util_DbConnection();
        
        $query_result = $db->executeQuery($sql, "ss", $model, $text);

        return $query_result->success;
    }

    public static function get_logs_user(int $ci, string $type_log = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type_log)) 
        {
            $sql = "SELECT id, tipo_log, texto, cedula_usuario 
                    FROM log_user 
                    WHERE cedula_usuario = ? 
                    ORDER BY fecha DESC";
            
            $query_result = $db->executeQuery($sql, "i", $ci);
        } 
        else 
        {
            $sql = "SELECT fecha, id, tipo_log, texto, cedula_usuario
                    FROM log_user 
                    WHERE cedula_usuario = ? AND tipo_log = ? 
                    ORDER BY fecha DESC";

            $query_result = $db->executeQuery($sql, "is", $ci, $type_log);
        }

        if (!$query_result->success || is_null($query_result->data)) 
        {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_users(string $type_user = "", string $type_log = ""): ?array
    {
        if (!empty($type_user) && defined('sql_usuario_tipo') && !in_array($type_user, sql_usuario_tipo)) {
            return null;
        }

        $where_clause = "";
        $types = "";
        $params = [];

        $has_user = !empty($type_user);
        $has_log  = !empty($type_log);

        if ($has_user && $has_log) {
            $where_clause = " WHERE u.tipo = ? AND l.tipo_log = ? ";
            $types = "ss";
            $params = [$type_user, $type_log];
        } else if ($has_user) {
            $where_clause = " WHERE u.tipo = ? ";
            $types = "s";
            $params = [$type_user];
        } else if ($has_log) {
            $where_clause = " WHERE l.tipo_log = ? ";
            $types = "s";
            $params = [$type_log];
        }

        // Nota el espacio extra antes de "WHERE" y antes de "ORDER"
        $sql = "SELECT l.id, l.fecha, l.tipo_log, l.texto, l.cedula_usuario, u.tipo AS tipo_usuario
                FROM log_user l
                INNER JOIN usuario u ON l.cedula_usuario = u.cedula    "
                . $where_clause . 
                " ORDER BY l.fecha DESC";

        $db = new Util_DbConnection();

        $query_result = empty($params) 
            ? $db->executeQuery($sql) 
            : $db->executeQuery($sql, $types, ...$params);

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_sql(): ?array 
    {
        $sql = "SELECT * FROM log_sql";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql);

        if (!$query_result->success || is_null($query_result->data)) 
        {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }
}