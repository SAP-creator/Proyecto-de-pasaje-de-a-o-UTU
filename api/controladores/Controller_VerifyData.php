<?php
include_once __DIR__ . "/../utils/Util_RestHttp.php";


class Controller_VerifyData {
    static public function keys_exists(bool $die, array $list, mixed ...$keys): bool {
        $verified_array = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $list)) {
                array_push($verified_array, $list[$key]);
            } else {
                if ($die) 
                {
                    // Enviar la respuesta HTTP
                    $res = Util_HttpResponse::error(http_unprocessable_entity, "No existe la key {$key} en el array -Verify");
                    $res->send();

                    exit;
                } 
                else
                {
                    return false;
                } 
            }
        }

        return true;
    }
}