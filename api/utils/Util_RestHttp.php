<?php 

include_once __DIR__ . "/../constantes/Const_Json.php";

const http_ok = 200;
const http_create = 201;

const http_bad_request = 400;
const http_unaunthorize = 401;
const http_forbidden = 403;
const http_not_found = 404;
const http_conflict = 409;
const http_unprocessable_entity = 422;
const http_internal_error = 500;

class Util_HttpResponse {

    private int $status;
    private string $body;

    private function __construct(int $status, string $body = "") {
        $this->status = $status;
        $this->body = $body;
    }

    public static function ok(mixed ...$data): self {
        $respuesta = empty($data) ? 204 : 200;
        return new self($respuesta, json_encode($data));
    }

    public static function created(mixed ...$data): self {
        $body = empty($data) ? "" : json_encode($data);
        return new self(201, $body);
    }

    public static function response(int $status, mixed ...$data): self {
        return new self($status, json_encode($data));
    }

    public static function error(int $code = 400, mixed ...$data): self {
        return new self($code, json_encode($data));
    }

    public function send(): void {
        http_response_code($this->status);
        header("Content-Type: application/json");
        echo $this->body;
    }
}