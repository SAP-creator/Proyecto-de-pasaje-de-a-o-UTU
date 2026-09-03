<?php

include_once __DIR__ . "/../modelo/Model_UserSetup.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../constantes/Const_Json.php";


class Controller_UserSetup {
    #si el usuario dentro del la base de datos esta completo entonces deja seguir, en el caso contrario envia un HttpResponse y hace un die
    #PREPARE THY SELF #me lo dice un rey a cada rato
    public static function user_is_complete(array $data)
    {
        Controller_VerifyData::keys_exists(true,$data, json_token);
        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);
        
        $user = $data[json_token][json_user];

        Controller_VerifyData::keys_exists(true, $user, json_ci);

        $ci = $user[json_ci];

        $is_complete = self::find_incomplete_data($data);

        if ( is_array($is_complete) )
        {
            $a =[];
            foreach($is_complete as $d){
                array_push($a, self::traductor[$d]);
            }
            Util_HttpResponse::error(
                http_forbidden,
                [json_error=>"usuario incompleto porfavor complete los datos del usuario para poder realizar opciones"],
                $a
                )->send();
            die;
        }            
        
        if ( is_null($is_complete) )
        {       
            Util_HttpResponse::error(http_internal_error)->send();
            die;
        }


    }

    private static function find_incomplete_data(array $data, bool $table = false): ?array {
        Controller_VerifyData::keys_exists(true,$data, json_token);
        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);

        $user = $data[json_token][json_user];
        Controller_VerifyData::keys_exists(true, $user, json_typeuser, json_ci);

        $ci = (int) $user[json_ci];
        $typeuser = (string) $user[json_typeuser];

        $complete_data = Model_UserSetup::find_incomplete_data($typeuser, $ci);

        if (!is_array($complete_data)) {
            return null;
        }
        
        $data_result = [];
        $separator = "__";
        
        foreach ($complete_data as $key => $value) {
            // Si el dato está vacío/null en la BD, está incompleto
            if (!$value) { 
                if (!$table) {
                    // Solo nos interesa el nombre de la columna limpia
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

    public static function complete_user(array $data): Util_HttpResponse {
        include_once __DIR__ . "/Controller_Auth.php";

        Controller_VerifyData::keys_exists(true,$data,json_token);

        if (Controller_Auth::comprobate_token($data,json_token) != true){
            return Util_HttpResponse::error(http_forbidden, "No tienes un token valido");
        }


        Controller_VerifyData::keys_exists(true, $data[json_token], json_user);
        $user = $data[json_token][json_user];
        Controller_VerifyData::keys_exists(true, $user, json_ci);
        $ci = (int) $user[json_ci];

        // 2. Verificar datos faltantes a nivel general
        $incomplete_data = self::find_incomplete_data($data, false);
       

        if (is_null($incomplete_data)) {
            return Util_HttpResponse::error(http_internal_error, "error en la base de datos");
        }

        if (empty($incomplete_data)) {
            return Util_HttpResponse::ok("Estan todos los datos completos");
        }
        
        // 3. Volvemos a pedir los datos faltantes pero esta vez conservando "tabla__columna"
        $TaC = self::find_incomplete_data($data, true); 
        $separator = "__";
        $cambios_realizados = 0;

        foreach ($TaC as $key_completa) {
            $p = strpos($key_completa, $separator);
            if ($p === false) continue;

            $table = substr($key_completa, 0, $p);
            $column = substr($key_completa, $p + strlen($separator));

            if (isset(self::traductor[$column])) {
                
                #era clave....
                $json_key = self::traductor[$column];

                if (isset($data[json_user][$json_key])) {
                    $value = $data[json_user][$json_key];

                    // Guardamos en la base de datos
                    Model_User::change_data($ci, $table, $column, $value);
                    $cambios_realizados++;
                }
            }
        }  

        
        if ($cambios_realizados > 0) {
            $is_complete = self::find_incomplete_data($data);
            $text = "ninguno";
            Model_UserSetup::set_iscomplete_user(!empty($is_complete),$ci);

            
            if (!empty($is_complete))
                $text = $is_complete;
            
        

            return Util_HttpResponse::ok("Datos actualizados correctamente. Procesados: $cambios_realizados", ["Datos restantes"=>$text]);
        }
        return Util_HttpResponse::error(http_bad_request, "Se enviaron datos inesesarios."); // Envió datos pero ninguno de los que faltaban

        
    }
    const traductor = [
        sql_clave => json_password,
        sql_nombre => json_first_name,
        sql_apellido => json_last_name
    ];
}
