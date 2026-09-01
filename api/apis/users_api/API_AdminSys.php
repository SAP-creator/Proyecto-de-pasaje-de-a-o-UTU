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
    // Valida que el token pertenezca al tipo de usuario autorizado
    AuthController::valid_user_type($datos, enum_tipo_admin_sistema);
    
    // Se obtiene el CI del admin autenticado desde la firma/token
    $ci_admin = AuthController::get_ci_from_token($datos);

    switch ($metodo) {
        case "POST":
            $res = opciones_POST($ruta, $datos, $ci_admin);
            break;
        case "GET":
            $res = opciones_GET($ruta, $datos, $ci_admin);
            break;
        default:
            $res = HttpResponse::error("Método {$metodo} no soportado en SysAdmin", http_bad_request);
            break;
    }
    $res->send();
}

function opciones_POST(string $ruta, array $datos, ?int $ci_admin): HttpResponse {
    switch ($ruta) {
        case "/requests/accept":
            VerifyDataController::keys_exists(true, $datos, key_user); 
            return SignController::accept_sign_up($datos, $ci_admin);

        default:
            return HttpResponse::error("Ruta \"{$ruta}\" no encontrada en POST", http_not_found);
    }
}

function opciones_GET(string $ruta, array $datos, ?int $ci_admin): HttpResponse {
    include_once __DIR__ . "/../../controladores/admin_controller.php";

    switch ($ruta) {
        case "/users": // Obtiene lista de usuarios
            return AdminController::get_users_data($datos, $ci_admin);

        case "/requests": // Obtiene lista de solicitudes pendientes
            return AdminController::get_request_user_data($datos, $ci_admin);

        case "/users/exists": // Verifica si un usuario existe
            return AdminController::has_user($datos, $ci_admin);

        case "/requests/exists": // Verifica si existe una solicitud de CI
            return AdminController::has_request_user($datos, $ci_admin);

        case "/logs/user": // Logs de un usuario individual por su CI
            return AdminController::get_logs_user($datos, $ci_admin);

        case "/logs/users": // Logs filtrados por tipo de usuario/log
            return AdminController::get_logs_users($datos, $ci_admin);

        case "/log/sql":
            return HttpResponse::ok("En construcción");

        default:
            return HttpResponse::error("Ruta \"{$ruta}\" no encontrada en GET", http_not_found);
    }
}