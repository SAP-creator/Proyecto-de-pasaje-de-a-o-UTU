<?php
include_once __DIR__ . "/../constantes/Const_Sql.php";
include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../modelo/Model_Log.php";

class Model_User
{
    private const model_log = "USER MODEL";


    public static function get_users(string $type = ""): ?array
    {
        $db = new Util_DbConnection();

        
        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_usuario;
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::model_log, "Obtener todos los usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::model_log, "Obtener usuarios filtrados por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_request_users(string $type = ""): ?array
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_soli_usuario;
            $query_result = $db->executeQuery($sql); 
            Model_Log::add_log_sql(self::model_log, "Obtener todas las solicitudes de usuarios");
        } else {
            if (!in_array($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
            Model_Log::add_log_sql(self::model_log, "Obtener solicitudes filtradas por tipo: {$type}");
        }

        if (!$query_result->success) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_user(int $ci): ?array 
    {
        $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE cedula = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        
        Model_Log::add_log_sql(self::model_log, "Consultar usuario por cédula: {$ci}");
        
        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function get_request_user(int $ci): ?array
    {
        $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);
        Model_Log::add_log_sql(self::model_log, "Consultar solicitud por cédula: {$ci}");

        if (!$query_result->success) { return null; }

        return $query_result->data->fetch_assoc(); 
    }

    public static function has_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::model_log, "Verificar existencia de usuario CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function has_request_user(int $ci, string $type = ""): ?bool
    {
        $db = new Util_DbConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);
            Model_Log::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci}");

            if (!$query_result->success) { return null; }
            return $query_result->data->num_rows > 0;
        }

        if (!in_array($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);
        Model_Log::add_log_sql(self::model_log, "Verificar existencia de solicitud CI: {$ci} con tipo: {$type}");

        if (!$query_result->success) { return null; }
        return $query_result->data->num_rows > 0;
    }

    public static function create_request_user(int $ci, string $clave, string $type): ?bool
    {
        $sql = "INSERT INTO ". sql_tabla_soli_usuario . " (cedula, clave, tipo) VALUES (?, ?, ?)";
        $db = new Util_DbConnection();
        $result = $db->executeQuery($sql, "iss", $ci, $clave, $type);
        
        Model_Log::add_log_sql(self::model_log, "Crear solicitud de usuario CI: {$ci} con tipo: {$type}");

        return $result->success;
    }

    public static function accept_request_user(int $ci): ?bool
    {
        $has_request = self::has_request_user($ci);
        if ($has_request !== true) { 
            return $has_request; 
        }

        $user_request = self::get_request_user($ci);
        if (!$user_request) {
            return false;
        }

        $tipo = $user_request[sql_tipo];
        $clave = $user_request[sql_clave];
        
        if (!array_key_exists($tipo, self::sql_accept_user)) {
            return false;
        }

        $db = new Util_DbConnection();

        foreach (self::sql_accept_user[$tipo] as $sql) {
            $result = str_contains($sql, "usuario") 
                ? $db->executeQuery($sql, "is", $ci, $clave)
                : $db->executeQuery($sql, "i", $ci);

            if (!$result->success) {
                return false;
            }
        }

        $sql_delete = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        $result_delete = $db->executeQuery($sql_delete, "i", $ci);

        Model_Log::add_log_sql(self::model_log, "Solicitud aceptada y usuario migrado CI: {$ci} como tipo: {$tipo}");

        return $result_delete->success;
    }

