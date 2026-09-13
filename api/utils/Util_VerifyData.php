<?php

include_once __DIR__ . "/Util_RestHttp.php";
include_once __DIR__ . "/Util_Code.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Util_VerifyData 
{
    private const SYS_NAME = "VerifyData";

    public static function keys_exists(bool $die, array $list, mixed ...$keys): bool 
    {
        $verified_array = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $list)) {
                array_push($verified_array, $list[$key]);
            } else {
                if ($die) {
                    Util_HttpResponse::error(
                        Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, "MissingKeyInArray"),
                        http_unprocessable_entity,
                        [json_error=>"No se encontro a {$key}"]
                    )->send();
                    die();
                } else {
                    return false;
                } 
            }
        }

        return true;
    }

    public static function verify_and_get_from_token(array $data, string $key, string $custom_error_code = "TokenDataNotFound"): mixed
    {
        $result = Controller_Auth::get_from_token($data, $key);

        if (!$result->is_found()) {
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, $custom_error_code),
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
                    Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, "DataIsNotString"),
                    http_bad_request
                )->send();
                die();
            }
            return null;
        }

        // 2. Intentar decodificar como Array
        $decoded = json_decode($str, true);

        // 3. Validar sintaxis revisando si hubo un error en la decodificación
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            if ($die) {
                Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, "InvalidJsonFormat"),
                    http_bad_request
                )->send();
                die();
            }
            return null;
        }

        return $decoded;
    }
}