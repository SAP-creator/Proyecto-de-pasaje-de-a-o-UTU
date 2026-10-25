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

    public static function ok(string $code, mixed ...$data): self {
        if (empty($data)) {
            return new self(204);
        }

        return new self(200, self::encode_body($code, $data));
    }

    public static function created(string $code, mixed ...$data): self {
        return new self(201, self::encode_body($code, $data));
    }

    public static function response(int $status, string $code, mixed ...$data): self {
        if ($status === 204) {
            return new self(204);
        }

        return new self($status, self::encode_body($code, $data));
    }

    public static function error(string $error_code, int $code = 400, mixed ...$data): self {
        if ($code === 204) {
            return new self(204);
        }
        return new self($code, self::encode_body($error_code, $data));
    }

    private static function encode_body(?string $code, array $data): string {
        $payload = match (count($data)) {
            0 => new stdClass(),
            1 => $data[0],
            default => $data
        };

        return json_encode([
            json_code => $code,
            json_data => $payload
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function send(): void {
        http_response_code($this->status);

        if ($this->status === 204) {
            return;
        }

        header("Content-Type: application/json");
        echo $this->body;
    }
}