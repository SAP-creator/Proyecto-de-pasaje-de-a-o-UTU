<?php
# Api de admin operador (AO): lo del vecino + ver centros, gestionar incidentes, retirar y ver retiros.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../controladores/Controller_Contenedor.php";
include_once __DIR__ . "/../../controladores/Controller_Centro.php";
include_once __DIR__ . "/../../controladores/Controller_Ruta.php";
include_once __DIR__ . "/../../controladores/Controller_Camion.php";
include_once __DIR__ . "/../../controladores/Controller_Trayecto.php";
include_once __DIR__ . "/../../controladores/Controller_Ejecucion.php";

Util_Router::run(path_api_admin_operador, [enum_tipo_admin_operador], [
        "GET /contenedor" =>               [Controller_Contenedor::class, "get_contenedores"],
        "GET /contenedor/data" =>          [Controller_Contenedor::class, "get_contenedor"],
        "POST /contenedor/estado" =>       [Controller_Contenedor::class, "add_estado"],
        "POST /contenedor/incidente" =>    [Controller_Contenedor::class, "create_incidente"],
        "GET /contenedor/incidente" =>     [Controller_Contenedor::class, "get_incidentes"],
        "PUT /contenedor/incidente" =>     [Controller_Contenedor::class, "update_incidente"],
        "DELETE /contenedor/incidente" =>  [Controller_Contenedor::class, "delete_incidente"],
        "GET /centro" =>                   [Controller_Centro::class, "get_centros"],
        "GET /centro/data" =>              [Controller_Centro::class, "get_centro"],
        "POST /centro/retiro" =>           [Controller_Centro::class, "retirar"],
        "GET /centro/retiro" =>            [Controller_Centro::class, "get_retiros"],
        "GET /ruta" =>                     [Controller_Ruta::class, "get_rutas"],
        "GET /ruta/data" =>                [Controller_Ruta::class, "get_ruta"],
        "GET /ruta/contenedor" =>          [Controller_Ruta::class, "get_contenedor_en_slot"],
        "GET /camion" =>                   [Controller_Camion::class, "get_camiones"],
        "GET /camion/data" =>              [Controller_Camion::class, "get_camion"],
        "POST /camion" =>                  [Controller_Camion::class, "create_camion"],
        "PUT /camion" =>                   [Controller_Camion::class, "update_camion"],
        "DELETE /camion" =>                [Controller_Camion::class, "delete_camion"],
        "GET /trayecto" =>                 [Controller_Trayecto::class, "get_trayectos"],
        "GET /trayecto/data" =>            [Controller_Trayecto::class, "get_trayecto"],
        "POST /trayecto" =>                [Controller_Trayecto::class, "create_trayecto"],
        "PUT /trayecto" =>                 [Controller_Trayecto::class, "update_trayecto"],
        "DELETE /trayecto" =>              [Controller_Trayecto::class, "delete_trayecto"],
        "GET /ejecucion" =>                [Controller_Ejecucion::class, "get_ejecuciones"],
        "GET /ejecucion/data" =>           [Controller_Ejecucion::class, "get_ejecucion"],
        "POST /ejecucion/pausar" =>        [Controller_Ejecucion::class, "pausar"],
        "POST /ejecucion/cancelar" =>      [Controller_Ejecucion::class, "cancelar"],
]);
