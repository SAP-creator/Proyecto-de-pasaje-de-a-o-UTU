<?php
include_once __DIR__ . "/../constantes/sql_constantes.php";
include_once __DIR__ . "/../utils/db_connection.php";

class UserModel{

    #-- retorna:
    #array - lo consiguio (aunque este vacio)
    #null - error en la query
    public static function get_users(string $type = ""): ?array
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_usuario;
            // Sin parámetros si no hay filtro
            $query_result = $db->executeQuery($sql); 
        } else {
            if (!array_key_exists($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
        }

        if (!($query_result->success)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    #-- retorna:
    #array - lo consiguio (aunque este vacio)
    #null - error en la query
    public static function get_request_users(string $type = ""): ?array
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT * FROM " . sql_tabla_soli_usuario;
            // Sin parámetros si no hay filtro
            $query_result = $db->executeQuery($sql); 
        } else {
            if (!array_key_exists($type, sql_usuario_tipo)) {
                return null; 
            }

            $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE tipo = ?";
            $query_result = $db->executeQuery($sql, "s", $type);
        }

        if (!($query_result->success)) {
            return null;
        }

        return $query_result->data->fetch_all(MYSQLI_ASSOC);
    }

    public static function get_user(int $ci): ?array 
    {
        $sql = "SELECT * FROM " . sql_tabla_usuario . " WHERE cedula = ?";
        
        $db = new DatabaseConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);

        if (!($query_result->success)) { return null; }

        // fetch_assoc() devuelve el array del usuario o null si no existe
        $user = $query_result->data->fetch_assoc();

        return $user; 
    }

    public static function get_request_user(int $ci): ?array
    {
        $sql = "SELECT * FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        
        $db = new DatabaseConnection();
        $query_result = $db->executeQuery($sql, "i", $ci);

        if (!($query_result->success)) {
            return null; 
        }

        // fetch_assoc() devuelve el array del usuario o null si no existe
        $user = $query_result->data->fetch_assoc();

        return $user; 
    }

    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function has_user(int $ci, string $type = ""): ?bool
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);

            if (!($query_result->success)) {
                return null;
            }

            return $query_result->data->num_rows > 0;
        }

        if (!array_key_exists($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);

        if (!($query_result->success)) {
            return null;
        }

        return $query_result->data->num_rows > 0;
    }

    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function has_request_user(int $ci,string $type = ""): ?bool
    {
        $db = new DatabaseConnection();

        if (empty($type)) {
            $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
            $query_result = $db->executeQuery($sql, "i", $ci);

            if (!($query_result->success)) {
                return null;
            }

            return $query_result->data->num_rows > 0;
        }

        if (!array_key_exists($type, sql_usuario_tipo)) {
            return null;
        }

        $sql = "SELECT 1 FROM " . sql_tabla_soli_usuario . " WHERE cedula = ? AND tipo = ?";
        $query_result = $db->executeQuery($sql, "is", $ci, $type);

        if (!($query_result->success)) {
            return null;
        }

        return $query_result->data->num_rows > 0;
    }

    #crea una solicitud de usuario en la base de datos
    #-- retorna:
    #true - lo consiguio
    #false - no lo consiguio
    #null - error en la query
    public static function create_request_user(int $ci,string $clave, string $type): ?bool
    {
        $sql = "INSERT INTO ". sql_tabla_soli_usuario . "
                (cedula, clave, tipo) VALUES (?, ?, ?)";
        
        $db = new DatabaseConnection();
        
        $result = $db->executeQuery($sql, "iss",$ci,$clave,$type);
        
        return $result->success;
    }

    # Acepta una solicitud de usuario. Elimina la solicitud y crea el usuario con sus subtablas
    #-- retorna:
    # true - lo consiguió
    # false - no lo consiguió o falló alguna query
    public static function accept_request_user(int $ci): ?bool
    {
        $has_request = UserModel::has_request_user($ci);
        if ($has_request !== true) { 
            return $has_request; 
        }

        $user_request = UserModel::get_request_user($ci);
        if (!$user_request) {
            return false;
        }

        $tipo = $user_request[sql_tipo];
        $clave = $user_request[sql_clave];
        
        if (!array_key_exists($tipo, self::sql_accept_user)) {
            return false;
        }

        $db = new DatabaseConnection();

        // 1. Ejecutar cada INSERT del array según el tipo de usuario
        foreach (self::sql_accept_user[$tipo] as $sql) {
            
            // Si la consulta es para la tabla 'usuario', lleva la clave además de la cédula
            $result = str_contains($sql, "usuario (") 
                ? $db->executeQuery($sql, "is", $ci, $clave)
                : $db->executeQuery($sql, "i", $ci);

            if (!$result->success) {
                return false;
            }
        }

        // 2. Eliminar la solicitud
        $sql_delete = "DELETE FROM " . sql_tabla_soli_usuario . " WHERE cedula = ?";
        $result_delete = $db->executeQuery($sql_delete, "i", $ci);

        return $result_delete->success;
    }

    // CORREGIDO: Los valores 'tipo' insertados coinciden exactamente con el ENUM
    private const sql_accept_user = [
        "vecino" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'vecino', true)",
            "INSERT INTO vecino (cedula) VALUES (?)"
        ],
        "operario" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'operario', false)", // 'operativo' -> 'operario'
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO operador (cedula) VALUES (?)"
        ],
        "admin operador" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin operador', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_municipal_operador (cedula) VALUES (?)"
        ],
        "admin general" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin general', false)",
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_municipal_general (cedula) VALUES (?)"
        ],
        "admin sistema" => [
            "INSERT INTO usuario (cedula, clave, tipo, datos_completados) VALUES (?, ?, 'admin sistema', false)", // 'admin' -> 'admin sistema'
            "INSERT INTO trabajador (cedula) VALUES (?)",
            "INSERT INTO admin_sistemas (cedula) VALUES (?)"
        ]
    ];
    
    
}
