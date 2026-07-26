<?php

include_once __DIR__ . "/../../utils/respuesta http.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
#NOTA: MOFICAR PARA CUANDO SE CAMBIE EL NOMBRE DE LA RUTA!
$path = str_replace('/Login/api/users', '', $path);
#require_once "./API_DefaultUser.php";

$data = json_decode(file_get_contents("php://input"), true);

opciones_http($method, $path,$data);

function opciones_http(string $metodo,string $ruta,array $datos){
    switch ($metodo) {
        case "POST":
            $res = opciones_post($ruta,$datos);
            break;


        case "OPTIONS":
            $res = new respuestaHTTP(
                200, 
                file_get_contents("opciones user.json"));

            break;

        default:
            $res = new respuestaHTTP(
                404, 
                json_encode(["error" => "Método no permitido"]));

            break;
    }
    $res->enviar();
}

function opciones_post(string $opcion,array $datos):respuestaHTTP{
    switch ($opcion){
        case "/singin":
            include_once __DIR__ . "/../../Controladores/singin.php";

            return iniciar_sesion($datos);
        case "/singup":
            include_once __DIR__ . "/../../Controladores/singup.php";

            return crear_sesion($datos);
        default:
            return $res = new respuestaHTTP(404,json_encode([
                "error" => "no existe la opcion {$opcion}. Porfavor revise nuevamente enviando un http OPTION a /api/users"
            ]));
    }
}
