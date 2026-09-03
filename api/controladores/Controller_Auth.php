<?php
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

class Controller_Auth {
    private const secret_key = "UnViMáMiGe_PaVen_Tip.Emp_huuuuuum";
    private const type_log = "AUTH CONTROLLER";

    public static function create_token(array $user, ...$extra_data): ?array 
    {
        if (!self::comprobate_required_data($user)) return null;

        $data_user = $user[json_user];

        $token_data = [];
        $token_data[json_ci] = $data_user[json_ci];
        $token_data[json_typeuser] = $data_user[json_typeuser];

        $signature = hash_hmac("sha256", json_encode($token_data), self::secret_key);

        $token = [
            json_token => [
                json_user => [
                    json_ci => $token_data[json_ci],
                    json_completeuser => $data_user[json_completeuser] ?? false,
                    json_typeuser => $token_data[json_typeuser]
                ],
                json_token_sig => $signature
            ]
        ];

        Model_Log::add_log_user($token_data[json_ci], self::type_log, "El usuario crea un token");

        return $token;
    }

    public static function comprobate_token(array $token, ...$extra_data): ?bool 
    {
        
        Controller_VerifyData::keys_exists(true,$token,json_token);
        Controller_VerifyData::keys_exists(true,$token[json_token],json_token_sig);

        if (!self::comprobate_required_data($token[json_token])) return null;
        
        $data_user = $token[json_token][json_user];

        $token_data = [];
        $token_data[json_ci] = $data_user[json_ci];
        $token_data[json_typeuser] = $data_user[json_typeuser];

        $signature = hash_hmac("sha256", json_encode($token_data), self::secret_key);

        Model_Log::add_log_user($token_data[json_ci], self::type_log, "Se comprueba el token del usuario");
         
        return hash_equals($signature, $token[json_token][json_token_sig]);
    }

    private static function comprobate_required_data(array $user): bool 
    {
        
        if (!array_key_exists(json_user, $user)) return false;
        $data_user = $user[json_user];

        if (!array_key_exists(json_typeuser, $data_user)) return false;
        if (!array_key_exists(json_ci, $data_user)) return false;

        return true;
    }

    public static function comprobate_token_typeuser(array $token, string $type_user): ?bool 
    {
        $first_check = self::comprobate_token($token);
        
        if ($first_check !== true) return $first_check;

        return $token[json_token][json_user][json_typeuser] == $type_user;
    }

    public static function valid_user_type(array $data, string $type_user) 
    {
        
        if (!array_key_exists(json_token, $data)) {
            $res = Util_HttpResponse::error(http_bad_request,"No se puede usar opciones de {$type_user} sin un token.");
            $res->send();
            die();
        }

        $auth = Controller_Auth::comprobate_token_typeuser([json_token => $data[json_token]], $type_user);
       
        if ($auth === false) {
            $res = Util_HttpResponse::error(http_unaunthorize,"El token no es valido");
            $res->send();
            die();
        }
        
        if (is_null($auth)) {
            $res = Util_HttpResponse::error(http_bad_request,"Error en los datos del token");
            $res->send();
            die();
        }
    }

    /**
     * Extrae de forma segura el CI del usuario desde la estructura del token.
     */
    public static function get_ci_from_token(array $data): ?int 
    {
        if (isset($data[json_token][json_user][json_ci])) {
            return (int)$data[json_token][json_user][json_ci];
        }
        return null;
    }
}