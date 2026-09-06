<?php

include_once __DIR__ . "/../../utils/Util_RestHttp.php";
include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../../controladores/Controller_UserSetup.php";
include_once __DIR__ . "/../../controladores/Controller_UserChangeData.php";
include_once __DIR__ . "/../../controladores/Controller_Sign.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];
$original_route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$route = str_replace(path_api_user, '', $original_route);

if (strlen($route) === 0 || $route[0] !== '/') {
    $route = '/' . $route;
}

// Lectura de body JSON
$input_raw = file_get_contents("php://input");
$data = json_decode($input_raw, true) ?? [];

// Validar estructura JSON únicamente si el método requiere un body obligatorio
if (!is_array($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
    Util_HttpResponse::error(http_bad_request, "No puede hacer esta petición sin un JSON válido en el body")->send();
    exit();
}

// Unificar parámetros en caso de enviarse por querystring
if (!empty($_GET)) {
    $data = array_merge($data, $_GET);
}

process_http_request($method, $route, $data);

function process_http_request(string $method, string $route, array $data) {
    $response = match ($method) {
        "POST"    => handle_post($route, $data),
        "PUT"     => handle_put($route, $data),
        "OPTIONS" => Util_HttpResponse::ok(json_decode(file_get_contents("opciones user.json"), true) ?? []),
        default   => Util_HttpResponse::error(http_bad_request, "Método {$method} no permitido en esta ruta")
    };
    
    $response->send();
}

function handle_post(string $route, array $data): Util_HttpResponse {
    Controller_VerifyData::keys_exists(true, $data, json_user);

    switch ($route) {
        case "/sign/in":
            return Controller_Sign::sign_in($data);

        case "/sign/up":
            return Controller_Sign::sign_up($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta \"{$route}\" no encontrada en POST");
    }
}

function handle_put(string $route, array $data): Util_HttpResponse {
    switch ($route) {
        case "/profile": // Usa Controller_UserChangeData::user_change_data_by_user
            return Controller_UserChangeData::user_change_data_by_user($data);

        case "/complete": // Completa la configuración/datos del usuario
            return Controller_UserSetup::complete_user($data);

        default:
            return Util_HttpResponse::error(http_not_found, "Ruta \"{$route}\" no encontrada en PUT");
    }
}