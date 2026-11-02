<?php

include_once __DIR__ . "/../modelo/Model_Camion.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Controller_Camion
{
    private const LOG_TYPE = "CAMION CONTROLLER";
    private const SYS_NAME = "ApiAdminOperador";
    private const ROLES_VER = [enum_tipo_vecino, enum_tipo_operario, enum_tipo_admin_operador, enum_tipo_admin_general, enum_tipo_admin_sistema];
    private const ROLES_GESTION = [enum_tipo_admin_operador];
    private const MATRICULA_MAX = 20;

    public static function get_camiones(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_VER);
        $camiones = Model_Camion::get_camiones();
        if ($camiones === null) {
            return self::error($ci, "GetCamionesError", http_internal_error, "Error al listar camiones");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó camiones");
        return Util_HttpResponse::ok(self::code("CamionesFetched"), Util_Translator::sql_to_json_keys($camiones));
    }

    public static function get_camion(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_VER);
        $payload = Util_VerifyData::verify_and_get_array($data, json_camion);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $camion = Model_Camion::get_camion($id);
        if ($camion === null) {
            return self::error($ci, "GetCamionError", http_internal_error, "Error al consultar camion {$id}");
        }
        if ($camion === []) {
            return self::error($ci, "CamionNotFound", http_not_found, "Camion {$id} inexistente");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó camion {$id}");
        return Util_HttpResponse::ok(self::code("CamionFetched"), [json_camion => Util_Translator::sql_to_json_keys($camion)]);
    }

    public static function create_camion(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $camion = Util_VerifyData::verify_and_get_array($data, json_camion);
        Util_VerifyData::keys_exists(true, $camion, json_matricula, json_cantidad, json_tipo_basuras);
        $error = self::validate_fields($camion, $ci, true);
        if ($error !== null) {
            return $error;
        }
        $id = Model_Camion::create_camion(trim($camion[json_matricula]), (float) $camion[json_cantidad], $camion[json_estado_camion] ?? enum_camion_disponible, $camion[json_tipo_basuras]);
        if ($id === null) {
            return self::error($ci, "CreateCamionError", http_internal_error, "Error al crear camion");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó camion {$id}");
        return Util_HttpResponse::created(self::code("CamionCreated"), [json_camion => [json_id => $id]]);
    }

    public static function update_camion(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $camion = Util_VerifyData::verify_and_get_array($data, json_camion);
        $id = Util_VerifyData::verify_and_get_int($camion, json_id);
        $error = self::validate_fields($camion, $ci, false);
        if ($error !== null) {
            return $error;
        }
        if (!array_intersect([json_matricula, json_cantidad, json_estado_camion, json_tipo_basuras], array_keys($camion))) {
            return self::error($ci, "EmptyCamionUpdate", http_bad_request, "No hay campos para actualizar");
        }
        $result = Model_Camion::update_camion(
            $id,
            $camion[json_matricula] ?? null,
            isset($camion[json_cantidad]) ? (float) $camion[json_cantidad] : null,
            $camion[json_estado_camion] ?? null,
            $camion[json_tipo_basuras] ?? null
        );
        if ($result === null) {
            return self::error($ci, "UpdateCamionError", http_internal_error, "Error al actualizar camion {$id}");
        }
        if (!$result) {
            $current = Model_Camion::get_camion($id);
            if ($current === null) {
                return self::error($ci, "UpdateCamionError", http_internal_error, "Error al verificar camion {$id}");
            }
            if ($current === []) {
                return self::error($ci, "CamionNotFound", http_not_found, "Camion {$id} inexistente");
            }
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó camion {$id}");
        return Util_HttpResponse::ok(self::code("CamionUpdated"), [json_camion => [json_id => $id]]);
    }

    public static function delete_camion(array $data): Util_HttpResponse
    {
        $ci = self::authorize($data, self::ROLES_GESTION);
        $camion = Util_VerifyData::verify_and_get_array($data, json_camion);
        $id = Util_VerifyData::verify_and_get_int($camion, json_id);
        $has_executions = Model_Camion::has_executions($id);
        if ($has_executions === null) {
            return self::error($ci, "CheckCamionExecutionsError", http_internal_error, "Error al verificar ejecuciones del camion {$id}");
        }
        if ($has_executions) {
            return self::error($ci, "CamionHasExecutions", http_conflict, "No se puede eliminar un camion asociado a ejecuciones");
        }
        $result = Model_Camion::delete_camion($id);
        if ($result === null) {
            return self::error($ci, "DeleteCamionError", http_internal_error, "Error al eliminar camion {$id}");
        }
        if (!$result) {
            return self::error($ci, "CamionNotFound", http_not_found, "Camion {$id} inexistente");
        }
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Eliminó camion {$id}");
        return Util_HttpResponse::ok(self::code("CamionDeleted"));
    }

    private static function validate_fields(array $camion, int $ci, bool $creating): ?Util_HttpResponse
    {
        foreach ([json_matricula, json_cantidad, json_tipo_basuras] as $key) {
            if ($creating || array_key_exists($key, $camion)) {
                if (!array_key_exists($key, $camion)) {
                    return self::error($ci, "MissingCamionField", http_unprocessable_entity, "Falta el campo requerido {$key}");
                }
            }
        }
        if (array_key_exists(json_matricula, $camion) && (!is_string($camion[json_matricula]) || trim($camion[json_matricula]) === "" || mb_strlen(trim($camion[json_matricula])) > self::MATRICULA_MAX)) {
            return self::error($ci, "InvalidMatricula", http_unprocessable_entity, "Matrícula inválida");
        }
        if (array_key_exists(json_cantidad, $camion) && (!is_numeric($camion[json_cantidad]) || !is_finite((float) $camion[json_cantidad]) || (float) $camion[json_cantidad] <= 0 || (float) $camion[json_cantidad] > 99999999.99)) {
            return self::error($ci, "InvalidCantidad", http_unprocessable_entity, "Cantidad debe ser un decimal positivo válido");
        }
        if (array_key_exists(json_estado_camion, $camion) && !in_array($camion[json_estado_camion], sql_camion_estado, true)) {
            return self::error($ci, "InvalidCamionEstado", http_unprocessable_entity, "Estado de camion inválido");
        }
        if (array_key_exists(json_tipo_basuras, $camion) && !Util_VerifyData::valid_enum_list($camion[json_tipo_basuras], sql_tipo_basura)) {
            return self::error($ci, "InvalidTipoBasuras", http_unprocessable_entity, "Tipos de basura inválidos");
        }
        return null;
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
