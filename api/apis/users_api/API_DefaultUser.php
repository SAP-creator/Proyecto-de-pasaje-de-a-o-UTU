<?php

include_once __DIR__ . "/../../utils/resp http.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

$method = $_SERVER['REQUEST_METHOD'];
$origina_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
#NOTA: MOFICAR PARA CUANDO SE CAMBIE EL NOMBRE DE LA RUTA!
$path = str_replace('/GIT/api/users', '', $origina_path);
#require_once "./API_DefaultUser.php";


$data = json_decode(file_get_contents("php://input"), true);



opciones_http($method, $path,$data);

function opciones_http(string $metodo,string $ruta,?array $datos){
    switch ($metodo) {
        case "POST":
            if (! is_array($datos)){
                $res = HttpResponse::error("No puede hacer una peticion POST sin json en body",http_bad_request);
                break;
            }
            $res = opciones_post($ruta,$datos);
            break;


        case "OPTIONS":
            $res = HttpResponse::ok(json_decode(file_get_contents("opciones user.json")));
           

            break;

        default:
            $res = HttpResponse::error("Metodo no permitido");
            

            break;
    }
    $res->send();
}

function opciones_post(string $opcion,array $datos):HttpResponse{
    switch ($opcion){
        case "/signin":
            return HttpResponse::ok("por ahora nada");
        case "/signup":
            return HttpResponse::ok("por ahora nada");

        default:
            return HttpResponse::error( 
                "no existe la opcion {$opcion}. Porfavor revise nuevamente enviando un http OPTION a /api/users",
                http_bad_request
            );
    }
}
