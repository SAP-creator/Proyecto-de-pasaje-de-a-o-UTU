<?php
# Api de vecino (V): ve contenedores y rutas, reporta estados e incidentes.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../controladores/Controller_Contenedor.php";
include_once __DIR__ . "/../../controladores/Controller_Ruta.php";

Util_Router::run(path_api_vecino, [enum_tipo_vecino], [
        "GET /contenedor" =>            [Controller_Contenedor::class, "get_contenedores"],
        "GET /contenedor/data" =>       [Controller_Contenedor::class, "get_contenedor"],
        "POST /contenedor/estado" =>    [Controller_Contenedor::class, "add_estado"],
        "POST /contenedor/incidente" => [Controller_Contenedor::class, "create_incidente"],
        "GET /contenedor/incidente" =>  [Controller_Contenedor::class, "get_incidentes"],
        "GET /ruta" =>                  [Controller_Ruta::class, "get_rutas"],
        "GET /ruta/data" =>             [Controller_Ruta::class, "get_ruta"],
        "GET /ruta/contenedor" =>       [Controller_Ruta::class, "get_contenedor_en_slot"],
]);
