<?php

include_once __DIR__ . "/../modelo/Model_Ruta.php";
include_once __DIR__ . "/../modelo/Model_Centro.php";
include_once __DIR__ . "/../modelo/Model_Contenedor.php";
include_once __DIR__ . "/../modelo/Model_Log.php";
include_once __DIR__ . "/../constantes/Const_Json.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_RestHttp.php";
include_once __DIR__ . "/../utils/Util_Code.php";
include_once __DIR__ . "/../utils/Util_VerifyData.php";
include_once __DIR__ . "/../utils/Util_Translator.php";
include_once __DIR__ . "/../controladores/Controller_Auth.php";

/**
 * CRUD de rutas: un centro de inicio, uno de final y contenedores en linea.
 *
 * Quien puede hacer que (AP = admin planificador = enum_tipo_admin_general):
 *  - Ver rutas: todos.
 *  - Crear, actualizar y borrar rutas: AP.
 *
 * Cada vez que se revisa una ruta (consultarla, pedir su contenedor de un slot
 * o actualizarla) se ejecuta antes Model_Ruta::limpiar_ruta, que saca de la
 * lista lo que ya no existe. Las advertencias avisan, sin bloquear, cuando un
 * centro no tiene alguno de los tipos de basura de los contenedores de la ruta.
 */
class Controller_Ruta
{
    private const LOG_TYPE = "RUTA CONTROLLER";
    private const SYS_NAME = "ApiAdminSys";

    private const ROLES_TODOS = [
        enum_tipo_vecino,
        enum_tipo_operario,
        enum_tipo_admin_operador,
        enum_tipo_admin_general,
        enum_tipo_admin_sistema
    ];
    private const ROLES_PLANIFICADOR = [enum_tipo_admin_general];

    // =================================================================
    // CRUD de ruta
    // =================================================================

