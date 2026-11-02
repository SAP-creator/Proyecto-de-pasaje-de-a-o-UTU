<?php

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/Util_RestHttp.php";
include_once __DIR__ . "/Util_Code.php";
include_once __DIR__ . "/Util_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";
include_once __DIR__ . "/../controladores/Controller_ChangeUser.php";

/**
 * Puerta de entrada comun de todas las APIs. Sin logica de negocio:
 *  1. CORS (incluye X-Token) y respuesta a OPTIONS.
 *  2. Lee el JSON: del body o, si el body viene vacio, de ?payload= (cualquier metodo).
 *  3. Si llega el header X-Token (JSON en base64) lo deja en $data[json_token].
 *  4. Si $roles no es null: valida el rol y que el perfil este completo.
 *  5. Despacha "METODO /ruta" al controlador y hace ->send().
 *
 * $routes: ["GET /contenedor" => [Controller_X::class, "metodo"], ...]
 */
class Util_Router
{
    private const SYS_NAME = "ApiRouter";

    public static function run(string $prefix, ?array $roles, array $routes): void
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Token");
        header("Content-Type: application/json; charset=UTF-8");

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        $route = self::get_route($prefix);
        $data = self::get_data();

        $token = self::get_token_from_header();
        if (!is_null($token)) {
            $data[json_token] = $token;
        }

        if (!is_null($roles)) {
            Controller_Auth::valid_user_types($data, $roles);
            Controller_ChangeUser::user_is_complete($data);
        }

        $key = "{$method} {$route}";

        if (!array_key_exists($key, $routes)) {
            $paths = array_map(fn(string $k): string => explode(" ", $k, 2)[1], array_keys($routes));
            $path_exists = in_array($route, $paths, true);

            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, self::SYS_NAME, $path_exists ? "MethodNotAllowed" : "PathNotFound"),
                $path_exists ? http_bad_request : http_not_found
            )->send();
            return;
        }

        try {
            call_user_func($routes[$key], $data)->send();
        } catch (Throwable $exception) {
            error_log("Router {$key}: " . $exception->getMessage());
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, self::SYS_NAME, "RouterException"),
                http_internal_error
            )->send();
        }
    }

    private static function get_route(string $prefix): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? "";

        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }

        return "/" . trim($path, "/");
    }

    private static function get_data(): array
    {
        $raw = file_get_contents("php://input");

        if (!is_string($raw) || trim($raw) === "") {
            $raw = $_GET['payload'] ?? "";
        }

        if (is_string($raw) && trim($raw) === "") {
            $raw = "{}";
        }

        return Util_VerifyData::valid_json($raw);
    }

    /**
     * X-Token = base64(JSON). Acepta {"TOKEN": {...}} o directamente el contenido de TOKEN.
     */
    private static function get_token_from_header(): ?array
    {
        $header = $_SERVER['HTTP_X_TOKEN'] ?? null;

        if (is_null($header)) {
            return null;
        }

        $json = base64_decode($header, true);
        $decoded = ($json === false) ? null : json_decode($json, true);

        if (!is_array($decoded)) {
            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::VIEW, self::SYS_NAME, "InvalidTokenHeader"),
                http_bad_request
            )->send();
            die();
        }

        return is_array($decoded[json_token] ?? null) ? $decoded[json_token] : $decoded;
    }
}
