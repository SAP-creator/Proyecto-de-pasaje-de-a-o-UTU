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

#Aunqque exista funciones identicas son varias asi el programador entiende rapidamente si es un error, un todo perfecto o un algo mas en vez de tener el significado del mensaje o codigo.

    private int $status;
    private string $body;

    #para trabajar mas rapido
    private function __construct(int $status, string $body = "") {
        $this->status = $status;
        $this->body = $body;
    }

    #Cuando quieres enviar que todo funciono correctamente se recomienda usar solo con http de 200 (si vas a usar 400 0 500 use error)
    public static function ok(mixed ... $data): self {
        $respuesta = 200;
        #si no tiene respuesta, tiro un 204 (No content)
        if (empty($data)) $respuesta = 204;
        return new self($respuesta, json_encode($data));
    }

    #cuando todo esta bien en la contruccion
    public static function created(mixed ... $data ): self {
        $json_data = [];

        foreach($data as $d){
            $json_data = json_encode($d);
        }

        return new self(201, $json_data);
    }

    #respuesta inespesifica, uselo solo cuando no exista otra opcion. Que no sea ni error ni ok
    public static function response(int $status, ... $data){

        return new self($status, json_encode($data));
    }

    #error. Recomendado usar los 400 y 500
    public static function error(int $code = 400, ... $data): self {


        return new self($code, json_encode($data));
    }

    #Envia una respuesta http con todo lo almacenado anterior mente.
    public function send(): void {
        http_response_code($this->status);
        header("Content-Type: application/json");
        echo $this->body;
    }
}