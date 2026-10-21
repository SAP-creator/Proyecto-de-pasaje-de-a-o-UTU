<?php
# Api de vecino (V): ve contenedores y rutas, reporta estados e incidentes.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../utils/Util_RouteGroups.php";

Util_Router::run(path_api_vecino, [enum_tipo_vecino], array_merge(
    Util_RouteGroups::contenedores_lectura(),
    Util_RouteGroups::reportes(),
    Util_RouteGroups::rutas_lectura()
));
