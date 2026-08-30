<?php

include_once __DIR__ . "/../modelo/user_model.php";
include_once __DIR__ . "/../constantes/json_constantes.php";
include_once __DIR__ . "/../constantes/rutas_constantes.php";
include_once __DIR__ . "/../utils/resp_http.php";
include_once __DIR__ . "/../controladores/verify_data_controller.php";
include_once __DIR__ . "/../controladores/auth_controller.php";


class SignController {

    private const clave = "TuMrTiUnPo lla. QuYaQuis Yo";

    static public function sign_in(array $data): HttpResponse 
    {

        $comp_user = $data[key_user];

        // Validar que existan las keys requeridas
        VerifyDataController::keys_exists($comp_user, key_ci, key_password);

        $comp_ci = $comp_user[key_ci];
        $comp_password = $comp_user[key_password];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return HttpResponse::error("La cedula debe ser un entero valido", http_bad_request);
        }
        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return HttpResponse::error("La cedula no puede ser negativa", http_bad_request);
        }

        if (strlen((string) $comp_ci) > 9) {
            return HttpResponse::error("La cedula no debe tener mas de 9 digitos", http_bad_request);
        }

        if (!is_string($comp_password)) {
            return HttpResponse::error("La clave debe ser texto", http_bad_request);
        }

        $user = UserModel::get_user($comp_ci);

        if (is_null($user)){
            return HttpResponse::error("",http_bad_request);
        }
        
        $real_hash_password = $user[sql_clave];

        $comp_hash_password = hash_hmac("sha256",$comp_password,self::clave);
        $comp_hash_string = (string) $comp_hash_password;
        $real_hash_string = (string) $real_hash_password;



        if (!hash_equals($comp_hash_string, $real_hash_string)) {
            return HttpResponse::error("clave incorrecta", http_unaunthorize);
        }
        
        $token_user = [
            key_user => [
                key_ci => $user[sql_cedula],
                key_completeuser => $user[sql_usuario_completo],
                key_typeuser => $user[sql_tipo]
                
            ]
        ];
        #para mi yo del futuro cercano. Para todo lo que es agregar cosas al token, es aca donde debes trabajar.

        return HttpResponse::ok(AuthController::create_token($token_user)) ;

    }


    static public function sign_up(array $data): HttpResponse 
    {

        $comp_user = $data[key_user];
        // Validar que existan las keys requeridas
        VerifyDataController::keys_exists($comp_user, key_ci, key_password, key_typeuser);

        $comp_ci = $comp_user[key_ci];
        $comp_password = $comp_user[key_password];
        $comp_typeuser = $comp_user[key_typeuser];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return HttpResponse::error("La cedula debe ser un entero valido", http_bad_request);
        }

        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return HttpResponse::error("La cedula no puede ser negativa", http_bad_request);
        }

        if (strlen((string) $comp_ci) > 9) {
            return HttpResponse::error("La cedula no debe tener mas de 9 digitos", http_bad_request);
        }

        if (!is_string($comp_password)) {
            return HttpResponse::error("La clave debe ser texto", http_bad_request);
        }

        if (! in_array($comp_typeuser, sql_usuario_tipo)){
            return HttpResponse::error("no es un tipo de usuario valido", http_bad_request);
        }


        if (! is_null(UserModel::get_user($comp_ci))){
            return HttpResponse::error("ya existe el usuario",http_bad_request);
        }

        if (! is_null(UserModel::get_request_user($comp_ci))){
            return HttpResponse::error("ya existe una solicitud de usuario",http_bad_request);
        }
        

        $hash_password = hash_hmac("sha256",$comp_password,self::clave);
        $sucess = UserModel::create_request_user($comp_ci, $hash_password, $comp_typeuser);

        if ($sucess != true){
            return HttpResponse::error("error interno", http_internal_error);
        }

        return HttpResponse::ok();

    }

    static public function accept_sign_up(array $data): HttpResponse
    {
       
        $user = $data[key_user];

        VerifyDataController::keys_exists($user, key_ci);

        $comp_ci = $user[key_ci];

        if (filter_var($comp_ci, FILTER_VALIDATE_INT) === false) {
            return HttpResponse::error("La cedula debe ser un entero valido", http_bad_request);
        }
        $comp_ci = (int) $comp_ci;

        if ($comp_ci < 0) {
            return HttpResponse::error("La cedula no puede ser negativa", http_bad_request);
        }
        
        if (strlen((string) $comp_ci) > 9) {
            return HttpResponse::error("La cedula no debe tener mas de 9 digitos", http_bad_request);
        }
        if (UserModel::has_user($comp_ci)){
            return HttpResponse::error("ya existe un usuario con esa cedula", http_bad_request);
        }

        if (! UserModel::has_request_user($comp_ci)){
            return HttpResponse::error("no existe una solicitud con esa cedula", http_bad_request);
        }
        
        $sucess = UserModel::accept_request_user($comp_ci);
        if ($sucess != true){
            return HttpResponse::error("error interno", http_bad_request);
        }

        return HttpResponse::ok();

    }
}