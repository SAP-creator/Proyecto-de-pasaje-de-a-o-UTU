<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

/**
 * Todo el tema "contenedor": contenedor, almacenado, estado, llenado,
 * vaciado e incidentes.
 *
 * Convencion (igual que Model_User):
 *  - null = error al ejecutar la query.
 *  - []   = la consulta funciono pero no hay datos (no existe).
 *
 * Las funciones publicas no reciben conexion: cada una crea su propia
 * instancia de Util_DbConnection. Las que necesitan varias queries "todo o
 * nada" hacen begin/commit/rollback con esa instancia y se la pasan a las
 * funciones privadas, asi la transaccion nunca sale de la funcion.
 *
 * Los arrays que devuelve usan como clave el nombre de la columna (o una
 * clave compuesta de Const_Sql); el controlador los traduce a JSON con
 * Util_Translator::sql_to_json_keys().
 */
class Model_Contenedor
{
    private const MODEL_LOG = "CONTENEDOR MODEL";

    // =================================================================
    // CONTENEDOR + ALMACENADO
    // =================================================================

    /**
     * Crea el contenedor con su almacenado, su estado inicial ([Normal], [Limpio])
     * y su llenado en 0. Todo o nada. Devuelve el id nuevo.
     */
    public static function create_contenedor(string $ubicacion, bool $en_funcion, array $tipo_basuras, float $cantidad, string $fecha): ?int
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $sql = "INSERT INTO " . sql_tabla_contenedor . " (" . sql_ubicacion . ", " . sql_en_funcion . ") VALUES (?, ?)";
            $query_result = self::ejecutar($db, "Insertar contenedor en: {$ubicacion}", $sql, "si", [$ubicacion, (int) $en_funcion]);

            $id = $query_result->insert_id;
            $ok = $query_result->success;

            if ($ok) {
                $ok = self::guardar_almacenado($db, $id, $tipo_basuras, $cantidad);
            }

            if ($ok) {
                $ok = self::insertar_estado($db, $id, [enum_cond_normal], [enum_basura_limpio], $fecha);
            }

