<?php

include_once __DIR__ . "/../modelo/Model_Trayecto.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Controller_Trayecto
{
    private const LOG_TYPE = "TRAYECTO CONTROLLER";
    private const SYS_NAME = "ApiAdminOperador";
    private const ROLES_VER = [enum_tipo_vecino, enum_tipo_operario, enum_tipo_admin_operador, enum_tipo_admin_general, enum_tipo_admin_sistema];
    private const ROLES_GESTION = [enum_tipo_admin_operador];

    public static function get_trayectos(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_VER);
        $trayectos = Model_Trayecto::get_trayectos();
        if ($trayectos === null) {
            return self::error($ci, "GetTrayectosError", http_internal_error, "Error al listar trayectos");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó trayectos");
        return Util_HttpResponse::ok(self::code("TrayectosFetched"), Util_Translator::sql_to_json_keys($trayectos));
    }

    public static function get_trayecto(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_VER);
        $payload = Util_VerifyData::verify_and_get_array($data, json_trayecto);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $trayecto = Model_Trayecto::get_trayecto($id);
        if ($trayecto === null) {
            return self::error($ci, "GetTrayectoError", http_internal_error, "Error al consultar trayecto {$id}");
        }
        if ($trayecto === []) {
            return self::error($ci, "TrayectoNotFound", http_not_found, "Trayecto {$id} inexistente");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó trayecto {$id}");
        return Util_HttpResponse::ok(self::code("TrayectoFetched"), [json_trayecto => Util_Translator::sql_to_json_keys($trayecto)]);
    }

    public static function create_trayecto(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $trayecto = Util_VerifyData::verify_and_get_array($data, json_trayecto);
        Util_VerifyData::keys_exists(true, $trayecto, json_id_ruta, json_hora);
        $error = self::validate_fields($trayecto, $ci, true);
        if ($error !== null) {
            return $error;
        }
        $id_ruta = (int) $trayecto[json_id_ruta];
        $error = self::validate_route($id_ruta, $ci);
        if ($error !== null) {
            return $error;
        }
        $id_camion = $trayecto[json_id_camion_auto] ?? null;
        if ($id_camion !== null) {
            $error = self::validate_truck((int) $id_camion, $ci);
            if ($error !== null) {
                return $error;
            }
        }
        $operario = $trayecto[json_cedula_operario_auto] ?? null;
        if ($operario !== null) {
            $error = self::validate_driver($operario, $ci);
            if ($error !== null) {
                return $error;
            }
        }
        $id = Model_Trayecto::create_trayecto(
            $id_ruta,
            $trayecto[json_dia_semana] ?? null,
            $trayecto[json_hora],
            $trayecto[json_fecha_especial] ?? null,
            (bool) ($trayecto[json_inicio_automatico] ?? false),
            $id_camion,
            $operario
        );
        if ($id === null) {
            return self::error($ci, "CreateTrayectoError", http_internal_error, "Error al crear trayecto");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó trayecto {$id}");
        return Util_HttpResponse::created(self::code("TrayectoCreated"), [json_trayecto => [json_id => $id]]);
    }

    public static function update_trayecto(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $trayecto = Util_VerifyData::verify_and_get_array($data, json_trayecto);
        $id = Util_VerifyData::verify_and_get_int($trayecto, json_id);
        $error = self::validate_fields($trayecto, $ci, false);
        if ($error !== null) {
            return $error;
        }
        $allowed = [json_id_ruta, json_dia_semana, json_hora, json_fecha_especial, json_inicio_automatico, json_id_camion_auto, json_cedula_operario_auto];
        if (!array_intersect($allowed, array_keys($trayecto))) {
            return self::error($ci, "EmptyTrayectoUpdate", http_bad_request, "No hay campos para actualizar");
        }
        if (array_key_exists(json_id_ruta, $trayecto)) {
            $error = self::validate_route((int) $trayecto[json_id_ruta], $ci);
            if ($error !== null) {
                return $error;
            }
        }
        if (array_key_exists(json_id_camion_auto, $trayecto) && $trayecto[json_id_camion_auto] !== null) {
            $error = self::validate_truck((int) $trayecto[json_id_camion_auto], $ci);
            if ($error !== null) {
                return $error;
            }
        }
        if (array_key_exists(json_cedula_operario_auto, $trayecto) && $trayecto[json_cedula_operario_auto] !== null) {
            $error = self::validate_driver($trayecto[json_cedula_operario_auto], $ci);
            if ($error !== null) {
                return $error;
            }
        }
        $result = Model_Trayecto::update_trayecto(
            $id,
            isset($trayecto[json_id_ruta]) ? (int) $trayecto[json_id_ruta] : null,
            $trayecto[json_dia_semana] ?? null,
            array_key_exists(json_dia_semana, $trayecto),
            $trayecto[json_hora] ?? null,
            array_key_exists(json_fecha_especial, $trayecto),
            $trayecto[json_fecha_especial] ?? null,
            array_key_exists(json_inicio_automatico, $trayecto) ? (bool) $trayecto[json_inicio_automatico] : null,
            array_key_exists(json_id_camion_auto, $trayecto),
            $trayecto[json_id_camion_auto] ?? null,
            array_key_exists(json_cedula_operario_auto, $trayecto),
            $trayecto[json_cedula_operario_auto] ?? null
        );
        if ($result === null) {
            return self::error($ci, "UpdateTrayectoError", http_internal_error, "Error al actualizar trayecto {$id}");
        }
        if (!$result) {
            $current = Model_Trayecto::get_trayecto($id);
            if ($current === null) {
                return self::error($ci, "UpdateTrayectoError", http_internal_error, "Error al verificar trayecto {$id}");
            }
            if ($current === []) {
                return self::error($ci, "TrayectoNotFound", http_not_found, "Trayecto {$id} inexistente");
            }
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó trayecto {$id}");
        return Util_HttpResponse::ok(self::code("TrayectoUpdated"), [json_trayecto => [json_id => $id]]);
    }

    public static function delete_trayecto(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $trayecto = Util_VerifyData::verify_and_get_array($data, json_trayecto);
        $id = Util_VerifyData::verify_and_get_int($trayecto, json_id);
        $has_executions = Model_Trayecto::has_executions($id);
        if ($has_executions === null) {
            return self::error($ci, "CheckTrayectoExecutionsError", http_internal_error, "Error al verificar ejecuciones del trayecto {$id}");
        }
        if ($has_executions) {
            return self::error($ci, "TrayectoHasExecutions", http_conflict, "No se puede eliminar un trayecto asociado a ejecuciones");
        }
        $result = Model_Trayecto::delete_trayecto($id);
        if ($result === null) {
            return self::error($ci, "DeleteTrayectoError", http_internal_error, "Error al eliminar trayecto {$id}");
        }
        if (!$result) {
            return self::error($ci, "TrayectoNotFound", http_not_found, "Trayecto {$id} inexistente");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Eliminó trayecto {$id}");
        return Util_HttpResponse::ok(self::code("TrayectoDeleted"));
    }

    private static function validate_fields(array $trayecto, int $ci, bool $creating): ?Util_HttpResponse
    {
        foreach ([json_id_ruta, json_hora] as $key) {
            if (($creating || array_key_exists($key, $trayecto)) && !array_key_exists($key, $trayecto)) {
                return self::error($ci, "MissingTrayectoField", http_unprocessable_entity, "Falta el campo requerido {$key}");
            }
        }
        if (array_key_exists(json_id_ruta, $trayecto) && (!is_int($trayecto[json_id_ruta]) && !(is_string($trayecto[json_id_ruta]) && ctype_digit($trayecto[json_id_ruta])) || (int) $trayecto[json_id_ruta] < 1)) {
            return self::error($ci, "InvalidRutaId", http_unprocessable_entity, "ID de ruta inválido");
        }
        if (array_key_exists(json_dia_semana, $trayecto) && $trayecto[json_dia_semana] !== null && !in_array($trayecto[json_dia_semana], sql_dia_semana, true)) {
            return self::error($ci, "InvalidTrayectoDay", http_unprocessable_entity, "Día de semana inválido");
        }
        if (array_key_exists(json_hora, $trayecto) && !self::valid_time($trayecto[json_hora])) {
            return self::error($ci, "InvalidTrayectoTime", http_unprocessable_entity, "Hora debe tener formato HH:MM:SS");
        }
        if (array_key_exists(json_fecha_especial, $trayecto) && $trayecto[json_fecha_especial] !== null && !self::valid_date_only($trayecto[json_fecha_especial])) {
            return self::error($ci, "InvalidSpecialDate", http_unprocessable_entity, "Fecha especial debe tener formato YYYY-MM-DD");
        }
        if (array_key_exists(json_inicio_automatico, $trayecto) && !is_bool($trayecto[json_inicio_automatico]) && !in_array($trayecto[json_inicio_automatico], [0, 1, "0", "1"], true)) {
            return self::error($ci, "InvalidAutomaticStart", http_unprocessable_entity, "Inicio automático debe ser booleano");
        }
        if (array_key_exists(json_id_camion_auto, $trayecto) && $trayecto[json_id_camion_auto] !== null && (!is_int($trayecto[json_id_camion_auto]) && !(is_string($trayecto[json_id_camion_auto]) && ctype_digit($trayecto[json_id_camion_auto])) || (int) $trayecto[json_id_camion_auto] < 1)) {
            return self::error($ci, "InvalidTruckId", http_unprocessable_entity, "ID de camión inválido");
        }
        if (array_key_exists(json_cedula_operario_auto, $trayecto) && $trayecto[json_cedula_operario_auto] !== null && (!is_string($trayecto[json_cedula_operario_auto]) || trim($trayecto[json_cedula_operario_auto]) === "" || mb_strlen($trayecto[json_cedula_operario_auto]) > 20)) {
            return self::error($ci, "InvalidOperatorId", http_unprocessable_entity, "Cédula de operario inválida");
        }
        return null;
    }

    private static function validate_route(int $id, int $ci): ?Util_HttpResponse
    {
        $exists = Model_Trayecto::route_exists($id);
        if ($exists === null) {
            return self::error($ci, "CheckRutaError", http_internal_error, "Error al validar ruta {$id}");
        }
        return $exists ? null : self::error($ci, "RutaNotFound", http_not_found, "Ruta {$id} inexistente");
    }

    private static function validate_driver(string $cedula, int $ci): ?Util_HttpResponse
    {
        $exists = Model_Trayecto::driver_exists($cedula);
        if ($exists === null) {
            return self::error($ci, "CheckCamioneroError", http_internal_error, "Error al validar camionero");
        }
        return $exists ? null : self::error($ci, "OperatorNotFound", http_not_found, "Operario de camión inexistente");
    }

    private static function validate_truck(int $id, int $ci): ?Util_HttpResponse
    {
        $exists = Model_Trayecto::truck_exists($id);
        if ($exists === null) {
            return self::error($ci, "CheckCamionError", http_internal_error, "Error al validar camión {$id}");
        }
        return $exists ? null : self::error($ci, "CamionNotFound", http_not_found, "Camión {$id} inexistente");
    }

    private static function valid_time(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $parsed = DateTime::createFromFormat("H:i:s", $value);
        return $parsed !== false && $parsed->format("H:i:s") === $value;
    }

    private static function valid_date_only(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $parsed = DateTime::createFromFormat("Y-m-d", $value);
        return $parsed !== false && $parsed->format("Y-m-d") === $value;
    }

    private static function authorize(array $data, array $roles): int
    {
        Controller_Auth::valid_user_types($data, $roles);
        return (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
    }

    private static function error(int $ci, string $name, int $status, string $description): Util_HttpResponse
    {
        Model_Log::add_log_user($ci, self::LOG_TYPE, $description);
        $error_code = Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, $name);
        return Util_HttpResponse::error($error_code, $status, [json_error => $description]);
    }

    private static function code(string $name): string
    {
        return Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, $name);
    }
}
