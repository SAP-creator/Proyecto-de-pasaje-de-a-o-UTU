<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";

class Controller_AdminSys
{
    private const type_log = "ADMIN CONTROLLER";

    public static function get_users_data(array $data): Util_HttpResponse
    {
        $has_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $type = $has_type ? $data[json_typeuser] : "";

        $all_data_users = Model_User::get_users($type);

        if (is_null($all_data_users)) {
            return Util_HttpResponse::error(http_bad_request, "Tipo de usuario incorrecto o error en BD");
        }

        $data_user = [];
        foreach ($all_data_users as $user) {
            $data_user[$user[sql_cedula]] = $user[sql_tipo];
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Obtiene la lista de usuarios");

        return Util_HttpResponse::ok($data_user);
    }

    public static function get_request_user_data(array $data): Util_HttpResponse
    {
        $has_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $type = $has_type ? $data[json_typeuser] : "";

        $requests = Model_User::get_request_users($type);

        if (is_null($requests)) 
            return Util_HttpResponse::error(http_internal_error, "Tipo de usuario incorrecto o error al obtener solicitudes");

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Obtiene las solicitudes de registro de usuarios");

        return Util_HttpResponse::ok($requests);
    }

    public static function has_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user = $data[json_user];
        Util_VerifyData::keys_exists(true, $user, json_ci);

        $ci = (int)$user[json_ci];
        $type = Util_VerifyData::keys_exists(false, $user, json_typeuser) ? $user[json_typeuser] : "";

        $exists = Model_User::has_user($ci, $type);

        if (is_null($exists)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la consulta de usuario");
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Verifica si existe el usuario con CI: {$ci}");

        return Util_HttpResponse::ok(["EXISTS" => $exists]);
    }

    public static function has_request_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user = $data[json_user];
        Util_VerifyData::keys_exists(true, $user, json_ci);

        $ci = (int)$user[json_ci];
        $type = Util_VerifyData::keys_exists(false, $user, json_typeuser) ? $user[json_typeuser] : "";

        $exists = Model_User::has_request_user($ci, $type);

        if (is_null($exists)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la consulta de solicitudes");
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Verifica si existe la solicitud para el CI: {$ci}");

        return Util_HttpResponse::ok(["exists" => $exists]);
    }

    public static function get_logs_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user = $data[json_user];
        Util_VerifyData::keys_exists(true, $user, json_ci);
        
        $ci = (int)$data[json_user][json_ci];
        $type_log = Util_VerifyData::keys_exists(false, $user, json_typelog) ? $user[json_typelog] : "";

        $logs = Model_Log::get_logs_user($ci, $type_log);

        if (is_null($logs)) {
            return Util_HttpResponse::error(http_internal_error, "Error al buscar logs del usuario {$ci} {$type_log}");
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Consulta el historial de logs del usuario CI: {$ci}");

        return Util_HttpResponse::ok($logs);
    }

    public static function get_logs_users(array $data): Util_HttpResponse
    {
        $type_user = Util_VerifyData::keys_exists(false, $data, json_typeuser) ? $data[json_typeuser] : "";
        $type_log = Util_VerifyData::keys_exists(false, $data, json_typelog) ? $data[json_typelog] : "";

        $logs = Model_Log::get_logs_users($type_user, $type_log);

        if (is_null($logs)) {
            return Util_HttpResponse::error(http_internal_error, "Error al buscar logs de usuarios con tipo: '{$type_user}' y categoría: '{$type_log}'");
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Consulta logs globales de usuarios filtrados por tipo: '{$type_user}'");

        return Util_HttpResponse::ok($logs);
    }

    public static function get_logs_sql(array $data): Util_HttpResponse
    {
        $logs = Model_Log::get_logs_sql();

        if (is_null($logs)){
            return Util_HttpResponse::error(http_internal_error, "error en la base de datos");
        }

        $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci_admin, self::type_log, "Consulta de peticiones sql");

        return Util_HttpResponse::ok($logs);
    }

    public static function get_data_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        Util_VerifyData::keys_exists(true, $data[json_user], json_ci);

        $user_data = Model_User::get_user_data($data[json_user][json_ci]);
        if (is_null($user_data)){
            return Util_HttpResponse::error(http_internal_error, "Error no se consiguo al user");
        }

        return Util_HttpResponse::ok($user_data);
    }

    public static function delete_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        Util_VerifyData::keys_exists(true, $data[json_user], json_ci);
        $ci = $data[json_user][json_ci];
        
        $a = Model_User::delete_user($ci);
        if ($a == true){
            return Util_HttpResponse::ok();
        }
        return Util_HttpResponse::error(http_bad_request);
    }

    public static function delete_user_request(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        Util_VerifyData::keys_exists(true, $data[json_user], json_ci);
        $ci = $data[json_user][json_ci];
        
        $a = Model_User::delete_user_request($ci);
    var_dump ($a);
        if ($a == true){
            return Util_HttpResponse::ok();
        }
        return Util_HttpResponse::error(http_bad_request);
    }
}