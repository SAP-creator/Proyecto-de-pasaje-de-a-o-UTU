<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/Model_Log.php";

class Model_Camion
{
    private const MODEL_LOG = "CAMION MODEL";

    public static function get_camiones(): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_matricula . ", " . sql_cantidad . ", " . sql_estado . ", " . sql_tipo_basuras . " FROM " . sql_tabla_camion . " ORDER BY " . sql_id . " ASC";
        $result = self::execute($sql, "Listar camiones");
        return self::rows($result);
    }

    public static function get_camion(int $id): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_matricula . ", " . sql_cantidad . ", " . sql_estado . ", " . sql_tipo_basuras . " FROM " . sql_tabla_camion . " WHERE " . sql_id . " = ?";
        $result = self::execute($sql, "Consultar camion {$id}", "i", [$id]);
        if ($result === null) {
            return null;
        }
        $row = $result->data->fetch_assoc();
        return $row === null ? [] : self::normalize($row);
    }

    public static function create_camion(string $matricula, float $cantidad, string $estado, array $tipo_basuras): ?int
    {
        $sql = "INSERT INTO " . sql_tabla_camion . " (" . sql_matricula . ", " . sql_cantidad . ", " . sql_estado . ", " . sql_tipo_basuras . ") VALUES (?, ?, ?, ?)";
        $result = self::execute($sql, "Crear camion {$matricula}", "sdss", [$matricula, $cantidad, $estado, json_encode($tipo_basuras, JSON_UNESCAPED_UNICODE)]);
        return $result === null ? null : $result->insert_id;
    }

    public static function update_camion(int $id, ?string $matricula, int|float|null $cantidad, ?string $estado, ?array $tipo_basuras): ?bool
    {
        $sets = [];
        $types = "";
        $params = [];
        foreach ([
            sql_matricula => [$matricula, "s"],
            sql_cantidad => [$cantidad, "d"],
            sql_estado => [$estado, "s"],
            sql_tipo_basuras => [$tipo_basuras === null ? null : json_encode($tipo_basuras, JSON_UNESCAPED_UNICODE), "s"]
        ] as $column => [$value, $type]) {
            if ($value !== null) {
                $sets[] = "{$column} = ?";
                $types .= $type;
                $params[] = $value;
            }
        }
        if (empty($sets)) {
            return false;
        }
        $types .= "i";
        $params[] = $id;
        $result = self::execute("UPDATE " . sql_tabla_camion . " SET " . implode(", ", $sets) . " WHERE " . sql_id . " = ?", "Actualizar camion {$id}", $types, $params);
        return $result === null ? null : $result->affected_rows > 0;
    }

    public static function delete_camion(int $id): ?bool
    {
        $result = self::execute("DELETE FROM " . sql_tabla_camion . " WHERE " . sql_id . " = ?", "Eliminar camion {$id}", "i", [$id]);
        return $result === null ? null : $result->affected_rows > 0;
    }

    public static function has_executions(int $id): ?bool
    {
        $sql = "SELECT " . sql_id . " FROM " . sql_tabla_ejecucion . " WHERE " . sql_id_camion . " = ? LIMIT 1";
        $result = self::execute($sql, "Verificar ejecuciones del camion {$id}", "i", [$id]);
        if ($result === null) {
            return null;
        }
        return $result->data->num_rows > 0;
    }

    private static function rows(?QueryResult $result): ?array
    {
        if ($result === null) {
            return null;
        }
        return array_map([self::class, "normalize"], $result->data->fetch_all(MYSQLI_ASSOC));
    }

    private static function normalize(array $row): array
    {
        $row[sql_id] = (int) $row[sql_id];
        $row[sql_cantidad] = (float) $row[sql_cantidad];
        $tipos = json_decode($row[sql_tipo_basuras], true);
        $row[sql_tipos_basura] = is_array($tipos) ? $tipos : [];
        unset($row[sql_tipo_basuras]);
        return $row;
    }

    private static function execute(string $sql, string $description, string $types = "", array $params = []): ?QueryResult
    {
        $db = new Util_DbConnection();
        $result = Util_DbConnection::executeLogged($db, self::MODEL_LOG, $description, $sql, $types, $params);
        return $result->success ? $result : null;
    }
}
