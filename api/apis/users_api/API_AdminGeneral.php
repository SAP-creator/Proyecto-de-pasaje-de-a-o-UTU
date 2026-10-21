<?php
# Api de admin general / planificador (AP): CRUD de contenedores, centros y rutas + lo de AO salvo retirar.

include_once __DIR__ . "/../../constantes/Const_Path.php";
include_once __DIR__ . "/../../constantes/Const_Sql.php";
include_once __DIR__ . "/../../utils/Util_Router.php";
include_once __DIR__ . "/../../utils/Util_RouteGroups.php";

Util_Router::run(path_api_admin_general, [enum_tipo_admin_general], array_merge(
    Util_RouteGroups::contenedores_lectura(),
    Util_RouteGroups::reportes(),
    Util_RouteGroups::rutas_lectura(),
    Util_RouteGroups::centros_lectura(),
    Util_RouteGroups::incidentes_gestion(),
    Util_RouteGroups::ver_retiros(),
    Util_RouteGroups::contenedor_crud(),
    Util_RouteGroups::centro_crud(),
    Util_RouteGroups::ruta_crud()
));
