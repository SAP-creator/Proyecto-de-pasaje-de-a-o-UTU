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
        json_last_name      => sql_apellido,
        json_user           => sql_tabla_usuario,
        json_typelog        => sql_tipo_log,
        json_id             => sql_id,
        json_fecha          => sql_fecha,
        json_cedula_usuario => sql_cedula_usuario,
        json_tipo_modelo    => sql_tipo_modelo,
        json_texto          => sql_texto,

        // Contenedores
        json_ubicacion         => sql_ubicacion,
        json_en_funcion        => sql_en_funcion,
        json_almacenado        => sql_tabla_almacenado,
        json_tipo_basuras      => sql_tipo_basuras,
        json_cantidad          => sql_cantidad,
        json_condicion         => sql_condicion,
        json_basura            => sql_basura,
        json_llenado           => sql_llenado,
        json_tipos             => sql_tipos,
        json_vaciado           => sql_tabla_vaciado,
        json_id_contenedor     => sql_id_contenedor,
        json_tipo_incidente    => sql_tipo_incidente,
        json_estado_incidente  => sql_estado_incidente,
        json_descripcion       => sql_descripcion,
        json_estado_actual     => sql_estado_actual,
        json_llenado_actual    => sql_llenado_actual,
        json_historial_estado  => sql_historial_estado,
        json_historial_llenado => sql_historial_llenado,

        // Centros de acopio y rutas
        json_almacenamientos      => sql_almacenamientos,
        json_volumen_actual       => sql_volumen_actual,
        json_volumen              => sql_volumen,
        json_id_centro            => sql_id_centro,
        json_id_almacenamiento    => sql_id_almacenamiento,
        json_retiros              => sql_retiros,
        json_id_centro_inicio     => sql_id_centro_inicio,
        json_id_centro_final      => sql_id_centro_final,
        json_contenedores         => sql_contenedores,
        json_slot                 => sql_slot,
        json_tipos_basura         => sql_tipos_basura,
        json_cantidad_contenedores => sql_cantidad_contenedores,
        json_advertencias         => sql_advertencias,
        json_origen               => sql_origen,
        json_faltan               => sql_faltan,
        json_todos                => sql_todos,
        json_incompleta           => sql_incompleta
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

    /**
     * Traduce las claves de un array, y las de todos los arrays anidados, de
     * SQL a JSON. Las claves sin traduccion y las numericas se dejan como estan.
     */
    public static function sql_to_json_keys(array $datos): array
    {
        return self::traducir_claves($datos, array_flip(self::MAPEO_JSON_SQL));
    }

    private static function traducir_claves(array $datos, array $mapa_inverso): array
    {
        $traducido = [];

        foreach ($datos as $clave => $valor) {
            $nueva_clave = is_string($clave) ? ($mapa_inverso[$clave] ?? $clave) : $clave;
            $traducido[$nueva_clave] = is_array($valor) ? self::traducir_claves($valor, $mapa_inverso) : $valor;
        }

        return $traducido;
    }
}
