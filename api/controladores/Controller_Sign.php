<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Path.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

class Controller_Sign 
{
    private const SECRET_KEY = "TuMrTiUnPo lla. QuYaQuis Yo";
    private const LOG_TYPE = "SIGN CONTROLLER";

    /**
     * Genera el hash HMAC SHA-256 de una contraseña.
     */
    public static function hash_password(string $password): string 
    {
        return hash_hmac("sha256", $password, self::SECRET_KEY);
    }

    public static function sign_in(array $data): Util_HttpResponse 
    {
        $user_payload = $data[json_user] ?? null;

        if (!is_array($user_payload)) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de inicio de sesión fallido: Estructura de usuario inválida");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Estructura del objeto usuario inválida"),
                http_bad_request
            );
        }

        Util_VerifyData::keys_exists(true, $user_payload, json_ci, json_password);

        $input_ci = $user_payload[json_ci] ?? null;
        $input_password = $user_payload[json_password] ?? null;

        if (filter_var($input_ci, FILTER_VALIDATE_INT) === false) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de inicio de sesión fallido: Cédula no entera");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula debe ser un entero válido"),
                http_unprocessable_entity
            );
        }
        
        $target_ci = (int) $input_ci;

        if ($target_ci < 0) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de inicio de sesión fallido: Cédula negativa");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no puede ser negativa"),
                http_unprocessable_entity
            );
        }

        if (strlen((string) $target_ci) > 9) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de inicio de sesión fallido: Cédula excede 9 dígitos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no debe tener más de 9 dígitos"),
                http_unprocessable_entity
            );
        }

        if (!is_string($input_password) || empty($input_password)) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de inicio de sesión fallido: Contraseña vacía o con formato inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La clave debe ser texto válido"),
                http_unprocessable_entity
            );
        }

        try {
            $user_record = Model_User::get_user($target_ci);

            if (is_null($user_record)) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de inicio de sesión fallido: Usuario no encontrado");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No se consiguió el usuario"),
                    http_bad_request
                );
            }

            $sql_pass_key = Util_Translator::json_to_sql(json_password);
            $stored_hash_password = $user_record[$sql_pass_key] ?? null;

            if (is_null($stored_hash_password)) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Error interno en inicio de sesión: Contraseña no registrada en BD");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error al obtener la contraseña almacenada"),
                    http_internal_error
                );
            }

            $computed_hash_password = self::hash_password($input_password);

            if (!hash_equals((string)$computed_hash_password, (string)$stored_hash_password)) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de inicio de sesión fallido: Contraseña incorrecta");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Clave incorrecta"),
                    http_unaunthorize
                );
            }

            $sql_ci_key = Util_Translator::json_to_sql(json_ci);
            $sql_complete_key = Util_Translator::json_to_sql(json_completeuser);
            $sql_type_key = Util_Translator::json_to_sql(json_typeuser);

            $token_user_data = [
                json_user => [
                    json_ci => $user_record[$sql_ci_key] ?? $target_ci,
                    json_completeuser => $user_record[$sql_complete_key] ?? false,
                    json_typeuser => $user_record[$sql_type_key] ?? null
                ]
            ];

            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Inicio de sesión exitoso");

            return Util_HttpResponse::ok(
                Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::LOG_TYPE, "Inicio de sesión exitoso"),
                Controller_Auth::create_token($token_user_data)
            );
        } catch (Throwable $exception) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Excepción en sign_in: " . $exception->getMessage());
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error en inicio de sesión: " . $exception->getMessage()),
                http_internal_error
            );
        }
    }

    public static function sign_up(array $data): Util_HttpResponse 
    {
        $user_payload = $data[json_user] ?? null;

        Util_VerifyData::keys_exists(true, $user_payload, json_ci, json_password, json_typeuser);

        $input_ci = $user_payload[json_ci];
        $input_password = $user_payload[json_password];
        $input_typeuser = $user_payload[json_typeuser];

        if (filter_var($input_ci, FILTER_VALIDATE_INT) === false) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de registro fallido: Cédula no entera");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula debe ser un entero válido"),
                http_unprocessable_entity
            );
        }

        $target_ci = (int) $input_ci;

        if ($target_ci < 0) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de registro fallido: Cédula negativa");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no puede ser negativa"),
                http_unprocessable_entity
            );
        }

        if (strlen((string) $target_ci) > 9) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de registro fallido: Cédula excede 9 dígitos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no debe tener más de 9 dígitos"),
                http_unprocessable_entity
            );
        }

        if (!is_string($input_password)) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de registro fallido: Contraseña no es una cadena válida");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La clave debe ser texto"),
                http_unprocessable_entity
            );
        }

        if (!in_array($input_typeuser, sql_usuario_tipo)) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Intento de registro fallido: Tipo de usuario inválido ({$input_typeuser})");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No es un tipo de usuario válido"),
                http_unprocessable_entity
            );
        }

        try {
            if (!is_null(Model_User::get_user($target_ci))) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Registro duplicado rechazado: El usuario ya existe");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Ya existe el usuario"),
                    http_conflict
                );
            }

            if (!is_null(Model_User::get_request_user($target_ci))) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Registro duplicado rechazado: Ya existe una solicitud de registro previa");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Ya existe una solicitud de usuario"),
                    http_conflict
                );
            }

            $hashed_password = self::hash_password($input_password);
            $is_created = Model_User::create_request_user($target_ci, $hashed_password, $input_typeuser);

            if (!$is_created) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Error en BD: No se pudo insertar la solicitud de registro");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No se pudo insertar la solicitud"),
                    http_internal_error
                );
            }

            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Solicitud de registro enviada con éxito como tipo: {$input_typeuser}");

            return Util_HttpResponse::created(
                Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::LOG_TYPE, "Solicitud de registro enviada con éxito como tipo: {$input_typeuser}")
            );
        } catch (Throwable $exception) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Excepción en sign_up: " . $exception->getMessage());
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error interno: " . $exception->getMessage()),
                http_internal_error
            );
        }
    }

    public static function accept_sign_up(array $data): Util_HttpResponse
    {
        $user_payload = $data[json_user] ?? null;

        Util_VerifyData::keys_exists(true, $user_payload, json_ci);

        $input_ci = $user_payload[json_ci];

        if (filter_var($input_ci, FILTER_VALIDATE_INT) === false) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Aprobación de registro fallida: Cédula no entera");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula debe ser un entero válido"),
                http_unprocessable_entity
            );
        }

        $target_ci = (int) $input_ci;

        if ($target_ci < 0) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Aprobación de registro fallida: Cédula negativa");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no puede ser negativa"),
                http_unprocessable_entity
            );
        }

        if (strlen((string) $target_ci) > 9) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Aprobación de registro fallida: Cédula excede 9 dígitos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "La cédula no debe tener más de 9 dígitos"),
                http_unprocessable_entity
            );
        }

        try {
            if (Model_User::has_user($target_ci)) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Aprobación de registro rechazada: Usuario activo ya registrado");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Ya existe un usuario con esa cédula"),
                    http_unprocessable_entity
                );
            }

            if (!Model_User::has_request_user($target_ci)) {
                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Aprobación de registro rechazada: No se encontró solicitud pendiente");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "No existe una solicitud con esa cédula"),
                    http_unprocessable_entity
                );
            }

            $admin_ci = Util_VerifyData::verify_and_get_from_token($data, json_ci);

            $is_accepted = Model_User::accept_request_user($target_ci);

            if (!$is_accepted) {
                Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Error al migrar la solicitud a usuario activo para la CI: {$target_ci}");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error al migrar la solicitud a usuario"),
                    http_internal_error
                );
            }

            Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "El administrador aprobó exitosamente la solicitud del usuario CI: {$target_ci}");

            return Util_HttpResponse::created(
                Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::LOG_TYPE, "El administrador aprobó exitosamente la solicitud del usuario CI: {$target_ci}")
            );
        } catch (Throwable $exception) {
            Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Excepción en accept_sign_up: " . $exception->getMessage());
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::LOG_TYPE, "Error en BD: " . $exception->getMessage()),
                http_internal_error
            );
        }
    }
}
