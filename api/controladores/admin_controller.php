<?php

include_once __DIR__ . "/../modelo/user_model.php";
include_once __DIR__ . "/../modelo/log_model.php";
include_once __DIR__ . "/../constantes/sql_constantes.php";
include_once __DIR__ . "/../constantes/json_constantes.php";
include_once __DIR__ . "/../utils/resp_http.php";
include_once __DIR__ . "/../controladores/verify_data_controller.php";

class AdminController
{
    private const type_log = "ADMIN CONTROLLER";

    public static function get_users_data(array $data, ?int $ci_admin): HttpResponse
    {
        $has_type = VerifyDataController::keys_exists(false, $data, key_typeuser);
        $type = $has_type ? $data[key_typeuser] : "";

        $all_data_users = UserModel::get_users($type);

        if (is_null($all_data_users)) {
            return HttpResponse::error("Tipo de usuario incorrecto o error en BD", http_bad_request);
        }

        $data_user = [];
        foreach ($all_data_users as $user) {
            $data_user[$user[sql_cedula]] = $user[sql_tipo];
        }

        if ($ci_admin) {
            LogModel::add_log_user($ci_admin, self::type_log, "Obtiene la lista de usuarios");
        }

        return HttpResponse::ok($data_user);
    }

    public static function get_request_user_data(array $data, ?int $ci_admin): HttpResponse
    {
        $has_type = VerifyDataController::keys_exists(false, $data, key_typeuser);
        $type = $has_type ? $data[key_typeuser] : "";

        $requests = UserModel::get_request_users($type);

        if (is_null($requests)) {
            return HttpResponse::error("Tipo de usuario incorrecto o error al obtener solicitudes", http_internal_error);
        }

        if ($ci_admin) 
        {
            LogModel::add_log_user($ci_admin, self::type_log, "Obtiene las solicitudes de registro de usuarios");
        }

        return HttpResponse::ok($requests);
    }

    public static function has_user(array $data, ?int $ci_admin): HttpResponse
    {
        if (!VerifyDataController::keys_exists(true, $data, key_ci)) {
            return HttpResponse::error("Falta el parámetro cédula", http_bad_request);
        }

        $ci = (int)$data[key_ci];
        $type = VerifyDataController::keys_exists(false, $data, key_typeuser) ? $data[key_typeuser] : "";

        $exists = UserModel::has_user($ci, $type);

        if (is_null($exists)) {
            return HttpResponse::error("Error en la consulta de usuario", http_bad_request);
        }

        if ($ci_admin) {
            LogModel::add_log_user($ci_admin, self::type_log, "Verifica si existe el usuario con CI: {$ci}");
        }

        return HttpResponse::ok(["exists" => $exists]);
    }

    public static function has_request_user(array $data, ?int $ci_admin): HttpResponse
    {
        if (!VerifyDataController::keys_exists(true, $data, key_ci)) {
            return HttpResponse::error("Falta el parámetro cédula", http_bad_request);
        }

        $ci = (int)$data[key_ci];
        $type = VerifyDataController::keys_exists(false, $data, key_typeuser) ? $data[key_typeuser] : "";

        $exists = UserModel::has_request_user($ci, $type);

        if (is_null($exists)) {
            return HttpResponse::error("Error en la consulta de solicitudes", http_internal_error);
        }

        if ($ci_admin) {
            LogModel::add_log_user($ci_admin, self::type_log, "Verifica si existe la solicitud para el CI: {$ci}");
        }

        return HttpResponse::ok(["exists" => $exists]);
    }

    public static function get_logs_user(array $data, ?int $ci_admin): HttpResponse
    {
        if (!VerifyDataController::keys_exists(true, $data, key_ci)) {
            return HttpResponse::error("Falta la cédula del usuario a consultar", http_bad_request);
        }
        
        $ci = (int)$data[key_ci];
        $type_log = VerifyDataController::keys_exists(false, $data, key_typelog) ? $data[key_typelog] : "";

        $logs = LogModel::get_logs_user($ci, $type_log);

        if (is_null($logs)) {
            return HttpResponse::error("Error al buscar logs del usuario {$ci} {$type_log}", http_internal_error);
        }

        if ($ci_admin) {
            LogModel::add_log_user($ci_admin, self::type_log, "Consulta el historial de logs del usuario CI: {$ci}");
        }

        return HttpResponse::ok($logs);
    }

    public static function get_logs_users(array $data, ?int $ci_admin): HttpResponse
    {
        $type_user = VerifyDataController::keys_exists(false, $data, key_typeuser) ? $data[key_typeuser] : "";
        $type_log = VerifyDataController::keys_exists(false, $data, key_typelog) ? $data[key_typelog] : "";

        $logs = LogModel::get_logs_users($type_user, $type_log);

        if (is_null($logs)) {
            return HttpResponse::error("Error al buscar logs de usuarios con tipo: '{$type_user}' y categoría: '{$type_log}'", http_internal_error);
        }

        if ($ci_admin) {
            LogModel::add_log_user($ci_admin, self::type_log, "Consulta logs globales de usuarios filtrados por tipo: '{$type_user}'");
        }

        return HttpResponse::ok($logs);
    }

    public static function get_logs_sql(?int $ci_admin):HttpResponse
    {
        $logs = LogModel::get_logs_sql();

        if (is_null($logs)){
            return HttpResponse::error("error en la base de datos",http_internal_error);
        }
        return HttpResponse::ok($logs);
    }
}