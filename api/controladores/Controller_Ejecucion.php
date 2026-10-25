<?php

include_once __DIR__ . "/../modelo/Model_Ejecucion.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Controller_Ejecucion
{
    private const LOG_TYPE = "EJECUCION CONTROLLER";
    private const SYS_NAME = "ApiEjecucion";
    private const ROLES_VER = [enum_tipo_operario, enum_tipo_admin_operador, enum_tipo_admin_general];
    private const ROLES_OPERARIO = [enum_tipo_operario];
    private const ROLES_OPERARIO_O_ADMIN = [enum_tipo_operario, enum_tipo_admin_operador];
    private const ROLES_PLANIFICADOR = [enum_tipo_admin_general];

    public static function get_ejecuciones(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_VER);
        $estado = $data[json_estado_ejecucion] ?? null;
        $id_trayecto = null;
        if ($estado !== null && !in_array($estado, sql_ejecucion_estado, true)) {
            return self::error($actor, "InvalidExecutionState", http_unprocessable_entity, "Estado de ejecución inválido");
        }
        if (array_key_exists(json_id_trayecto, $data)) {
            $id_trayecto = filter_var($data[json_id_trayecto], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if ($id_trayecto === false) {
                return self::error($actor, "InvalidTripId", http_unprocessable_entity, "ID de trayecto inválido");
            }
        }
        $rows = Model_Ejecucion::get_ejecuciones($estado, $id_trayecto);
        if ($rows === null) {
            return self::error($actor, "GetExecutionsError", http_internal_error, "Error al listar ejecuciones");
        }
        Model_Log::add_log_user((int) $actor, self::LOG_TYPE, "Listó ejecuciones");
        return Util_HttpResponse::ok(self::code("ExecutionsFetched"), Util_Translator::sql_to_json_keys($rows));
    }

    public static function get_ejecucion(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_VER);
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $row = Model_Ejecucion::get_ejecucion($id);
        if ($row === null) {
            return self::error($actor, "GetExecutionError", http_internal_error, "Error al consultar ejecución {$id}");
        }
        if ($row === []) {
            return self::error($actor, "ExecutionNotFound", http_not_found, "Ejecución {$id} inexistente");
        }
        Model_Log::add_log_user((int) $actor, self::LOG_TYPE, "Consultó ejecución {$id}");
        return Util_HttpResponse::ok(self::code("ExecutionFetched"), [json_ejecucion => Util_Translator::sql_to_json_keys($row)]);
    }

    /** AP crea directamente una ejecución aprobada, tomando el calendario del trayecto. */
    public static function aprobar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_PLANIFICADOR);
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        Util_VerifyData::keys_exists(true, $payload, json_id_trayecto, json_fecha_programada, json_id_camion, json_cedula_operario);
        $id_trayecto = self::positive_int($payload[json_id_trayecto]);
        $id_camion = self::positive_int($payload[json_id_camion]);
        if ($id_trayecto === null || $id_camion === null) {
            return self::error($actor, "InvalidExecutionAssignment", http_unprocessable_entity, "Trayecto y camión deben tener IDs positivos");
        }
        $fecha = $payload[json_fecha_programada];
        if (!self::valid_date($fecha)) {
            return self::error($actor, "InvalidScheduledDate", http_unprocessable_entity, "La fecha programada debe usar formato YYYY-MM-DD");
        }
        $operario = $payload[json_cedula_operario];
        if (!is_string($operario) || trim($operario) === "" || mb_strlen($operario) > 20) {
            return self::error($actor, "InvalidOperatorId", http_unprocessable_entity, "Cédula de operario inválida");
        }
        $notes = $payload[json_notas] ?? null;
        if ($notes !== null && !is_string($notes)) {
            return self::error($actor, "InvalidNotes", http_unprocessable_entity, "Las notas deben ser texto");
        }
        $result = Model_Ejecucion::aprobar($id_trayecto, $fecha, $actor, $id_camion, trim($operario), $notes);
        if (is_int($result)) {
            Model_Log::add_log_user((int) $actor, self::LOG_TYPE, "Aprobó y creó ejecución {$result} para trayecto {$id_trayecto}");
            return Util_HttpResponse::created(self::code("ExecutionApproved"), [json_ejecucion => [json_id => $result, json_estado_ejecucion => enum_ejecucion_aprobada]]);
        }
        return match ($result) {
            "not_found" => self::error($actor, "TripNotFound", http_not_found, "Trayecto inexistente"),
            "assignment_not_found" => self::error($actor, "AssignmentNotFound", http_not_found, "Camión u operario no existe o no está habilitado"),
            "truck_unavailable" => self::error($actor, "TruckUnavailable", http_conflict, "El camión no está disponible"),
            default => self::error($actor, "ApproveExecutionError", http_internal_error, "No se pudo aprobar la ejecución")
        };
    }

    /** O can start only the approved execution assigned to their own account. */
    public static function iniciar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_OPERARIO);
        $id = self::execution_id($data);
        return self::transition($actor, $id, Model_Ejecucion::iniciar($id, $actor), "ExecutionStarted");
    }

    public static function pausar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_OPERARIO_O_ADMIN);
        $id = self::execution_id($data);
        $is_admin = self::role($data) === enum_tipo_admin_operador;
        return self::transition($actor, $id, Model_Ejecucion::pausar($id, $actor, $is_admin), "ExecutionPaused");
    }

    public static function reanudar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_OPERARIO);
        $id = self::execution_id($data);
        return self::transition($actor, $id, Model_Ejecucion::reanudar($id, $actor, false), "ExecutionResumed");
    }

    public static function terminar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_OPERARIO);
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $llenado = null;
        if (array_key_exists(json_llenado_camion, $payload)) {
            $llenado = filter_var($payload[json_llenado_camion], FILTER_VALIDATE_INT, ["options" => ["min_range" => llenado_vacio, "max_range" => llenado_sobrellenado]]);
            if ($llenado === false) {
                return self::error($actor, "InvalidTruckFill", http_unprocessable_entity, "Llenado del camión debe ser un entero de 0 a 11");
            }
        }
        return self::transition($actor, $id, Model_Ejecucion::terminar($id, $actor, $llenado), "ExecutionFinished");
    }

    public static function cancelar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_OPERARIO_O_ADMIN);
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $notes = $payload[json_notas] ?? null;
        if ($notes !== null && !is_string($notes)) {
            return self::error($actor, "InvalidNotes", http_unprocessable_entity, "Las notas deben ser texto");
        }
        $is_admin = self::role($data) === enum_tipo_admin_operador;
        return self::transition($actor, $id, Model_Ejecucion::cancelar($id, $actor, $is_admin, $notes), "ExecutionCancelled");
    }

    /** AP may reject/revoke an approved but not yet started execution. */
    public static function rechazar(array $data): Util_HttpResponse
    {
        $actor = self::authorize($data, self::ROLES_PLANIFICADOR);
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $notes = $payload[json_notas] ?? null;
        if ($notes !== null && !is_string($notes)) {
            return self::error($actor, "InvalidNotes", http_unprocessable_entity, "Las notas deben ser texto");
        }
        return self::transition($actor, $id, Model_Ejecucion::rechazar($id, $notes), "ExecutionRejected");
    }

    private static function execution_id(array $data): int
    {
        $payload = Util_VerifyData::verify_and_get_array($data, json_ejecucion);
        return Util_VerifyData::verify_and_get_int($payload, json_id);
    }

    private static function authorize(array $data, array $roles): string
    {
        Controller_Auth::valid_user_types($data, $roles);
        return (string) Util_VerifyData::verify_and_get_from_token($data, json_ci);
    }

    private static function role(array $data): string
    {
        return (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);
    }

    private static function transition(string $actor, int $id, string $result, string $success): Util_HttpResponse
    {
        if ($result === "ok") {
            Model_Log::add_log_user((int) $actor, self::LOG_TYPE, "{$success}: ejecución {$id}");
            return Util_HttpResponse::ok(self::code($success), [json_ejecucion => [json_id => $id]]);
        }
        return match ($result) {
            "not_found" => self::error($actor, "ExecutionNotFound", http_not_found, "Ejecución {$id} inexistente"),
            "forbidden" => self::error($actor, "ExecutionAssignmentForbidden", http_forbidden, "La ejecución no está asignada a este operario"),
            "invalid_state" => self::error($actor, "InvalidExecutionTransition", http_conflict, "La ejecución no está en un estado válido para esta operación"),
            "route_busy" => self::error($actor, "RouteAlreadyExecuting", http_conflict, "Ya hay una ejecución activa para esta ruta"),
            "conflict" => self::error($actor, "ExecutionStateConflict", http_conflict, "La ejecución o el camión cambió de estado simultáneamente"),
            "truck_unavailable" => self::error($actor, "TruckUnavailable", http_conflict, "El camión no está disponible"),
            default => self::error($actor, "ExecutionTransitionError", http_internal_error, "No se pudo actualizar la ejecución")
        };
    }

    private static function positive_int(mixed $value): ?int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        return $result === false ? null : $result;
    }

    private static function valid_date(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $date = DateTime::createFromFormat("!Y-m-d", $value);
        return $date !== false && $date->format("Y-m-d") === $value;
    }

    private static function error(string $actor, string $name, int $status, string $description): Util_HttpResponse
    {
        Model_Log::add_log_user((int) $actor, self::LOG_TYPE, $description);
        return Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, $name),
            $status,
            [json_error => $description]
        );
    }

    private static function code(string $name): string
    {
        return Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, $name);
    }
}
