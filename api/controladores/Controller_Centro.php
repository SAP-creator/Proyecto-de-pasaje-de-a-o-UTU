<?php

include_once __DIR__ . "/../modelo/Model_Centro.php";
include_once __DIR__ . "/../modelo/Model_Ruta.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

/**
 * CRUD de centros de acopio, con sus almacenamientos y retiros.
 *
 * Quien puede hacer que (AP = admin planificador = enum_tipo_admin_general):
 *  - Ver centros: operador camion, admin operador y AP.            (supuesto)
 *  - Crear, actualizar y borrar centros: AP.
 *  - Retirar volumen de un almacenamiento: admin operador.         (supuesto)
 *  - Ver los retiros: admin operador y AP.                         (supuesto)
 */
class Controller_Centro
{
    private const LOG_TYPE = "CENTRO CONTROLLER";
    private const SYS_NAME = "ApiAdminSys";

    private const ROLES_VER = [enum_tipo_operario, enum_tipo_admin_operador, enum_tipo_admin_general];
    private const ROLES_PLANIFICADOR = [enum_tipo_admin_general];
    private const ROLES_RETIRO = [enum_tipo_admin_operador];
    private const ROLES_VER_RETIROS = [enum_tipo_admin_operador, enum_tipo_admin_general];

    private const UBICACION_MAX = 255;

    // =================================================================
    // CRUD de centro
    // =================================================================

    public static function create_centro(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $centro = Util_VerifyData::verify_and_get_array($data, json_centro);
        Util_VerifyData::keys_exists(true, $centro, json_ubicacion, json_almacenamientos);

        $error = self::validar_ubicacion($centro[json_ubicacion], $ci);
        if ($error !== null) {
            return $error;
        }

        $error = self::validar_almacenamientos($centro[json_almacenamientos], $ci, false);
        if ($error !== null) {
            return $error;
        }

        $id = Model_Centro::create_centro(trim($centro[json_ubicacion]), self::armar_almacenamientos($centro[json_almacenamientos]));

        if ($id === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al crear el centro");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CreateCentroError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó el centro {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "CentroCreated"),
            [json_centro => [json_id => $id]]
        );
    }

    public static function get_centros(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_VER);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        // Filtro opcional
        $tipo_basura = $data[json_tipo_basura] ?? "";

        if ($tipo_basura !== "" && !in_array($tipo_basura, sql_tipo_basura, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de centros rechazado: tipo de basura inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoBasura"),
                http_unprocessable_entity
            );
        }

        $centros = Model_Centro::get_centros($tipo_basura);

