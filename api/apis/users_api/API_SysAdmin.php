<?php
include_once __DIR__ . "/../../utils/respuesta http.php";
include_once __DIR__ . "/../../Controladores/auth.php";


header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

#NOTA: MOFICAR PARA CUANDO SE CAMBIE EL NOMBRE DE LA RUTA!
$path = str_replace('/GIT/api/users/admin', '', $path);

$data = json_decode(file_get_contents("php://input"), true);


opciones_http($method,$path,$data);

function opciones_http(string $metodo, string $ruta, ?array $datos){ 
    if (! (is_array($datos) && array_key_exists("token", $datos)) ){
        $res = new respuestaHTTP(400,json_encode(["error" =>"no se puede verificar si es admin si no se envia un token de usuario"]));
        $res->enviar();
        return;
    }
    
    if (comprobar_token_tipo_usuario(["token"=>$datos["token"]],"admin") != true){
        $res = new respuestaHTTP(400,json_encode(["error" =>"no se pudo verificar si el usuario es admin o no."]));
        $res->enviar();
        return;
    }
    

    switch ($metodo) {
        case "POST":
            $res = opciones_POST($ruta,$datos);
            break;

        case "GET":
            $res = opciones_GET($ruta,$datos);
            break;
        
        default:
            

            $res =new respuestaHTTP(404,json_encode(["error"=> "no existe el metodo {$metodo} en admin"]));
    }
    $res->enviar();
}

function opciones_POST(string $ruta,array $datos):respuestaHTTP{

    include_once __DIR__ . "/../../Controladores/userdata.php";

    switch ($ruta){

        case "/aceptarsingup":
     
            if (!array_key_exists("CI", $datos)){
                return new respuestaHTTP(400, json_encode(["error"=>"No se envió la cédula"]));
            }

            $res = aceptar_solicitud($datos["CI"]);

            if ($res === null){
                return new respuestaHTTP(500, json_encode(["error"=>"Hubo un error en la base de datos"]));
            }

            if ($res === false){
                return new respuestaHTTP(400, json_encode(["error"=>"No se pudo aceptar la petición"]));
            }

            return new respuestaHTTP(200);

        default:
            return new respuestaHTTP(404, json_encode(["error"=>"no existe la ruta {$ruta} en el metodo Post de admin"]));
    }

}

function opciones_GET(string $ruta, array $datos):respuestaHTTP{

    include_once __DIR__ . "/../../Controladores/userdata.php";

    switch ($ruta){

        case "/users/data":

            $usuarios = todos_los_usuarios();

            if (is_null($usuarios)){
                return new respuestaHTTP(
                    500,
                    json_encode(["error"=>"Hubo un error en la base de datos. Intentar más tarde"])
                );
            }

            return new respuestaHTTP(200, $usuarios);


        case "/users/data/peticiones":

            $usuarios = todas_las_solicitudes_usuario();

            if (is_null($usuarios)){
                return new respuestaHTTP(
                    500,
                    json_encode(["error"=>"Hubo un error en la base de datos. Intentar más tarde"])
                );
            }

            return new respuestaHTTP(200, $usuarios);


        case "/users/exist":

            if (!array_key_exists("CEDULA", $datos)){
                return new respuestaHTTP(
                    400,
                    json_encode(["error"=>"No se envió la cédula"])
                );
            }

            $existe = conseguir_usuario($datos["CEDULA"]);

            if (is_null($existe)){
                return new respuestaHTTP(
                    500,
                    json_encode(["error"=>"Hubo un error en la base de datos. Intentar más tarde"])
                );
            }

            return new respuestaHTTP(
                200,
                json_encode(["exist" => $existe])
            );


        case "/logs/users":
            return new respuestaHTTP(200);


        case "/logs/sql":
            return new respuestaHTTP(200);


        default:
            return new respuestaHTTP(
                404,
                json_encode(["error"=>"no existe la ruta {$ruta} en el metodo GET de admin"])
            );
    }

}
