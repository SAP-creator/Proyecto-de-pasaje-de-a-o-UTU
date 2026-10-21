<?php
# Api de admin de sistema (AS): usuarios, solicitudes y logs. De lo demas solo ve/reporta como el vecino.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../utils/Util_RouteGroups.php";
include_once __DIR__ . "/../../controladores/Controller_AdminSys.php";
include_once __DIR__ . "/../../controladores/Controller_Sign.php";
include_once __DIR__ . "/../../controladores/Controller_ChangeUser.php";

Util_Router::run(path_api_sysadmin, [enum_tipo_admin_sistema], array_merge(
    [
        "GET /users"           => [Controller_AdminSys::class, "get_users_data"],
        "GET /user/exists"     => [Controller_AdminSys::class, "has_user"],
        "GET /user/data"       => [Controller_AdminSys::class, "get_data_user"],
        "GET /requests"        => [Controller_AdminSys::class, "get_request_user_data"],
        "GET /requests/exists" => [Controller_AdminSys::class, "has_request_user"],
        "GET /logs/user"       => [Controller_AdminSys::class, "get_logs_user"],
        "GET /logs/users"      => [Controller_AdminSys::class, "get_logs_users"],
        "GET /logs/sql"        => [Controller_AdminSys::class, "get_logs_sql"],

        "POST /requests/accept" => [Controller_Sign::class, "accept_sign_up"],

        "PUT /user/data"       => [Controller_ChangeUser::class, "modify_user_by_admin"],

        "DELETE /user"         => [Controller_AdminSys::class, "delete_user"],
        "DELETE /requests"     => [Controller_AdminSys::class, "delete_user_request"],
    ],
    Util_RouteGroups::contenedores_lectura(),
    Util_RouteGroups::reportes(),
    Util_RouteGroups::rutas_lectura()
));
