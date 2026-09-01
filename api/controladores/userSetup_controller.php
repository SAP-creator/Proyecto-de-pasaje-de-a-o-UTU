<?php

include_once __DIR__ . "/../controladores/userSetup_controller.php";
include_once __DIR__ . "/../utils/resp_http.php";
include_once __DIR__ . "/../controladores/verify_data_controller.php";
include_once __DIR__ . "/../constantes/json_constantes.php";



class UserSetupController{

    #si el usuario dentro del la base de datos esta completo entonces deja seguir, en el caso contrario envia un HttpResponse y hace un die
    #PREPARE THY SELF #me lo dice un rey a cada rato
    public static function user_is_complete(array $data)
    {
        VerifyDataController::keys_exists(true, $data, key_user);
        
        $user = $data[key_user];

        VerifyDataController::keys_exists(true, $user, key_ci);

        $ci = $data[key_ci];

        $is_complete = UserSetupModel::user_is_complete($ci);

        if ( is_array($is_complete) )
        {
            HttpResponse::error(json_encode($is_complete), http_forbidden)->send();
            die;
        }            
        
        if ( is_null($is_complete) )
        {       
            HttpResponse::error("",http_internal_error)->send();
            die;
        }


    }

    #completa el usuario con toda la informacion que le falta. Rellena todas las que puede pero tira un warning si falta alguna (null)
    #cuando todos los datos del usuario (no son null) entonces cambia datos completos a true.
    public static function complet_user(array $data){

    }

}