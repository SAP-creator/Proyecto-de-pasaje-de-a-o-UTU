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
        $sql = "INSERT INTO log_sql (tipo_modelo, texto) VALUES (?,?)";
        
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
                    FROM log 
                    WHERE cedula_usuario = ? 
                    ORDER BY fecha DESC";

            $query_result = $db->executeQuery($sql, "i", $ci);
        } 
        else 
        {
            $sql = "SELECT fecha, id, tipo_log, texto, cedula_usuario
                    FROM log 
                    WHERE cedula_usuario = ? AND tipo_log = ? 
                    ORDER BY fecha DESC";

            $query_result = $db->executeQuery($sql, "is", $ci, $type_log);
        }

        if (!$query_result->success) 
        {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }


    public static function get_logs_users(string $type_user, string $type_log = ""): ?array
    {
        if (!empty($type_user) && defined('sql_usuario_tipo') && !in_array($type_user, sql_usuario_tipo)) 
        {
            return null;
        }

        $db = new Util_DbConnection();

        if (empty($type_log)) 
        {
            $sql = "SELECT l.id, l.tipo_log, l.texto, l.cedula_usuario, u.tipo AS tipo_usuario
                    FROM log l
                    INNER JOIN usuario u ON l.cedula_usuario = u.cedula
                    WHERE u.tipo = ?
                    ORDER BY l.fecha DESC";

            $query_result = $db->executeQuery($sql, "s", $type_user);
        } 
        else 
        {
            $sql = "SELECT l.id, l.tipo_log, l.texto, l.cedula_usuario, u.tipo AS tipo_usuario
                    FROM log l
                    INNER JOIN usuario u ON l.cedula_usuario = u.cedula
                    WHERE u.tipo = ? AND l.tipo_log = ?
                    ORDER BY l.fecha DESC";

            $query_result = $db->executeQuery($sql, "ss", $type_user, $type_log);
        }

        if (!$query_result->success) 
        {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_sql(): ?array{
        $sql = "SELECT FROM * log_sql";

        $db = new Util_DbConnection();

        $query_result = $db->executeQuery($sql);

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }
}