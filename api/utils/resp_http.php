<?php 

include_once __DIR__ . "/../constantes/json_constantes.php";


const http_ok = 200;
const http_create = 201;

const http_bad_request = 400;
const http_unaunthorize = 401;
const http_forbidden = 403;
const http_not_found = 404;
const http_internal_error = 500;



class HttpResponse {

    private int $status;
    private string $body;

    private function __construct(int $status, string $body = "") {
        $this->status = $status;
        $this->body = $body;
    }

    public static function ok(mixed ... $data): self {
        $respuesta = 200;
        #si no tiene respuesta, tiro un 204 (No content)
        if (empty($data)) $respuesta = 204;
        return new self($respuesta, json_encode($data));
    }

    public static function created(mixed ... $data ): self {
        $json_data = [];

        foreach($data as $d){
            $json_data = json_encode($d);
        }

        return new self(201, $json_data);
    }



    public static function error(string $message = "Error", int $code = 400): self {
        return new self($code, json_encode([key_error => $message]));
    }

    public function send(): void {
        http_response_code($this->status);
        header("Content-Type: application/json");
        echo $this->body;
    }
}