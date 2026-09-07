<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";
include_once __DIR__ . "/Controller_Sign.php";

class Controller_UserChangeData 
{
    private const LOG_TYPE = "USER CHANGE DATA CONTROLLER";

    private const PERMITIDO_USUARIO = [
        enum_tipo_vecino => [
            sql_tabla_usuario => [
                json_password => 's'
            ]
        ],
        enum_tipo_operario => [
            sql_tabla_usuario => [
                json_password => 's'
            ],
            sql_tabla_trabajador => [
                json_first_name => 's',
                json_last_name  => 's'
            ]
        ],
        enum_tipo_admin_operador => [
            sql_tabla_usuario => [
                json_password => 's'
            ],
            sql_tabla_trabajador => [
                json_first_name => 's',
                json_last_name  => 's'
            ]
        ],
        enum_tipo_admin_general => [
            sql_tabla_usuario => [
                json_password => 's'
            ],
            sql_tabla_trabajador => [
                json_first_name => 's',
                json_last_name  => 's'
            ]
        ],
        enum_tipo_admin_sistema => [
            sql_tabla_usuario => [
                json_password => 's'
            ],
            sql_tabla_trabajador => [
                json_first_name => 's',
                json_last_name  => 's'
            ]
        ]
    ];

    private const PERMITIDO_ADMIN = [
        sql_tabla_usuario => [
            json_password => 's'
        ],
        sql_tabla_trabajador => [
            json_first_name => 's',
            json_last_name  => 's'
        ],
        sql_tabla_operador => [],
        sql_tabla_muni_operador => [],
        sql_tabla_muni_general => [],
        sql_tabla_admin => []
    ];

    public static function user_change_data_by_user(array $data): Util_HttpResponse 
    {
        if (Controller_Auth::comprobate_token($data) !== true) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de actualización fallido: Token inválido o no proporcionado");
            return Util_HttpResponse::error(http_forbidden, "No tienes un token válido");
        }

        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];

        $schema_permitido = self::PERMITIDO_USUARIO[$typeuser] ?? [];

        if (empty($schema_permitido)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Intento de actualización rechazado: Rol '{$typeuser}' sin permisos definidos");
            return Util_HttpResponse::error(http_forbidden, "El tipo de usuario no tiene permisos de modificación asignados");
        }

        $user_payload = (array) $user_payload;
        $schema_permitido = (array) $schema_permitido;

        $exito = self::process_update($ci, $user_payload, $schema_permitido);
        
        if (!$exito) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Fallo al actualizar datos personales: Datos inválidos o tipos incoherentes");
            return Util_HttpResponse::error(http_bad_request, "No se enviaron datos válidos o los tipos de datos no coinciden");
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "El usuario actualizó sus datos personales correctamente");
        return Util_HttpResponse::ok(["mensaje" => "Tus datos se han actualizado correctamente"]);
    }

    public static function user_change_data_by_admin(array $data): Util_HttpResponse 
    {
        $admin_ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);
        $es_admin = Controller_Auth::comprobate_token_typeuser($data, enum_tipo_admin_sistema);

        if ($es_admin !== true) {
            Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Acceso denegado: Intento de modificación administrativa sin rol de administrador de sistema");
            return Util_HttpResponse::error(http_unaunthorize, "Acceso denegado. Requiere permisos de administrador");
        }

        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];

        $target_ci = isset($user_payload[json_ci]) ? (int) $user_payload[json_ci] : 0;

        if ($target_ci <= 0) {
            Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Intento de actualización administrativa fallido: Cédula objetivo faltante o inválida");
            return Util_HttpResponse::error(http_bad_request, "Falta o es inválida la cédula del usuario objetivo");
        }

        $exito = self::process_update($target_ci, $user_payload, self::PERMITIDO_ADMIN);

        if (!$exito) {
            Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "Fallo al actualizar datos del usuario CI {$target_ci}: Campos inválidos o tipos de datos incorrectos");
            return Util_HttpResponse::error(http_bad_request, "No se enviaron campos válidos para actualizar o el tipo de dato es incorrecto");
        }

        Model_Log::add_log_user($admin_ci, self::LOG_TYPE, "El administrador actualizó exitosamente los datos del usuario CI: {$target_ci}");
        return Util_HttpResponse::ok(["mensaje" => "Datos del usuario actualizados correctamente por el administrador"]);
    }

    public static function process_update(int $ci, array $payload, array $schema_permitido): bool 
    {
        $changes_by_table = [];

        foreach ($schema_permitido as $tabla => $campos_permitidos) {
            foreach ($campos_permitidos as $json_key => $tipo_esperado) {
                if (array_key_exists($json_key, $payload)) {
                    $valor = $payload[$json_key];

                    if (!self::validate_data_type($valor, $tipo_esperado)) {
                        Model_Log::add_log_user($ci, self::LOG_TYPE, "Validación omitida: El campo '{$json_key}' no coincide con el tipo esperado '{$tipo_esperado}'");
                        continue;
                    }

                    if ($json_key === json_password) {
                        $valor = Controller_Sign::hash_password((string) $valor);
                    }

                    $changes_by_table[$tabla][$json_key] = $valor;
                }
            }
        }

        if (empty($changes_by_table)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Proceso de actualización cancelado: No se identificaron campos válidos permitidos");
            return false;
        }

        foreach ($changes_by_table as $tabla => $columnas) {
            foreach ($columnas as $columna_json => $nuevo_valor) {
                $columna_sql = Util_Translator::json_to_sql($columna_json);

                $target_ci = (int) $ci;
                $tabla_str = (string) $tabla;
                $columna_sql_str = (string) $columna_sql;

                $exito = Model_User::change_data($target_ci, $tabla_str, $columna_sql_str, $nuevo_valor);

                if (!$exito) {
                    Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Error en BD al modificar columna '{$columna_sql_str}' en la tabla '{$tabla_str}'");
                    return false;
                }

                Model_Log::add_log_user($target_ci, self::LOG_TYPE, "Columna '{$columna_sql_str}' actualizada en la tabla '{$tabla_str}'");
            }
        }

        return true;
    }

    private static function validate_data_type(mixed $value, string $expected_type): bool 
    {
        return match ($expected_type) {
            'i'     => is_int($value) || (is_string($value) && ctype_digit($value)),
            's'     => is_string($value),
            'd'     => is_float($value) || is_numeric($value),
            default => false,
        };
    }
}