    private const sql_accept_user = [
        enum_tipo_vecino => [
            "INSERT INTO ".sql_tabla_usuario." (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_vecino."', true)",
            "INSERT INTO ".enum_tipo_vecino." (cedula) VALUES (?)"
        ],
        enum_tipo_operario => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_operario."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_operador." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_operador => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_operador."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_muni_operador." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_general => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_general."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_muni_general." (cedula) VALUES (?)"
        ],
        enum_tipo_admin_sistema => [
            "INSERT INTO ".sql_tabla_usuario."  (cedula, clave, tipo, datos_completados) VALUES (?, ?, '".enum_tipo_admin_sistema."', false)",
            "INSERT INTO ".sql_tabla_trabajador." (cedula) VALUES (?)",
            "INSERT INTO ".sql_tabla_admin." (cedula) VALUES (?)"
        ]
    ];


    public static function change_data(int $ci, string $table, string $collum, mixed $new_value): ?bool
    {
        if (self::has_user($ci) !== true) {
            return null;
        }


        $sql = "UPDATE " . $table . "
                SET " . $collum . " = ?
                WHERE " . sql_cedula . " = ?";

        $type = "";

        if (is_double($new_value)) {
            $type = "d";
        } elseif (is_int($new_value)) {
            $type = "i";
        } elseif (is_string($new_value)) {
            $type = "s";
        } else {
            return null;
        }

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, $type . "i", $new_value, $ci);
        self::set_complete_user($ci);

        return $result_query->success;
    }




    ##estos sql solo de vuelven booleans
    private const sql_user_complete = [
        enum_tipo_vecino => null,
        
        enum_tipo_operario => "SELECT
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
    
        WHERE u.cedula = ?",
        
        enum_tipo_admin_operador => "SELECT
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_general => "SELECT
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_sistema => "SELECT
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?"
    ];



    ##funciones nuevas

    #esta funcion modifica la columna datos completados del usuario dependiendo si tiene todos sus datos importantes ingresados.
    private static function set_complete_user(int $ci): void
    {
        if (!self::has_user($ci)) {
            return;
        }

        $db = new Util_DbConnection();

        $user = Model_User::get_user($ci);
        if (!$user) {
            return;
        }

        $typeuser = $user[sql_tipo] ?? null;

        if (!array_key_exists($typeuser, self::sql_user_complete) || self::sql_user_complete[$typeuser] === null) {
            return;
        }

        $sql = self::sql_user_complete[$typeuser];

        $result_query_user_com = $db->executeQuery($sql, "i", $ci);

        if ($result_query_user_com->success != true) {
            return;
        }

        // fetch_assoc obtiene la primera fila directamente como array [columna => valor]
        $data = $result_query_user_com->data->fetch_assoc();

        if (empty($data)) {
            return;
        }

        $completo = true;
        foreach ($data as $columna => $valor_bool) {
            // En tus SQL de consulta usas IF(col IS NULL, FALSE, TRUE)
            // Por lo tanto, si alguna columna devuelve 0 / false, el usuario está incompleto
            if ($valor_bool) {
                $completo = false;
                break;
            }
        }

        // Convertimos el booleano a entero (1 o 0) para MySQL
        $val_completo = $completo ? 1 : 0;

        $sql_complete = "UPDATE " . sql_tabla_usuario . " SET " . sql_usuario_completo . " = ? WHERE " . sql_cedula . " = ?";
        
        // Pasamos dos enteros: el estado completado (1/0) y la cédula ($ci)
        $db->executeQuery($sql_complete, "ii", $val_completo, $ci);
    }

    public static function find_incomplete_data(string $typeuser, int $ci): bool|null|array
    {


        if (! in_array($typeuser, sql_usuario_tipo) )
            return null;
        
        $sql = self::sql_user_complete[ $typeuser ];
        if ($sql == null)
            return true;

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery( $sql, "i", $ci );

        if ( $result_query->success != true )
            {
            return null;}
        
        $data = $result_query->data->fetch_assoc();
        if ( $data == null )
            {
            return null;}

        if ( empty($data) )
            {
            return true;}

        return $data;
    }

    public static function user_is_complete(int $ci): ?bool
    {
        $sql = "SELECT ".sql_usuario_completo." FROM ".sql_tabla_usuario." WHERE ".sql_cedula." = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);

        if ($result_query->success != true)
            return null;
        
        $data = $result_query->data->fetch_assoc();

        if ($data == null)
            return null;
        return (bool) $data["datos_completados"];

    }

  
    public static function get_user_data(int $ci): ?array
    {
        $user_base = self::get_user($ci);

        if (!$user_base) {
            return null;
        }

        $typeuser = $user_base[sql_tipo] ?? null;

        $joins_by_type = [
            enum_tipo_vecino => [
                sql_tabla_vecino
            ],
            enum_tipo_operario => [
                sql_tabla_trabajador,
                sql_tabla_operador
            ],
            enum_tipo_admin_operador => [
                sql_tabla_trabajador,
                sql_tabla_muni_operador
            ],
            enum_tipo_admin_general => [
                sql_tabla_trabajador,
                sql_tabla_muni_general
            ],
            enum_tipo_admin_sistema => [
                sql_tabla_trabajador,
                sql_tabla_admin
            ]
        ];

        $tables_to_join = $joins_by_type[$typeuser] ?? [];

        $sql = "SELECT u.cedula, u.tipo, u.datos_completados";
        $joins_sql = "";

        foreach ($tables_to_join as $index => $tabla) {
            $alias = "t" . ($index + 1);
            $sql .= ", {$alias}.*";
            $joins_sql .= " LEFT JOIN {$tabla} {$alias} ON u.cedula = {$alias}.cedula";
        }

        $sql .= " FROM " . sql_tabla_usuario . " u{$joins_sql} WHERE u.cedula = ?";

        // 4. Ejecutar la consulta
        $db = new Util_DbConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);

        Model_Log::add_log_sql(self::model_log, "Consulta administrativa de datos completos para CI: {$ci}");

        if (!$query_result->success) {
            return null;
        }

        $data = $query_result->data->fetch_assoc();

        if (!$data) {
            return null;
        }

        unset($data['clave']); 

        return $data;
    }

    public static function delete_user(int $ci): ?bool
    {
        if (! self::has_user($ci)){
            return null;
        }

        $sql = "DELETE FROM ".sql_tabla_usuario." WHERE `".sql_cedula."` = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql,"i",$ci);

        return  $result_query->success;
    }

    public static function delete_user_request(int $ci): ?bool
    {
        if (! self::has_request_user($ci)){
            return null;
        }

        $sql = "DELETE FROM ".sql_tabla_soli_usuario." WHERE ".sql_cedula." = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql,"i",$ci);

        return  $result_query->success;
    }
}