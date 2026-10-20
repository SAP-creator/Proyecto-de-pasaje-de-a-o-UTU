<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

/**
 * Rutas: un centro de inicio, uno de final y contenedores en linea (slot 1, 2, 3...).
 *
 * Convencion (igual que Model_User y Model_Contenedor):
 *  - null = error al ejecutar la query.
 *  - []   = la consulta funciono pero no hay datos (no existe).
 *
 * Las funciones publicas no reciben conexion: cada una crea su propia
 * instancia de Util_DbConnection y, si necesita varias queries "todo o nada",
 * hace begin/commit/rollback con ella.
 *
 * Las tablas de la ruta no tienen clave foranea hacia contenedor ni hacia
 * centro: lo que se borre se limpia con limpiar_ruta() la proxima vez que se
 * revise la ruta.
 */
class Model_Ruta
{
    private const MODEL_LOG = "RUTA MODEL";

    // =================================================================
    // CRUD
    // =================================================================

    /**
     * Crea la ruta y numera los contenedores en el orden recibido. Todo o nada.
     *
     * @param int[] $contenedores ids de contenedor, en el orden de la ruta
     */
    public static function create_ruta(int $id_centro_inicio, int $id_centro_final, array $contenedores): ?int
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $sql = "INSERT INTO " . sql_tabla_ruta . " (" . sql_id_centro_inicio . ", " . sql_id_centro_final . ") VALUES (?, ?)";
            $query_result = self::ejecutar($db, "Insertar ruta del centro {$id_centro_inicio} al {$id_centro_final}", $sql, "ii", [$id_centro_inicio, $id_centro_final]);

            $id = $query_result->insert_id;
            $ok = $query_result->success;

            if ($ok) {
                $ok = self::guardar_contenedores($db, $id, $contenedores);
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
     * @return ?array [] si no existe, o ['id', 'id_centro_inicio', 'id_centro_final', 'incompleta',
     *                'contenedores' => [['slot' => int, 'id_contenedor' => int], ...]]
     */
    public static function get_ruta(int $id): ?array
    {
        $sql = "SELECT " . sql_id . ", " . sql_id_centro_inicio . ", " . sql_id_centro_final . "
                FROM " . sql_tabla_ruta . "
                WHERE " . sql_id . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar ruta {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        if ($fila === null) {
            return [];
        }

        $sql = "SELECT " . sql_orden . ", " . sql_id_contenedor . "
                FROM " . sql_tabla_ruta_contenedor . "
                WHERE " . sql_id_ruta . " = ?
                ORDER BY " . sql_orden . " ASC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar contenedores de la ruta {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $contenedores = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila_contenedor) {
            $contenedores[] = [
                'slot'          => (int) $fila_contenedor[sql_orden],
                'id_contenedor' => (int) $fila_contenedor[sql_id_contenedor]
            ];
        }

        $inicio = $fila[sql_id_centro_inicio] === null ? null : (int) $fila[sql_id_centro_inicio];
        $final = $fila[sql_id_centro_final] === null ? null : (int) $fila[sql_id_centro_final];

        return [
            'id'               => (int) $fila[sql_id],
            'id_centro_inicio' => $inicio,
            'id_centro_final'  => $final,
            'incompleta'       => $inicio === null || $final === null,
            'contenedores'     => $contenedores
        ];
    }