        if ($centros === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al listar centros");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentrosError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó centros");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "CentrosFetched"),
            Util_Translator::sql_to_json_keys($centros)
        );
    }

    public static function get_centro(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_VER);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_centro);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        $centro = Model_Centro::get_centro($id);

        if ($centro === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        if ($centro === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta rechazada: el centro {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó el centro {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "CentroFetched"),
            Util_Translator::sql_to_json_keys($centro)
        );
    }

    public static function update_centro(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_centro);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        // Todo es opcional: solo se modifica lo que llega
        $ubicacion = null;
        if (array_key_exists(json_ubicacion, $payload)) {
            $error = self::validar_ubicacion($payload[json_ubicacion], $ci);

            if ($error !== null) {
                return $error;
            }

            $ubicacion = trim($payload[json_ubicacion]);
        }

        $almacenamientos = null;
        if (array_key_exists(json_almacenamientos, $payload)) {
            $error = self::validar_almacenamientos($payload[json_almacenamientos], $ci, true);

            if ($error !== null) {
                return $error;
            }

            $almacenamientos = self::armar_almacenamientos($payload[json_almacenamientos]);
        }

        if ($ubicacion === null && $almacenamientos === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del centro {$id} rechazada: no se envió ningún campo");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_bad_request
            );
        }

        $actual = Model_Centro::get_centro($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: el centro {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        // Los almacenamientos que traen id tienen que ser de este centro
        if ($almacenamientos !== null) {
            $ids_del_centro = array_column($actual['almacenamientos'], 'id');

            foreach ($almacenamientos as $almacenamiento) {
                if (isset($almacenamiento['id']) && !in_array($almacenamiento['id'], $ids_del_centro, true)) {
                    Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del centro {$id} rechazada: el almacenamiento {$almacenamiento['id']} no es de este centro");
                    return Util_HttpResponse::error(
                        Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "AlmacenamientoNotFound"),
                        http_not_found
                    );
                }
            }
        }

        if (Model_Centro::update_centro($id, $ubicacion, $almacenamientos) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al actualizar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UpdateCentroError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó el centro {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "CentroUpdated")
        );
    }

    public static function delete_centro(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_centro);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        // Se puede borrar aunque una ruta lo use, salvo que esa ruta se este ejecutando
        $en_ejecucion = Model_Ruta::centro_en_ejecucion($id);

        if ($en_ejecucion === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar las ejecuciones del centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetEjecucionesError"),
                http_internal_error
            );
        }

        if ($en_ejecucion) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: una ruta que usa el centro {$id} se está ejecutando");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroInExecution"),
                http_conflict
            );
        }

        $borrado = Model_Centro::delete_centro($id);

        if ($borrado === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al borrar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteCentroError"),
                http_internal_error
            );
        }

        if ($borrado === false) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: el centro {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borró el centro {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "CentroDeleted")
        );
    }

    // =================================================================
    // Retiros
    // =================================================================

    public static function retirar(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_RETIRO);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_centro);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $id_almacenamiento = Util_VerifyData::verify_and_get_int($payload, json_id_almacenamiento);

        // Sin VOLUMEN se retira todo lo que haya
        $volumen = null;
        if (isset($payload[json_volumen])) {
            $volumen = filter_var($payload[json_volumen], FILTER_VALIDATE_FLOAT);

            if ($volumen === false || $volumen <= 0) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiro rechazado en el centro {$id}: el volumen no es un número mayor a 0");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidVolumen"),
                    http_unprocessable_entity
                );
            }
        }

        $centro = Model_Centro::get_centro($id);

        if ($centro === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        if ($centro === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiro rechazado: el centro {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        $almacenamiento = null;
        foreach ($centro['almacenamientos'] as $candidato) {
            if ($candidato['id'] === $id_almacenamiento) {
                $almacenamiento = $candidato;
            }
        }

        if ($almacenamiento === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiro rechazado: el almacenamiento {$id_almacenamiento} no es del centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "AlmacenamientoNotFound"),
                http_not_found
            );
        }

        if ($almacenamiento['volumen_actual'] <= 0) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiro rechazado: el almacenamiento {$id_almacenamiento} ya está vacío");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "AlmacenamientoEmpty"),
                http_conflict
            );
        }

        if ($volumen !== null && $volumen > $almacenamiento['volumen_actual']) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiro rechazado: se pidió más volumen del que hay en el almacenamiento {$id_almacenamiento}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "VolumeExceedsCurrent"),
                http_unprocessable_entity
            );
        }

        $retirado = Model_Centro::retirar($id, $id_almacenamiento, $volumen === null ? null : (float) $volumen, $ci, date('Y-m-d H:i:s'));

        if ($retirado === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al retirar volumen del almacenamiento {$id_almacenamiento}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "WithdrawError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Retiró {$retirado} litros del almacenamiento {$id_almacenamiento} del centro {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "Withdrawn"),
            [json_volumen => $retirado]
        );
    }

    public static function get_retiros(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_VER_RETIROS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_centro);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        // Filtro opcional
        $id_almacenamiento = 0;
        if (isset($payload[json_id_almacenamiento])) {
            $id_almacenamiento = filter_var($payload[json_id_almacenamiento], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id_almacenamiento === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta de retiros rechazada: id de almacenamiento inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenamientoId"),
                    http_unprocessable_entity
                );
            }
        }

        $centro = Model_Centro::get_centro($id);

        if ($centro === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        if ($centro === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta de retiros rechazada: el centro {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        $retiros = Model_Centro::get_retiros($id, $id_almacenamiento);

        if ($retiros === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar los retiros del centro {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRetirosError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó los retiros del centro {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RetirosFetched"),
            Util_Translator::sql_to_json_keys($retiros)
        );
    }

    // =================================================================
    // Validaciones compartidas por crear y actualizar
    // =================================================================

    /**
     * Devuelve la respuesta de error o null si la ubicacion esta bien.
     */
    private static function validar_ubicacion(mixed $ubicacion, int $ci): ?Util_HttpResponse
    {
        if (!is_string($ubicacion) || trim($ubicacion) === "") {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: la ubicación está vacía o no es un texto");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidUbicacion"),
                http_unprocessable_entity
            );
        }

        if (!Util_VerifyData::comprobar_longitud($ubicacion, self::UBICACION_MAX)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: la ubicación supera los " . self::UBICACION_MAX . " caracteres");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UbicacionTooLong"),
                http_unprocessable_entity
            );
        }

        return null;
    }

    /**
     * Valida la lista de almacenamientos: al menos uno, cada uno con tipos de basura
     * validos y una cantidad mayor a 0. Con $permite_id, cada uno puede traer el id
     * de uno existente (para actualizarlo en vez de crear uno nuevo).
     * Devuelve la respuesta de error o null si esta todo bien.
     */
    private static function validar_almacenamientos(mixed $lista, int $ci, bool $permite_id): ?Util_HttpResponse
    {
        if (!is_array($lista) || empty($lista) || $lista !== array_values($lista)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: los almacenamientos tienen que ser una lista con al menos uno");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenamientos"),
                http_unprocessable_entity
            );
        }

        foreach ($lista as $almacenamiento) {
            if (!is_array($almacenamiento)) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: un almacenamiento no es un objeto");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenamientos"),
                    http_unprocessable_entity
                );
            }

            if (!Util_VerifyData::valid_enum_list($almacenamiento[json_tipo_basuras] ?? null, sql_tipo_basura)) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: tipos de basura inválidos, repetidos o vacíos en un almacenamiento");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoBasuras"),
                    http_unprocessable_entity
                );
            }

            $cantidad = filter_var($almacenamiento[json_cantidad] ?? null, FILTER_VALIDATE_FLOAT);

            if ($cantidad === false || $cantidad <= 0) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: la cantidad de un almacenamiento no es un número mayor a 0");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCantidad"),
                    http_unprocessable_entity
                );
            }

            if (!isset($almacenamiento[json_id])) {
                continue;
            }

            if (!$permite_id) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: un centro nuevo no puede traer ids de almacenamiento");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenamientos"),
                    http_unprocessable_entity
                );
            }

            if (filter_var($almacenamiento[json_id], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Centro rechazado: el id de un almacenamiento no es válido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenamientoId"),
                    http_unprocessable_entity
                );
            }
        }

        return null;
    }

    /**
     * Pasa la lista que mando el cliente (claves JSON) a la que espera el modelo.
     */
    private static function armar_almacenamientos(array $lista): array
    {
        $almacenamientos = [];

        foreach ($lista as $almacenamiento) {
            $armado = [
                'tipo_basuras' => $almacenamiento[json_tipo_basuras],
                'cantidad'     => (float) $almacenamiento[json_cantidad]
            ];

            if (isset($almacenamiento[json_id])) {
                $armado['id'] = (int) $almacenamiento[json_id];
            }

            $almacenamientos[] = $armado;
        }

        return $almacenamientos;
    }
}
