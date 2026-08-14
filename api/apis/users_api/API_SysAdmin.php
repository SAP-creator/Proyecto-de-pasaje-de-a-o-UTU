<?php
include_once __DIR__ . "/../../utils/resp_http.php";

include_once __DIR__ . "/../../constantes/json_constantes.php";
include_once __DIR__ . "/../../constantes/rutas_constantes.php";

include_once __DIR__ . "/../../controladores/auth_controller.php";






header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


const user = "sysAdmin";
$path = str_replace(ruta_api_sysadmin , '', $path);

$data = json_decode(file_get_contents("php://input"), true);

if (! is_array($data)){
    $res = HttpResponse::error("no puedes entrar puto");
    $res->send();
    die();
}

opciones_http($method,$path,$data);

function opciones_http(string $metodo, string $ruta, ?array $datos){ 
    AuthController::valid_user_type($datos,user);

    switch ($metodo) {
        case "POST":
            $res = opciones_POST($ruta,$datos);
            break;

        case "GET":
            $res = opciones_GET($ruta,$datos);
            break;
        
        default:
            
            $res = HttpResponse::error("no existe el metodo {$metodo} en admistrador de sistemas");
    }
    $res->send();
}

function opciones_POST(string $ruta,array $datos):HttpResponse{

    include_once __DIR__ . "/../../Controladores/userdata.php";

    switch ($ruta){

        case "/aceptarsingup":
            return HttpResponse::ok("por ahora nada");
            break;
            

        default:
            return HttpResponse::error("no existe la ruta {$ruta} en el metodo de POST de administrador de sistema.");
    }

}

function opciones_GET(string $ruta, array $datos):HttpResponse{

    include_once __DIR__ . "/../../Controladores/userdata.php";

    switch ($ruta){

        case "/users/data":
            return HttpResponse::ok("por ahora nada");
            


        case "/users/data/peticiones":
            return HttpResponse::ok("por ahora nada");
            


        case "/users/exist":
            return HttpResponse::ok("por ahora nada");
            


        case "/logs/users":
            return HttpResponse::ok("por ahora nada");


        case "/logs/sql":
            return HttpResponse::ok("por ahora nada");


        default:
            return HttpResponse::error("no existe la ruta {$ruta} en el metodo GET de administrador de sistema");
            
    }

}
