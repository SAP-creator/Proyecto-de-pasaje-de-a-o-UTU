<?php

include_once __DIR__ . "/../utils/Util_DbConnection.php";
include_once __DIR__ . "/../constantes/Const_Sql.php";

class Model_UserSetup {

    public static function set_iscomplete_user(bool $complete,int $ci){
        $sql = "UPDATE ".sql_tabla_usuario." SET datos_completados = $complete WHERE ".sql_cedula." = ?";
        $db = new Util_DbConnection();

        $db->executeQuery($sql,"i",$ci);
    }

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


        if (! in_array($typeuser, sql_usuario_tipo) )
            {var_dump($typeuser, sql_usuario_tipo);
            return null;}
        
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
 
    private const sql_user_complete = [
        enum_tipo_vecino => null,
        
        enum_tipo_operario => "SELECT
            IF(t.nombre IS NULL, FALSE, TRUE) AS trabajador__nombre,
            IF(t.apellido IS NULL, FALSE, TRUE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
    
        WHERE u.cedula = ?",
        
        enum_tipo_admin_operador => "SELECT
            IF(t.nombre IS NULL, FALSE, TRUE) AS trabajador__nombre,
            IF(t.apellido IS NULL, FALSE, TRUE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_general => "SELECT
            IF(t.nombre IS NULL, FALSE, TRUE) AS trabajador__nombre,
            IF(t.apellido IS NULL, FALSE, TRUE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?",
        
        enum_tipo_admin_sistema => "SELECT
         t.nombre,
            IF(t.nombre IS NULL, FALSE, TRUE) AS trabajador__nombre,
            IF(t.apellido IS NULL, FALSE, TRUE) AS trabajador__apellido
        FROM usuario u 
        LEFT JOIN trabajador t ON u.cedula = t.cedula 
        WHERE u.cedula = ?"
    ];
    
}