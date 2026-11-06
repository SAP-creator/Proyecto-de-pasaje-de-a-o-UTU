<?php
# Api publica de usuario: sign in / sign up (sin token) y perfil de cualquier rol (el controlador valida el token).

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../controladores/Controller_Sign.php";
include_once __DIR__ . "/../../controladores/Controller_ChangeUser.php";

Util_Router::run(path_api_user, null, [
    "POST /sign/in" => [Controller_Sign::class, "sign_in"],
    "POST /sign/up" => [Controller_Sign::class, "sign_up"],

    // Modifica los datos propios (lo permitido por Const_Modificable segun el tipo del token)
    "PUT /profile"  => [Controller_ChangeUser::class, "modify_own_data"],

    // Completa los datos que faltan. Funciona con el perfil incompleto
    "PUT /complete" => [Controller_ChangeUser::class, "complete_user"],
]);
