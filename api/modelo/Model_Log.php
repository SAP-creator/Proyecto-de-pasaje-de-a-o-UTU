<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";

class Model_Log
{
    private const MODEL_LOG = "LOG MODEL";

    public static function add_log_user(int $ci, string $type_log, string $text): ?bool
    {
        $sql = "INSERT INTO " . sql_tabla_log_user . " (" . sql_cedula_usuario . ", " . sql_tipo_log . ", " . sql_texto . ") VALUES (?, ?, ?)";
        $params = [$ci, $type_log, $text];

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "iss", ...$params);

        self::add_log_sql(self::MODEL_LOG, "Insertar log de usuario para CI: {$ci} - Tipo: {$type_log}", $sql, $params);

        return $query_result->success;
    }

    /**
     * Guarda un registro de auditoria SQL. Ademas del texto descriptivo, guarda
     * la query ejecutada y los parametros que se le pasaron, para poder
     * reconstruir exactamente que se ejecuto contra la base de datos.
     *
     * @param array $params Parametros ingresados en la query, en el mismo orden
     *                       en el que se enviaron (posicion => valor).
     */
    public static function add_log_sql(string $model, string $text, string $query = "", array $params = []): ?bool
    {
        $parametros_json = json_encode(self::indexar_parametros($params));

        $sql = "INSERT INTO " . sql_tabla_log_sql . " (" . sql_tipo_modelo . ", " . sql_texto . ", " . sql_query . ", " . sql_parametros . ") VALUES (?, ?, ?, ?)";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "ssss", $model, $text, $query, $parametros_json);

        return $query_result->success;
    }

    /**
     * Convierte la lista de parametros en un array asociativo cuya clave es la
     * posicion del parametro (como string, para que sobreviva el json_encode)
     * y cuyo valor es el valor ingresado en esa posicion.
     */
    private static function indexar_parametros(array $params): array
    {
        $parametros_indexados = [];

        foreach (array_values($params) as $posicion => $valor) {
            $parametros_indexados[(string) $posicion] = $valor;
        }

        return $parametros_indexados;
    }

    public static function get_logs_user(int $ci, string $type_log = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type_log)) {
            $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_log . ", " . sql_texto . ", " . sql_cedula_usuario . "
                    FROM " . sql_tabla_log_user . "
                    WHERE " . sql_cedula_usuario . " = ?
                    ORDER BY " . sql_fecha . " DESC";
            $params = [$ci];

            $query_result = $db->executeQuery($sql, "i", ...$params);
            self::add_log_sql(self::MODEL_LOG, "Consultar logs del usuario CI: {$ci}", $sql, $params);
        } else {
            $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_log . ", " . sql_texto . ", " . sql_cedula_usuario . "
                    FROM " . sql_tabla_log_user . "
                    WHERE " . sql_cedula_usuario . " = ? AND " . sql_tipo_log . " = ?
                    ORDER BY " . sql_fecha . " DESC";
            $params = [$ci, $type_log];

            $query_result = $db->executeQuery($sql, "is", ...$params);
            self::add_log_sql(self::MODEL_LOG, "Consultar logs del usuario CI: {$ci} filtrados por tipo: {$type_log}", $sql, $params);
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

        self::add_log_sql(self::MODEL_LOG, "Consultar historial general de logs de usuarios con filtros (tipo usuario: {$type_user}, tipo log: {$type_log})", $sql, $params);

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_logs_sql(): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_modelo . ", " . sql_texto . ", " . sql_query . ", " . sql_parametros . "
                FROM " . sql_tabla_log_sql . " 
                ORDER BY " . sql_fecha . " DESC";

        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql);

        self::add_log_sql(self::MODEL_LOG, "Consultar registros de auditoría de consultas SQL", $sql, []);

        if (!$query_result->success || is_null($query_result->data)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }
}
