<?php
include_once __DIR__ . "/../../utils/resp_http.php";
include_once __DIR__ . "/../../constantes/json_constantes.php";
include_once __DIR__ . "/../../constantes/rutas_constantes.php";
include_once __DIR__ . "/../controladores/verify_data_controller.php";
include_once __DIR__ . "/../../controladores/auth_controller.php";
include_once __DIR__ . "/../../controladores/sign_controller.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = str_replace(ruta_api_sysadmin, '', $path);
$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data) && $method === 'POST') {
    $res = HttpResponse::error("Estructura JSON inválida");
    $res->send();
    exit();
}

opciones_http($method, $path, $data ?? []);

function opciones_http(string $metodo, string $ruta, ?array $datos) { 
    AuthController::valid_user_type($datos, enum_tipo_admin_sistema);
    
    switch ($metodo) {
        case "POST":
            $res = opciones_POST($ruta, $datos);
            break;
        case "GET":
            $res = opciones_GET($ruta, $datos);
            break;
        default:
            $res = HttpResponse::error("Método {$metodo} no soportado en SysAdmin", http_bad_request);
            break;
    }
    $res->send();
}

function opciones_POST(string $ruta, array $datos): HttpResponse {
    switch ($ruta) {
        case "/requests/accept": // Antes /AceptarSignUp
            VerifyDataController::keys_exists(true, $datos, key_user); 
            return SignController::accept_sign_up($datos);

        default:
            return HttpResponse::error("Ruta \"{$ruta}\" no encontrada en POST", http_not_found);
    }
}

function opciones_GET(string $ruta, array $datos): HttpResponse {
    include_once __DIR__ . "/../../controladores/admin_controller.php";

    switch ($ruta) {
        case "/users": // Obtiene lista de usuarios (filtrada o completa)
            return AdminController::get_users_data($datos);

        case "/requests": // Obtiene lista de solicitudes pendientes
            return AdminController::get_request_user_data($datos);

        case "/users/exists": // Verifica si un usuario ya existe
            return AdminController::has_user($datos);

        case "/requests/exists": // Verifica si existe una solicitud para un CI
            return AdminController::has_request_user($datos);

        case "/logs/users":
            return HttpResponse::ok("Logs de usuario pendientes");

        case "/logs/sql":
            return HttpResponse::ok("Logs de SQL pendientes");

        default:
            return HttpResponse::error("Ruta \"{$ruta}\" no encontrada en GET", http_not_found);
    }
}