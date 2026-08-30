<?php
include_once __DIR__ . "/../utils/resp_http.php";


class VerifyDataController {
    static public function keys_exists(bool $die, array $list, mixed ...$keys): bool {
        $verified_array = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $list)) {
                array_push($verified_array, $list[$key]);
            } else {
                if ($die) 
                {
                    // Enviar la respuesta HTTP
                    $res = HttpResponse::error("No existe la key {$key} en el array -Verify", http_bad_request);
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