<?php
# Api de operador de camion (O): lo del vecino + llenado, vaciado y ver centros.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../utils/Util_RouteGroups.php";

Util_Router::run(path_api_operador, [enum_tipo_operario], array_merge(
    Util_RouteGroups::contenedores_lectura(),
    Util_RouteGroups::reportes(),
    Util_RouteGroups::rutas_lectura(),
    Util_RouteGroups::llenado_vaciado(),
    Util_RouteGroups::centros_lectura()
));
