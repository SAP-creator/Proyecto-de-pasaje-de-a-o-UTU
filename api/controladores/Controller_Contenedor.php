<?php

include_once __DIR__ . "/../modelo/Model_Contenedor.php";
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
 * CRUD de contenedores (con estado, llenado, vaciado e incidentes).
 *
 * Quien puede hacer que (AP = admin planificador = enum_tipo_admin_general):
 *  - Ver contenedores, agregar estado, crear y ver incidentes: todos.
 *  - Crear, actualizar y eliminar contenedor: AP.
 *  - Agregar llenado y vaciar: operador camion.
 *  - Poner o quitar "Reparacion" en un estado: admin operador.
 *  - Actualizar y eliminar incidentes: admin operador y AP.   (ASUNCION)
 */
class Controller_Contenedor
{
    private const LOG_TYPE = "CONTENEDOR CONTROLLER";
    private const SYS_NAME = "ApiAdminSys";

    private const ROLES_TODOS = [
        enum_tipo_vecino,
        enum_tipo_operario,
        enum_tipo_admin_operador,
        enum_tipo_admin_general,
        enum_tipo_admin_sistema
    ];
    private const ROLES_PLANIFICADOR = [enum_tipo_admin_general];
    private const ROLES_OPERARIO = [enum_tipo_operario];
    private const ROLES_ADMIN_OPERADOR_Y_PLANIFICADOR = [enum_tipo_admin_operador, enum_tipo_admin_general];

    // Lo unico que un vecino puede reportar al notificar un problema
    private const VECINO_CONDICIONES = [enum_cond_vandalizado, enum_cond_roto, enum_cond_quemado];
    private const VECINO_BASURA = [enum_basura_afuera];

    private const UBICACION_MAX = 255;
    private const DESCRIPCION_MAX = 2000;

    // =================================================================
    // CRUD de contenedor
    // =================================================================