    /**
     * Listado resumido. Con $id_centro > 0 trae las rutas que empiezan o terminan en ese centro.
     *
     * @return ?array [['id', 'id_centro_inicio', 'id_centro_final', 'cantidad_contenedores'], ...]
     */
    public static function get_rutas(int $id_centro = 0): ?array
    {
        $where_clause = "";
        $types = "";
        $params = [];

        if ($id_centro > 0) {
            $where_clause = "WHERE r." . sql_id_centro_inicio . " = ? OR r." . sql_id_centro_final . " = ?";
            $types = "ii";
            $params = [$id_centro, $id_centro];
        }

        $sql = "SELECT r." . sql_id . ", r." . sql_id_centro_inicio . ", r." . sql_id_centro_final . ",
                       (SELECT COUNT(*) FROM " . sql_tabla_ruta_contenedor . " rc WHERE rc." . sql_id_ruta . " = r." . sql_id . ") AS " . sql_cantidad_contenedores . "
                FROM " . sql_tabla_ruta . " r {$where_clause}
                ORDER BY r." . sql_id . " ASC";

        $query_result = self::ejecutar(new Util_DbConnection(), "Listar rutas (centro opcional)", $sql, $types, $params);

        if (!$query_result->success) {
            return null;
        }

        $rutas = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $rutas[] = [
                'id'                    => (int) $fila[sql_id],
                'id_centro_inicio'      => $fila[sql_id_centro_inicio] === null ? null : (int) $fila[sql_id_centro_inicio],
                'id_centro_final'       => $fila[sql_id_centro_final] === null ? null : (int) $fila[sql_id_centro_final],
                'cantidad_contenedores' => (int) $fila[sql_cantidad_contenedores]
            ];
        }

