<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Path.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../controladores/Controller_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";


class Controller_Sign {

    private const secret_key = "TuMrTiUnPo lla. QuYaQuis Yo";
    private const type_log = "SIGN CONTROLLER";

    static public function sign_in(array $data): Util_HttpResponse 
    {
       

        $comp_user = $data[json_user];

        // Validar que existan las keys requeridas
        Controller_VerifyData::keys_exists(true, $comp_user, json_ci, json_password);

        $comp_ci = $comp_user[json_ci];
        $comp_password = $comp_user[json_password];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula debe ser un entero valido");
        }
        $comp_ci = (int) $comp_ci;
        
        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no puede ser negativa");
        }

        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no debe tener mas de 9 digitos");
        }

        if (!is_string($comp_password)) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La clave debe ser texto");
        }
        
        $user = Model_User::get_user($comp_ci);

        if (is_null($user)){
            return Util_HttpResponse::error(http_bad_request,"No se consiguo el user");
        }
        
        $real_hash_password = $user[sql_clave];

        $comp_hash_password = hash_hmac("sha256",$comp_password,self::secret_key);
        $comp_hash_string = (string) $comp_hash_password;
        $real_hash_string = (string) $real_hash_password;



        if (!hash_equals($comp_hash_string, $real_hash_string)) {
            return Util_HttpResponse::error(http_unaunthorize,"clave incorrecta");
        }
        
        $token_user = [
            json_user => [
                json_ci => $user[sql_cedula],
                json_completeuser => $user[sql_usuario_completo],
                json_typeuser => $user[sql_tipo]
                
            ]
        ];
        #para mi yo del futuro cercano. Para todo lo que es agregar cosas al token, es aca donde debes trabajar.

        Model_Log::add_log_user($comp_ci,self::type_log,"El usuario inicio sesion");

        return Util_HttpResponse::ok(Controller_Auth::create_token($token_user)) ;

    }


    static public function sign_up(array $data): Util_HttpResponse 
    {

        $comp_user = $data[json_user];
        // Validar que existan las keys requeridas
        Controller_VerifyData::keys_exists(true, $comp_user, json_ci, json_password, json_typeuser);

        $comp_ci = $comp_user[json_ci];
        $comp_password = $comp_user[json_password];
        $comp_typeuser = $comp_user[json_typeuser];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula debe ser un entero valido");
        }

        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no puede ser negativa");
        }

        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no debe tener mas de 9 digitos");
        }

        if (!is_string($comp_password)) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La clave debe ser texto");
        }

        if (! in_array($comp_typeuser, sql_usuario_tipo)){
            return Util_HttpResponse::error(http_unprocessable_entity,"no es un tipo de usuario valido");
        }


        if (! is_null(Model_User::get_user($comp_ci))){
            return Util_HttpResponse::error(http_conflict,"ya existe el usuario");
        }

        if (! is_null(Model_User::get_request_user($comp_ci))){
            return Util_HttpResponse::error(http_conflict,"ya existe una solicitud de usuario");
        }
        

        $hash_password = hash_hmac("sha256",$comp_password,self::secret_key);
        $sucess = Model_User::create_request_user($comp_ci, $hash_password, $comp_typeuser);

        if ($sucess != true){
            return Util_HttpResponse::error(http_internal_error,"error interno");
        }

        Model_Log::add_log_user($comp_ci, self::type_log, "Quiere registrarse en el sistema el user");

        return Util_HttpResponse::created();

    }

    static public function accept_sign_up(array $data): Util_HttpResponse
    {
       
        $user = $data[json_user];

        Controller_VerifyData::keys_exists(true ,$user, json_ci);

        $comp_ci = $user[json_ci];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula debe ser un entero valido");
        }
        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no puede ser negativa");
        }
        
        if (strlen((string) $comp_ci) > 9) {
            return Util_HttpResponse::error(http_unprocessable_entity,"La cedula no debe tener mas de 9 digitos");
        }
        if (Model_User::has_user($comp_ci)){
            return Util_HttpResponse::error(http_unprocessable_entity,"ya existe un usuario con esa cedula");
        }

        if (! Model_User::has_request_user($comp_ci)){
            return Util_HttpResponse::error(http_unprocessable_entity,"no existe una solicitud con esa cedula");
        }
        
        $sucess = Model_User::accept_request_user($comp_ci);
        if ($sucess != true){
            return Util_HttpResponse::error(http_internal_error,"error interno");
        }

        Model_Log::add_log_user($comp_ci, self::type_log, "se le acepto al usuario la solicitud de ser registrado");

        return Util_HttpResponse::created();

    }
}