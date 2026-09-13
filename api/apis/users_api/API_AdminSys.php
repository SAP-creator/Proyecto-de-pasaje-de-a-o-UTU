<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);




# Api de admin de sistema.

include_once __DIR__ . "/../../utils/Util_RestHttp.php";
include_once __DIR__ . "/../../utils/Util_VerifyData.php";
include_once __DIR__ . "/../../utils/Util_Code.php";

include_once __DIR__ . "/../../constantes/Const_Json.php";
include_once __DIR__ . "/../../constantes/Const_Path.php";

include_once __DIR__ . "/../../controladores/Controller_UserSetup.php";
include_once __DIR__ . "/../../controladores/Controller_Auth.php";

include_once __DIR__ . "/../../controladores/Controller_Sign.php";
include_once __DIR__ . "/../../controladores/Controller_AdminSys.php";

include_once __DIR__ . "/../../controladores/Controller_UserChangeData.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];



if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Sanitización única del prefijo del path
$path = str_replace(path_api_sysadmin, '', $path);


if ($method === 'GET' || $method === 'DELETE') {
    // Para GET, viene por el query string ?payload=...
    $input_raw = $_GET['payload'] ?? '';
} else {
    // Para POST/PUT, viene por el body de la petición
    $input_raw = file_get_contents('php://input');
}

$data = (array) Util_VerifyData::valid_json($input_raw);

// Validación global de autenticación para SysAdmin
Controller_Auth::valid_user_type($data, enum_tipo_admin_sistema);
Controller_UserSetup::user_is_complete($data);

http_options($method, $path, $data);

function http_options(string $method, string $route, array $data) { 
    $res = match ($method) {
        "GET"    => get_options($route, $data),
        "POST"   => post_options($route, $data),
        "PUT"    => put_options($route, $data),
        "DELETE" => delete_options($route, $data),
        default  => Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "ApiAdminSys", "MethodNotAllowed"),
            http_bad_request,
            "Método {$method} no soportado en SysAdmin admin sys"
        )
    };
    
    $res->send();
}

function get_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        // Consigue todos los usuarios existentes
        case "/users":           return Controller_AdminSys::get_users_data($data);

        // Existe un usuario
        case "/user/exists":     return Controller_AdminSys::has_user($data);

        // Consigue los datos (excepto clave) de un usuario
        case "/user/data":       return Controller_AdminSys::get_data_user($data);

        // Consigue todas las solicitudes de usuarios
        case "/requests":        return Controller_AdminSys::get_request_user_data($data);

        // Existe esta request de usuario
        case "/requests/exists": return Controller_AdminSys::has_request_user($data);

        // Consigue los logs de un user
        case "/logs/user":       return Controller_AdminSys::get_logs_user($data);

        // Consigue los logs de todos los user (o todos los de un tipo)
        case "/logs/users":      return Controller_AdminSys::get_logs_users($data);
        
        // Consigue los logs de sql
        case "/logs/sql":        return Controller_AdminSys::get_logs_sql($data);

        // Error genérico
        default: return Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "ApiAdminSys", "PathNotFound"),
            http_not_found
        );
    }
}

function post_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        // Acepta la solicitud y crea un usuario
        case "/requests/accept": return Controller_Sign::accept_sign_up($data);

        default: return Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "ApiAdminSys", "PathNotFound"),
            http_not_found
        );
    }
}

function put_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        // Modifica todos los datos (excepto cédula y clave) de un user
        case "/user/data":       return Controller_UserChangeData::user_change_data_by_admin($data);

        // Error default
        default: return Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "ApiAdminSys", "PathNotFound"),
            http_not_found
        );
    }
}

function delete_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        // Elimina a un usuario
        case "/user":            return Controller_AdminSys::delete_user($data);

        // Elimina una solicitud de usuario
        case "/requests":        return Controller_AdminSys::delete_user_request($data);

        // Error genérico
        default: return Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, "ApiAdminSys", "PathNotFound"),
            http_not_found
        );
    }
}