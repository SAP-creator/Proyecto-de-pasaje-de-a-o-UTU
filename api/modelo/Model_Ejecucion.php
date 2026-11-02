<?php

include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/Model_Log.php";

class Model_Ejecucion
{
    private const MODEL_LOG = "EJECUCION MODEL";

    public static function get_ejecuciones(?string $estado = null, ?int $id_trayecto = null): ?array
    {
        $where = [];
        $types = "";
        $params = [];
        if ($estado !== null) {
            $where[] = "e." . sql_estado_ejecucion . " = ?";
            $types .= "s";
            $params[] = $estado;
        }
        if ($id_trayecto !== null) {
            $where[] = "e." . sql_id_trayecto . " = ?";
            $types .= "i";
            $params[] = $id_trayecto;
        }
        $sql = "SELECT e.*, e." . sql_estado_ejecucion . " AS " . sql_estado_ejecucion_alias . " FROM " . sql_tabla_ejecucion . " e" . (empty($where) ? "" : " WHERE " . implode(" AND ", $where)) . " ORDER BY e." . sql_fecha_programada . " DESC, e." . sql_id . " DESC";
        $result = self::execute($sql, "Listar ejecuciones", $types, $params);
        if ($result === null) {
            return null;
        }
        return array_map(static function (array $row): array {
            unset($row[sql_estado_ejecucion]);
            return $row;
        }, $result->data->fetch_all(MYSQLI_ASSOC));
    }

    public static function get_ejecucion(int $id): ?array
    {
        $result = self::execute("SELECT *, " . sql_estado_ejecucion . " AS " . sql_estado_ejecucion_alias . " FROM " . sql_tabla_ejecucion . " WHERE " . sql_id . " = ?", "Consultar ejecucion {$id}", "i", [$id]);
        if ($result === null) {
            return null;
        }
        $row = $result->data->fetch_assoc();
        if ($row === null) {
            return [];
        }
        unset($row[sql_estado_ejecucion]);
        $pausas = self::execute(
            "SELECT " . sql_id . ", " . sql_fecha_pausa . ", " . sql_fecha_reanudacion . ", " . sql_notas . " FROM " . sql_tabla_ejecucion_pausa . " WHERE " . sql_id_ejecucion . " = ? ORDER BY " . sql_fecha_pausa . " ASC, " . sql_id . " ASC",
            "Consultar pausas de ejecucion {$id}",
            "i",
            [$id]
        );
        if ($pausas === null) {
            return null;
        }
        $row[sql_pausas] = $pausas->data->fetch_all(MYSQLI_ASSOC);
        $containers = self::execute("SELECT " . sql_id_contenedor . ", " . sql_vaciado . " FROM " . sql_tabla_ejecucion_contenedor . " WHERE " . sql_id_ejecucion . " = ? ORDER BY " . sql_id_contenedor . " ASC", "Consultar contenedores de ejecucion {$id}", "i", [$id]);
        if ($containers === null) {
            return null;
        }
        $row[sql_contenedores] = $containers->data->fetch_all(MYSQLI_ASSOC);
        return $row;
    }

