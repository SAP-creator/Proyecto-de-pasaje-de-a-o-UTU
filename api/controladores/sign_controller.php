<?

include_once __DIR__ . "/../modelo/user_model.php";
include_once __DIR__ . "/../constantes/json_constantes.php";
include_once __DIR__ . "/../constantes/rutas_constantes.php";
include_once __DIR__ . "/../utils/resp_http.php";



class SingController{
    static public function sing_in(array $data): HttpResponse{
        $ci = $data[key_ci];
        $password = $data[key_password];

        if (! is_int($ci)){
            return HttpResponse::error("La cedula debe ser un int", http_bad_request);
        }
        if (! is_string($password)){
            return HttpResponse::error("La clave debe ser texto", http_bad_request);
        }

        $user = UserModel::get_user($ci);

        return HttpResponse::ok($user);
    }
}