<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

/**
 * Centros de acopio: ubicacion, array de almacenamientos y retiros.
 *
 * Convencion (igual que Model_User y Model_Contenedor):
 *  - null = error al ejecutar la query.
 *  - []   = la consulta funciono pero no hay datos (no existe).
 *
 * Las funciones publicas no reciben conexion: cada una crea su propia
 * instancia de Util_DbConnection y, si necesita varias queries "todo o nada",
 * hace begin/commit/rollback con ella.
 *
 * Un almacenamiento es ['id', 'tipo_basuras' => [...], 'cantidad' => float,
 * 'volumen_actual' => float]. "cantidad" es el volumen maximo, en litros.
 */
class Model_Centro
{
    private const MODEL_LOG = "CENTRO MODEL";

    // =================================================================
    // Centro + almacenamientos
    // =================================================================

    /**
     * Crea el centro con sus almacenamientos (volumen_actual en 0). Todo o nada.
     *
     * @param array $almacenamientos [['tipo_basuras' => [...], 'cantidad' => float], ...]
     */
    public static function create_centro(string $ubicacion, array $almacenamientos): ?int
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $sql = "INSERT INTO " . sql_tabla_centro . " (" . sql_ubicacion . ") VALUES (?)";
            $query_result = self::ejecutar($db, "Insertar centro en: {$ubicacion}", $sql, "s", [$ubicacion]);

            $id = $query_result->insert_id;
            $ok = $query_result->success;

            foreach ($almacenamientos as $almacenamiento) {
                if (!$ok) {
                    break;
                }

                $ok = self::insertar_almacenamiento($db, $id, $almacenamiento['tipo_basuras'], $almacenamiento['cantidad']) !== null;
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
     * @return ?array [] si no existe, o ['id', 'ubicacion', 'almacenamientos' => [...]]
     */
    public static function get_centro(int $id): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_ubicacion . "
                FROM " . sql_tabla_centro . "
                WHERE " . sql_id . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar centro {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        if ($fila === null) {
            return [];
        }

        $almacenamientos = self::consultar_almacenamientos([$id]);

        if ($almacenamientos === null) {
            return null;
        }

