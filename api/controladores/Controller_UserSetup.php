<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/Controller_Auth.php";
include_once __DIR__ . "/Controller_UserChangeData.php";

class Controller_UserSetup {

    public static function user_is_complete(array $data): void
    {
        Controller_VerifyData::keys_exists(true, $data, json_token);
        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);
        
        $user = $data[json_token][json_user];

        Controller_VerifyData::keys_exists(true, $user, json_ci);
        
        $ci = (int) $user[json_ci];

        $complete = Model_User::user_is_complete($ci);

        if ($complete === true) {
            return;
        }

        if (is_null($complete)) {
            Util_HttpResponse::error(http_internal_error)->send();
            die;
        }

        $is_complete = self::find_incomplete_data($data);
        if (is_array($is_complete)) {
            $a = [];
            foreach ($is_complete as $d) {
                array_push($a, Util_Translator::sql_to_json($d) ?? []);
            }
            Util_HttpResponse::error(
                http_forbidden,
                [json_error => "usuario incompleto porfavor complete los datos del usuario para poder realizar opciones"],
                $a
            )->send();
            die;
        }            
        
        if (is_null($is_complete)) {       
            Util_HttpResponse::error(http_internal_error)->send();
            die;
        }
    }

    private static function find_incomplete_data(array $data, bool $table = false): ?array 
    {
        Controller_VerifyData::keys_exists(true, $data, json_token);
        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);

        $user = $data[json_token][json_user];
        Controller_VerifyData::keys_exists(true, $user, json_typeuser, json_ci);

        $ci = (int) $user[json_ci];
        $typeuser = (string) $user[json_typeuser];

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
        Controller_VerifyData::keys_exists(true, $data, json_token);

        if (Controller_Auth::comprobate_token($data, json_token) !== true) {
            return Util_HttpResponse::error(http_forbidden, "No tienes un token valido");
        }

        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);
        $user_token = $data[json_token][json_user];
        
        Controller_VerifyData::keys_exists(true, $user_token, json_ci);
        $ci = (int) $user_token[json_ci];
    
        if (Model_User::user_is_complete($ci)){
            return Util_HttpResponse::ok("el usuario ya esta completo");
        }


        // Verificar datos faltantes antes de intentar actualizar
        $untranslate_incomplete_data = self::find_incomplete_data($data, false);

        if (is_null($untranslate_incomplete_data)) {
            return Util_HttpResponse::error(http_internal_error, "Error en la base de datos");
        }

        if (empty($untranslate_incomplete_data)) {
            return Util_HttpResponse::ok("Estan todos los datos completos");
        }

        // Traducir campos faltantes de SQL a JSON
        $translate_incomplete_data = [];
        foreach ($untranslate_incomplete_data as $un_data) {
            array_push($translate_incomplete_data, Util_Translator::sql_to_json($un_data));
        }
        
        // Verificar que venga el payload de datos del usuario
        Controller_VerifyData::keys_exists(true, $data, json_user);

        $data_to_change = [];
        
        // Recorremos los datos enviados por el usuario ($key => $value)
        foreach ($data[json_user] as $field_key => $field_value) {
            // Opcional: Solo guardar si el campo enviado está dentro de la lista de incompletos
            if (in_array($field_key, $translate_incomplete_data, true)) {
                $data_to_change[$field_key] = $field_value;
            }
        }

        if (empty($data_to_change)) {
            return Util_HttpResponse::error(http_bad_request, "No se enviaron datos validos o faltantes para actualizar");
        }
        
        // Asignamos solo los campos filtrados
        $data[json_user] = $data_to_change;

        // Ejecutar la actualización
        return Controller_UserChangeData::user_change_data_by_user($data);
    }
}