<?php
include_once __DIR__ . "/../constantes/json_constantes.php";
include_once __DIR__ . "/../controladores/verify_data_controller.php";
include_once __DIR__ . "/../modelo/log_model.php";

class AuthController {
    private const clave = "UnViMáMiGe_PaVen_Tip.Emp_huuuuuum";
    private const type_log = "AUTH CONTROLLER";

    public static function create_token(array $user, ...$datos_extras): ?array 
    {
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
                    key_completeuser => $data_user[key_completeuser] ?? false,
                    key_typeuser => $token_data[key_typeuser]
                ],
                key_token_sig => $firma
            ]
        ];

        LogModel::add_log_user($token_data[key_ci], self::type_log, "El usuario crea un token");

        return $token;
    }

    public static function comprobate_token(array $token, ...$datos_extras): ?bool 
    {
        if (!array_key_exists(key_token, $token)) return null;
        if (!array_key_exists(key_token_sig, $token[key_token])) return null;
        if (!self::comprobate_required_data($token[key_token])) return null;

        $data_user = $token[key_token][key_user];

        $token_data = [];
        $token_data[key_ci] = $data_user[key_ci];
        $token_data[key_typeuser] = $data_user[key_typeuser];

        $firma = hash_hmac("sha256", json_encode($token_data), self::clave);

        LogModel::add_log_user($token_data[key_ci], self::type_log, "Se comprueba el token del usuario");

        return hash_equals($firma, $token[key_token][key_token_sig]);
    }

    private static function comprobate_required_data(array $user): bool 
    {
        if (!array_key_exists(key_user, $user)) return false;
        $data_user = $user[key_user];

        if (!array_key_exists(key_typeuser, $data_user)) return false;
        if (!array_key_exists(key_ci, $data_user)) return false;

        return true;
    }

    public static function comprobate_token_typeuser(array $token, string $type_user): ?bool 
    {
        $primera_comprobacion = self::comprobate_token($token);
        
        if ($primera_comprobacion !== true) return $primera_comprobacion;

        return $token[key_token][key_user][key_typeuser] == $type_user;
    }

    public static function valid_user_type(array $data, string $type_user) 
    {
        if (is_null($data)) {
            $res = HttpResponse::error("No se puede comprobar si es un {$type_user} sin los datos");
            $res->send();
            die();
        }

        if (!array_key_exists(key_token, $data)) {
            $res = HttpResponse::error("No se puede usar opciones de {$type_user} sin un token.");
            $res->send();
            die();
        }

        $auth = AuthController::comprobate_token_typeuser([key_token => $data[key_token]], $type_user);
        
        if ($auth === false) {
            $res = HttpResponse::error("El token no es valido");
            $res->send();
            die();
        }
        
        if (is_null($auth)) {
            $res = HttpResponse::error("Error en los datos del token");
            $res->send();
            die();
        }
    }

    /**
     * Extrae de forma segura el CI del usuario desde la estructura del token.
     */
    public static function get_ci_from_token(array $data): ?int 
    {
        if (isset($data[key_token][key_user][key_ci])) {
            return (int)$data[key_token][key_user][key_ci];
        }
        return null;
    }
}