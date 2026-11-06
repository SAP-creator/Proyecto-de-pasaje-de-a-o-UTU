<?php

include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/Model_Log.php";

class Model_Trayecto
{
    private const MODEL_LOG = "TRAYECTO MODEL";

    public static function get_trayectos(): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_id_ruta . ", " . sql_dia_semana_columna . ", " . sql_hora . ", " . sql_fecha_especial . ", " . sql_inicio_automatico . ", " . sql_id_camion_auto . ", " . sql_cedula_operario_auto . " FROM " . sql_tabla_trayecto . " ORDER BY " . sql_id . " ASC";
        return self::rows(self::execute($sql, "Listar trayectos"));
    }

    public static function get_trayecto(int $id): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_id_ruta . ", " . sql_dia_semana_columna . ", " . sql_hora . ", " . sql_fecha_especial . ", " . sql_inicio_automatico . ", " . sql_id_camion_auto . ", " . sql_cedula_operario_auto . " FROM " . sql_tabla_trayecto . " WHERE " . sql_id . " = ?";
        $result = self::execute($sql, "Consultar trayecto {$id}", "i", [$id]);
        if ($result === null) {
            return null;
        }
        $row = $result->data->fetch_assoc();
        return $row === null ? [] : self::normalize($row);
    }

    public static function create_trayecto(int $id_ruta, ?string $dia_semana, string $hora, ?string $fecha_especial, bool $inicio_automatico, ?int $id_camion_auto, ?string $cedula_operario_auto): ?int
    {
        $sql = "INSERT INTO " . sql_tabla_trayecto . " (" . sql_id_ruta . ", " . sql_dia_semana_columna . ", " . sql_hora . ", " . sql_fecha_especial . ", " . sql_inicio_automatico . ", " . sql_id_camion_auto . ", " . sql_cedula_operario_auto . ") VALUES (?, ?, ?, ?, ?, ?, ?)";
        $result = self::execute($sql, "Crear trayecto de ruta {$id_ruta}", "isssiis", [$id_ruta, $dia_semana, $hora, $fecha_especial, (int) $inicio_automatico, $id_camion_auto, $cedula_operario_auto]);
        return $result === null ? null : $result->insert_id;
    }

    public static function update_trayecto(int $id, ?int $id_ruta, ?string $dia_semana, bool $update_dia_semana, ?string $hora, bool $update_fecha_especial, ?string $fecha_especial, ?bool $inicio_automatico, bool $update_camion_auto, ?int $id_camion_auto, bool $update_operario, ?string $cedula_operario_auto): ?bool
    {
        $sets = [];
        $types = "";
        $params = [];
        foreach ([
            sql_id_ruta => [$id_ruta, "i"],
            sql_dia_semana_columna => [$update_dia_semana ? $dia_semana : null, "s"],
            sql_hora => [$hora, "s"],
            sql_fecha_especial => [$update_fecha_especial ? $fecha_especial : null, "s"],
            sql_inicio_automatico => [$inicio_automatico === null ? null : (int) $inicio_automatico, "i"],
            sql_id_camion_auto => [$update_camion_auto ? $id_camion_auto : null, "i"],
            sql_cedula_operario_auto => [$update_operario ? $cedula_operario_auto : null, "s"]
        ] as $column => [$value, $type]) {
            if ($value !== null || ($column === sql_dia_semana_columna && $update_dia_semana) || ($column === sql_fecha_especial && $update_fecha_especial) || ($column === sql_id_camion_auto && $update_camion_auto) || ($column === sql_cedula_operario_auto && $update_operario)) {
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
        $result = self::execute("UPDATE " . sql_tabla_trayecto . " SET " . implode(", ", $sets) . " WHERE " . sql_id . " = ?", "Actualizar trayecto {$id}", $types, $params);
        return $result === null ? null : $result->affected_rows > 0;
    }

    public static function delete_trayecto(int $id): ?bool
    {
        $result = self::execute("DELETE FROM " . sql_tabla_trayecto . " WHERE " . sql_id . " = ?", "Eliminar trayecto {$id}", "i", [$id]);
        return $result === null ? null : $result->affected_rows > 0;
    }

    public static function route_exists(int $id_ruta): ?bool
    {
        return self::exists("SELECT " . sql_id . " FROM " . sql_tabla_ruta . " WHERE " . sql_id . " = ?", "i", [$id_ruta], "Validar ruta {$id_ruta}");
    }

    public static function driver_exists(string $cedula): ?bool
    {
        return self::exists("SELECT " . sql_cedula . " FROM " . sql_tabla_operador . " WHERE " . sql_cedula . " = ?", "s", [$cedula], "Validar operario de camion");
    }

    public static function truck_exists(int $id): ?bool
    {
        return self::exists("SELECT " . sql_id . " FROM " . sql_tabla_camion . " WHERE " . sql_id . " = ?", "i", [$id], "Validar camion {$id}");
    }

    public static function has_executions(int $id): ?bool
    {
        $result = self::execute("SELECT " . sql_id . " FROM " . sql_tabla_ejecucion . " WHERE " . sql_id_trayecto . " = ? LIMIT 1", "Verificar ejecuciones del trayecto {$id}", "i", [$id]);
        if ($result === null) {
            return null;
        }
        return $result->data->num_rows > 0;
    }

    private static function exists(string $sql, string $types, array $params, string $description): ?bool
    {
        $result = self::execute($sql, $description, $types, $params);
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
        $row[sql_id_ruta] = (int) $row[sql_id_ruta];
        $row[sql_inicio_automatico] = (bool) $row[sql_inicio_automatico];
        $row[sql_id_camion_auto] = $row[sql_id_camion_auto] === null ? null : (int) $row[sql_id_camion_auto];
        return $row;
    }

    private static function execute(string $sql, string $description, string $types = "", array $params = []): ?QueryResult
    {
        $db = new Util_DbConnection();
        $result = Util_DbConnection::executeLogged($db, self::MODEL_LOG, $description, $sql, $types, $params);
        return $result->success ? $result : null;
    }
}
