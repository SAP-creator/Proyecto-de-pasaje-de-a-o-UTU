<?php


include_once __DIR__ . "/Util_RestHttp.php";
include_once __DIR__ . "/Util_Code.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Util_VerifyData {

    private const LOG_TYPE = "VERIFY DATA UTIL";

    static public function keys_exists(bool $die, array $list, mixed ...$keys): bool {
        $verified_array = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $list)) {
                array_push($verified_array, $list[$key]);
            } else {
                if ($die) 
                {
                    // Enviar la respuesta HTTP
                    Util_HttpResponse::error(
                        Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No existe la key {$key} en el array -Verify"),
                        http_unprocessable_entity
                    )->send();
                    //MY JUDGMENT IS DEAD -dijo un rey. No se cual rey pueda ser, no le pude ver la cara.
                    die;
                } 
                else
                {
                    return false;
                } 
            }
        }

        return true;
    }

    public static function verify_and_get_from_token(array $data, string $key, string $custom_error_msg = "Datos del token inválidos o incompletos"): mixed
    {
        $result = Controller_Auth::get_from_token($data, $key);

        if (!$result->is_found()) {
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, $custom_error_msg),
                http_bad_request
            )->send();
            die();
        }

        return $result->get_value();
    }

    public static function valid_json(mixed $str, bool $die = true): ?array 
    {
        // 1. Validar que sea un string
        if (!is_string($str)) {
            if ($die) {
                Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No es posible procesar JSON: el dato enviado no es un texto"),
                    http_bad_request
                )->send();
                exit;
            }
            return null;
        }

        // 2. Intentar decodificar como Array
        $decoded = json_decode($str, true);

        // 3. Validar sintaxis revisando si hubo un error en la decodificación
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            if ($die) {
                Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error: lo enviado no es un JSON válido"),
                    http_bad_request
                )->send();
                exit;
            }
            return null;
        }

        return $decoded;
    }
}
