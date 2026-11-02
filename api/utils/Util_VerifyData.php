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

    /**
     * Devuelve $list[$key] como array. Si falta o no es un objeto/lista JSON, responde 422 y corta.
     */
    public static function verify_and_get_array(array $list, string $key): array
    {
        self::keys_exists(true, $list, $key);

        if (!is_array($list[$key])) {
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, "InvalidArray"),
                http_unprocessable_entity,
                [json_error => "{$key} debe ser un objeto"]
            )->send();
            die();
        }

        return $list[$key];
    }

    /**
     * Devuelve $list[$key] como int dentro de [$min, $max]. Si falta o es invalido, responde 422 y corta.
     */
    public static function verify_and_get_int(array $list, string $key, int $min = 1, int $max = PHP_INT_MAX): int
    {
        self::keys_exists(true, $list, $key);

        $value = filter_var($list[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        if ($value === false) {
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::UTIL, self::SYS_NAME, "InvalidInteger"),
                http_unprocessable_entity,
                [json_error => "{$key} debe ser un entero entre {$min} y {$max}"]
            )->send();
            die();
        }

        return $value;
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

    /**
     * Comprueba que el largo del valor, convertido a string, no supere $max.
     * Sirve para string, int y float (ej: un DECIMAL(10,2) ocupa a lo sumo 11 caracteres).
     */
    public static function comprobar_longitud(mixed $valor, int $max): bool
    {
        if (!is_scalar($valor)) {
            return false;
        }

        return mb_strlen((string) $valor) <= $max;
    }

    /**
     * Valida una fecha completa con formato Y-m-d H:i:s (de año a segundos).
     */
    public static function valid_date(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);

        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }

    /**
     * Valida que $values sea una lista (array secuencial) de strings, todos
     * pertenecientes a $allowed y sin repetidos.
     */
    public static function valid_enum_list(mixed $values, array $allowed, bool $allow_empty = false): bool
    {
        if (!is_array($values) || $values !== array_values($values)) {
            return false;
        }

        if (empty($values)) {
            return $allow_empty;
        }

        foreach ($values as $value) {
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                return false;
            }
        }

        return count($values) === count(array_unique($values));
    }
}
