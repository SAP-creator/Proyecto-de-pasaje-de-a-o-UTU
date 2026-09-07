<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/Controller_Auth.php";
include_once __DIR__ . "/Controller_Sign.php";
include_once __DIR__ . "/Controller_UserChangeData.php";

class Controller_UserSetup 
{
    /**
     * Verifica si el usuario autenticado tiene el perfil completo.
     */
    public static function user_is_complete(array $data): void
    {
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

            Util_HttpResponse::error(
                http_forbidden,
                [json_error => "Usuario incompleto, por favor complete los datos para continuar"],
                $translated_fields
            )->send();
            exit;
        }            
        
        // Error fallback si ocurrió un problema en la consulta
        Util_HttpResponse::error(http_internal_error)->send();
        exit;
    }

    private static function find_incomplete_data(array $data, bool $table = false): ?array 
    {
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        $complete_data = Model_User::find_incomplete_data($typeuser, $ci);
        
        if (!is_array($complete_data)) {
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
            return Util_HttpResponse::error(http_forbidden, "No tienes un token válido");
        }

        // Verificar primero en el Token antes de hacer peticiones
        $is_complete_flag = Util_VerifyData::verify_and_get_from_token($data, json_completeuser);
        if ($is_complete_flag === true || $is_complete_flag === 1 || $is_complete_flag === 's') {
            return Util_HttpResponse::ok("El usuario ya está completo");
        }

        // Obtener los datos faltantes de la BD
        $untranslate_incomplete_data = self::find_incomplete_data($data, false);

        if (is_null($untranslate_incomplete_data)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la base de datos");
        }

        if (empty($untranslate_incomplete_data)) {
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
            return Util_HttpResponse::error(http_bad_request, "No se enviaron datos válidos o faltantes para actualizar");
        }
        
        $data[json_user] = $data_to_change;

        // Ejecutar la actualización a través del controlador correspondiente
        return Controller_UserChangeData::user_change_data_by_user($data);
    }
}