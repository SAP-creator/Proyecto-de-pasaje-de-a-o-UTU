<?php
# Api de admin operador (AO): lo del vecino + ver centros, gestionar incidentes, retirar y ver retiros.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../utils/Util_RouteGroups.php";

Util_Router::run(path_api_admin_operador, [enum_tipo_admin_operador], array_merge(
    Util_RouteGroups::contenedores_lectura(),
    Util_RouteGroups::reportes(),
    Util_RouteGroups::rutas_lectura(),
    Util_RouteGroups::centros_lectura(),
    Util_RouteGroups::incidentes_gestion(),
    Util_RouteGroups::retirar(),
    Util_RouteGroups::ver_retiros()
));
