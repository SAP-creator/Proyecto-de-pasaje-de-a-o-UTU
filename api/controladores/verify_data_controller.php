<?php
include_once __DIR__ . "/../utils/resp_http.php";


class VerifyDataController {
    static public function keys_exists(array $list, mixed ...$keys): array {
        $verified_array = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $list)) {
                array_push($verified_array, $list[$key]);
            } else {
                // Enviar la respuesta HTTP
                $res = HttpResponse::error("No existe la key {$key} en el array", http_bad_request);
                $res->send();

                exit; 
            }
        }

        return $verified_array;
    }
}