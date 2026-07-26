<?php


include_once __DIR__ . "/../utils/database.php";
include_once __DIR__ . "/../utils/respuesta http.php";

include_once "auth.php";
const ci = "CI";
const password = "PASSWORD";
const typeuser = "TYPEUSER";

function iniciar_sesion(array $datos): respuestaHTTP{


    if (! array_key_exists(ci, $datos)       ||
        ! array_key_exists(password, $datos) ||
        ! array_key_exists(typeuser, $datos) )
    {   

        return new respuestaHTTP(400,json_encode(["error"=> "No se pudo procesar la solicitud de sing in por que CI, PASSWORD o TYPEUSER no existe en la peticion.", "datos enviados" => $datos]));

    }


    $db = new ConexionDatabase();

    $sql = "
        SELECT *
        FROM usuario
        WHERE cedula = ? AND clave = ? AND tipo = ?;
    ";

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

    $usuario = $consulta->respuesta->fetch_assoc();

    if (!$usuario) {
        return new respuestaHTTP(
            401,
            json_encode([
                "error" => "Cédula, contraseña o tipo de usuario incorrectos"
            ])
        );
    }

    return new respuestaHTTP(
        200,
        crear_token($usuario)
    );
}

