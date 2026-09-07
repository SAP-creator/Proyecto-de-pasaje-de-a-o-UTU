<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";

class Model_Log
{
    private const MODEL_LOG = "LOG MODEL";

    public static function add_log_user(int $ci, string $type_log, string $text): ?bool
    {
        $sql = "INSERT INTO " . sql_tabla_log_user . " (" . sql_cedula_usuario . ", " . sql_tipo_log . ", " . sql_texto . ") VALUES (?, ?, ?)";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "iss", $ci, $type_log, $text);

        self::add_log_sql(self::MODEL_LOG, "Insertar log de usuario para CI: {$ci} - Tipo: {$type_log}");

        return $query_result->success;
    }

    public static function add_log_sql(string $model, string $text): ?bool
    {
        $sql = "INSERT INTO " . sql_tabla_log_sql . " (" . sql_tipo_modelo . ", " . sql_texto . ") VALUES (?, ?)";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "ss", $model, $text);

        return $query_result->success;
    }

    public static function get_logs_user(int $ci, string $type_log = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type_log)) {
            $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_log . ", " . sql_texto . ", " . sql_cedula_usuario . "
                    FROM " . sql_tabla_log_user . "
                    WHERE " . sql_cedula_usuario . " = ?
                    ORDER BY " . sql_fecha . " DESC";

            $query_result = $db->executeQuery($sql, "i", $ci);
            self::add_log_sql(self::MODEL_LOG, "Consultar logs del usuario CI: {$ci}");
        } else {
            $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_log . ", " . sql_texto . ", " . sql_cedula_usuario . "
                    FROM " . sql_tabla_log_user . "
                    WHERE " . sql_cedula_usuario . " = ? AND " . sql_tipo_log . " = ?
                    ORDER BY " . sql_fecha . " DESC";

            $query_result = $db->executeQuery($sql, "is", $ci, $type_log);
            self::add_log_sql(self::MODEL_LOG, "Consultar logs del usuario CI: {$ci} filtrados por tipo: {$type_log}");
        }

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_users(string $type_user = "", string $type_log = ""): ?array
    {
        if (!empty($type_user) && defined('sql_usuario_tipo') && !in_array($type_user, sql_usuario_tipo)) {
            return null;
        }

        $conditions = [];
        $types = "";
        $params = [];

        if (!empty($type_user)) {
            $conditions[] = "u." . sql_tipo . " = ?";
            $types .= "s";
            $params[] = $type_user;
        }

        if (!empty($type_log)) {
            $conditions[] = "l." . sql_tipo_log . " = ?";
            $types .= "s";
            $params[] = $type_log;
        }

        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

        $sql = "SELECT l." . sql_id . ", l." . sql_fecha . ", l." . sql_tipo_log . ", l." . sql_texto . ", l." . sql_cedula_usuario . ", u." . sql_tipo . " AS tipo_usuario
                FROM " . sql_tabla_log_user . " l
                INNER JOIN " . sql_tabla_usuario . " u ON l." . sql_cedula_usuario . " = u." . sql_cedula . " "
                . $where_clause .
                " ORDER BY l." . sql_fecha . " DESC";

        $db = new Util_DbConnection();

        $query_result = empty($params)
            ? $db->executeQuery($sql)
            : $db->executeQuery($sql, $types, ...$params);

        self::add_log_sql(self::MODEL_LOG, "Consultar historial general de logs de usuarios con filtros (tipo usuario: {$type_user}, tipo log: {$type_log})");

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_sql(): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_modelo . ", " . sql_texto . " 
                FROM " . sql_tabla_log_sql . " 
                ORDER BY " . sql_fecha . " DESC";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql);

        self::add_log_sql(self::MODEL_LOG, "Consultar registros de auditoría de consultas SQL");

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }
}