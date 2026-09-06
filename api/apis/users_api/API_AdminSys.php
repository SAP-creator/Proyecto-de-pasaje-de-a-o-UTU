<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/../../utils/Util_RestHttp.php";
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
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = str_replace(path_api_sysadmin, '', $path);


// Para métodos GET/DELETE los parámetros suelen venir por $_GET o querystring, pero si vienen por JSON se leen aquí
$input_raw = file_get_contents("php://input");
$data = json_decode($input_raw, true) ?? [];

if (!is_array($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
    Util_HttpResponse::error(http_bad_request, "Estructura JSON inválida")->send();
    exit();
}

// Mezclar con los datos pasados por URL (?ci=12345678) si existen
if (!empty($_GET)) {
    $data = array_merge($data, $_GET);
}

Controller_Auth::valid_user_type($data, enum_tipo_admin_sistema);
Controller_UserSetup::user_is_complete($data);

$path = str_replace(path_api_sysadmin,"",$path);


http_options($method, $path, $data);

function http_options(string $method, string $route, array $data) { 
    $res = match ($method) {
        "GET"    => get_options($route, $data),
        "POST"   => post_options($route, $data),
        "PUT"    => put_options($route, $data),
        "DELETE" => delete_options($route, $data),
        default  => Util_HttpResponse::error(http_bad_request, "Método {$method} no soportado en SysAdmin admin sys")
    };
    
    $res->send();
}

function get_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/users": // Obtiene lista de usuarios
            return Controller_AdminSys::get_users_data($data);

        case "/user/exists": // Verifica si un usuario existe
            return Controller_AdminSys::has_user($data);

        case "/user/data": // Obtiene los datos completos de un usuario
            return Controller_AdminSys::get_data_user($data);

        case "/requests": // Obtiene lista de solicitudes pendientes
            return Controller_AdminSys::get_request_user_data($data);

        case "/requests/exists": // Verifica si existe una solicitud por CI
            return Controller_AdminSys::has_request_user($data);

        case "/logs/user": // Logs de un usuario individual por su CI
            return Controller_AdminSys::get_logs_user($data);

        case "/logs/users": // Logs filtrados por tipo de usuario/log
            return Controller_AdminSys::get_logs_users($data);
        
        case "/logs/sql": // Obtiene todos los logs de SQL
            return Controller_AdminSys::get_logs_sql($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en GET admin sys");
    }
}

function post_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/requests/accept": // Aceptar solicitud de usuario
            Controller_VerifyData::keys_exists(true, $data, json_user); 
            return Controller_Sign::accept_sign_up($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en POST admin sys");
    }
}

function put_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/user/data": // Modificar datos del usuario (reemplaza /user/data/change)
            return Controller_UserChangeData::user_change_data_by_admin($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en PUT");
    }
}

function delete_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/user": // Borrar usuario (reemplaza /user/delete)
            return Controller_AdminSys::delete_user($data);

        case "/requests": // Borrar solicitud de usuario (reemplaza /user/request/delente)
            return Controller_AdminSys::delete_user_request($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en DELETE admin sys");
    }
}