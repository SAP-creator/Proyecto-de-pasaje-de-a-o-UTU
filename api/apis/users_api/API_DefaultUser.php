<?php

include_once __DIR__ . "/../../utils/resp_http.php";
include_once __DIR__ . "/../../constantes/rutas_constantes.php";

include_once __DIR__ . "/../../controladores/verify_data_controller.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];
$original_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Eliminar la base de la ruta para dejar solo el endpoint
$path = str_replace(ruta_api_usuario, '', $original_path);

#¿porque puse esto?
if (strlen($path) === 0 || $path[0] !== '/') {
    $path = '/' . $path;
}

$data = json_decode(file_get_contents("php://input"), true);

opciones_http($method, $path, $data);

function opciones_http(string $metodo, string $ruta, ?array $datos) {
    switch ($metodo) {
        case "POST":
            if (!is_array($datos)) {
                $res = HttpResponse::error("No puede hacer una peticion POST sin json en body", http_bad_request);
                break;
            }
            $res = opciones_post($ruta, $datos);
            break;

        case "OPTIONS":
            $res = HttpResponse::ok(json_decode(file_get_contents("opciones user.json")));
            break;

        default:
            $res = HttpResponse::error("Metodo {$metodo} no permitido");
            break;
    }
    $res->send();
}

function opciones_post(string $opcion, array $datos): HttpResponse {
    // Corregido "Sing" por "Sign"
    if (str_contains($opcion, "Sign")) {
        
        $sign_op = str_replace("/Sign", "", $opcion);
        include_once __DIR__ . "/../../controladores/sign_controller.php";
        
        switch ($sign_op) {
            case "In":
                VerifyDataController::keys_exists($datos,key_user);
                

                return SignController::sign_in($datos);
                break;
            case "Up":
                VerifyDataController::keys_exists($datos,key_user);

                return SignController::sign_up($datos);
                break;
        }
    }

    return HttpResponse::error( 
        "No existe la opcion {$opcion}. Por favor revise nuevamente enviando un HTTP OPTIONS a /api/users",
        http_bad_request
    );
}