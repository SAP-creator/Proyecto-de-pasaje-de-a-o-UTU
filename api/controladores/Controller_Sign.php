<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Path.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";


class Controller_Sign {

    private const secret_key = "TuMrTiUnPo lla. QuYaQuis Yo";
    private const type_log = "SIGN CONTROLLER";

    /**
     * Genera el hash HMAC SHA-256 de una contraseña.
     * Úsalo desde otros controladores (como Controller_UserChangeData) antes de guardar o modificar la clave.
     */
    public static function hash_password(string $password): string 
    {
        return hash_hmac("sha256", $password, self::secret_key);
    }

    static public function sign_in(array $data): Util_HttpResponse 
    {
        $comp_user = $data[json_user] ?? null;

        if (!is_array($comp_user)) {
            return Util_HttpResponse::error(http_bad_request, "Estructura del objeto usuario inválida");
        }

        Util_VerifyData::keys_exists(true, $comp_user, json_ci, json_password);

        $comp_ci = $comp_user[json_ci] ?? null;
        $comp_password = $comp_user[json_password] ?? null;

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula debe ser un entero valido");
        }
        $comp_ci = (int) $comp_ci;
        
        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no puede ser negativa");
        }

        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no debe tener mas de 9 digitos");
        }

        if (!is_string($comp_password) || empty($comp_password)) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La clave debe ser texto valido");
        }
        
        try {
            $user = Model_User::get_user($comp_ci);

            if (is_null($user)) {
                return Util_HttpResponse::error(http_bad_request, "No se consiguió el usuario");
            }
            
            $sql_pass_key = Util_Translator::json_to_sql(json_password);
            $real_hash_password = $user[$sql_pass_key] ?? null;

            if (is_null($real_hash_password)) {
                return Util_HttpResponse::error(http_internal_error, "Error al obtener la contraseña almacenada");
            }

            $comp_hash_password = self::hash_password($comp_password);

            if (!hash_equals((string)$comp_hash_password, (string)$real_hash_password)) {
                return Util_HttpResponse::error(http_unaunthorize, "Clave incorrecta");
            }
            
            // Mapeo seguro de campos SQL hacia el Token
            $sql_ci_key = Util_Translator::json_to_sql(json_ci);
            $sql_complete_key = Util_Translator::json_to_sql(json_completeuser);
            $sql_type_key = Util_Translator::json_to_sql(json_typeuser);

            $token_user = [
                json_user => [
                    json_ci => $user[$sql_ci_key] ?? $comp_ci,
                    json_completeuser => $user[$sql_complete_key] ?? false,
                    json_typeuser => $user[$sql_type_key] ?? null
                ]
            ];

            Model_Log::add_log_user($comp_ci, self::type_log, "El usuario inicio sesion");

            return Util_HttpResponse::ok(Controller_Auth::create_token($token_user));
        } catch (Throwable $e) {
            return Util_HttpResponse::error(http_internal_error, "Error en inicio de sesión: " . $e->getMessage());
        }
    }

    static public function sign_up(array $data): Util_HttpResponse 
    {
        $comp_user = $data[json_user] ?? null;

        Util_VerifyData::keys_exists(true, $comp_user, json_ci, json_password, json_typeuser);

        $comp_ci = $comp_user[json_ci];
        $comp_password = $comp_user[json_password];
        $comp_typeuser = $comp_user[json_typeuser];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula debe ser un entero valido");
        }

        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no puede ser negativa");
        }

        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no debe tener mas de 9 digitos");
        }

        if (!is_string($comp_password)) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La clave debe ser texto");
        }

        if (!in_array($comp_typeuser, sql_usuario_tipo)){
            return Util_HttpResponse::error(http_unprocessable_entity, "no es un tipo de usuario valido");
        }

        try {
            if (!is_null(Model_User::get_user($comp_ci))){
                return Util_HttpResponse::error(http_conflict, "ya existe el usuario");
            }

            if (!is_null(Model_User::get_request_user($comp_ci))){
                return Util_HttpResponse::error(http_conflict, "ya existe una solicitud de usuario");
            }

            $hash_password = self::hash_password($comp_password);
            $sucess = Model_User::create_request_user($comp_ci, $hash_password, $comp_typeuser);

            if (!$sucess){
                return Util_HttpResponse::error(http_internal_error, "No se pudo insertar la solicitud");
            }

            return Util_HttpResponse::created();
        } catch (Throwable $e) {
            return Util_HttpResponse::error(http_internal_error, "Error interno: " . $e->getMessage());
        }
    }

    static public function accept_sign_up(array $data): Util_HttpResponse
    {
        $user = $data[json_user] ?? null;

        Util_VerifyData::keys_exists(true, $user, json_ci);

        $comp_ci = $user[json_ci];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula debe ser un entero valido");
        }
        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no puede ser negativa");
        }
        
        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity, "La cedula no debe tener mas de 9 digitos");
        }

        try {
            if (Model_User::has_user($comp_ci)){
                return Util_HttpResponse::error(http_unprocessable_entity, "ya existe un usuario con esa cedula");
            }

            if (!Model_User::has_request_user($comp_ci)){
                return Util_HttpResponse::error(http_unprocessable_entity, "no existe una solicitud con esa cedula");
            }

            $ci_admin = Util_VerifyData::verify_and_get_from_token($data, json_ci);
            
            $sucess = Model_User::accept_request_user($comp_ci);
            if (!$sucess){
                return Util_HttpResponse::error(http_internal_error, "Error al migrar la solicitud a usuario");
            }

            Model_Log::add_log_user($ci_admin, self::type_log, "se le acepto al usuario la solicitud de ser registrado");

            return Util_HttpResponse::created();
        } catch (Throwable $e) {
            return Util_HttpResponse::error(http_internal_error, "Error en BD: " . $e->getMessage());
        }
    }
}