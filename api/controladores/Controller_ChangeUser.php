<?php

include_once __DIR__ . "/../modelo/Model_User.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../constantes/Const_Modificable.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/Controller_Auth.php";
include_once __DIR__ . "/Controller_Sign.php";

/**
 * Fusion de Controller_UserSetup y Controller_UserChangeData.
 *
 *  - modificar_datos: UNICA funcion que modifica datos de un usuario. Lo que se
 *    puede modificar sale de Const_Modificable.php segun el tipo de usuario.
 *    El router decide si la llamada es del propio usuario (tipo = el del token)
 *    o de un admin (tipo = tipo_modificador_admin, tras validar que el token es
 *    de admin sistema) y le pasa el tipo, la cedula objetivo y la del actor.
 *  - user_is_complete: corta la peticion si el perfil esta incompleto.
 *  - complete_user: completa los datos que faltan usando modificar_datos.
 */
class Controller_ChangeUser
{
    private const LOG_TYPE = "CHANGE USER CONTROLLER";
    private const SYS_NAME = "ApiAdminSys";

    // =================================================================
    // Modificar datos
    // =================================================================

    /**
     * @param array  $datos        Payload plano con claves JSON: [json_password => "...", json_first_name => "..."]
     * @param string $tipo_usuario enum_tipo_* (modifica lo suyo) o tipo_modificador_admin (modifica lo de otro)
     * @param int    $ci_objetivo  Usuario al que se le modifican los datos
     * @param ?int   $ci_actor     Quien lo hace (para los logs). Si es null, es el propio usuario
     */
    public static function modificar_datos(array $datos, string $tipo_usuario, int $ci_objetivo, ?int $ci_actor = null): Util_HttpResponse
    {
        $ci_actor ??= $ci_objetivo;

        if (!array_key_exists($tipo_usuario, modificable_usuario)) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: el tipo '{$tipo_usuario}' no tiene permisos definidos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UserTypePermissionsMissing"),
                http_forbidden
            );
        }

        // Se arma [tabla => [columna_sql => valor]] solo con lo permitido para este tipo
        $cambios = [];

        foreach (modificable_usuario[$tipo_usuario] as $tabla => $columnas) {
            foreach ($columnas as $json_key => $regla) {
                if (!array_key_exists($json_key, $datos)) {
                    continue;
                }

                $valor = $datos[$json_key];

                if (!self::validate_data_type($valor, $regla['tipo'])) {
                    Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: el campo '{$json_key}' no coincide con el tipo esperado '{$regla['tipo']}'");
                    return Util_HttpResponse::error(
                        Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidDataType"),
                        http_bad_request
                    );
                }

                if (!Util_VerifyData::comprobar_longitud($valor, $regla['cantidad'])) {
                    Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: el campo '{$json_key}' supera los {$regla['cantidad']} caracteres");
                    return Util_HttpResponse::error(
                        Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DataTooLong"),
                        http_bad_request
                    );
                }

                // Unico lugar donde se hashea la contraseña
                if ($json_key === json_password) {
                    $valor = Controller_Sign::hash_password((string) $valor);
                }

                $cambios[$tabla][Util_Translator::json_to_sql($json_key)] = $valor;
            }
        }

        if (empty($cambios)) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: no se envió ningún campo permitido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_bad_request
            );
        }

        $usuario = Model_User::get_user($ci_objetivo);

        if (is_null($usuario)) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: el usuario CI {$ci_objetivo} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UserNotFound"),
                http_not_found
            );
        }

        // Los datos de trabajador solo existen para los usuarios que son trabajadores
        if (isset($cambios[sql_tabla_trabajador]) && !in_array($usuario[sql_tipo], sql_trabajador_tipo, true)) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Modificación rechazada: el usuario CI {$ci_objetivo} ({$usuario[sql_tipo]}) no es trabajador");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "FieldNotApplicableToUserType"),
                http_bad_request
            );
        }

        if (Model_User::change_data_many($ci_objetivo, $cambios) === null) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "Error en BD al modificar los datos del usuario CI {$ci_objetivo}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UpdateUserError"),
                http_internal_error
            );
        }

        // Solo se registran los nombres de las columnas, nunca los valores (hay contraseñas)
        $columnas_modificadas = [];
        foreach ($cambios as $tabla => $columnas) {
            foreach (array_keys($columnas) as $columna) {
                $columnas_modificadas[] = "{$tabla}.{$columna}";
            }
        }

        $texto_columnas = implode(", ", $columnas_modificadas);

        if ($ci_actor === $ci_objetivo) {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "El usuario actualizó sus datos personales ({$texto_columnas})");
        } else {
            Model_Log::add_log_user($ci_actor, self::LOG_TYPE, "El administrador actualizó los datos del usuario CI {$ci_objetivo} ({$texto_columnas})");
        }

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserDataUpdated")
        );
    }

    /**
     * Entrada del router: el usuario autenticado modifica SUS datos.
     * El tipo y la cedula salen del token, nunca del cliente.
     */
    public static function modify_own_data(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, sql_usuario_tipo);

        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $tipo_usuario = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);
        $payload = Util_VerifyData::verify_and_get_array($data, json_user);

        return self::modificar_datos($payload, $tipo_usuario, $ci);
    }

    /**
     * Entrada del router: un admin sistema modifica los datos de otro usuario.
     * El payload trae USER: {CI: <objetivo>, <campos a modificar>}.
     */
    public static function modify_user_by_admin(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, [enum_tipo_admin_sistema]);

        $ci_admin = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $payload = Util_VerifyData::verify_and_get_array($data, json_user);
        $ci_objetivo = Util_VerifyData::verify_and_get_int($payload, json_ci);

        return self::modificar_datos($payload, tipo_modificador_admin, $ci_objetivo, $ci_admin);
    }

    // =================================================================
    // Perfil completo
    // =================================================================

    /**
     * Intercepta la peticion y verifica si el usuario autenticado tiene el perfil completo.
     * Si no esta completo, interrumpe la ejecucion enviando un error estructurado.
     */
    public static function user_is_complete(array $data): void
    {
        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);

        // 1. Extraer directamente la propiedad que indica si esta completo desde el Token
        $is_complete_flag = Util_VerifyData::verify_and_get_from_token($data, json_completeuser);

        // Si en el token figura como completo (true, 1, 's', etc.), se aprueba la ejecucion
        if ($is_complete_flag === true || $is_complete_flag === 1 || $is_complete_flag === 's') {
            return;
        }

        // 2. SOLO si el Token indica que no esta completo, se consulta la BD para saber que campos le faltan
        $incomplete_fields = self::find_incomplete_data($data);

        if (is_array($incomplete_fields)) {
            $translated_fields = [];
            foreach ($incomplete_fields as $field) {
                $translated_fields[] = Util_Translator::sql_to_json($field) ?? $field;
            }

            Model_Log::add_log_user($ci, self::LOG_TYPE, "Acceso denegado: El usuario tiene el perfil incompleto");

            Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UserProfileIncomplete"),
                http_forbidden,
                [json_error => "Usuario incompleto, por favor complete los datos para continuar"],
                $translated_fields
            )->send();
            die();
        }

        // Error fallback si ocurrio un problema en la consulta
        Model_Log::add_log_user($ci, self::LOG_TYPE, "Error interno al verificar si el usuario está completo");
        Util_HttpResponse::error(
            Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UserCheckException"),
            http_internal_error
        )->send();
        die();
    }

    public static function complete_user(array $data): Util_HttpResponse
    {
        if (Controller_Auth::comprobate_token($data) !== true) {
            Model_Log::add_log_user(0, self::LOG_TYPE, "Intento de completar usuario fallido: Token inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidToken"),
                http_forbidden
            );
        }

        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        // Verificar primero en el Token antes de hacer peticiones
        $is_complete_flag = Util_VerifyData::verify_and_get_from_token($data, json_completeuser);

        if ($is_complete_flag === true || $is_complete_flag === 1 || $is_complete_flag === 's') {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Completado omitido: El usuario ya posee el perfil completo");
            return Util_HttpResponse::ok(
                Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "UserAlreadyComplete")
            );
        }

        // Datos que le faltan segun la BD, ya traducidos a claves JSON
        $incomplete_data = self::find_incomplete_data($data, false);

        if (is_null($incomplete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error interno al recuperar datos faltantes para completar perfil");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "IncompleteDataDbError"),
                http_internal_error
            );
        }

        // La BD ya tiene todo pero el token quedo viejo: se entrega uno nuevo
        if (empty($incomplete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Completado omitido: Todos los datos ya están ingresados en BD");
            return self::respuesta_perfil_completo($ci, $typeuser, "AllDataAlreadyComplete");
        }

        $missing_keys = [];
        foreach ($incomplete_data as $sql_column) {
            $missing_keys[] = Util_Translator::sql_to_json($sql_column) ?? $sql_column;
        }

        $payload = Util_VerifyData::verify_and_get_array($data, json_user);

        // Solo se aceptan los campos que faltan; el resto del payload se ignora
        $data_to_change = array_intersect_key($payload, array_flip($missing_keys));

        if (empty($data_to_change)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Intento de completado fallido: No se enviaron datos faltantes válidos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidMissingDataProvided"),
                http_bad_request
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Procesando actualización de campos para completar el perfil");

        $respuesta = self::modificar_datos($data_to_change, $typeuser, $ci);

        // Si despues de modificar el perfil quedo completo, se entrega un token nuevo
        // (el que mando el cliente sigue diciendo COMPLETEUSER = false)
        if (Model_User::user_is_complete($ci) === true) {
            return self::respuesta_perfil_completo($ci, $typeuser, "UserCompleted");
        }

        return $respuesta;
    }

    /**
     * Respuesta de "perfil completo" con un token nuevo que ya lo refleja.
     */
    private static function respuesta_perfil_completo(int $ci, string $typeuser, string $mensaje): Util_HttpResponse
    {
        $token = Controller_Auth::create_token([
            json_user => [
                json_ci           => $ci,
                json_typeuser     => $typeuser,
                json_completeuser => true
            ]
        ]);

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, $mensaje),
            $token
        );
    }

    /**
     * Campos que le faltan al usuario segun la BD. Con $table = false devuelve
     * solo el nombre de la columna; con true, "tabla__columna".
     */
    private static function find_incomplete_data(array $data, bool $table = false): ?array
    {
        $ci = (int) (Util_VerifyData::verify_and_get_from_token($data, json_ci) ?? 0);
        $typeuser = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);
        $translated_typeuser = Util_Translator::json_to_sql($typeuser) ?? $typeuser;

        $complete_data = Model_User::find_incomplete_data($translated_typeuser, $ci);

        if (!is_array($complete_data)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar campos incompletos");
            return null;
        }

        $data_result = [];
        $separator = "__";

        foreach ($complete_data as $key => $is_null) {
            if (!$is_null) {
                continue;
            }

            if (!$table) {
                $p = strpos($key, $separator);

                if ($p !== false) {
                    $key = substr($key, $p + strlen($separator));
                }
            }

            $data_result[] = $key;
        }

        return $data_result;
    }

    private static function validate_data_type(mixed $value, string $expected_type): bool
    {
        return match ($expected_type) {
            dato_int    => is_int($value) || (is_string($value) && ctype_digit($value)),
            dato_string => is_string($value) && trim($value) !== "",
            dato_double => is_float($value) || is_numeric($value),
            default     => false,
        };
    }
}
