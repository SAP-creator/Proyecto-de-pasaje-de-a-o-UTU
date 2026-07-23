<?php

#un video mas mi gente para perder el tiempo. Empezemos (eso es lo que se significa)
#profe no me ponga bajo por esto
const clave = "UvDmMgPeT_Emp.hummmm";

#constantes (por si modifica algo solo tengo que modificar esto)
const tipo_user = "tipo";
const cedula = "cedula";

const firma = "firma";
const user = "user";

#retorna null en caso de que los datos esta los datos esten mal
function crear_token(array $user_data): ?string{

    if (! (array_key_exists(tipo_user,$user_data) || array_key_exists(cedula,$user_data))){
        return null;
    }

    #por si lo envian con contraseña
    unset($user_data["clave"]);

    $firma = hash_hmac(
        "sha256",
        json_encode($user_data),
        clave
    );

    return json_encode([
        "token" => [
            user => $user_data,
            firma => $firma
        ]
    ]);
}


#retorna null en el caso que no exista alguno de los datos
function comprobar_token(array $token): ?bool{

    if (! array_key_exists("token",$token)){
        return null;
    }
    $datos_token = $token["token"];


    if (! (array_key_exists(firma,$datos_token) || array_key_exists(user,$datos_token))){
        return null;
    }
    $firma = $datos_token[firma];

    $user = $datos_token[user];

    $user_firmado = hash_hmac(
        "sha256",
        json_encode($user),
        clave
    );

    return hash_equals($firma,$user_firmado);

}

#retorna true si ve que existe ese user y ademas es del tipo que se estaba buscando
#retorna false si no es asi
#retorna null si hubo un error en los datos
function comprobar_token_tipo_usuario(array $token,string $tipo_buscado): ?bool{
    $verificacion_existencia = comprobar_token($token);
    if ($verificacion_existencia != true){
        return $verificacion_existencia;
    }
    $user_data = $token["token"]["user"];

    if (! array_key_exists("tipo",$user_data)){
        
        return null;
    }
    $tipo_user = $user_data["tipo"];
    return  strcmp($tipo_user,$tipo_buscado) == 0; 

}
?>