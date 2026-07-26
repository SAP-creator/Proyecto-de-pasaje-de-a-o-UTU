<?php 

include_once __DIR__ . "/../utils/database.php";





function todos_los_usuarios():?string{
    $sql = "SELECT * FROM `usuario`";
    $db =new ConexionDatabase();
    $consulta = $db->peticion($sql);

    if (! $consulta->funciona){
        return null;
    }
    $usuarios = [];

    while ($fila = $consulta->respuesta->fetch_assoc()) {
        $usuarios[] = $fila;
    }

    foreach ($usuarios as $index => $user) {
        unset($user["clave"]);
        
        $usuarios[$index] =  $user;
    }
    return json_encode($usuarios);
}

# Retorna:
# - true  si existe una petición de registro para esa cédula.
# - false si no existe.
# - null  si ocurrió un error en la consulta.
function conseguir_peticion_user(int $ci): ?bool{
    $sql = "SELECT * FROM `usuario` WHERE cedula = ?";
    $db = new ConexionDatabase();
    $consulta = $db->peticion($sql,"i",$ci);
    if (! $consulta->funciona){
        return null;
    }

    $user = $consulta->respuesta->fetch_assoc();
    #modificar
    if (!array_key_exists("tipo",$user)){
        return false;
    }
    return true;
}

function todas_las_peticiones_usuario():?string{
    $sql = "SELECT * FROM `peticion usuario`";
    $db =new ConexionDatabase();
    $consulta = $db->peticion($sql);

    if (! $consulta->funciona){
        return null;
    }
    $usuarios = [];

    while ($fila = $consulta->respuesta->fetch_assoc()) {
        $usuarios[] = $fila;
    }

    foreach ($usuarios as $index => $user) {
        unset($user["clave"]);
        
        $usuarios[$index] =  $user;
    }
    return json_encode($usuarios);
}


# Retorna:
# - true  si el usuario existe.
# - false si no existe.
# - null  si ocurrió un error en la consulta.
function conseguir_usuario(int $ci): ?bool{
    $sql = "SELECT * FROM usuario WHERE cedula = ?";
    $db = new ConexionDatabase();
    $consulta = $db->peticion($sql,"i",$ci);
    if (! $consulta->funciona){
        return null;
    }

    $user = $consulta->respuesta->fetch_assoc();
    ##modificar
    if (!$user){
        return false;
    }
    return true;
}

function aceptar_peticion(int $ci): ?bool {

    $a = conseguir_usuario($ci);

    if ($a === null) {
        return null;
    }

    if ($a === true) {
        // Ya existe el usuario
        return false;
    }

    $b = conseguir_peticion_user($ci);

    if ($b === null) {
        return null;
    }

    if ($b === false) {
        // No existe una petición para esa cédula
        return false;
    }

    $db = new ConexionDatabase();

    $sql = "INSERT INTO usuario (cedula, clave, tipo)
            SELECT cedula, clave, tipo
            FROM 'peticion usuario'
            WHERE cedula = ?;";

    $consulta = $db->peticion($sql, "i", $ci);

    if (!$consulta->funciona) {
        return null;
    }

    $sql = "DELETE FROM 'peticion usuario'
            WHERE cedula = ?";

    $consulta = $db->peticion($sql, "i", $ci);

    if (!$consulta->funciona) {
        return null;
    }

    return true;
}

?>