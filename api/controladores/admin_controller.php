<?php

include_once __DIR__ . "/../modelo/user_model.php";
include_once __DIR__ . "/../constantes/sql_constantes.php";
include_once __DIR__ . "/../constantes/json_constantes.php";

include_once __DIR__ . "/../utils/resp_http.php";

include_once __DIR__ . "/../controladores/verify_data_controller.php";


class AdminController
{
    public static function get_users_data(array $data): HttpResponse
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

        return HttpResponse::ok($data_user);
    }

    public static function get_request_user_data(array $data): HttpResponse
    {
        $has_type = VerifyDataController::keys_exists(false, $data, key_typeuser);
        $type = $has_type ? $data[key_typeuser] : "";

        $requests = UserModel::get_request_users($type);

        if (is_null($requests)) {
            return HttpResponse::error("Tipo de usuario incorrecto o error al obtener solicitudes", http_internal_error);
        }

        return HttpResponse::ok($requests);
    }

    public static function has_user(array $data): HttpResponse
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

        return HttpResponse::ok(["exists" => $exists]);
    }

    public static function has_request_user(array $data): HttpResponse
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

        return HttpResponse::ok(["exists" => $exists]);
    }
}