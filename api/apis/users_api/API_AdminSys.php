<?php
include_once __DIR__ . "/../../utils/Util_RestHttp.php";
include_once __DIR__ . "/../../constantes/Const_Json.php";
include_once __DIR__ . "/../../constantes/Const_Paths.php";
include_once __DIR__ . "/../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../../controladores/Controller_Auth.php";
include_once __DIR__ . "/../../controladores/Controller_Sign.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = str_replace(path_api_sysadmin, '', $path);
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) && $method === 'POST') {
    Util_HttpResponse::error(http_bad_request, "Estructura JSON inválida")->send();
    exit();
}

// Valida que el token pertenezca al tipo de usuario autorizado
Controller_Auth::valid_user_type($data, enum_tipo_admin_sistema);
Controller_UserSetup::user_is_complete($data);


http_options($method, $path, $data ?? []);

function http_options(string $method, string $route, ?array $data) { 
    
    
    // Se obtiene el CI del admin autenticado desde la firma/token
    $ci_admin = Controller_Auth::get_ci_from_token($data);

    switch ($method) {
        case "POST":
            $res = post_options($route, $data);
            break;
        case "GET":
            $res = get_options($route, $data, $ci_admin);
            break;
        default:
            $res = Util_HttpResponse::error("Método {$method} no soportado en SysAdmin", http_bad_request);
            break;
    }
    $res->send();
}

function post_options(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/requests/accept":
            Controller_VerifyData::keys_exists(true, $data, json_user); 
            return Controller_Sign::accept_sign_up($data);

        default:
            return Util_HttpResponse::error("Ruta \"{$route}\" no encontrada en POST", http_not_found);
    }
}

function get_options(string $route, array $data, ?int $ci_admin): Util_HttpResponse {
    include_once __DIR__ . "/../../controladores/Controller_AdminSys.php";

    switch ($route) {
        case "/users": // Obtiene lista de usuarios
            return Controller_AdminSys::get_users_data($data, $ci_admin);

        case "/requests": // Obtiene lista de solicitudes pendientes
            return Controller_AdminSys::get_request_user_data($data, $ci_admin);

        case "/users/exists": // Verifica si un usuario existe
            return Controller_AdminSys::has_user($data, $ci_admin);

        case "/requests/exists": // Verifica si existe una solicitud de CI
            return Controller_AdminSys::has_request_user($data, $ci_admin);

        case "/logs/user": // Logs de un usuario individual por su CI
            return Controller_AdminSys::get_logs_user($data, $ci_admin);

        case "/logs/users": // Logs filtrados por tipo de usuario/log
            return Controller_AdminSys::get_logs_users($data, $ci_admin);

        case "/log/sql":
            return Controller_AdminSys::get_logs_sql($ci_admin);

        default:
            return Util_HttpResponse::error("Ruta \"{$route}\" no encontrada en GET", http_not_found);
    }
}