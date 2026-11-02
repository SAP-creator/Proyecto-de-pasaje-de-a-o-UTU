<?php

/*
Como funcionan los envios

   -1-               -2-                  -3-             -4-
error/ok + vista/controlador/modelo +  nom_sistema   +  que paso

1:
    String
    
    OK
    Error

2:
    String

    #que fue lo que fallo/funciono
|   V - Vista
    C - Controlador
    M - Modelo

3:
    String

    #Nombre del sistema
4:
    String

    #que paso
    sea un texto muy reducido y en snakeCase
*/

class StatusCode
{
    public const OK = 'OK';
    public const ERROR = 'Error';
}

class LayerCode
{
    public const VIEW = 'V';
    public const CONTROLLER = 'C';
    public const MODEL = 'M';
    public const UTIL = 'U';
}

class Util_Code
{
    public static function create(string $status, string $layer, string $systemName, string $message): string
    {
        return "{$status} {$layer} {$systemName} {$message}";
    }
}