    public static function create_ruta(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $ruta = Util_VerifyData::verify_and_get_array($data, json_ruta);
        $id_centro_inicio = Util_VerifyData::verify_and_get_int($ruta, json_id_centro_inicio);
        $id_centro_final = Util_VerifyData::verify_and_get_int($ruta, json_id_centro_final);
        Util_VerifyData::keys_exists(true, $ruta, json_contenedores);

        $contenedores = self::validar_contenedores($ruta[json_contenedores], $ci);
        if ($contenedores instanceof Util_HttpResponse) {
            return $contenedores;
        }

        $error = self::validar_centro($id_centro_inicio, $ci);
        if ($error !== null) {
            return $error;
        }

        $error = self::validar_centro($id_centro_final, $ci);
        if ($error !== null) {
            return $error;
        }

        $id = Model_Ruta::create_ruta($id_centro_inicio, $id_centro_final, $contenedores);

        if ($id === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al crear la ruta");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CreateRutaError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó la ruta {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RutaCreated"),
            [json_ruta => Util_Translator::sql_to_json_keys(['id' => $id, 'advertencias' => self::calcular_advertencias($id)])]
        );
    }

    public static function get_rutas(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        // Filtro opcional: rutas que empiezan o terminan en un centro
        $id_centro = 0;
        if (isset($data[json_id_centro])) {
            $id_centro = filter_var($data[json_id_centro], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id_centro === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de rutas rechazado: id de centro inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCentroId"),
                    http_unprocessable_entity
                );
            }
        }

        $rutas = Model_Ruta::get_rutas($id_centro);

        if ($rutas === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al listar rutas");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRutasError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó rutas");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RutasFetched"),
            Util_Translator::sql_to_json_keys($rutas)
        );
    }

    public static function get_ruta(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_ruta);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        if (Model_Ruta::limpiar_ruta($id) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CleanRutaError"),
                http_internal_error
            );
        }

        $ruta = Model_Ruta::get_ruta($id);

        if ($ruta === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRutaError"),
                http_internal_error
            );
        }

        if ($ruta === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta rechazada: la ruta {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "RutaNotFound"),
                http_not_found
            );
        }

        $tipos_basura = Model_Ruta::get_tipos_basura($id);
        $cantidad_contenedores = Model_Ruta::get_cantidad_contenedores($id);

        if ($tipos_basura === null || $cantidad_contenedores === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar los contenedores de la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRutaContenedoresError"),
                http_internal_error
            );
        }

        $advertencias = self::armar_advertencias($ruta, $tipos_basura);

        if ($advertencias === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar los centros de la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        $ruta['tipos_basura'] = $tipos_basura;
        $ruta['cantidad_contenedores'] = $cantidad_contenedores;
        $ruta['advertencias'] = $advertencias;

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó la ruta {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RutaFetched"),
            Util_Translator::sql_to_json_keys($ruta)
        );
    }

    public static function update_ruta(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_ruta);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        // Todo es opcional: solo se modifica lo que llega
        $id_centro_inicio = null;
        if (isset($payload[json_id_centro_inicio])) {
            $id_centro_inicio = filter_var($payload[json_id_centro_inicio], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id_centro_inicio === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización de la ruta {$id} rechazada: id del centro de inicio inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCentroId"),
                    http_unprocessable_entity
                );
            }
        }

        $id_centro_final = null;
        if (isset($payload[json_id_centro_final])) {
            $id_centro_final = filter_var($payload[json_id_centro_final], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id_centro_final === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización de la ruta {$id} rechazada: id del centro de final inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCentroId"),
                    http_unprocessable_entity
                );
            }
        }

        $contenedores = null;
        if (array_key_exists(json_contenedores, $payload)) {
            $contenedores = self::validar_contenedores($payload[json_contenedores], $ci);

            if ($contenedores instanceof Util_HttpResponse) {
                return $contenedores;
            }
        }

        if ($id_centro_inicio === null && $id_centro_final === null && $contenedores === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización de la ruta {$id} rechazada: no se envió ningún campo");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_bad_request
            );
        }

        if ($id_centro_inicio !== null) {
            $error = self::validar_centro($id_centro_inicio, $ci);

            if ($error !== null) {
                return $error;
            }
        }

        if ($id_centro_final !== null) {
            $error = self::validar_centro($id_centro_final, $ci);

            if ($error !== null) {
                return $error;
            }
        }

        if (Model_Ruta::limpiar_ruta($id) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CleanRutaError"),
                http_internal_error
            );
        }

        $actual = Model_Ruta::get_ruta($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetRutaError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: la ruta {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "RutaNotFound"),
                http_not_found
            );
        }

        // La lista de contenedores no se toca mientras un trayecto de la ruta se este ejecutando
        if ($contenedores !== null) {
            $ejecutandose = Model_Ruta::ruta_ejecutandose($id);

            if ($ejecutandose === null) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar las ejecuciones de la ruta {$id}");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetEjecucionesError"),
                    http_internal_error
                );
            }

            if ($ejecutandose) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: la ruta {$id} se está ejecutando");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "RutaInExecution"),
                    http_conflict
                );
            }
        }

        if (Model_Ruta::update_ruta($id, $id_centro_inicio, $id_centro_final, $contenedores) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al actualizar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UpdateRutaError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó la ruta {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RutaUpdated"),
            Util_Translator::sql_to_json_keys(['advertencias' => self::calcular_advertencias($id)])
        );
    }

    public static function delete_ruta(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_ruta);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        $con_ejecucion = Model_Ruta::ruta_con_ejecucion_abierta($id);

        if ($con_ejecucion === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar las ejecuciones de la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetEjecucionesError"),
                http_internal_error
            );
        }

        if ($con_ejecucion) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: la ruta {$id} tiene una ejecución abierta");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "RutaHasOpenExecution"),
                http_conflict
            );
        }

        $borrada = Model_Ruta::delete_ruta($id);

        if ($borrada === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al borrar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteRutaError"),
                http_internal_error
            );
        }

        if ($borrada === false) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: la ruta {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "RutaNotFound"),
                http_not_found
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borró la ruta {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "RutaDeleted")
        );
    }

    // =================================================================
    // Contenedor guardado en una posicion de la ruta
    // =================================================================

    public static function get_contenedor_en_slot(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_ruta);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);
        $slot = Util_VerifyData::verify_and_get_int($payload, json_slot);

        if (Model_Ruta::limpiar_ruta($id) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CleanRutaError"),
                http_internal_error
            );
        }

        $id_contenedor = Model_Ruta::get_contenedor_en_slot($id, $slot);

        if ($id_contenedor === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el slot {$slot} de la ruta {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetSlotError"),
                http_internal_error
            );
        }

        if ($id_contenedor === 0) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta rechazada: la ruta {$id} no tiene nada en el slot {$slot}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "SlotNotFound"),
                http_not_found
            );
        }

        $contenedor = Model_Contenedor::get_contenedor($id_contenedor);

        if ($contenedor === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id_contenedor}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($contenedor === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta rechazada: el contenedor {$id_contenedor} ya no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        $contenedor['slot'] = $slot;

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó el contenedor del slot {$slot} de la ruta {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "SlotFetched"),
            Util_Translator::sql_to_json_keys($contenedor)
        );
    }

    // =================================================================
    // Validaciones compartidas por crear y actualizar
    // =================================================================

    /**
     * Devuelve la respuesta de error o null si el centro existe.
     */
    private static function validar_centro(int $id_centro, int $ci): ?Util_HttpResponse
    {
        $centro = Model_Centro::get_centro($id_centro);

        if ($centro === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el centro {$id_centro}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetCentroError"),
                http_internal_error
            );
        }

        if ($centro === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Ruta rechazada: el centro {$id_centro} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CentroNotFound"),
                http_not_found
            );
        }

        return null;
    }

    /**
     * Valida la lista de contenedores de la ruta (al menos uno, ids validos, sin repetir
     * y existentes; puede incluir contenedores fuera de funcion). Devuelve la lista de ids
     * como int, en el orden recibido, o la respuesta de error.
     */
    private static function validar_contenedores(mixed $lista, int $ci): array|Util_HttpResponse
    {
        if (!is_array($lista) || empty($lista) || $lista !== array_values($lista)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Ruta rechazada: los contenedores tienen que ser una lista con al menos uno");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidContenedores"),
                http_unprocessable_entity
            );
        }

        $ids = [];

        foreach ($lista as $valor) {
            $id = (is_int($valor) || is_string($valor))
                ? filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;

            if ($id === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Ruta rechazada: un id de contenedor no es válido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidContenedorId"),
                    http_unprocessable_entity
                );
            }

            $ids[] = $id;
        }

        if (count($ids) !== count(array_unique($ids))) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Ruta rechazada: hay un contenedor repetido en la lista");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DuplicatedContenedor"),
                http_unprocessable_entity
            );
        }

        foreach ($ids as $id) {
            $contenedor = Model_Contenedor::get_contenedor($id);

            if ($contenedor === null) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                    http_internal_error
                );
            }

            if ($contenedor === []) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Ruta rechazada: el contenedor {$id} no existe");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                    http_not_found
                );
            }
        }

        return $ids;
    }

    // =================================================================
    // Advertencias
    // =================================================================

    /**
     * Una advertencia por cada centro (inicio o final) que no tenga alguno, o ninguno,
     * de los tipos de basura de la ruta. 'todos' es true cuando le faltan todos.
     * Devuelve null si falla la BD.
     */
    private static function armar_advertencias(array $ruta, array $tipos_ruta): ?array
    {
        $advertencias = [];

        if (empty($tipos_ruta)) {
            return $advertencias;
        }

        $centros = [
            'centro_inicio' => $ruta['id_centro_inicio'],
            'centro_final'  => $ruta['id_centro_final']
        ];

        foreach ($centros as $origen => $id_centro) {
            if ($id_centro === null) {
                continue;
            }

            $centro = Model_Centro::get_centro($id_centro);

            if ($centro === null) {
                return null;
            }

            if ($centro === []) {
                continue;
            }

            $tipos_del_centro = [];
            foreach ($centro['almacenamientos'] as $almacenamiento) {
                $tipos_del_centro = array_merge($tipos_del_centro, $almacenamiento['tipo_basuras']);
            }

            $faltan = array_values(array_diff($tipos_ruta, $tipos_del_centro));

            if (!empty($faltan)) {
                $advertencias[] = [
                    'origen' => $origen,
                    'faltan' => $faltan,
                    'todos'  => count($faltan) === count($tipos_ruta)
                ];
            }
        }

        return $advertencias;
    }

    /**
     * Las advertencias de una ruta ya guardada. Si algo falla devuelve una lista vacia:
     * se usa despues de crear o actualizar, y ahi no se pisa un exito con un error.
     */
    private static function calcular_advertencias(int $id_ruta): array
    {
        $ruta = Model_Ruta::get_ruta($id_ruta);
        $tipos_ruta = Model_Ruta::get_tipos_basura($id_ruta);

        if (empty($ruta) || $tipos_ruta === null) {
            return [];
        }

        return self::armar_advertencias($ruta, $tipos_ruta) ?? [];
    }
}
