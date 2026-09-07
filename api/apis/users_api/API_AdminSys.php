<?php
#Api de admin de sistema.
#casi 100% humana, quitando algunos comentarios, y los nombres de variables... no soy bueno poniendo nombre de variables como vera en los controladores

include_once __DIR__ . "/../../utils/Util_RestHttp.php";
include_once __DIR__ . "/../../utils/Util_VerifyData.php";
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

// Lectura del payload en formato JSON para todos los métodos (incluyendo GET y DELETE según especificación)
$input_raw = file_get_contents("php://input");
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
        default  => Util_HttpResponse::error(http_bad_request, "Método {$method} no soportado en SysAdmin admin sys")
    };
    
    $res->send();
}

function get_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        //Consigue todos los usuarios existentes
        case "/users":           return Controller_AdminSys::get_users_data($data);

        //Existe un usuario
        case "/user/exists":     return Controller_AdminSys::has_user($data);

        //Consigue los datos (exepto clave) de un usuario
        case "/user/data":       return Controller_AdminSys::get_data_user($data);

        //Consigue todas las solicitudes de usuarios
        case "/requests":        return Controller_AdminSys::get_request_user_data($data);

        //existe esta request de usuario
        case "/requests/exists": return Controller_AdminSys::has_request_user($data);

        //consigue los logs de un user
        case "/logs/user":       return Controller_AdminSys::get_logs_user($data);

        //consigue los logs de todos los user (o todos los de un tipo)
        case "/logs/users":      return Controller_AdminSys::get_logs_users($data);
        
        //consigue los logs de sql.
        case "/logs/sql":        return Controller_AdminSys::get_logs_sql($data);

        //errror generico
        default:                 return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en GET admin sys");
    }
}

function post_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        //acepta la solicitud y crea un usuario
        case "/requests/accept": return Controller_Sign::accept_sign_up($data);

        default:                 return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en POST admin sys");
    }
}

function put_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        //modifica todos los datso (exepto cedula y clave) de un user
        case "/user/data":       return Controller_UserChangeData::user_change_data_by_admin($data);

        //error default
        default:                 return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en PUT admin sys");
    }
}

function delete_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        //elimina a un usuario
        case "/user":            return Controller_AdminSys::delete_user($data);

        //elimna una solicutud de usuario
        case "/requests":        return Controller_AdminSys::delete_user_request($data);

        //error generico
        default:                 return Util_HttpResponse::error(http_not_found, "Ruta '{$route}' no encontrada en DELETE admin sys");
    }
}