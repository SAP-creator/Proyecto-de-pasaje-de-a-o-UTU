<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/Controller_Auth.php";
include_once __DIR__ . "/Controller_Sign.php";
include_once __DIR__ . "/Controller_UserChangeData.php";

class Controller_UserSetup 
{
    private const LOG_TYPE = "USER SETUP CONTROLLER";

    /**
     * Verifica si el usuario autenticado tiene el perfil completo.
     */
    public static function user_is_complete(array $data): void
    {
        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);

        // 1. Extraer directamente la propiedad que indica si está completo desde el Token
        $is_complete_flag = Util_VerifyData::verify_and_get_from_token($data, json_completeuser);

        // Si en el token figura como completo (true, 1, 's', etc.), se aprueba la ejecución
        if ($is_complete_flag === true || $is_complete_flag === 1 || $is_complete_flag === 's') {
            return;
        }

        // 2. SOLO si el Token indica que no está completo, consultamos la BD para averiguar qué campos le faltan
        $incomplete_fields = self::find_incomplete_data($data);

        if (is_array($incomplete_fields)) {
            $translated_fields = [];
            foreach ($incomplete_fields as $field) {
                array_push($translated_fields, Util_Translator::sql_to_json($field) ?? []);
            }

            Model_Log::add_log_user($ci, self::LOG_TYPE, "Acceso denegado: El usuario tiene el perfil incompleto");

            Util_HttpResponse::error(
                http_forbidden,
                [json_error => "Usuario incompleto, por favor complete los datos para continuar"],
                $translated_fields
            )->send();
            exit;
        }            
        
        // Error fallback si ocurrió un problema en la consulta
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Error interno al verificar si el usuario está completo");
        Util_HttpResponse::error(http_internal_error)->send();
        exit;
    }

    private static function find_incomplete_data(array $data, bool $table = false): ?array 
    {
        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        $complete_data = Model_User::find_incomplete_data($typeuser, $ci);
        
        if (!is_array($complete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar campos incompletos");
            return null;
        }
        
        $data_result = [];
        $separator = "__";

        foreach ($complete_data as $key => $is_null) {
            if ($is_null) { 
                if (!$table) {
                    $p = strpos($key, $separator);
                    if ($p !== false) {
                        $key = substr($key, $p + strlen($separator));
                    }
                }
                
                array_push($data_result, $key);
            }
        }

        return $data_result;
    }

    public static function complete_user(array $data): Util_HttpResponse 
    {
        if (Controller_Auth::comprobate_token($data) !== true) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de completar usuario fallido: Token inválido");
            return Util_HttpResponse::error(http_forbidden, "No tienes un token válido");
        }

        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);

        // Verificar primero en el Token antes de hacer peticiones
        $is_complete_flag = Util_VerifyData::verify_and_get_from_token($data, json_completeuser);
        if ($is_complete_flag === true || $is_complete_flag === 1 || $is_complete_flag === 's') {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Completado omitido: El usuario ya posee el perfil completo");
            return Util_HttpResponse::ok("El usuario ya está completo");
        }

        // Obtener los datos faltantes de la BD
        $untranslate_incomplete_data = self::find_incomplete_data($data, false);

        if (is_null($untranslate_incomplete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error interno al recuperar datos faltantes para completar perfil");
            return Util_HttpResponse::error(http_internal_error, "Error en la base de datos");
        }

        if (empty($untranslate_incomplete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Completado omitido: Todos los datos ya están ingresados en BD");
            return Util_HttpResponse::ok("Están todos los datos completos");
        }

        // Traducir campos faltantes de SQL a JSON
        $translate_incomplete_data = [];
        foreach ($untranslate_incomplete_data as $un_data) {
            array_push($translate_incomplete_data, Util_Translator::sql_to_json($un_data));
        }
        
        Util_VerifyData::keys_exists(true, $data, json_user);

        $data_to_change = [];
        
        foreach ($data[json_user] as $field_key => $field_value) {
            if (in_array($field_key, $translate_incomplete_data, true)) {
                // Si el campo a actualizar es la contraseña, le aplicamos el hash HMAC
                if ($field_key === json_password) {
                    $field_value = Controller_Sign::hash_password((string) $field_value);
                }

                $data_to_change[$field_key] = $field_value;
            }
        }

        if (empty($data_to_change)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Intento de completado fallido: No se enviaron datos faltantes válidos");
            return Util_HttpResponse::error(http_bad_request, "No se enviaron datos válidos o faltantes para actualizar");
        }

        $data[json_user] = $data_to_change;

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Procesando actualización de campos para completar el perfil");

        // Ejecutar la actualización a través del controlador correspondiente
        return Controller_UserChangeData::user_change_data_by_user($data);
    }
}