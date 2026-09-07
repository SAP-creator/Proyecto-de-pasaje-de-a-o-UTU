<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";
include_once __DIR__ . "/Controller_Sign.php";

class Controller_UserChangeData 
{
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
            return Util_HttpResponse::error(http_forbidden, "No tienes un token válido");
        }

        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];

        $schema_permitido = self::PERMITIDO_USUARIO[$typeuser] ?? [];

        if (empty($schema_permitido)) {
            return Util_HttpResponse::error(http_forbidden, "El tipo de usuario no tiene permisos de modificación asignados");
        }

        $user_payload = (array) $user_payload;
        $schema_permitido = (array) $schema_permitido;

        $exito = self::process_update($ci, $user_payload, $schema_permitido);
        if (!$exito) {
            return Util_HttpResponse::error(http_bad_request, "No se enviaron datos válidos o los tipos de datos no coinciden");
        }

        return Util_HttpResponse::ok(["mensaje" => "Tus datos se han actualizado correctamente"]);
    }

    public static function user_change_data_by_admin(array $data): Util_HttpResponse 
    {
        $es_admin = Controller_Auth::comprobate_token_typeuser($data, enum_tipo_admin_sistema);

        if ($es_admin !== true) {
            return Util_HttpResponse::error(http_unaunthorize, "Acceso denegado. Requiere permisos de administrador");
        }

        Util_VerifyData::keys_exists(true, $data, json_user);
        $user_payload = $data[json_user];

        // Se castea adecuadamente a int
        $target_ci = isset($user_payload[json_ci]) ? (int) $user_payload[json_ci] : 0;

        if ($target_ci <= 0) {
            return Util_HttpResponse::error(http_bad_request, "Falta o es inválida la cédula del usuario objetivo");
        }

        $exito = self::process_update($target_ci, $user_payload, self::PERMITIDO_ADMIN);

        if (!$exito) {
            return Util_HttpResponse::error(http_bad_request, "No se enviaron campos válidos para actualizar o el tipo de dato es incorrecto");
        }

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
                        continue;
                    }

                    // Si el campo es la contraseña, aplicamos el hash
                    if ($json_key === json_password) {
                        $valor = Controller_Sign::hash_password((string) $valor);
                    }

                    $changes_by_table[$tabla][$json_key] = $valor;
                }
            }
        }

        if (empty($changes_by_table)) {
            return false;
        }
       
        foreach ($changes_by_table as $tabla => $columnas) {
            foreach ($columnas as $columna_json => $nuevo_valor) {
                $columna_sql = Util_Translator::json_to_sql($columna_json);

                $ci = (int) $ci;
                $tabla = (string) $tabla;
                $columna_sql = (string) $columna_sql;
                $exito = Model_User::change_data($ci, $tabla, $columna_sql, $nuevo_valor);
                
                if (!$exito) {
                    return false;
                }
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