        return $rutas;
    }

    /**
     * Modifica solo lo que no sea null. Si llega $contenedores, reemplaza la lista completa
     * (y la vuelve a numerar de 1 a N). Todo o nada.
     */
    public static function update_ruta(int $id, ?int $id_centro_inicio, ?int $id_centro_final, ?array $contenedores): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $ok = true;
            $sets = [];
            $types = "";
            $params = [];

            if ($id_centro_inicio !== null) {
                $sets[] = sql_id_centro_inicio . " = ?";
                $types .= "i";
                $params[] = $id_centro_inicio;
            }

            if ($id_centro_final !== null) {
                $sets[] = sql_id_centro_final . " = ?";
                $types .= "i";
                $params[] = $id_centro_final;
            }

            if (!empty($sets)) {
                $sql = "UPDATE " . sql_tabla_ruta . " SET " . implode(", ", $sets) . " WHERE " . sql_id . " = ?";
                $ok = self::ejecutar($db, "Actualizar centros de la ruta {$id}", $sql, $types . "i", [...$params, $id])->success;
            }

            if ($ok && $contenedores !== null) {
                $sql = "DELETE FROM " . sql_tabla_ruta_contenedor . " WHERE " . sql_id_ruta . " = ?";
                $ok = self::ejecutar($db, "Vaciar la lista de contenedores de la ruta {$id}", $sql, "i", [$id])->success;

                if ($ok) {
                    $ok = self::guardar_contenedores($db, $id, $contenedores);
                }
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
    public static function delete_ruta(int $id): ?bool
    {
        $sql = "DELETE FROM " . sql_tabla_ruta . " WHERE " . sql_id . " = ?";
        $query_result = self::ejecutar(new Util_DbConnection(), "Borrar ruta {$id}", $sql, "i", [$id]);

        return $query_result->success ? $query_result->affected_rows > 0 : null;
    }

    // =================================================================
    // Consultas de la ruta
    // =================================================================

    /**
     * Tipos de basura de todos sus contenedores, sin repetir. Es una consulta de
     * solo lectura que cruza con almacenado (los contenedores que ya no existen no cuentan).
     *
     * @return ?string[]
     */
    public static function get_tipos_basura(int $id): ?array
    {
        $sql = "SELECT a." . sql_tipo_basuras . "
                FROM " . sql_tabla_ruta_contenedor . " rc
                INNER JOIN " . sql_tabla_almacenado . " a ON a." . sql_id_contenedor . " = rc." . sql_id_contenedor . "
                WHERE rc." . sql_id_ruta . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar tipos de basura de la ruta {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        $tipos = [];

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $fila) {
            $tipos = array_merge($tipos, json_decode($fila[sql_tipo_basuras], true));
        }

        return array_values(array_unique($tipos));
    }

    public static function get_cantidad_contenedores(int $id): ?int
    {
        $sql = "SELECT COUNT(*) AS " . sql_cantidad_contenedores . "
                FROM " . sql_tabla_ruta_contenedor . "
                WHERE " . sql_id_ruta . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Contar contenedores de la ruta {$id}", $sql, "i", [$id]);

        if (!$query_result->success) {
            return null;
        }

        return (int) $query_result->data->fetch_assoc()[sql_cantidad_contenedores];
    }

    /**
     * @return ?int el id del contenedor que esta en ese slot, o 0 si no hay ninguno
     */
    public static function get_contenedor_en_slot(int $id, int $slot): ?int
    {
        $sql = "SELECT " . sql_id_contenedor . "
                FROM " . sql_tabla_ruta_contenedor . "
                WHERE " . sql_id_ruta . " = ? AND " . sql_orden . " = ?";

        $query_result = self::ejecutar(new Util_DbConnection(), "Consultar el contenedor del slot {$slot} de la ruta {$id}", $sql, "ii", [$id, $slot]);

        if (!$query_result->success) {
            return null;
        }

        $fila = $query_result->data->fetch_assoc();

        return $fila === null ? 0 : (int) $fila[sql_id_contenedor];
    }

    /**
     * Revisa que sigan existiendo todos los contenedores y centros de la ruta:
     *  - saca de la lista los contenedores que ya no existen y renumera los slots de 1 a N;
     *  - deja en NULL el centro de inicio o de final que ya no existe.
     * Todo o nada.
     */
    public static function limpiar_ruta(int $id): ?bool
    {
        $db = new Util_DbConnection();
        $db->begin();

        try {
            $sql = "DELETE rc FROM " . sql_tabla_ruta_contenedor . " rc
                    LEFT JOIN " . sql_tabla_contenedor . " c ON c." . sql_id . " = rc." . sql_id_contenedor . "
                    WHERE rc." . sql_id_ruta . " = ? AND c." . sql_id . " IS NULL";
            $ok = self::ejecutar($db, "Sacar de la ruta {$id} los contenedores que ya no existen", $sql, "i", [$id])->success;

            if ($ok) {
                $ok = self::renumerar_contenedores($db, $id);
            }

            if ($ok) {
                $ok = self::dejar_en_null_centro_inexistente($db, $id, sql_id_centro_inicio);
            }

            if ($ok) {
                $ok = self::dejar_en_null_centro_inexistente($db, $id, sql_id_centro_final);
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

    // =================================================================
    // Ejecuciones: lo que bloquea editar o borrar
    // =================================================================

    /**
     * ¿La ruta se esta ejecutando (alguna ejecucion en curso o pausada)? Bloquea editar sus contenedores.
     */
    public static function ruta_ejecutandose(int $id): ?bool
    {
        return self::existe_ejecucion(sql_ejecucion_en_curso_o_pausada, sql_id_ruta . " = ?", "i", [$id], "ruta {$id}");
    }

    /**
     * ¿La ruta tiene alguna ejecucion abierta (aprobada, en curso o pausada)? Bloquea borrarla.
     */
    public static function ruta_con_ejecucion_abierta(int $id): ?bool
    {
        return self::existe_ejecucion(sql_ejecucion_abierta, sql_id_ruta . " = ?", "i", [$id], "ruta {$id}");
    }

    /**
     * ¿Alguna ruta que usa este centro se esta ejecutando? Bloquea borrar el centro.
     */
    public static function centro_en_ejecucion(int $id_centro): ?bool
    {
        $rutas_del_centro = "SELECT " . sql_id . " FROM " . sql_tabla_ruta . "
                             WHERE " . sql_id_centro_inicio . " = ? OR " . sql_id_centro_final . " = ?";

        return self::existe_ejecucion(sql_ejecucion_en_curso_o_pausada, sql_id_ruta . " IN ({$rutas_del_centro})", "ii", [$id_centro, $id_centro], "centro {$id_centro}");
    }

    /**
     * ¿Alguna ruta que usa este contenedor se esta ejecutando? Bloquea borrar el contenedor.
     */
    public static function contenedor_en_ejecucion(int $id_contenedor): ?bool
    {
        $rutas_del_contenedor = "SELECT " . sql_id_ruta . " FROM " . sql_tabla_ruta_contenedor . "
                                 WHERE " . sql_id_contenedor . " = ?";

        return self::existe_ejecucion(sql_ejecucion_en_curso_o_pausada, sql_id_ruta . " IN ({$rutas_del_contenedor})", "i", [$id_contenedor], "contenedor {$id_contenedor}");
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
     * Guarda los contenedores de la ruta numerados de 1 a N, en el orden recibido.
     */
    private static function guardar_contenedores(Util_DbConnection $db, int $id_ruta, array $contenedores): bool
    {
        $sql = "INSERT INTO " . sql_tabla_ruta_contenedor . " (" . sql_id_ruta . ", " . sql_id_contenedor . ", " . sql_orden . ") VALUES (?, ?, ?)";

        foreach (array_values($contenedores) as $posicion => $id_contenedor) {
            $params = [$id_ruta, $id_contenedor, $posicion + 1];

            if (!self::ejecutar($db, "Agregar el contenedor {$id_contenedor} a la ruta {$id_ruta}", $sql, "iii", $params)->success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cierra los huecos de la numeracion. Va de menor a mayor y solo mueve hacia
     * abajo, asi nunca choca con el UNIQUE (id_ruta, orden).
     */
    private static function renumerar_contenedores(Util_DbConnection $db, int $id_ruta): bool
    {
        $sql = "SELECT " . sql_id_contenedor . "
                FROM " . sql_tabla_ruta_contenedor . "
                WHERE " . sql_id_ruta . " = ?
                ORDER BY " . sql_orden . " ASC";

        $query_result = self::ejecutar($db, "Consultar el orden de los contenedores de la ruta {$id_ruta}", $sql, "i", [$id_ruta]);

        if (!$query_result->success) {
            return false;
        }

        $sql = "UPDATE " . sql_tabla_ruta_contenedor . "
                SET " . sql_orden . " = ?
                WHERE " . sql_id_ruta . " = ? AND " . sql_id_contenedor . " = ? AND " . sql_orden . " <> ?";

        foreach ($query_result->data->fetch_all(MYSQLI_ASSOC) as $posicion => $fila) {
            $nuevo_orden = $posicion + 1;
            $params = [$nuevo_orden, $id_ruta, (int) $fila[sql_id_contenedor], $nuevo_orden];

            if (!self::ejecutar($db, "Renumerar la ruta {$id_ruta}", $sql, "iiii", $params)->success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Si el centro de esa columna (inicio o final) ya no existe, deja la columna en NULL.
     * $columna sale de las constantes de Const_Sql, nunca del cliente.
     */
    private static function dejar_en_null_centro_inexistente(Util_DbConnection $db, int $id_ruta, string $columna): bool
    {
        $sql = "UPDATE " . sql_tabla_ruta . " r
                SET r.{$columna} = NULL
                WHERE r." . sql_id . " = ? AND r.{$columna} IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM " . sql_tabla_centro . " c WHERE c." . sql_id . " = r.{$columna})";

        return self::ejecutar($db, "Revisar {$columna} de la ruta {$id_ruta}", $sql, "i", [$id_ruta])->success;
    }

    /**
     * ¿Hay alguna ejecucion en uno de los $estados que cumpla $condicion?
     * $condicion y los $estados salen de las constantes, nunca del cliente.
     */
    private static function existe_ejecucion(array $estados, string $condicion, string $types, array $params, string $texto): ?bool
    {
        $sql = "SELECT 1 FROM " . sql_tabla_ejecucion . "
                WHERE " . sql_estado_ejecucion . " IN (" . implode(", ", array_fill(0, count($estados), "?")) . ")
                AND {$condicion}
                LIMIT 1";

        $query_result = self::ejecutar(new Util_DbConnection(), "Buscar ejecuciones para la {$texto}", $sql, str_repeat("s", count($estados)) . $types, [...$estados, ...$params]);

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->num_rows > 0;
    }
}
