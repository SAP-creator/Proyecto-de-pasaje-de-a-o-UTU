<?php

include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";

class Model_UserSetup {
    public static function user_is_complete(int $ci): ?bool
    {
        $sql = "SELECT datos_completados FROM usuario WHERE ci = ?";

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery($sql, "i", $ci);

        if ($result_query->success != true)
            return null;
        
        $data = $result_query->data->fetch_assoc();

        if ($data == null)
            return null;

        return (bool) $data;

    }


    public static function find_incomplete_data(string $typeuser, int $ci): bool|null|array
    {
        if ( in_array($typeuser, sql_usuario_tipo) )
            return null;
        
        $sql = self::sql_user_complete[ $typeuser ];

        if ($sql == null)
            return true;

        $db = new Util_DbConnection();

        $result_query = $db->executeQuery( $sql, "i", $ci );

        if ( $result_query->success != true )
            return null;
        
        $data = $result_query->data->fetch_assoc();

        if ( $data == null )
            return null;

        if ( empty($data) )
            return true;

        return $data;
    }
 
    private const sql_user_complete = [
        enum_tipo_vecino => null,
        
        enum_tipo_operario => "SELECT
            u.*,
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
    
        WHERE u.cedula = ?",
        
        enum_tipo_admin_operador => "SELECT
            u.*,
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_general => "SELECT
            u.*,
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_sistema => "SELECT
            u.*,
            IF(t.nombre IS NULL, TRUE, FALSE) AS trabajador__nombre,
            IF(t.apellido IS NULL, TRUE, FALSE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?"
    ];
    
}