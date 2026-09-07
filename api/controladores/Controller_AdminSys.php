<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";

class Controller_AdminSys
{
    private const LOG_TYPE = "ADMIN CONTROLLER";

    public static function get_users_data(array $data): Util_HttpResponse
    {
        $has_user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $user_type = $has_user_type ? $data[json_typeuser] : "";

        $users = Model_User::get_users($user_type);

        if (is_null($users)) {
            return Util_HttpResponse::error(http_bad_request, "Tipo de usuario incorrecto o error en BD");
        }

        $users_map = [];
        foreach ($users as $user) {
            $users_map[$user[sql_cedula]] = $user[sql_tipo];
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Obtiene la lista de usuarios");

        return Util_HttpResponse::ok($users_map);
    }

    public static function get_request_user_data(array $data): Util_HttpResponse
    {
        $has_user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $user_type = $has_user_type ? $data[json_typeuser] : "";

        $requests = Model_User::get_request_users($user_type);

        if (is_null($requests)) {
            return Util_HttpResponse::error(http_internal_error, "Tipo de usuario incorrecto o error al obtener solicitudes");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Obtiene las solicitudes de registro de usuarios");

        return Util_HttpResponse::ok($requests);
    }

    public static function has_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = (int)$user_payload[json_ci];
        $user_type = Util_VerifyData::keys_exists(false, $user_payload, json_typeuser) ? $user_payload[json_typeuser] : "";

        $user_exists = Model_User::has_user($target_ci, $user_type);

        if (is_null($user_exists)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la consulta de usuario");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Verifica si existe el usuario con CI: {$target_ci}");

        return Util_HttpResponse::ok(["exists" => $user_exists]);
    }

    public static function has_request_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = (int)$user_payload[json_ci];
        $user_type = Util_VerifyData::keys_exists(false, $user_payload, json_typeuser) ? $user_payload[json_typeuser] : "";

        $request_exists = Model_User::has_request_user($target_ci, $user_type);

        if (is_null($request_exists)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la consulta de solicitudes");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Verifica si existe la solicitud para el CI: {$target_ci}");

        return Util_HttpResponse::ok(["exists" => $request_exists]);
    }

    public static function get_logs_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);
        
        $target_ci = (int)$user_payload[json_ci];
        $log_category = Util_VerifyData::keys_exists(false, $user_payload, json_typelog) ? $user_payload[json_typelog] : "";

        $logs = Model_Log::get_logs_user($target_ci, $log_category);

        if (is_null($logs)) {
            return Util_HttpResponse::error(http_internal_error, "Error al buscar logs del usuario {$target_ci} {$log_category}");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta el historial de logs del usuario CI: {$target_ci}");

        return Util_HttpResponse::ok($logs);
    }

    public static function get_logs_users(array $data): Util_HttpResponse
    {
        $user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser) ? $data[json_typeuser] : "";
        $log_category = Util_VerifyData::keys_exists(false, $data, json_typelog) ? $data[json_typelog] : "";

        $logs = Model_Log::get_logs_users($user_type, $log_category);

        if (is_null($logs)) {
            return Util_HttpResponse::error(http_internal_error, "Error al buscar logs de usuarios con tipo: '{$user_type}' y categoría: '{$log_category}'");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta logs globales de usuarios filtrados por tipo: '{$user_type}'");

        return Util_HttpResponse::ok($logs);
    }

    public static function get_logs_sql(array $data): Util_HttpResponse
    {
        $sql_logs = Model_Log::get_logs_sql();

        if (is_null($sql_logs)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la base de datos al obtener logs SQL");
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta de peticiones SQL");

        return Util_HttpResponse::ok($sql_logs);
    }

    public static function get_data_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];

        $user_details = Model_User::get_user_data($target_ci);
        if (is_null($user_details)) {
            return Util_HttpResponse::error(http_internal_error, "Error: no se pudo obtener la información del usuario");
        }

        $ci = Util_VerifyData::verify_and_get_from_token($data,json_ci);

        Model_Log::add_log_user($ci,self::LOG_TYPE,"El usuario busca datos del user {$target_ci}");

        return Util_HttpResponse::ok($user_details);
    }

    public static function delete_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];
        
        $is_deleted = Model_User::delete_user($target_ci);
        if ($is_deleted === false) {
            return Util_HttpResponse::error(http_bad_request, "No se pudo eliminar el usuario");
            
        }
         $ci = Util_VerifyData::verify_and_get_from_token($data,json_ci);


        Model_Log::add_log_user($ci,self::LOG_TYPE,"el usuario borra a user {$target_ci}");

        return Util_HttpResponse::ok();
    }

    public static function delete_user_request(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];
        
        $is_deleted = Model_User::delete_user_request($target_ci);
        if ($is_deleted === true) {
            return Util_HttpResponse::error(http_bad_request, "No se pudo eliminar la solicitud de usuario");
        }

        $ci = Util_VerifyData::verify_and_get_from_token($data,json_ci);
        Model_Log::add_log_user($ci,self::LOG_TYPE,"el usuario borra la solicitud de usuario {$target_ci}");
        return Util_HttpResponse::ok();
    }
}