    /** Creates an approved execution, copying its route schedule from the selected trip. */
    public static function aprobar(int $id_trayecto, string $fecha_programada, string $cedula_aprobador, int $id_camion, string $cedula_operario, ?string $notas): int|string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $trip = self::query($db,
                "SELECT " . sql_id_ruta . ", " . sql_dia_semana_columna . ", " . sql_hora . ", " . sql_id_camion_auto . ", " . sql_cedula_operario_auto . " FROM " . sql_tabla_trayecto . " WHERE " . sql_id . " = ? FOR UPDATE",
                "Leer trayecto para aprobar ejecucion {$id_trayecto}", "i", [$id_trayecto]);
            if (!$trip->success) {
                throw new RuntimeException("db");
            }
            $schedule = $trip->data->fetch_assoc();
            if ($schedule === null) {
                $db->rollback();
                return "not_found";
            }
            $truck = self::query($db, "SELECT " . sql_estado . " FROM " . sql_tabla_camion . " WHERE " . sql_id . " = ? FOR UPDATE", "Validar camion {$id_camion}", "i", [$id_camion]);
            $operator = self::query($db, "SELECT " . sql_cedula . " FROM " . sql_tabla_operador . " WHERE " . sql_cedula . " = ?", "Validar operario", "s", [$cedula_operario]);
            if (!$truck->success || !$operator->success) {
                throw new RuntimeException("db");
            }
            $truck_row = $truck->data->fetch_assoc();
            if ($truck_row === null || $operator->data->num_rows === 0) {
                $db->rollback();
                return "assignment_not_found";
            }
            if ($truck_row[sql_estado] !== enum_camion_disponible) {
                $db->rollback();
                return "truck_unavailable";
            }
            $sql = "INSERT INTO " . sql_tabla_ejecucion . " (" . sql_id_trayecto . ", " . sql_id_ruta . ", " . sql_dia_semana_columna . ", " . sql_hora . ", " . sql_estado_ejecucion . ", " . sql_fecha_programada . ", " . sql_cedula_aprobador . ", " . sql_fecha_aprobacion . ", " . sql_notas . ", " . sql_cedula_operario . ", " . sql_id_camion . ")
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
            $result = self::query($db, $sql, "Aprobar ejecucion del trayecto {$id_trayecto}", "iisssssssi", [
                $id_trayecto,
                (int) $schedule[sql_id_ruta],
                $schedule[sql_dia_semana_columna],
                $schedule[sql_hora],
                enum_ejecucion_aprobada,
                $fecha_programada,
                $cedula_aprobador,
                $notas,
                $cedula_operario,
                $id_camion
            ]);
            if (!$result->success) {
                throw new RuntimeException("db");
            }
            $id_ejecucion = $result->insert_id;
            $containers = self::query($db,
                "INSERT INTO " . sql_tabla_ejecucion_contenedor . " (" . sql_id_ejecucion . ", " . sql_id_contenedor . ") SELECT ?, " . sql_id_contenedor . " FROM " . sql_tabla_ruta_contenedor . " WHERE " . sql_id_ruta . " = ?",
                "Copiar contenedores de ruta a ejecucion {$id_ejecucion}", "ii", [$id_ejecucion, (int) $schedule[sql_id_ruta]]);
            if (!$containers->success) {
                throw new RuntimeException("db");
            }
            $db->commit();
            return $id_ejecucion;
        } catch (Throwable) {
            $db->rollback();
            return "db_error";
        }
    }

    /** The only start transition is approved -> in progress. */
    public static function iniciar(int $id, string $operario): string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                $db->rollback();
                return "not_found";
            }
            if ($execution[sql_estado_ejecucion] !== enum_ejecucion_aprobada) {
                $db->rollback();
                return "invalid_state";
            }
            if ($execution[sql_cedula_operario] !== $operario) {
                $db->rollback();
                return "forbidden";
            }
            $route_lock = self::query($db, "SELECT " . sql_id . " FROM " . sql_tabla_ruta . " WHERE " . sql_id . " = ? FOR UPDATE", "Bloquear ruta para iniciar ejecucion {$id}", "i", [(int) $execution[sql_id_ruta]]);
            $active = self::query($db, "SELECT " . sql_id . " FROM " . sql_tabla_ejecucion . " WHERE " . sql_id_ruta . " = ? AND " . sql_estado_ejecucion . " IN (?, ?) AND " . sql_id . " <> ? LIMIT 1 FOR UPDATE", "Verificar otra ejecucion activa de ruta {$id}", "issi", [(int) $execution[sql_id_ruta], enum_ejecucion_en_curso, enum_ejecucion_pausada, $id]);
            $truck = self::query($db, "SELECT " . sql_estado . " FROM " . sql_tabla_camion . " WHERE " . sql_id . " = ? FOR UPDATE", "Verificar camion para iniciar ejecucion {$id}", "i", [(int) $execution[sql_id_camion]]);
            if (!$route_lock->success || !$active->success || !$truck->success) {
                throw new RuntimeException("db");
            }
            if ($active->data->num_rows > 0) {
                $db->rollback();
                return "route_busy";
            }
            $truck_row = $truck->data->fetch_assoc();
            if ($truck_row === null || $truck_row[sql_estado] !== enum_camion_disponible) {
                $db->rollback();
                return "truck_unavailable";
            }
            $truck_update = self::query($db, "UPDATE " . sql_tabla_camion . " SET " . sql_estado . " = ? WHERE " . sql_id . " = ? AND " . sql_estado . " = ?", "Marcar camion en trayecto para ejecucion {$id}", "sis", [enum_camion_en_trayecto, (int) $execution[sql_id_camion], enum_camion_disponible]);
            $execution_update = self::query($db, "UPDATE " . sql_tabla_ejecucion . " SET " . sql_estado_ejecucion . " = ?, " . sql_fecha_inicio . " = NOW() WHERE " . sql_id . " = ? AND " . sql_estado_ejecucion . " = ?", "Iniciar ejecucion {$id}", "sis", [enum_ejecucion_en_curso, $id, enum_ejecucion_aprobada]);
            if (!$truck_update->success || $truck_update->affected_rows !== 1 || !$execution_update->success || $execution_update->affected_rows !== 1) {
                throw new RuntimeException("conflict");
            }
            $db->commit();
            return "ok";
        } catch (Throwable $exception) {
            $db->rollback();
            return $exception->getMessage() === "conflict" ? "conflict" : "db_error";
        }
    }

    public static function pausar(int $id, string $actor, bool $admin_operador): string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                $db->rollback();
                return "not_found";
            }
            if ($execution[sql_estado_ejecucion] !== enum_ejecucion_en_curso) {
                $db->rollback();
                return "invalid_state";
            }
            if (!$admin_operador && $execution[sql_cedula_operario] !== $actor) {
                $db->rollback();
                return "forbidden";
            }
            $pause = self::query($db, "INSERT INTO " . sql_tabla_ejecucion_pausa . " (" . sql_id_ejecucion . ", " . sql_fecha_pausa . ", " . sql_notas . ") VALUES (?, NOW(), NULL)", "Registrar pausa de ejecucion {$id}", "i", [$id]);
            $update = self::query($db, "UPDATE " . sql_tabla_ejecucion . " SET " . sql_estado_ejecucion . " = ? WHERE " . sql_id . " = ? AND " . sql_estado_ejecucion . " = ?", "Pausar ejecucion {$id}", "sis", [enum_ejecucion_pausada, $id, enum_ejecucion_en_curso]);
            if (!$pause->success || !$update->success || $update->affected_rows !== 1) {
                throw new RuntimeException("db");
            }
            $db->commit();
            return "ok";
        } catch (Throwable) {
            $db->rollback();
            return "db_error";
        }
    }

    public static function reanudar(int $id, string $actor, bool $admin_operador): string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                $db->rollback();
                return "not_found";
            }
            if ($execution[sql_estado_ejecucion] !== enum_ejecucion_pausada) {
                $db->rollback();
                return "invalid_state";
            }
            if (!$admin_operador && $execution[sql_cedula_operario] !== $actor) {
                $db->rollback();
                return "forbidden";
            }
            $pause = self::query($db, "UPDATE " . sql_tabla_ejecucion_pausa . " SET " . sql_fecha_reanudacion . " = NOW() WHERE " . sql_id_ejecucion . " = ? AND " . sql_fecha_reanudacion . " IS NULL ORDER BY " . sql_fecha_pausa . " DESC, " . sql_id . " DESC LIMIT 1", "Cerrar pausa de ejecucion {$id}", "i", [$id]);
            $update = self::query($db, "UPDATE " . sql_tabla_ejecucion . " SET " . sql_estado_ejecucion . " = ? WHERE " . sql_id . " = ? AND " . sql_estado_ejecucion . " = ?", "Reanudar ejecucion {$id}", "sis", [enum_ejecucion_en_curso, $id, enum_ejecucion_pausada]);
            if (!$pause->success || $pause->affected_rows !== 1 || !$update->success || $update->affected_rows !== 1) {
                throw new RuntimeException("db");
            }
            $db->commit();
            return "ok";
        } catch (Throwable) {
            $db->rollback();
            return "db_error";
        }
    }

    public static function terminar(int $id, string $operario, ?int $llenado): string
    {
        return self::finish_or_cancel($id, enum_ejecucion_en_curso, enum_ejecucion_finalizada, $operario, $llenado, false);
    }

    public static function rechazar(int $id, ?string $notas): string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                $db->rollback();
                return "not_found";
            }
            if ($execution[sql_estado_ejecucion] !== enum_ejecucion_aprobada) {
                $db->rollback();
                return "invalid_state";
            }
            $sql = "UPDATE " . sql_tabla_ejecucion . " SET " . sql_estado_ejecucion . " = ?, " . sql_fecha_final . " = NOW()" . ($notas === null ? "" : ", " . sql_notas . " = ?") . " WHERE " . sql_id . " = ? AND " . sql_estado_ejecucion . " = ?";
            $types = $notas === null ? "sis" : "ssis";
            $params = $notas === null ? [enum_ejecucion_rechazada, $id, enum_ejecucion_aprobada] : [enum_ejecucion_rechazada, $notas, $id, enum_ejecucion_aprobada];
            $result = self::query($db, $sql, "Rechazar ejecucion {$id}", $types, $params);
            if (!$result->success || $result->affected_rows !== 1) {
                throw new RuntimeException("db");
            }
            $db->commit();
            return "ok";
        } catch (Throwable) {
            $db->rollback();
            return "db_error";
        }
    }

    public static function cancelar(int $id, string $actor, bool $admin_operador, ?string $notas): string
    {
        $db = new Util_DbConnection();
        $db->begin();
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                $db->rollback();
                return "not_found";
            }
            if (!in_array($execution[sql_estado_ejecucion], [enum_ejecucion_aprobada, enum_ejecucion_en_curso, enum_ejecucion_pausada], true)) {
                $db->rollback();
                return "invalid_state";
            }
            if (!$admin_operador && $execution[sql_cedula_operario] !== $actor) {
                $db->rollback();
                return "forbidden";
            }
            $result = self::finish_or_cancel($id, $execution[sql_estado_ejecucion], enum_ejecucion_cancelada, $actor, null, true, $notas, $db);
            if ($result !== "ok") {
                $db->rollback();
                return $result;
            }
            $db->commit();
            return "ok";
        } catch (Throwable) {
            $db->rollback();
            return "db_error";
        }
    }

    private static function finish_or_cancel(int $id, string $expected, string $next, string $actor, ?int $llenado, bool $allow_paused, ?string $notas = null, ?Util_DbConnection $transaction = null): string
    {
        $owns_transaction = $transaction === null;
        $db = $transaction ?? new Util_DbConnection();
        if ($owns_transaction) {
            $db->begin();
        }
        try {
            $execution = self::load_for_update($db, $id);
            if ($execution === null) {
                if ($owns_transaction) $db->rollback();
                return "not_found";
            }
            $allowed = $allow_paused ? [enum_ejecucion_aprobada, enum_ejecucion_en_curso, enum_ejecucion_pausada] : [$expected];
            if (!in_array($execution[sql_estado_ejecucion], $allowed, true)) {
                if ($owns_transaction) $db->rollback();
                return "invalid_state";
            }
            if ($next === enum_ejecucion_finalizada && $execution[sql_cedula_operario] !== $actor) {
                if ($owns_transaction) $db->rollback();
                return "forbidden";
            }
            $sql = "UPDATE " . sql_tabla_ejecucion . " SET " . sql_estado_ejecucion . " = ?, " . sql_fecha_final . " = NOW(), " . sql_llenado_camion . " = ?" . ($notas === null ? "" : ", " . sql_notas . " = ?") . " WHERE " . sql_id . " = ? AND " . sql_estado_ejecucion . " = ?";
            $types = $notas === null ? "siis" : "sisis";
            $params = $notas === null ? [$next, $llenado, $id, $execution[sql_estado_ejecucion]] : [$next, $llenado, $notas, $id, $execution[sql_estado_ejecucion]];
            $update = self::query($db, $sql, "Cambiar estado de ejecucion {$id} a {$next}", $types, $params);
            if (!$update->success || $update->affected_rows !== 1) {
                throw new RuntimeException("db");
            }
            if (in_array($execution[sql_estado_ejecucion], [enum_ejecucion_en_curso, enum_ejecucion_pausada], true)) {
                $truck = self::query($db, "UPDATE " . sql_tabla_camion . " SET " . sql_estado . " = ? WHERE " . sql_id . " = ? AND " . sql_estado . " = ?", "Liberar camion al cerrar ejecucion {$id}", "sis", [enum_camion_disponible, (int) $execution[sql_id_camion], enum_camion_en_trayecto]);
                if (!$truck->success || $truck->affected_rows !== 1) {
                    throw new RuntimeException("db");
                }
                if ($execution[sql_estado_ejecucion] === enum_ejecucion_pausada) {
                    $pause = self::query($db, "UPDATE " . sql_tabla_ejecucion_pausa . " SET " . sql_fecha_reanudacion . " = NOW() WHERE " . sql_id_ejecucion . " = ? AND " . sql_fecha_reanudacion . " IS NULL", "Cerrar pausas al finalizar/cancelar ejecucion {$id}", "i", [$id]);
                    if (!$pause->success) {
                        throw new RuntimeException("db");
                    }
                }
            }
            if ($owns_transaction) $db->commit();
            return "ok";
        } catch (Throwable) {
            if ($owns_transaction) $db->rollback();
            return "db_error";
        }
    }

    private static function load_for_update(Util_DbConnection $db, int $id): ?array
    {
        $result = self::query($db, "SELECT * FROM " . sql_tabla_ejecucion . " WHERE " . sql_id . " = ? FOR UPDATE", "Bloquear ejecucion {$id}", "i", [$id]);
        if (!$result->success) {
            throw new RuntimeException("db");
        }
        return $result->data->fetch_assoc() ?: null;
    }

    private static function query(Util_DbConnection $db, string $sql, string $description, string $types = "", array $params = []): QueryResult
    {
        return Util_DbConnection::executeLogged($db, self::MODEL_LOG, $description, $sql, $types, $params);
    }

    private static function execute(string $sql, string $description, string $types = "", array $params = []): ?QueryResult
    {
        $result = self::query(new Util_DbConnection(), $sql, $description, $types, $params);
        return $result->success ? $result : null;
    }
}
