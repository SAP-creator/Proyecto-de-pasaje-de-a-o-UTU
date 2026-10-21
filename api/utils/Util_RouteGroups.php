<?php

include_once __DIR__ . "/../controladores/Controller_Contenedor.php";
include_once __DIR__ . "/../controladores/Controller_Centro.php";
include_once __DIR__ . "/../controladores/Controller_Ruta.php";

/**
 * Grupos de rutas que se repiten entre roles. Cada API arma su lista sumando
 * los grupos que le corresponden (array_merge). Los controladores vuelven a
 * validar el rol, asi que esto no es la unica barrera.
 */
class Util_RouteGroups
{
    public static function contenedores_lectura(): array
    {
        return [
            "GET /contenedor"      => [Controller_Contenedor::class, "get_contenedores"],
            "GET /contenedor/data" => [Controller_Contenedor::class, "get_contenedor"],
        ];
    }

    // Estado e incidentes que puede reportar cualquier rol
    public static function reportes(): array
    {
        return [
            "POST /contenedor/estado"    => [Controller_Contenedor::class, "add_estado"],
            "POST /contenedor/incidente" => [Controller_Contenedor::class, "create_incidente"],
            "GET /contenedor/incidente"  => [Controller_Contenedor::class, "get_incidentes"],
        ];
    }

    public static function rutas_lectura(): array
    {
        return [
            "GET /ruta"            => [Controller_Ruta::class, "get_rutas"],
            "GET /ruta/data"       => [Controller_Ruta::class, "get_ruta"],
            "GET /ruta/contenedor" => [Controller_Ruta::class, "get_contenedor_en_slot"],
        ];
    }

    public static function llenado_vaciado(): array
    {
        return [
            "POST /contenedor/llenado" => [Controller_Contenedor::class, "add_llenado"],
            "POST /contenedor/vaciado" => [Controller_Contenedor::class, "empty_contenedor"],
        ];
    }

    public static function centros_lectura(): array
    {
        return [
            "GET /centro"      => [Controller_Centro::class, "get_centros"],
            "GET /centro/data" => [Controller_Centro::class, "get_centro"],
        ];
    }

    public static function incidentes_gestion(): array
    {
        return [
            "PUT /contenedor/incidente"    => [Controller_Contenedor::class, "update_incidente"],
            "DELETE /contenedor/incidente" => [Controller_Contenedor::class, "delete_incidente"],
        ];
    }

    public static function retirar(): array
    {
        return [
            "POST /centro/retiro" => [Controller_Centro::class, "retirar"],
        ];
    }

    public static function ver_retiros(): array
    {
        return [
            "GET /centro/retiro" => [Controller_Centro::class, "get_retiros"],
        ];
    }

    public static function contenedor_crud(): array
    {
        return [
            "POST /contenedor"   => [Controller_Contenedor::class, "create_contenedor"],
            "PUT /contenedor"    => [Controller_Contenedor::class, "update_contenedor"],
            "DELETE /contenedor" => [Controller_Contenedor::class, "delete_contenedor"],
        ];
    }

    public static function centro_crud(): array
    {
        return [
            "POST /centro"   => [Controller_Centro::class, "create_centro"],
            "PUT /centro"    => [Controller_Centro::class, "update_centro"],
            "DELETE /centro" => [Controller_Centro::class, "delete_centro"],
        ];
    }

    public static function ruta_crud(): array
    {
        return [
            "POST /ruta"   => [Controller_Ruta::class, "create_ruta"],
            "PUT /ruta"    => [Controller_Ruta::class, "update_ruta"],
            "DELETE /ruta" => [Controller_Ruta::class, "delete_ruta"],
        ];
    }
}