        return [
            'id'              => (int) $fila[sql_id],
            'ubicacion'       => $fila[sql_ubicacion],
            'almacenamientos' => $almacenamientos[$id] ?? []
        ];
    }

    /**
     * Listado de centros con sus almacenamientos. Con $tipo_basura solo trae
     * los centros que tengan algun almacenamiento que incluya ese tipo.
     */
    public static function get_centros(string $tipo_basura = ""): ?array
    {
        $where_clause = "";
        $types = "";
        $params = [];

        if ($tipo_basura !== "") {
            $where_clause = "WHERE EXISTS (
                                SELECT 1 FROM " . sql_tabla_almacenamiento_centro . " a
                                WHERE a." . sql_id_centro . " = c." . sql_id . "
                                AND JSON_CONTAINS(a." . sql_tipo_basuras . ", JSON_QUOTE(?)))";
            $types = "s";
            $params = [$tipo_basura];
        }

        $sql = "SELECT c." . sql_id . ", c." . sql_ubicacion . "
                FROM " . sql_tabla_centro . " c {$where_clause}
                ORDER BY c." . sql_id . " ASC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Listar centros (tipo de basura opcional)", $sql, $types, $params);

        if (!$query_result->success) {
            return null;
        }

        $filas = $query_result->data->fetch_all(MYSQLI_ASSOC);

        if (empty($filas)) {
            return [];
        }

        $almacenamientos = self::consultar_almacenamientos(array_map(fn($fila) => (int) $fila[sql_id], $filas));

        if ($almacenamientos === null) {
            return null;
        }

        $centros = [];

        foreach ($filas as $fila) {
            $id = (int) $fila[sql_id];

            $centros[] = [
                'id'              => $id,
                'ubicacion'       => $fila[sql_ubicacion],
                'almacenamientos' => $almacenamientos[$id] ?? []
            ];
        }

        return $centros;
    }

    /**
     * Modifica la ubicacion y/o reemplaza la lista de almacenamientos.
     * En la lista, el que trae 'id' se actualiza (conserva su volumen_actual),
     * el que no trae 'id' es nuevo (empieza en 0) y los que no aparecen se borran.
     * El controlador ya verifico que los ids pertenezcan al centro.
     */
    public static function update_centro(int $id, ?string $ubicacion, ?array $almacenamientos): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $ok = true;

            if ($ubicacion !== null) {
                $sql = "UPDATE " . sql_tabla_centro . " SET " . sql_ubicacion . " = ? WHERE " . sql_id . " = ?";
                $ok = self::ejecutar($db, "Actualizar ubicacion del centro {$id}", $sql, "si", [$ubicacion, $id])->success;
            }

            if ($ok && $almacenamientos !== null) {
                $ok = self::reemplazar_almacenamientos($db, $id, $almacenamientos);
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
    public static function delete_centro(int $id): ?bool
    {
        $sql = "DELETE FROM " . sql_tabla_centro . " WHERE " . sql_id . " = ?";
        $query_result = self::ejecutar(new Util_DbConnection(), "Borrar centro {$id}", $sql, "i", [$id]);

        return $query_result->success ? $query_result->affected_rows > 0 : null;
    }

    // =================================================================
    // Retiros (es lo que hace bajar el volumen_actual de un almacenamiento)
    // =================================================================

    /**
     * Resta volumen del almacenamiento y deja el retiro en el historial, todo o nada.
     * Con $volumen = null retira todo lo que haya. Nunca retira mas que el
     * volumen actual. Devuelve el volumen retirado realmente.
     */
    public static function retirar(int $id_centro, int $id_almacenamiento, ?float $volumen, int $ci, string $fecha): ?float
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            // FOR UPDATE: si dos personas retiran a la vez, la segunda espera a que termine la primera
            $sql = "SELECT " . sql_volumen_actual . "
                    FROM " . sql_tabla_almacenamiento_centro . "
                    WHERE " . sql_id . " = ? AND " . sql_id_centro . " = ?
                    FOR UPDATE";
            $query_result = self::ejecutar($db, "Consultar volumen del almacenamiento {$id_almacenamiento}", $sql, "ii", [$id_almacenamiento, $id_centro]);

            $fila = $query_result->success ? $query_result->data->fetch_assoc() : null;
            $ok = $fila !== null;

            if ($ok) {
                $actual = (float) $fila[sql_volumen_actual];
                $retirado = $volumen === null ? $actual : min($volumen, $actual);

                $sql = "UPDATE " . sql_tabla_almacenamiento_centro . "
                        SET " . sql_volumen_actual . " = " . sql_volumen_actual . " - ?
                        WHERE " . sql_id . " = ?";
                $ok = self::ejecutar($db, "Restar volumen al almacenamiento {$id_almacenamiento}", $sql, "di", [$retirado, $id_almacenamiento])->success;
            }

            if ($ok) {
                $sql = "INSERT INTO " . sql_tabla_retiro_centro . " (" . sql_id_centro . ", " . sql_id_almacenamiento . ", " . sql_fecha . ", " . sql_volumen . ", " . sql_cedula_usuario . ") VALUES (?, ?, ?, ?, ?)";
                $ok = self::ejecutar($db, "Guardar retiro del almacenamiento {$id_almacenamiento}", $sql, "iisdi", [$id_centro, $id_almacenamiento, $fecha, $retirado, $ci])->success;
            }
        } catch (Throwable) {
            $ok = false;
        }

        if (!$ok) {
            $db->rollback();
            return null;
        }

        $db->commit();
        return $retirado;
    }

    /**
     * Historial de retiros, del mas nuevo al mas viejo. Con $id_almacenamiento > 0 filtra por almacenamiento.
     *
     * @return ?array [['id', 'id_almacenamiento', 'fecha', 'volumen', 'cedula_usuario'], ...]
     */
    public static function get_retiros(int $id_centro, int $id_almacenamiento = 0): ?array
    {
        $where = sql_id_centro . " = ?";
        $types = "i";
        $params = [$id_centro];

        if ($id_almacenamiento > 0) {
            $where .= " AND " . sql_id_almacenamiento . " = ?";
            $types .= "i";
            $params[] = $id_almacenamiento;
        }

        $sql = "SELECT " . sql_id . ", " . sql_id_almacenamiento . ", " . sql_fecha . ", " . sql_volumen . ", " . sql_cedula_usuario . "
                FROM " . sql_tabla_retiro_centro . "
                WHERE {$where}
                ORDER BY " . sql_fecha . " DESC, " . sql_id . " DESC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar retiros del centro {$id_centro}", $sql, $types, $params);

        if (!$query_result->success) {
            return null;
        }

        $retiros = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $retiros[] = [
                'id'                => (int) $fila[sql_id],
                'id_almacenamiento' => (int) $fila[sql_id_almacenamiento],
                'fecha'             => $fila[sql_fecha],
                'volumen'           => (float) $fila[sql_volumen],
                'cedula_usuario'    => (int) $fila[sql_cedula_usuario]
            ];
        }

        return $retiros;
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
     * @return ?int el id del almacenamiento nuevo, o null si fallo
     */
    private static function insertar_almacenamiento(Util_DbConnection $db, int $id_centro, array $tipo_basuras, float $cantidad): ?int
    {
        $sql = "INSERT INTO " . sql_tabla_almacenamiento_centro . " (" . sql_id_centro . ", " . sql_tipo_basuras . ", " . sql_cantidad . ", " . sql_volumen_actual . ") VALUES (?, ?, ?, 0)";
        $params = [$id_centro, json_encode(array_values($tipo_basuras), JSON_UNESCAPED_UNICODE), $cantidad];

        $query_result = self::ejecutar($db, "Agregar almacenamiento al centro {$id_centro}", $sql, "isd", $params);

        return $query_result->success ? $query_result->insert_id : null;
    }

    /**
     * Deja los almacenamientos del centro exactamente como la lista recibida.
     */
    private static function reemplazar_almacenamientos(Util_DbConnection $db, int $id_centro, array $almacenamientos): bool
    {
        $ids_que_quedan = [];

        foreach ($almacenamientos as $almacenamiento) {
            if (isset($almacenamiento['id'])) {
                $sql = "UPDATE " . sql_tabla_almacenamiento_centro . "
                        SET " . sql_tipo_basuras . " = ?, " . sql_cantidad . " = ?
                        WHERE " . sql_id . " = ? AND " . sql_id_centro . " = ?";
                $params = [
                    json_encode(array_values($almacenamiento['tipo_basuras']), JSON_UNESCAPED_UNICODE),
                    $almacenamiento['cantidad'],
                    $almacenamiento['id'],
                    $id_centro
                ];

                if (!self::ejecutar($db, "Actualizar almacenamiento {$almacenamiento['id']}", $sql, "sdii", $params)->success) {
                    return false;
                }

                $ids_que_quedan[] = $almacenamiento['id'];
                continue;
            }

            $id_nuevo = self::insertar_almacenamiento($db, $id_centro, $almacenamiento['tipo_basuras'], $almacenamiento['cantidad']);

            if ($id_nuevo === null) {
                return false;
            }

            $ids_que_quedan[] = $id_nuevo;
        }

        // Se borran los que ya no estan en la lista
        $sql = "DELETE FROM " . sql_tabla_almacenamiento_centro . " WHERE " . sql_id_centro . " = ?";
        $types = "i";
        $params = [$id_centro];

        if (!empty($ids_que_quedan)) {
            $sql .= " AND " . sql_id . " NOT IN (" . implode(", ", array_fill(0, count($ids_que_quedan), "?")) . ")";
            $types .= str_repeat("i", count($ids_que_quedan));
            $params = array_merge($params, $ids_que_quedan);
        }

        return self::ejecutar($db, "Borrar almacenamientos que ya no estan en el centro {$id_centro}", $sql, $types, $params)->success;
    }

    /**
     * Almacenamientos de varios centros, agrupados: [id_centro => [almacenamiento, ...]].
     */
    private static function consultar_almacenamientos(array $ids_centro): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_id_centro . ", " . sql_tipo_basuras . ", " . sql_cantidad . ", " . sql_volumen_actual . "
                FROM " . sql_tabla_almacenamiento_centro . "
                WHERE " . sql_id_centro . " IN (" . implode(", ", array_fill(0, count($ids_centro), "?")) . ")
                ORDER BY " . sql_id_centro . " ASC, " . sql_id . " ASC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar almacenamientos de los centros", $sql, str_repeat("i", count($ids_centro)), $ids_centro);

        if (!$query_result->success) {
            return null;
        }

        $agrupados = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $agrupados[(int) $fila[sql_id_centro]][] = [
                'id'             => (int) $fila[sql_id],
                'tipo_basuras'   => json_decode($fila[sql_tipo_basuras], true),
                'cantidad'       => (float) $fila[sql_cantidad],
                'volumen_actual' => (float) $fila[sql_volumen_actual]
            ];
        }

        return $agrupados;
    }
}
