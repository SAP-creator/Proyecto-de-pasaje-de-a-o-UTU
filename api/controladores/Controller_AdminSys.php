<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";

class Controller_AdminSys
{
    private const LOG_TYPE = "ADMIN CONTROLLER";
    private const SYS_NAME = "ApiAdminSys";

    public static function get_users_data(array $data): Util_HttpResponse
    {
        $has_user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $user_type = $has_user_type ? $data[json_typeuser] : "";

        $users = Model_User::get_users($user_type);

        if (is_null($users)) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetUsersError"),
                http_bad_request
            );
        }

        $users_map = [];
        foreach ($users as $user) {
            $translated_ci = Util_Translator::sql_to_json($user[sql_cedula]) ?? $user[sql_cedula];
            $translated_tipo = Util_Translator::sql_to_json($user[sql_tipo]) ?? $user[sql_tipo];
            $users_map[$translated_ci] = $translated_tipo;
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Obtiene lista de usuarios");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UsersFetched"),
            $users_map
        );
    }

    public static function get_request_user_data(array $data): Util_HttpResponse
    {
        $has_user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser);
        $user_type = $has_user_type ? $data[json_typeuser] : "";

        $requests = Model_User::get_request_users($user_type);

        if (is_null($requests)) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRequestsError"),
                http_internal_error
            );
        }

        $translated_requests = [];
        foreach ($requests as $req) {
            $translated_row = [];
            foreach ($req as $sql_col => $value) {
                $json_key = Util_Translator::sql_to_json($sql_col);
                $translated_row[$json_key ?? $sql_col] = $value;
            }
            $translated_requests[] = $translated_row;
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Obtiene solicitudes de registro de usuarios");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RequestsFetched"),
            $translated_requests
        );
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
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "HasUserError"),
                http_internal_error
            );
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Verifica existencia de usuario CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserChecked"),
            [json_exist => $user_exists]
        );
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
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "HasRequestError"),
                http_internal_error
            );
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Verifica existencia de solicitud CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RequestChecked"),
            [json_exist => $request_exists]
        );
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
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetUserLogsError"),
                http_internal_error
            );
        }

        $translated_logs = [];
        foreach ($logs as $log) {
            $translated_row = [];
            foreach ($log as $sql_col => $value) {
                $json_key = Util_Translator::sql_to_json($sql_col);
                $translated_row[$json_key ?? $sql_col] = $value;
            }
            $translated_logs[] = $translated_row;
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta historial de logs CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserLogsFetched"),
            $translated_logs
        );
    }

    public static function get_logs_users(array $data): Util_HttpResponse
    {
        $user_type = Util_VerifyData::keys_exists(false, $data, json_typeuser) ? $data[json_typeuser] : "";
        $log_category = Util_VerifyData::keys_exists(false, $data, json_typelog) ? $data[json_typelog] : "";

        $logs = Model_Log::get_logs_users($user_type, $log_category);

        if (is_null($logs)) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetUsersLogsError"),
                http_internal_error
            );
        }

        $translated_logs = [];
        foreach ($logs as $log) {
            $translated_row = [];
            foreach ($log as $sql_col => $value) {
                $json_key = Util_Translator::sql_to_json($sql_col);
                $translated_row[$json_key ?? $sql_col] = $value;
            }
            $translated_logs[] = $translated_row;
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta logs globales de usuarios filtrados por tipo: '{$user_type}'");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UsersLogsFetched"),
            $translated_logs
        );
    }

    public static function get_logs_sql(array $data): Util_HttpResponse
    {
        $sql_logs = Model_Log::get_logs_sql();

        if (is_null($sql_logs)) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetSqlLogsError"),
                http_internal_error
            );
        }

        $translated_logs = [];
        foreach ($sql_logs as $log) {
            $translated_row = [];
            foreach ($log as $sql_col => $value) {
                $json_key = Util_Translator::sql_to_json($sql_col);
                $translated_row[$json_key ?? $sql_col] = $value;
            }
            $translated_logs[] = $translated_row;
        }

        $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Consulta de peticiones SQL");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "SqlLogsFetched"),
            $translated_logs
        );
    }

    public static function get_data_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];

        $user_details = Model_User::get_user_data($target_ci);
        if (is_null($user_details)) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetUserDataError"),
                http_internal_error
            );
        }

        $translated_details = [];
        foreach ($user_details as $sql_col => $value) {
            $json_key = Util_Translator::sql_to_json($sql_col);
            $translated_details[$json_key ?? $sql_col] = $value;
        }

        $ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta datos del usuario CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserDataFetched"),
            $translated_details
        );
    }

    public static function delete_user(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];
        
        $is_deleted = Model_User::delete_user($target_ci);
        if ($is_deleted === false) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteUserError"),
                http_bad_request
            );
        }
        $ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borra al usuario CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserDeleted")
        );
    }

    public static function delete_user_request(array $data): Util_HttpResponse
    {
        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];
        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $target_ci = $user_payload[json_ci];
        
        $is_deleted = Model_User::delete_user_request($target_ci);
        if ($is_deleted === false) {
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteRequestError"),
                http_bad_request
            );
        }

        $ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borra la solicitud del usuario CI: {$target_ci}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RequestDeleted")
        );
    }
}