            if ($ok) {
                $ok = self::insertar_llenado($db, $id, llenado_vacio, $fecha);
            }
        } catch (Throwable) {
            $ok = false;
        }

        if (!$ok) {
            $db->rollback();
            return null;
        }

        $db->commit();
        return $id;
    }

    /**
     * Modifica solo lo que no sea null. El almacenado se guarda completo:
     * el controlador manda tipo_basuras y cantidad juntos, o ninguno.
     */
    public static function update_contenedor(int $id, ?string $ubicacion, ?bool $en_funcion, ?array $tipo_basuras, ?float $cantidad): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $ok = self::actualizar($db, sql_tabla_contenedor, $id, [
                sql_ubicacion  => $ubicacion,
                sql_en_funcion => $en_funcion === null ? null : (int) $en_funcion
            ]);

            if ($ok && $tipo_basuras !== null && $cantidad !== null) {
                $ok = self::guardar_almacenado($db, $id, $tipo_basuras, $cantidad);
            }
        } catch (Throwable) {
            $ok = false;
        }

        if (!$ok) {
            $db->rollback();
            return null;
        }

        $db->commit();
        return true;
    }

    /**
     * @return ?bool true si se borro, false si no existia, null si fallo la query.
     */
    public static function delete_contenedor(int $id): ?bool
    {
        $sql = "DELETE FROM " . sql_tabla_contenedor . " WHERE " . sql_id . " = ?";
        $query_result = self::ejecutar(new Util_DbConnection(), "Borrar contenedor {$id}", $sql, "i", [$id]);

        return $query_result->success ? $query_result->affected_rows > 0 : null;
    }

    /**
     * @return ?array [] si no existe, o ['id', 'ubicacion', 'en_funcion', 'almacenado' => null | ['tipo_basuras', 'cantidad']]
     */
    public static function get_contenedor(int $id): ?array
    {
        $sql = "SELECT c." . sql_id . ", c." . sql_ubicacion . ", c." . sql_en_funcion . ", a." . sql_tipo_basuras . ", a." . sql_cantidad . "
                FROM " . sql_tabla_contenedor . " c
                LEFT JOIN " . sql_tabla_almacenado . " a ON a." . sql_id_contenedor . " = c." . sql_id . "
                WHERE c." . sql_id . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        if ($fila === null) {
            return [];
        }

        return [
            'id'         => (int) $fila[sql_id],
            'ubicacion'  => $fila[sql_ubicacion],
            'en_funcion' => (bool) $fila[sql_en_funcion],
            'almacenado' => self::armar_almacenado($fila)
        ];
    }

    /**
     * Listado resumido: contenedor, almacenado, estado actual y ultimo llenado.
     */
    public static function get_contenedores(?bool $en_funcion = null, string $tipo_basura = ""): ?array
    {
        $where = [];
        $types = "";
        $params = [];

        if ($en_funcion !== null) {
            $where[] = "c." . sql_en_funcion . " = ?";
            $types .= "i";
            $params[] = (int) $en_funcion;
        }

        if ($tipo_basura !== "") {
            $where[] = "JSON_CONTAINS(a." . sql_tipo_basuras . ", JSON_QUOTE(?))";
            $types .= "s";
            $params[] = $tipo_basura;
        }

        $where_clause = empty($where) ? "" : " WHERE " . implode(" AND ", $where);

        // El estado y el llenado actuales son la fila mas reciente de cada tabla
        $sql = "SELECT c." . sql_id . ", c." . sql_ubicacion . ", c." . sql_en_funcion . ",
                       a." . sql_tipo_basuras . ", a." . sql_cantidad . ",
                       e." . sql_condicion . ", e." . sql_basura . ", e." . sql_fecha . " AS estado_fecha,
                       l." . sql_llenado . ", l." . sql_fecha . " AS llenado_fecha
                FROM " . sql_tabla_contenedor . " c
                LEFT JOIN " . sql_tabla_almacenado . " a ON a." . sql_id_contenedor . " = c." . sql_id . "
                LEFT JOIN " . sql_tabla_estado_contenedor . " e ON e." . sql_id . " = (
                    SELECT e2." . sql_id . " FROM " . sql_tabla_estado_contenedor . " e2
                    WHERE e2." . sql_id_contenedor . " = c." . sql_id . "
                    ORDER BY e2." . sql_fecha . " DESC, e2." . sql_id . " DESC LIMIT 1)
                LEFT JOIN " . sql_tabla_llenado . " l ON l." . sql_id . " = (
                    SELECT l2." . sql_id . " FROM " . sql_tabla_llenado . " l2
                    WHERE l2." . sql_id_contenedor . " = c." . sql_id . "
                    ORDER BY l2." . sql_fecha . " DESC, l2." . sql_id . " DESC LIMIT 1)"
                . $where_clause .
                " ORDER BY c." . sql_id . " ASC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Listar contenedores (en_funcion y tipo de basura opcionales)", $sql, $types, $params);

        if (!$query_result->success) {
            return null;
        }

        $contenedores = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $contenedores[] = [
                'id'             => (int) $fila[sql_id],
                'ubicacion'      => $fila[sql_ubicacion],
                'en_funcion'     => (bool) $fila[sql_en_funcion],
                'almacenado'     => self::armar_almacenado($fila),
                'estado_actual'  => $fila[sql_condicion] === null ? null : self::armar_estado($fila, 'estado_fecha'),
                'llenado_actual' => $fila[sql_llenado] === null ? null : self::armar_llenado($fila, 'llenado_fecha')
            ];
        }

        return $contenedores;
    }

    // =================================================================
    // ESTADO (una sola tabla: el actual es el de fecha mas reciente)
    // =================================================================

    /**
     * @return ?array [] si no tiene estados, o ['condicion' => [], 'basura' => [], 'fecha' => string]
     */
    public static function get_estado_actual(int $id): ?array
    {
        return self::consultar_estado_actual(new Util_DbConnection(), $id);
    }

    /**
     * Estados anteriores al actual, del mas nuevo al mas viejo.
     */
    public static function get_historial_estado(int $id): ?array
    {
        $sql = "SELECT " . sql_condicion . ", " . sql_basura . ", " . sql_fecha . "
                FROM " . sql_tabla_estado_contenedor . "
                WHERE " . sql_id_contenedor . " = ?
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar historial de estados del contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $filas = array_slice($query_result->data->fetch_all(MYSQLI_ASSOC), 1); // la primera es el estado actual

        return array_map([self::class, 'armar_estado'], $filas);
    }

    public static function add_estado(int $id, array $condicion, array $basura, string $fecha): ?bool
    {
        $ok = self::insertar_estado(new Util_DbConnection(), $id, $condicion, $basura, $fecha);

        return $ok ?: null;
    }

    // =================================================================
    // LLENADO Y VACIADO
    // =================================================================

    /**
     * @return ?array [] si no tiene llenados, o ['llenado' => int, 'fecha' => string]
     */
    public static function get_ultimo_llenado(int $id): ?array
    {
        return self::consultar_ultimo_llenado(new Util_DbConnection(), $id);
    }

    /**
     * Todos los llenados, del mas nuevo al mas viejo.
     */
    public static function get_historial_llenado(int $id): ?array
    {
        $sql = "SELECT " . sql_llenado . ", " . sql_fecha . "
                FROM " . sql_tabla_llenado . "
                WHERE " . sql_id_contenedor . " = ?
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar historial de llenado del contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        return array_map([self::class, 'armar_llenado'], $query_result->data->fetch_all(MYSQLI_ASSOC));
    }

    /**
     * Registra el llenado y deja el estado coherente con el: "Sobre_llenado" esta
     * si y solo si el ultimo llenado es 11. Las dos cosas juntas o ninguna.
     */
    public static function add_llenado(int $id, int $llenado, string $fecha): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $ok = self::insertar_llenado($db, $id, $llenado, $fecha);

            if ($ok) {
                $ok = self::sincronizar_sobrellenado($db, $id, $fecha);
            }
        } catch (Throwable) {
            $ok = false;
        }

        if (!$ok) {
            $db->rollback();
            return null;
        }

        $db->commit();
        return true;
    }

    /**
     * Vacia el contenedor: llenado 0, copia de los tipos de basura vaciados y
     * ajuste de Sobre_llenado. Todo o nada.
     */
    public static function empty_contenedor(int $id, array $tipos, string $fecha): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $ok = self::insertar_llenado($db, $id, llenado_vacio, $fecha);

            if ($ok) {
                $ok = self::insertar_vaciado($db, $id, $tipos, $fecha);
            }

            if ($ok) {
                $ok = self::sincronizar_sobrellenado($db, $id, $fecha);
            }
        } catch (Throwable) {
            $ok = false;
        }

        if (!$ok) {
            $db->rollback();
            return null;
        }

        $db->commit();
        return true;
    }

    /**
     * Todos los vaciados, del mas nuevo al mas viejo: [['tipos' => [...], 'fecha' => string], ...]
     */
    public static function get_historial_vaciado(int $id): ?array
    {
        $sql = "SELECT " . sql_tipos . ", " . sql_fecha . "
                FROM " . sql_tabla_vaciado . "
                WHERE " . sql_id_contenedor . " = ?
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar historial de vaciados del contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $vaciados = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $vaciados[] = [
                'tipos' => json_decode($fila[sql_tipos], true),
                'fecha' => $fila[sql_fecha]
            ];
        }

        return $vaciados;
    }

    // =================================================================
    // INCIDENTES
    // =================================================================

    public static function add_incidente(int $id_contenedor, string $tipo, string $descripcion): ?int
    {
        $sql = "INSERT INTO " . sql_tabla_incidente . " (" . sql_id_contenedor . ", " . sql_tipo_incidente . ", " . sql_descripcion . ") VALUES (?, ?, ?)";

        $query_result = self::ejecutar(new Util_DbConnection(), "Agregar incidente {$tipo} al contenedor {$id_contenedor}", $sql, "iss", [$id_contenedor, $tipo, $descripcion]);

        return $query_result->success ? $query_result->insert_id : null;
    }

    /**
     * @return ?array [] si no existe, o ['id', 'fecha', 'tipo_incidente', 'estado_incidente', 'descripcion', 'id_contenedor']
     */
    public static function get_incidente(int $id): ?array
    {
        $incidentes = self::consultar_incidentes("WHERE " . sql_id . " = ?", "i", [$id], "Consultar incidente {$id}");

        return $incidentes === null ? null : ($incidentes[0] ?? []);
    }

    public static function get_incidentes(int $id_contenedor = 0, string $estado = ""): ?array
    {
        $where = [];
        $types = "";
        $params = [];

        if ($id_contenedor > 0) {
            $where[] = sql_id_contenedor . " = ?";
            $types .= "i";
            $params[] = $id_contenedor;
        }

        if ($estado !== "") {
            $where[] = sql_estado_incidente . " = ?";
            $types .= "s";
            $params[] = $estado;
        }

        $where_clause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

        return self::consultar_incidentes($where_clause, $types, $params, "Listar incidentes (contenedor y estado opcionales)");
    }

    public static function update_incidente(int $id, ?string $tipo, ?string $estado, ?string $descripcion): ?bool
    {
        $ok = self::actualizar(new Util_DbConnection(), sql_tabla_incidente, $id, [
            sql_tipo_incidente   => $tipo,
            sql_estado_incidente => $estado,
            sql_descripcion      => $descripcion
        ]);

        return $ok ?: null;
    }

    /**
     * @return ?bool true si se borro, false si no existia, null si fallo la query.
     */
    public static function delete_incidente(int $id): ?bool
    {
        $sql = "DELETE FROM " . sql_tabla_incidente . " WHERE " . sql_id . " = ?";
        $query_result = self::ejecutar(new Util_DbConnection(), "Borrar incidente {$id}", $sql, "i", [$id]);

        return $query_result->success ? $query_result->affected_rows > 0 : null;
    }

    // =================================================================
    // Internas: reciben la conexion de la funcion publica que las llama
    // =================================================================

    /**
     * Ejecuta la query en la conexion recibida y la deja registrada en log_sql
     * (Model_Log usa su propia conexion, asi que el log no entra en la transaccion).
     */
    private static function ejecutar(Util_DbConnection $db, string $texto, string $sql, string $types = "", array $params = []): QueryResult
    {
        $query_result = $db->executeQuery($sql, $types, ...$params);

        Model_Log::add_log_sql(self::MODEL_LOG, $texto, $sql, $params);

        return $query_result;
    }

    /**
     * UPDATE de una tabla con clave "id": solo toca las columnas cuyo valor no sea null.
     * Los valores tienen que venir como int, float o string.
     */
    private static function actualizar(Util_DbConnection $db, string $tabla, int $id, array $campos): bool
    {
        $campos = array_filter($campos, fn($valor) => $valor !== null);

        if (empty($campos)) {
            return true;
        }

        $sets = implode(", ", array_map(fn($columna) => "{$columna} = ?", array_keys($campos)));
        $types = implode("", array_map(fn($valor) => is_int($valor) ? "i" : (is_float($valor) ? "d" : "s"), $campos)) . "i";
        $params = [...array_values($campos), $id];

        $sql = "UPDATE {$tabla} SET {$sets} WHERE " . sql_id . " = ?";

        return self::ejecutar($db, "Actualizar {$tabla} {$id}", $sql, $types, $params)->success;
    }

    private static function guardar_almacenado(Util_DbConnection $db, int $id, array $tipo_basuras, float $cantidad): bool
    {
        $sql = "INSERT INTO " . sql_tabla_almacenado . " (" . sql_id_contenedor . ", " . sql_tipo_basuras . ", " . sql_cantidad . ") VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE " . sql_tipo_basuras . " = VALUES(" . sql_tipo_basuras . "), " . sql_cantidad . " = VALUES(" . sql_cantidad . ")";
        $params = [$id, json_encode(array_values($tipo_basuras), JSON_UNESCAPED_UNICODE), $cantidad];

        return self::ejecutar($db, "Guardar almacenado del contenedor {$id}", $sql, "isd", $params)->success;
    }

    private static function insertar_estado(Util_DbConnection $db, int $id, array $condicion, array $basura, string $fecha): bool
    {
        $sql = "INSERT INTO " . sql_tabla_estado_contenedor . " (" . sql_id_contenedor . ", " . sql_fecha . ", " . sql_condicion . ", " . sql_basura . ") VALUES (?, ?, ?, ?)";
        $params = [
            $id,
            $fecha,
            json_encode(array_values($condicion), JSON_UNESCAPED_UNICODE),
            json_encode(array_values($basura), JSON_UNESCAPED_UNICODE)
        ];

        return self::ejecutar($db, "Agregar estado al contenedor {$id}", $sql, "isss", $params)->success;
    }

    private static function insertar_llenado(Util_DbConnection $db, int $id, int $llenado, string $fecha): bool
    {
        $sql = "INSERT INTO " . sql_tabla_llenado . " (" . sql_id_contenedor . ", " . sql_llenado . ", " . sql_fecha . ") VALUES (?, ?, ?)";

        return self::ejecutar($db, "Agregar llenado {$llenado} al contenedor {$id}", $sql, "iis", [$id, $llenado, $fecha])->success;
    }

    private static function insertar_vaciado(Util_DbConnection $db, int $id, array $tipos, string $fecha): bool
    {
        $sql = "INSERT INTO " . sql_tabla_vaciado . " (" . sql_id_contenedor . ", " . sql_fecha . ", " . sql_tipos . ") VALUES (?, ?, ?)";
        $params = [$id, $fecha, json_encode(array_values($tipos), JSON_UNESCAPED_UNICODE)];

        return self::ejecutar($db, "Agregar vaciado al contenedor {$id}", $sql, "iss", $params)->success;
    }

    private static function consultar_estado_actual(Util_DbConnection $db, int $id): ?array
    {
        $sql = "SELECT " . sql_condicion . ", " . sql_basura . ", " . sql_fecha . "
                FROM " . sql_tabla_estado_contenedor . "
                WHERE " . sql_id_contenedor . " = ?
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC
                LIMIT 1";

        $query_result = self::ejecutar($db, "Consultar estado actual del contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        return $fila === null ? [] : self::armar_estado($fila);
    }

    private static function consultar_ultimo_llenado(Util_DbConnection $db, int $id): ?array
    {
        $sql = "SELECT " . sql_llenado . ", " . sql_fecha . "
                FROM " . sql_tabla_llenado . "
                WHERE " . sql_id_contenedor . " = ?
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC
                LIMIT 1";

        $query_result = self::ejecutar($db, "Consultar ultimo llenado del contenedor {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        return $fila === null ? [] : self::armar_llenado($fila);
    }

    /**
     * Deja el estado coherente con el ULTIMO llenado: "Sobre_llenado" esta si y
     * solo si ese llenado es 11. Si hay que cambiarlo agrega un estado nuevo
     * (misma condicion; si la basura queda vacia pasa a [Limpio]).
     * Lee con la misma conexion, asi ve el llenado que se acaba de insertar.
     */
    private static function sincronizar_sobrellenado(Util_DbConnection $db, int $id, string $fecha): bool
    {
        $ultimo_llenado = self::consultar_ultimo_llenado($db, $id);
        $actual = self::consultar_estado_actual($db, $id);

        if ($ultimo_llenado === null || $actual === null) {
            return false;
        }

        $condicion = $actual['condicion'] ?? [enum_cond_normal];
        $basura = $actual['basura'] ?? [enum_basura_limpio];

        $debe_tener = ($ultimo_llenado['llenado'] ?? null) === llenado_sobrellenado;
        $tiene = in_array(enum_basura_sobrellenado, $basura, true);

        if ($debe_tener === $tiene) {
            return true;
        }

        if ($debe_tener) {
            $basura = array_values(array_diff($basura, [enum_basura_limpio]));
            $basura[] = enum_basura_sobrellenado;
        } else {
            $basura = array_values(array_diff($basura, [enum_basura_sobrellenado]));

            if (empty($basura)) {
                $basura = [enum_basura_limpio];
            }
        }

        // Si el llenado es de una fecha anterior al estado actual, el estado nuevo
        // usa la fecha del actual para seguir siendo el mas reciente
        $fecha_estado = max($fecha, $actual['fecha'] ?? $fecha);

        return self::insertar_estado($db, $id, $condicion, $basura, $fecha_estado);
    }

    private static function consultar_incidentes(string $where_clause, string $types, array $params, string $texto): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_fecha . ", " . sql_tipo_incidente . ", " . sql_estado_incidente . ", " . sql_descripcion . ", " . sql_id_contenedor . "
                FROM " . sql_tabla_incidente . " {$where_clause}
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC";

        $query_result = self::ejecutar(new Util_DbConnection(), $texto, $sql, $types, $params);

        if (!$query_result->success) {
            return null;
        }

        $incidentes = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $incidentes[] = [
                'id'               => (int) $fila[sql_id],
                'fecha'            => $fila[sql_fecha],
                'tipo_incidente'   => $fila[sql_tipo_incidente],
                'estado_incidente' => $fila[sql_estado_incidente],
                'descripcion'      => $fila[sql_descripcion],
                'id_contenedor'    => (int) $fila[sql_id_contenedor]
            ];
        }

        return $incidentes;
    }

    // Forma de los arrays que devuelve el modelo (una sola vez cada una)

    private static function armar_almacenado(array $fila): ?array
    {
        if ($fila[sql_tipo_basuras] === null) {
            return null;
        }

        return [
            'tipo_basuras' => json_decode($fila[sql_tipo_basuras], true),
            'cantidad'     => (float) $fila[sql_cantidad]
        ];
    }

    private static function armar_estado(array $fila, string $campo_fecha = sql_fecha): array
    {
        return [
            'condicion' => json_decode($fila[sql_condicion], true),
            'basura'    => json_decode($fila[sql_basura], true),
            'fecha'     => $fila[$campo_fecha]
        ];
    }

    private static function armar_llenado(array $fila, string $campo_fecha = sql_fecha): array
    {
        return [
            'llenado' => (int) $fila[sql_llenado],
            'fecha'   => $fila[$campo_fecha]
        ];
    }
}
