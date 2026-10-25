<?php
# Api de admin general / planificador (AP): CRUD de contenedores, centros y rutas + lo de AO salvo retirar.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../controladores/Controller_Contenedor.php";
include_once __DIR__ . "/../../controladores/Controller_Centro.php";
include_once __DIR__ . "/../../controladores/Controller_Ruta.php";
include_once __DIR__ . "/../../controladores/Controller_Camion.php";
include_once __DIR__ . "/../../controladores/Controller_Trayecto.php";
include_once __DIR__ . "/../../controladores/Controller_Ejecucion.php";

Util_Router::run(path_api_admin_general, [enum_tipo_admin_general], [
        "GET /contenedor"      =>           [Controller_Contenedor::class, "get_contenedores"],
        "GET /contenedor/data" =>           [Controller_Contenedor::class, "get_contenedor"],
        "POST /contenedor/estado"    =>     [Controller_Contenedor::class, "add_estado"],
        "POST /contenedor/incidente" =>     [Controller_Contenedor::class, "create_incidente"],
        "GET /contenedor/incidente"  =>     [Controller_Contenedor::class, "get_incidentes"],
        "PUT /contenedor/incidente"  =>     [Controller_Contenedor::class, "update_incidente"],
        "DELETE /contenedor/incidente" =>   [Controller_Contenedor::class, "delete_incidente"],
        "POST /contenedor"       =>         [Controller_Contenedor::class, "create_contenedor"],
        "PUT /contenedor"        =>         [Controller_Contenedor::class, "update_contenedor"],
        "DELETE /contenedor"     =>         [Controller_Contenedor::class, "delete_contenedor"],
        "GET /centro"            =>         [Controller_Centro::class, "get_centros"],
        "GET /centro/data"       =>         [Controller_Centro::class, "get_centro"],
        "POST /centro"           =>         [Controller_Centro::class, "create_centro"],
        "PUT /centro"            =>         [Controller_Centro::class, "update_centro"],
        "DELETE /centro"         =>         [Controller_Centro::class, "delete_centro"],
        "GET /centro/retiro"     =>         [Controller_Centro::class, "get_retiros"],
        "GET /ruta"              =>         [Controller_Ruta::class, "get_rutas"],
        "GET /ruta/data"         =>         [Controller_Ruta::class, "get_ruta"],
        "GET /ruta/contenedor"   =>         [Controller_Ruta::class, "get_contenedor_en_slot"],
        "POST /ruta"             =>         [Controller_Ruta::class, "create_ruta"],
        "PUT /ruta"              =>         [Controller_Ruta::class, "update_ruta"],
        "DELETE /ruta"           =>         [Controller_Ruta::class, "delete_ruta"],
        "GET /camion"            =>         [Controller_Camion::class, "get_camiones"],
        "GET /camion/data"       =>         [Controller_Camion::class, "get_camion"],
        "GET /trayecto"          =>         [Controller_Trayecto::class, "get_trayectos"],
        "GET /trayecto/data"     =>         [Controller_Trayecto::class, "get_trayecto"],
        "GET /ejecucion"         =>         [Controller_Ejecucion::class, "get_ejecuciones"],
        "GET /ejecucion/data"    =>         [Controller_Ejecucion::class, "get_ejecucion"],
        "POST /ejecucion/aprobar"  =>       [Controller_Ejecucion::class, "aprobar"],
        "POST /ejecucion/rechazar" =>       [Controller_Ejecucion::class, "rechazar"],
]);
