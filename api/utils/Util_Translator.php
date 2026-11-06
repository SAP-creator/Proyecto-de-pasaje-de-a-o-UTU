<?php

include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";

class Util_Translator
{
    /** Bidirectional mapping from public JSON keys to SQL columns/aliases. */
    private const MAPEO_JSON_SQL = [
        json_ci => sql_cedula,
        json_password => sql_clave,
        json_completeuser => sql_usuario_completo,
        json_typeuser => sql_tipo,
        json_first_name => sql_nombre,
        json_last_name => sql_apellido,
        json_user => sql_tabla_usuario,
        json_typelog => sql_tipo_log,
        json_id => sql_id,
        json_fecha => sql_fecha,
        json_cedula_usuario => sql_cedula_usuario,
        json_tipo_modelo => sql_tipo_modelo,
        json_texto => sql_texto,
        json_ubicacion => sql_ubicacion,
        json_en_funcion => sql_en_funcion,
        json_almacenado => sql_tabla_almacenado,
        json_tipo_basuras => sql_tipo_basuras,
        json_cantidad => sql_cantidad,
        json_condicion => sql_condicion,
        json_basura => sql_basura,
        json_llenado => sql_llenado,
        json_tipos => sql_tipos,
        json_vaciado => sql_tabla_vaciado,
        json_id_contenedor => sql_id_contenedor,
        json_tipo_incidente => sql_tipo_incidente,
        json_estado_incidente => sql_estado_incidente,
        json_descripcion => sql_descripcion,
        json_estado_actual => sql_estado_actual,
        json_llenado_actual => sql_llenado_actual,
        json_historial_estado => sql_historial_estado,
        json_historial_llenado => sql_historial_llenado,
        json_almacenamientos => sql_almacenamientos,
        json_volumen_actual => sql_volumen_actual,
        json_volumen => sql_volumen,
        json_id_centro => sql_id_centro,
        json_id_almacenamiento => sql_id_almacenamiento,
        json_retiros => sql_retiros,
        json_id_centro_inicio => sql_id_centro_inicio,
        json_id_centro_final => sql_id_centro_final,
        json_contenedores => sql_contenedores,
        json_slot => sql_slot,
        json_tipos_basura => sql_tipos_basura,
        json_cantidad_contenedores => sql_cantidad_contenedores,
        json_advertencias => sql_advertencias,
        json_origen => sql_origen,
        json_faltan => sql_faltan,
        json_todos => sql_todos,
        json_incompleta => sql_incompleta,
        json_matricula => sql_matricula,
        json_id_ruta => sql_id_ruta,
        json_estado_camion => sql_estado,
        json_dia_semana => sql_dia_semana_columna,
        json_hora => sql_hora,
        json_fecha_especial => sql_fecha_especial,
        json_inicio_automatico => sql_inicio_automatico,
        json_id_camion_auto => sql_id_camion_auto,
        json_cedula_operario_auto => sql_cedula_operario_auto,
        json_id_trayecto => sql_id_trayecto,
        json_estado_ejecucion => sql_estado_ejecucion_alias,
        json_fecha_programada => sql_fecha_programada,
        json_cedula_aprobador => sql_cedula_aprobador,
        json_fecha_aprobacion => sql_fecha_aprobacion,
        json_fecha_inicio => sql_fecha_inicio,
        json_fecha_final => sql_fecha_final,
        json_notas => sql_notas,
        json_parado => sql_parado,
        json_cedula_operario => sql_cedula_operario,
        json_id_camion => sql_id_camion,
        json_llenado_camion => sql_llenado_camion,
        json_id_ejecucion => sql_id_ejecucion,
        json_fecha_pausa => sql_fecha_pausa,
        json_fecha_reanudacion => sql_fecha_reanudacion,
        json_pausas => sql_pausas
    ];

    public static function json_to_sql(string $json_key): ?string
    {
        return self::MAPEO_JSON_SQL[$json_key] ?? null;
    }

    public static function sql_to_json(string $sql_column): ?string
    {
        $inverse = array_flip(self::MAPEO_JSON_SQL);
        return $inverse[$sql_column] ?? null;
    }

    public static function sql_to_json_keys(array $datos): array
    {
        return self::translate_keys($datos, array_flip(self::MAPEO_JSON_SQL));
    }

    private static function translate_keys(array $datos, array $map): array
    {
        $translated = [];
        foreach ($datos as $key => $value) {
            $new_key = is_string($key) ? ($map[$key] ?? $key) : $key;
            $translated[$new_key] = is_array($value) ? self::translate_keys($value, $map) : $value;
        }
        return $translated;
    }
}