    public static function create_contenedor(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $contenedor = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        Util_VerifyData::keys_exists(true, $contenedor, json_ubicacion);
        $almacenado = Util_VerifyData::verify_and_get_array($contenedor, json_almacenado);
        Util_VerifyData::keys_exists(true, $almacenado, json_tipo_basuras, json_cantidad);

        $error = self::validar_datos_contenedor($contenedor, $ci);
        if ($error !== null) {
            return $error;
        }

        $en_funcion = filter_var($contenedor[json_en_funcion] ?? true, FILTER_VALIDATE_BOOLEAN);

        $id = Model_Contenedor::create_contenedor(
            trim($contenedor[json_ubicacion]),
            $en_funcion,
            $almacenado[json_tipo_basuras],
            (float) $almacenado[json_cantidad],
            date('Y-m-d H:i:s')
        );

        if ($id === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al crear el contenedor");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CreateContenedorError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó el contenedor {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorCreated"),
            [json_contenedor => [json_id => $id]]
        );
    }

    public static function get_contenedores(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        // Filtros opcionales
        $en_funcion = null;
        if (isset($data[json_en_funcion])) {
            $en_funcion = filter_var($data[json_en_funcion], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($en_funcion === null) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de contenedores rechazado: en_funcion inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidEnFuncion"),
                    http_unprocessable_entity
                );
            }
        }

        $tipo_basura = $data[json_tipo_basura] ?? "";
        if ($tipo_basura !== "" && !in_array($tipo_basura, sql_tipo_basura, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de contenedores rechazado: tipo de basura inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoBasura"),
                http_unprocessable_entity
            );
        }

        $contenedores = Model_Contenedor::get_contenedores($en_funcion, $tipo_basura);

        if ($contenedores === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al listar contenedores");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedoresError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó contenedores");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedoresFetched"),
            Util_Translator::sql_to_json_keys($contenedores)
        );
    }

    public static function get_contenedor(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        $contenedor = Model_Contenedor::get_contenedor($id);

        if ($contenedor === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($contenedor === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Consulta rechazada: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        $estado_actual = Model_Contenedor::get_estado_actual($id);
        $ultimo_llenado = Model_Contenedor::get_ultimo_llenado($id);
        $historial_estado = Model_Contenedor::get_historial_estado($id);
        $historial_llenado = Model_Contenedor::get_historial_llenado($id);
        $historial_vaciado = Model_Contenedor::get_historial_vaciado($id);

        if (in_array(null, [$estado_actual, $ultimo_llenado, $historial_estado, $historial_llenado, $historial_vaciado], true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar los historiales del contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorHistoryError"),
                http_internal_error
            );
        }

        $contenedor['estado_actual'] = $estado_actual ?: null;
        $contenedor['llenado_actual'] = $ultimo_llenado ?: null;
        $contenedor['historial_estado'] = self::agrupar_por_anio_mes($historial_estado);
        $contenedor['historial_llenado'] = self::agrupar_por_anio_mes($historial_llenado);
        $contenedor['vaciado'] = self::agrupar_por_anio_mes($historial_vaciado);

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Consultó el contenedor {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorFetched"),
            Util_Translator::sql_to_json_keys($contenedor)
        );
    }

    public static function update_contenedor(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        $error = self::validar_datos_contenedor($payload, $ci);
        if ($error !== null) {
            return $error;
        }

        // Todo es opcional: solo se modifica lo que llega
        $ubicacion = isset($payload[json_ubicacion]) ? trim($payload[json_ubicacion]) : null;
        $en_funcion = isset($payload[json_en_funcion]) ? filter_var($payload[json_en_funcion], FILTER_VALIDATE_BOOLEAN) : null;
        $almacenado = (array) ($payload[json_almacenado] ?? []);
        $tipo_basuras = $almacenado[json_tipo_basuras] ?? null;
        $cantidad = isset($almacenado[json_cantidad]) ? (float) $almacenado[json_cantidad] : null;

        if ($ubicacion === null && $en_funcion === null && $tipo_basuras === null && $cantidad === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del contenedor {$id} rechazada: no se envió ningún campo");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_bad_request
            );
        }

        $actual = Model_Contenedor::get_contenedor($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        // El almacenado se guarda completo: lo que no llegó se toma del actual
        if ($tipo_basuras !== null || $cantidad !== null) {
            $tipo_basuras ??= $actual['almacenado']['tipo_basuras'] ?? null;
            $cantidad ??= $actual['almacenado']['cantidad'] ?? null;

            if ($tipo_basuras === null || $cantidad === null) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: el contenedor {$id} no tiene almacenado, hay que mandar tipos y cantidad");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "MissingAlmacenadoData"),
                    http_unprocessable_entity
                );
            }
        }

        if (Model_Contenedor::update_contenedor($id, $ubicacion, $en_funcion, $tipo_basuras, $cantidad) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al actualizar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UpdateContenedorError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó el contenedor {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorUpdated")
        );
    }

    public static function delete_contenedor(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $payload = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($payload, json_id);

        // Se puede borrar aunque una ruta lo use, salvo que esa ruta se este ejecutando
        $en_ejecucion = Model_Ruta::contenedor_en_ejecucion($id);

        if ($en_ejecucion === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al revisar las ejecuciones del contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetEjecucionesError"),
                http_internal_error
            );
        }

        if ($en_ejecucion) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: una ruta que usa el contenedor {$id} se está ejecutando");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorInExecution"),
                http_conflict
            );
        }

        $borrado = Model_Contenedor::delete_contenedor($id);

        if ($borrado === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al borrar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteContenedorError"),
                http_internal_error
            );
        }

        if ($borrado === false) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borró el contenedor {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorDeleted")
        );
    }

    // =================================================================
    // Estado, llenado y vaciado
    // =================================================================

    public static function add_estado(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);
        $tipo_usuario = (string) Util_VerifyData::verify_and_get_from_token($data, json_typeuser);

        $contenedor = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($contenedor, json_id);
        $estado = Util_VerifyData::verify_and_get_array($contenedor, json_estado);

        $condicion = $estado[json_condicion] ?? null;
        $basura = $estado[json_basura] ?? null;
        $fecha = $estado[json_fecha] ?? date('Y-m-d H:i:s');

        if ($condicion === null && $basura === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado para el contenedor {$id}: no se envió condición ni basura");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_unprocessable_entity
            );
        }

        if (!Util_VerifyData::valid_date($fecha)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado para el contenedor {$id}: fecha inválida");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidFecha"),
                http_unprocessable_entity
            );
        }

        $existe = Model_Contenedor::get_contenedor($id);

        if ($existe === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($existe === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        $actual = Model_Contenedor::get_estado_actual($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el estado actual del contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetEstadoError"),
                http_internal_error
            );
        }

        $nuevo_estado = self::armar_estado($actual, $condicion, $basura, $fecha, $tipo_usuario, $ci);

        // Si no se pudo armar, armar_estado ya devuelve la respuesta de error
        if ($nuevo_estado instanceof Util_HttpResponse) {
            return $nuevo_estado;
        }

        if (Model_Contenedor::add_estado($id, $nuevo_estado['condicion'], $nuevo_estado['basura'], $fecha) !== true) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al agregar el estado al contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "AddEstadoError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Agregó un estado al contenedor {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "EstadoAdded")
        );
    }

    public static function add_llenado(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_OPERARIO);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $contenedor = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($contenedor, json_id);
        $llenado = Util_VerifyData::verify_and_get_int($contenedor, json_llenado, llenado_vacio, llenado_sobrellenado);
        $fecha = $contenedor[json_fecha] ?? date('Y-m-d H:i:s');

        if (!Util_VerifyData::valid_date($fecha)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Llenado rechazado para el contenedor {$id}: fecha inválida");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidFecha"),
                http_unprocessable_entity
            );
        }

        $actual = Model_Contenedor::get_contenedor($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Llenado rechazado: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        if ($actual['almacenado'] === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Llenado rechazado: el contenedor {$id} no tiene almacenado");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorWithoutAlmacenado"),
                http_unprocessable_entity
            );
        }

        if (Model_Contenedor::add_llenado($id, $llenado, $fecha) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al agregar el llenado al contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "AddLlenadoError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Agregó llenado {$llenado} al contenedor {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "LlenadoAdded")
        );
    }

    public static function empty_contenedor(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_OPERARIO);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $contenedor = Util_VerifyData::verify_and_get_array($data, json_contenedor);
        $id = Util_VerifyData::verify_and_get_int($contenedor, json_id);
        $fecha = $contenedor[json_fecha] ?? date('Y-m-d H:i:s');

        if (!Util_VerifyData::valid_date($fecha)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Vaciado rechazado para el contenedor {$id}: fecha inválida");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidFecha"),
                http_unprocessable_entity
            );
        }

        $actual = Model_Contenedor::get_contenedor($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetContenedorError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Vaciado rechazado: el contenedor {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        if ($actual['almacenado'] === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Vaciado rechazado: el contenedor {$id} no tiene almacenado");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorWithoutAlmacenado"),
                http_unprocessable_entity
            );
        }

        if (Model_Contenedor::empty_contenedor($id, $actual['almacenado']['tipo_basuras'], $fecha) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al vaciar el contenedor {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "EmptyContenedorError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Vació el contenedor {$id}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorEmptied")
        );
    }

    // =================================================================
    // Incidentes (ASUNCION: permisos y estados no estaban definidos)
    // =================================================================

    public static function create_incidente(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $incidente = Util_VerifyData::verify_and_get_array($data, json_incidente);
        $id_contenedor = Util_VerifyData::verify_and_get_int($incidente, json_id_contenedor);
        Util_VerifyData::keys_exists(true, $incidente, json_tipo_incidente);

        $tipo = $incidente[json_tipo_incidente];
        $descripcion = $incidente[json_descripcion] ?? "";

        if (!in_array($tipo, sql_incidente_tipo, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Incidente rechazado: tipo inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoIncidente"),
                http_unprocessable_entity
            );
        }

        if (!is_string($descripcion)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Incidente rechazado: la descripción no es un texto");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidDescripcion"),
                http_unprocessable_entity
            );
        }

        if (!Util_VerifyData::comprobar_longitud($descripcion, self::DESCRIPCION_MAX)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Incidente rechazado: la descripción supera los " . self::DESCRIPCION_MAX . " caracteres");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DescripcionTooLong"),
                http_unprocessable_entity
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
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Incidente rechazado: el contenedor {$id_contenedor} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ContenedorNotFound"),
                http_not_found
            );
        }

        $id = Model_Contenedor::add_incidente($id_contenedor, $tipo, $descripcion);

        if ($id === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al crear el incidente del contenedor {$id_contenedor}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "CreateIncidenteError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Creó el incidente {$id} ({$tipo}) en el contenedor {$id_contenedor}");

        return Util_HttpResponse::created(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "IncidenteCreated"),
            [json_incidente => [json_id => $id]]
        );
    }

    public static function get_incidentes(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_TODOS);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        // Filtros opcionales
        $id_contenedor = 0;
        if (isset($data[json_id_contenedor])) {
            $id_contenedor = filter_var($data[json_id_contenedor], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id_contenedor === false) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de incidentes rechazado: id de contenedor inválido");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidContenedorId"),
                    http_unprocessable_entity
                );
            }
        }

        $estado = $data[json_estado_incidente] ?? "";
        if ($estado !== "" && !in_array($estado, sql_incidente_estado, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Listado de incidentes rechazado: estado inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidEstadoIncidente"),
                http_unprocessable_entity
            );
        }

        $incidentes = Model_Contenedor::get_incidentes($id_contenedor, $estado);

        if ($incidentes === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al listar incidentes");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetIncidentesError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Listó incidentes");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "IncidentesFetched"),
            Util_Translator::sql_to_json_keys($incidentes)
        );
    }

    public static function update_incidente(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_ADMIN_OPERADOR_Y_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $incidente = Util_VerifyData::verify_and_get_array($data, json_incidente);
        $id = Util_VerifyData::verify_and_get_int($incidente, json_id);

        // Todo es opcional: solo se modifica lo que llega
        $tipo = $incidente[json_tipo_incidente] ?? null;
        $estado = $incidente[json_estado_incidente] ?? null;
        $descripcion = $incidente[json_descripcion] ?? null;

        if ($tipo !== null && !in_array($tipo, sql_incidente_tipo, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del incidente {$id} rechazada: tipo inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoIncidente"),
                http_unprocessable_entity
            );
        }

        if ($estado !== null && !in_array($estado, sql_incidente_estado, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del incidente {$id} rechazada: estado inválido");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidEstadoIncidente"),
                http_unprocessable_entity
            );
        }

        if ($descripcion !== null && !is_string($descripcion)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del incidente {$id} rechazada: la descripción no es un texto");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidDescripcion"),
                http_unprocessable_entity
            );
        }

        if ($descripcion !== null && !Util_VerifyData::comprobar_longitud($descripcion, self::DESCRIPCION_MAX)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del incidente {$id} rechazada: la descripción supera los " . self::DESCRIPCION_MAX . " caracteres");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DescripcionTooLong"),
                http_unprocessable_entity
            );
        }

        if ($tipo === null && $estado === null && $descripcion === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización del incidente {$id} rechazada: no se envió ningún campo");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NothingToUpdate"),
                http_bad_request
            );
        }

        $actual = Model_Contenedor::get_incidente($id);

        if ($actual === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al consultar el incidente {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "GetIncidenteError"),
                http_internal_error
            );
        }

        if ($actual === []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualización rechazada: el incidente {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "IncidenteNotFound"),
                http_not_found
            );
        }

        if (Model_Contenedor::update_incidente($id, $tipo, $estado, $descripcion) !== true) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al actualizar el incidente {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UpdateIncidenteError"),
                http_internal_error
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Actualizó el incidente {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "IncidenteUpdated")
        );
    }

    public static function delete_incidente(array $data): Util_HttpResponse
    {
        Controller_Auth::valid_user_types($data, self::ROLES_ADMIN_OPERADOR_Y_PLANIFICADOR);
        $ci = (int) Util_VerifyData::verify_and_get_from_token($data, json_ci);

        $incidente = Util_VerifyData::verify_and_get_array($data, json_incidente);
        $id = Util_VerifyData::verify_and_get_int($incidente, json_id);

        $borrado = Model_Contenedor::delete_incidente($id);

        if ($borrado === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Error en BD al borrar el incidente {$id}");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "DeleteIncidenteError"),
                http_internal_error
            );
        }

        if ($borrado === false) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Borrado rechazado: el incidente {$id} no existe");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "IncidenteNotFound"),
                http_not_found
            );
        }

        Model_Log::add_log_user($ci, self::LOG_TYPE, "Borró el incidente {$id}");

        return Util_HttpResponse::ok(
            Util_Code::create(StatusCode::OK, LayerCode::CONTROLLER, self::SYS_NAME, "IncidenteDeleted")
        );
    }

    // =================================================================
    // Validaciones y reglas de negocio
    // =================================================================

    /**
     * Valida los campos de un contenedor que vengan en el payload (sirve para
     * crear, donde ya se exigieron los obligatorios, y para actualizar, donde
     * todo es opcional). Devuelve la respuesta de error o null si esta todo bien.
     */
    private static function validar_datos_contenedor(array $payload, int $ci): ?Util_HttpResponse
    {
        if (array_key_exists(json_ubicacion, $payload)) {
            $ubicacion = $payload[json_ubicacion];

            if (!is_string($ubicacion) || trim($ubicacion) === "") {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: la ubicación está vacía o no es un texto");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidUbicacion"),
                    http_unprocessable_entity
                );
            }

            if (!Util_VerifyData::comprobar_longitud($ubicacion, self::UBICACION_MAX)) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: la ubicación supera los " . self::UBICACION_MAX . " caracteres");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "UbicacionTooLong"),
                    http_unprocessable_entity
                );
            }
        }

        if (isset($payload[json_en_funcion])
            && filter_var($payload[json_en_funcion], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: en_funcion no es un booleano");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidEnFuncion"),
                http_unprocessable_entity
            );
        }

        if (!isset($payload[json_almacenado])) {
            return null;
        }

        $almacenado = $payload[json_almacenado];

        if (!is_array($almacenado)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: el almacenado no es un objeto");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidAlmacenado"),
                http_unprocessable_entity
            );
        }

        if (array_key_exists(json_tipo_basuras, $almacenado)
            && !Util_VerifyData::valid_enum_list($almacenado[json_tipo_basuras], sql_tipo_basura)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: tipos de basura inválidos, repetidos o vacíos");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidTipoBasuras"),
                http_unprocessable_entity
            );
        }

        if (array_key_exists(json_cantidad, $almacenado)) {
            $cantidad = filter_var($almacenado[json_cantidad], FILTER_VALIDATE_FLOAT);

            if ($cantidad === false || $cantidad <= 0) {
                Model_Log::add_log_user($ci, self::LOG_TYPE, "Contenedor rechazado: la cantidad no es un número mayor a 0");
                return Util_HttpResponse::error(
                    Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCantidad"),
                    http_unprocessable_entity
                );
            }
        }

        return null;
    }

    /**
     * Arma el estado nuevo a partir del actual y de lo que mandó el usuario.
     * Devuelve ['condicion' => [...], 'basura' => [...]] o, si algo no se
     * permite, directamente la respuesta de error.
     *
     * Reglas:
     *  - condicion: null = se deja la anterior; [] = [Normal]; otro valor = se reemplaza.
     *  - basura: null o [] = se deja la anterior; otro valor = se reemplaza.
     *  - "Normal" (en condicion) y "Limpio" (en basura) son exclusivos.
     *  - "Sobre_llenado" no se acepta como dato: lo maneja el llenado. El del
     *    estado anterior siempre se conserva.
     *  - "Reparacion" solo la pone o la quita el admin operador.
     *  - El vecino solo puede reportar problemas (VECINO_CONDICIONES y VECINO_BASURA).
     *  - La fecha no puede ser anterior a la del estado actual.
     */
    private static function armar_estado(array $actual, mixed $condicion, mixed $basura, string $fecha, string $tipo_usuario, int $ci): array|Util_HttpResponse
    {
        $condicion_previa = $actual['condicion'] ?? [enum_cond_normal];
        $basura_previa = $actual['basura'] ?? [enum_basura_limpio];

        // Solo hay algo que validar si mandaron una lista con contenido
        $condicion_enviada = ($condicion !== null && $condicion !== []);
        $basura_enviada = ($basura !== null && $basura !== []);

        if ($actual !== [] && $fecha < $actual['fecha']) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: la fecha {$fecha} es anterior a la del estado actual");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "EstadoDateBeforeCurrent"),
                http_unprocessable_entity
            );
        }

        if ($condicion_enviada && !Util_VerifyData::valid_enum_list($condicion, sql_estado_condicion)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: condición inválida, repetida o fuera del enum");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidCondicion"),
                http_unprocessable_entity
            );
        }

        if ($condicion_enviada && in_array(enum_cond_normal, $condicion, true) && count($condicion) > 1) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: Normal no se puede combinar con otras condiciones");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "NormalIsExclusive"),
                http_unprocessable_entity
            );
        }

        if ($basura_enviada && !Util_VerifyData::valid_enum_list($basura, sql_estado_basura)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: basura inválida, repetida o fuera del enum");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "InvalidBasura"),
                http_unprocessable_entity
            );
        }

        if ($basura_enviada && in_array(enum_basura_sobrellenado, $basura, true)) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: Sobre_llenado solo lo agrega el llenado");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "SobrellenadoNotAllowed"),
                http_unprocessable_entity
            );
        }

        if ($basura_enviada && in_array(enum_basura_limpio, $basura, true) && count($basura) > 1) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: Limpio no se puede combinar con otros valores de basura");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "LimpioIsExclusive"),
                http_unprocessable_entity
            );
        }

        // El vecino solo reporta problemas: no puede dejar listas vacías ni mandar otros valores
        if ($tipo_usuario === enum_tipo_vecino && ($condicion === [] || $basura === [])) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: un vecino no puede mandar listas vacías");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "EstadoVecinoDenied"),
                http_forbidden
            );
        }

        if ($tipo_usuario === enum_tipo_vecino && $condicion_enviada && array_diff($condicion, self::VECINO_CONDICIONES) !== []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: un vecino solo puede reportar vandalizado, roto o quemado");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "EstadoVecinoDenied"),
                http_forbidden
            );
        }

        if ($tipo_usuario === enum_tipo_vecino && $basura_enviada && array_diff($basura, self::VECINO_BASURA) !== []) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: un vecino solo puede reportar basura afuera");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "EstadoVecinoDenied"),
                http_forbidden
            );
        }

        // ---- Armado del estado nuevo ----
        if ($condicion === null) {
            $nueva_condicion = $condicion_previa;
        } elseif ($condicion === []) {
            $nueva_condicion = [enum_cond_normal];
        } else {
            $nueva_condicion = $condicion;
        }

        $nueva_basura = $basura_enviada ? $basura : $basura_previa;

        // El Sobre_llenado del estado anterior se conserva (solo el llenado lo agrega o lo quita)
        if (in_array(enum_basura_sobrellenado, $basura_previa, true)) {
            $nueva_basura = array_values(array_diff($nueva_basura, [enum_basura_limpio]));

            if (!in_array(enum_basura_sobrellenado, $nueva_basura, true)) {
                $nueva_basura[] = enum_basura_sobrellenado;
            }
        }

        // ---- Reparacion: solo el admin operador la pone o la quita ----
        $tenia_reparacion = in_array(enum_cond_reparacion, $condicion_previa, true);
        $tiene_reparacion = in_array(enum_cond_reparacion, $nueva_condicion, true);

        if ($tipo_usuario !== enum_tipo_admin_operador && $tenia_reparacion !== $tiene_reparacion) {
            Model_Log::add_log_user($ci, self::LOG_TYPE, "Estado rechazado: solo el admin operador puede poner o quitar Reparacion");
            return Util_HttpResponse::error(
                Util_Code::create(StatusCode::ERROR, LayerCode::CONTROLLER, self::SYS_NAME, "ReparacionDenied"),
                http_forbidden
            );
        }

        return ['condicion' => $nueva_condicion, 'basura' => $nueva_basura];
    }

    /**
     * Agrupa filas con 'fecha' en [anio => [mes => [filas]]].
     */
    private static function agrupar_por_anio_mes(array $filas): array
    {
        $agrupado = [];

        foreach ($filas as $fila) {
            $agrupado[(int) substr($fila['fecha'], 0, 4)][(int) substr($fila['fecha'], 5, 2)][] = $fila;
        }

        return $agrupado;
    }
}
