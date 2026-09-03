<?php


include_once __DIR__ . "/../../utils/Util_RestHttp.php";
include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../../controladores/Controller_UserSetup.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];
$original_route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$route = str_replace(path_api_user, '', $original_route);

if (strlen($route) === 0 || $route[0] !== '/') {
    $route = '/' . $route;
}
$data = json_decode(file_get_contents("php://input"), true);

process_http_request($method, $route, $data);

function process_http_request(string $method, string $route, ?array $data) {
   
    switch ($method) {
        case "POST":
            if (!is_array($data)) {
                $response = Util_HttpResponse::error(http_bad_request,"No puede hacer una peticion POST sin json en body");
                break;
            }
            $response = handle_post($route, $data);
            break;

        case "OPTIONS":
            $response = Util_HttpResponse::ok(json_decode(file_get_contents("opciones user.json")));
            break;

        default:
            $response = Util_HttpResponse::error(http_bad_request, "Metodo {$method} no permitido");
            break;
    }
    
    $response->send();
}

function handle_post(string $route_option, array $data): Util_HttpResponse {
   
    include_once __DIR__ . "/../../controladores/Controller_Sign.php";
    Controller_VerifyData::keys_exists(true, $data, json_user);

    switch ($route_option) {
        case "/SignIn":
            return Controller_Sign::sign_in($data);

        case "/SignUp":
            return Controller_Sign::sign_up($data);

        case "/Complete":
            return Controller_UserSetup::complete_user($data);
    }



    return Util_HttpResponse::error( 
        http_bad_request,
        "No existe la opcion {$route_option}. Por favor revise nuevamente enviando un HTTP OPTIONS a /api/users",
    );
}