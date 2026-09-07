<?php
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

class Controller_Auth {
    private const secret_key = "UnViMáMiGe_PaVen_Tip.Emp_huuuuuum";
    private const type_log = "AUTH CONTROLLER";

    public static function create_token(array $user, ...$extra_data): ?array 
    {
        if (!self::comprobate_required_data($user)) {
            Model_Log::add_log_user(0, self::type_log, "Fallo al crear token: Estructura de datos invalida o incompleta");
            return null;
        }

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

        Model_Log::add_log_user($token_data[json_ci], self::type_log, "Token generado exitosamente para tipo de usuario: {$token_data[json_typeuser]}");

        return $token;
    }

    public static function comprobate_token(array $token, ...$extra_data): ?bool 
    {
        Util_VerifyData::keys_exists(true, $token, json_token);
        Util_VerifyData::keys_exists(true, $token[json_token], json_token_sig);

        if (!self::comprobate_required_data($token[json_token])) {
            Model_Log::add_log_user(0, self::type_log, "Fallo al comprobar token: Datos de usuario requeridos ausentes en el token");
            return null;
        }
        
        $data_user = $token[json_token][json_user];

        $token_data = [];
        $token_data[json_ci] = $data_user[json_ci];
        $token_data[json_typeuser] = $data_user[json_typeuser];

        $signature = hash_hmac("sha256", json_encode($token_data), self::secret_key);
        $is_valid = hash_equals($signature, $token[json_token][json_token_sig]);

        if ($is_valid) {
            Model_Log::add_log_user($token_data[json_ci], self::type_log, "Firma de token validada correctamente");
        } else {
            Model_Log::add_log_user($token_data[json_ci], self::type_log, "Fallo de validacion: Firma de token invalida o manipulada");
        }
         
        return $is_valid;
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
        
        if ($first_check !== true) {
            return $first_check;
        }

        $ci = $token[json_token][json_user][json_ci] ?? 0;
        $current_type = $token[json_token][json_user][json_typeuser] ?? '';
        $matches = ($current_type === $type_user);

        if (!$matches) {
            Model_Log::add_log_user($ci, self::type_log, "Acceso denegado: El tipo de usuario '{$current_type}' no coincide con el requerido '{$type_user}'");
        }

        return $matches;
    }

    public static function valid_user_type(array $data, string $type_user) 
    {
        if (!array_key_exists(json_token, $data)) {
            Model_Log::add_log_user(0, self::type_log, "Intento de acceso rechazado a funcionalidad '{$type_user}': Token ausente");
            $res = Util_HttpResponse::error(http_bad_request, "No se puede usar opciones de {$type_user} sin un token.");
            $res->send();
            die();
        }

        $auth = Controller_Auth::comprobate_token_typeuser([json_token => $data[json_token]], $type_user);
       
        if ($auth === false) {
            $ci = $data[json_token][json_user][json_ci] ?? 0;
            Model_Log::add_log_user($ci, self::type_log, "Acceso no autorizado rechazado para el rol '{$type_user}'");
            $res = Util_HttpResponse::error(http_unaunthorize, "El token no es valido");
            $res->send();
            die();
        }
        
        if (is_null($auth)) {
            Model_Log::add_log_user(0, self::type_log, "Error en estructura de token durante verificacion de rol '{$type_user}'");
            $res = Util_HttpResponse::error(http_bad_request, "Error en los datos del token");
            $res->send();
            die();
        }

        $ci = $data[json_token][json_user][json_ci] ?? 0;
        Model_Log::add_log_user($ci, self::type_log, "Validacion de rol '{$type_user}' exitosa");
    }

    public static function get_from_token(array $data, string $data_to_extract): TokenResult
    {
        if (!Util_VerifyData::keys_exists(false, $data, json_token)) {
            Model_Log::add_log_user(0, self::type_log, "Fallo al extraer '{$data_to_extract}': Estructura sin clave token");
            return new TokenResult(false);
        }

        if (!Util_VerifyData::keys_exists(false, $data[json_token], json_user)) {
            Model_Log::add_log_user(0, self::type_log, "Fallo al extraer '{$data_to_extract}': Clave usuario ausente en token");
            return new TokenResult(false);
        }

        if (!Util_VerifyData::keys_exists(false, $data[json_token][json_user], $data_to_extract)) {
            $ci = $data[json_token][json_user][json_ci] ?? 0;
            Model_Log::add_log_user($ci, self::type_log, "Fallo al extraer propiedad '{$data_to_extract}' de los datos del token");
            return new TokenResult(false);
        }

        $ci = $data[json_token][json_user][json_ci] ?? 0;
        Model_Log::add_log_user($ci, self::type_log, "Propiedad '{$data_to_extract}' extraida exitosamente del token");

        return new TokenResult(true, $data[json_token][json_user][$data_to_extract]);
    }
}

class TokenResult
{
    private bool $found;
    private mixed $value;

    public function __construct(bool $found, mixed $value = null)
    {
        $this->found = $found;
        $this->value = $value;
    }

    public function is_found(): bool
    {
        return $this->found;
    }

    public function get_value(): mixed
    {
        return $this->value;
    }
}