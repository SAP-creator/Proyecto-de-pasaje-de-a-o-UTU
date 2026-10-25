<?php
# Api de operador de camion (O): lo del vecino + llenado, vaciado y ver centros.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../controladores/Controller_Contenedor.php";
include_once __DIR__ . "/../../controladores/Controller_Centro.php";
include_once __DIR__ . "/../../controladores/Controller_Ruta.php";
include_once __DIR__ . "/../../controladores/Controller_Camion.php";
include_once __DIR__ . "/../../controladores/Controller_Trayecto.php";
include_once __DIR__ . "/../../controladores/Controller_Ejecucion.php";

Util_Router::run(path_api_operador, [enum_tipo_operario], [
        "GET /contenedor" =>            [Controller_Contenedor::class, "get_contenedores"],
        "GET /contenedor/data" =>       [Controller_Contenedor::class, "get_contenedor"],
        "POST /contenedor/estado" =>    [Controller_Contenedor::class, "add_estado"],
        "POST /contenedor/incidente" => [Controller_Contenedor::class, "create_incidente"],
        "GET /contenedor/incidente" =>  [Controller_Contenedor::class, "get_incidentes"],
        "POST /contenedor/llenado" =>   [Controller_Contenedor::class, "add_llenado"],
        "POST /contenedor/vaciado" =>   [Controller_Contenedor::class, "empty_contenedor"],
        "GET /centro" =>                [Controller_Centro::class, "get_centros"],
        "GET /centro/data" =>           [Controller_Centro::class, "get_centro"],
        "GET /ruta" =>                  [Controller_Ruta::class, "get_rutas"],
        "GET /ruta/data" =>             [Controller_Ruta::class, "get_ruta"],
        "GET /ruta/contenedor" =>       [Controller_Ruta::class, "get_contenedor_en_slot"],
        "GET /camion" =>                [Controller_Camion::class, "get_camiones"],
        "GET /camion/data" =>           [Controller_Camion::class, "get_camion"],
        "GET /trayecto" =>              [Controller_Trayecto::class, "get_trayectos"],
        "GET /trayecto/data" =>         [Controller_Trayecto::class, "get_trayecto"],
        "GET /ejecucion" =>             [Controller_Ejecucion::class, "get_ejecuciones"],
        "GET /ejecucion/data" =>        [Controller_Ejecucion::class, "get_ejecucion"],
        "POST /ejecucion/iniciar" =>    [Controller_Ejecucion::class, "iniciar"],
        "POST /ejecucion/pausar" =>     [Controller_Ejecucion::class, "pausar"],
        "POST /ejecucion/reanudar" =>   [Controller_Ejecucion::class, "reanudar"],
        "POST /ejecucion/terminar" =>   [Controller_Ejecucion::class, "terminar"],
        "POST /ejecucion/cancelar" =>   [Controller_Ejecucion::class, "cancelar"],
]);
