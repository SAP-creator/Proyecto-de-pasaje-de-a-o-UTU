<?php

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";

class Util_Translator
{
    /**
     * Mapeo bidireccional de constantes JSON a SQL
     */
    private const MAPEO_JSON_SQL = [
        json_ci             => sql_cedula,
        json_password       => sql_clave,
        json_completeuser   => sql_usuario_completo,
        json_typeuser       => sql_tipo,
        json_first_name     => sql_nombre,
        json_last_name      => sql_apellido
    ];

    /**
     * Traduce una clave JSON proveniente de la API a una columna SQL
     */
    public static function json_to_sql(string $json_key): ?string 
    {
        return self::MAPEO_JSON_SQL[$json_key] ?? null;
    }

    /**
     * Traduce el nombre de una columna SQL a su clave JSON de la API
     */
    public static function sql_to_json(string $sql_column): ?string 
    {
        $mapa_inverso = array_flip(self::MAPEO_JSON_SQL);
        return $mapa_inverso[$sql_column] ?? null;
    }
}