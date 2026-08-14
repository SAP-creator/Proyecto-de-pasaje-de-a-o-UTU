<?php
include_once __DIR__ . "/../constantes/json_constantes.php";

class AuthController{
    private const clave = "UnViMáMiGe_PaVen_Tip.Emp_huuuuuum";

    
    #retorna:
    #array los datos estan bien. Y el hash salio perfecto.
    #null error en el envio de datos.
    public static function create_token(array $user): ?array {
        if (!self::comprobate_required_data($user)) return null;


        $data_user = $user[key_user];

        $token_data = [];
        $token_data[key_ci] = $data_user[key_ci];
        $token_data[key_typeuser] = $data_user[key_typeuser];

        $firma = hash_hmac("sha256", json_encode($token_data), self::clave);

        $token = [
            key_token => [
                key_user => [
                    key_ci => $token_data[key_ci],
                    key_typeuser => $token_data[key_typeuser]
                ],
                key_token_sig => $firma
            ]
        ];

        return $token;
    }

    #Retorna:
    #true -> el token es valido.
    #false -> el token no es valido (o esta alterado).
    #null -> mal envio de datos.
    public static function comprobate_token(array $token): ?bool {
        if (!array_key_exists(key_token, $token)) return null;

        if (!array_key_exists(key_token_sig, $token[key_token])) return null;

        if (!self::comprobate_required_data($token[key_token])) return null;

        $data_user = $token[key_token][key_user];

        $token_data = [];
        $token_data[key_ci] = $data_user[key_ci];
        $token_data[key_typeuser] = $data_user[key_typeuser];

        $firma = hash_hmac("sha256", json_encode($token_data), self::clave);

        return hash_equals($firma, $token[key_token][key_token_sig]);
    }

    #Retorna:
    #true -> los datos basicos requeridos estan.
    #false -> los datos no estan bien puestos.
    private static function comprobate_required_data(array $user): bool{
        if (! array_key_exists(key_user,$user))return false;
        $data_user = $user[key_user];

        if (! array_key_exists(key_typeuser,$data_user))return false;
        if (! array_key_exists(key_ci,$data_user))return false;
        return true;
    }

    #Retorna:
    #true -> el token es valido y el usuario es del tipo es igual a que se esperaba (o sea $type_user).
    #false -> el token no es valido o no es el tipo de usuario que se esperaba.
    #null -> mal envio de datos.
    public static function comprobate_token_typeuser(array $token,string $type_user): ?bool{
        $primera_comprobacion = self::comprobate_token($token);
        
        if ($primera_comprobacion != true)return $primera_comprobacion;
       
        return $token[key_token][key_user][key_typeuser] === $type_user;

    }

    #Si el token que tiene dentro data es valido no hace nada la funcion.
    #En el caso que no sea valido ejecuta "die()"
    public static function valid_user_type(array $data,string $type_user){

        if (is_null($data)){
            $res = HttpResponse::error("no se puede comprobar si es un {$type_user} sin los datos");
            $res->send();
            die();
        }

        if (! array_key_exists(key_token,$data)) {
            $res = HttpResponse::error("no se puede usar opciones de {$type_user} sin un token.");
            $res->send();
            die();
            }
    
        $auth = AuthController::comprobate_token_typeuser( [ key_token =>  $data[key_token] ] ,$type_user);
        if ($auth === false){
            $res = HttpResponse::error("el token no es valido");
            $res->send();
            die();
        }
        if (is_null($auth)){
            $res = HttpResponse::error("error en los datos del token");
            $res->send();
            die();
        }
    }

}