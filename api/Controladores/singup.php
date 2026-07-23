<?php 
include_once __DIR__ . "/../utils/database.php";
include_once __DIR__ . "/../utils/respuesta http.php";

include_once "auth.php";


const ci = "CI";
const password = "PASSWORD";
const typeuser = "TYPEUSER";

function crear_sesion(array $datos) : respuestaHTTP{

    
    

    if (! array_key_exists(ci, $datos)       ||
        ! array_key_exists(password, $datos) ||
        ! array_key_exists(typeuser, $datos) )
    {   

        return new respuestaHTTP(400,json_encode(["error"=> "No se pudo procesar la solicitud de sing up por que CI, PASSWORD o TYPEUSER no existe en la peticion.", "datos enviados" => $datos]));

    }

    $db = new ConexionDatabase();

    $hay_usuario = existe_usuario($datos["CI"],$db);

    if ($hay_usuario === null){
        
        return new respuestaHTTP(500,json_encode(["error"=>"hubo un error en la base de datos al procesar la peticion"]));
    }

    if ($hay_usuario === true){
        return new respuestaHTTP(400, json_encode(["error" => "ya existe ese usuario"]));
    }

    $sql = "
    INSERT INTO `peticion usuario` 
    (`cedula`, `clave`, `tipo`) 
    
    VALUES (?, ?, ?);";

    $consulta = $db->peticion(
        $sql,
        "iis",
        $datos[ci],
        $datos[password],
        $datos[typeuser]
    );

    if (! $consulta->funciona) {
        return new respuestaHTTP(
            500,
            json_encode([
                "error" => $consulta->problema
            ])
        );
    }

    $token = crear_token(["cedula"=>$datos[ci],"tipo"=>$datos[typeuser]]);

    return new respuestaHTTP(200,json_encode($token));


}

#puede retornar null por un error

function existe_usuario(int $cedula,ConexionDatabase $db): ?bool{
    $sql_existe = "
        SELECT *
        FROM usuario
        WHERE cedula = ?;
    ";


    $consulta = $db->peticion(
        $sql_existe,
        "i",
        $cedula
    );
    
    if (! $consulta->funciona) {
        return null;
    }

    $usuario = $consulta->respuesta->fetch_assoc();
    return !($usuario === null);
}
?>