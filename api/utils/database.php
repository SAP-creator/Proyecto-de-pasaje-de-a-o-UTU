<?php

class ConexionDatabase {

    private mysqli $mysqli;

    public function __construct() {
        // Faltaba asignarlo a la propiedad
        $this->mysqli = new mysqli("localhost", "root", "", "DB");

        if ($this->mysqli->connect_error) {
            throw new Exception("Error de conexión: " . $this->mysqli->connect_error);
        }
    }

    public function peticion(string $sql_peticion, string $tipo_parametro = "", mixed ...$parametros): respuesta_sql {

        $respuesta = new respuesta_sql();

        // Si el número de tipos no coincide con el número de parámetros
        if (strlen($tipo_parametro) != count($parametros)) {
            $respuesta->problema =
                "La cantidad de tipos (" . strlen($tipo_parametro) .
                ") no coincide con la cantidad de parámetros (" .
                count($parametros) . ").";

            return $respuesta;
        }

        $stmt = $this->mysqli->prepare($sql_peticion);

        if (!$stmt) {
            $respuesta->problema = "Error al preparar la consulta";
            return $respuesta;
        }

        if (!empty($parametros)) {
            $stmt->bind_param($tipo_parametro, ...$parametros);
        }
        $error = !$stmt->execute();
  
        if ($error == true) {
            $respuesta->problema = "Error al ejecutar la consulta";
            echo "Stmt: " . $stmt->error . "<br>";
            echo "MySQLi: " . $this->mysqli->error . "<br>";
            $stmt->close();
            return $respuesta;
        }


        $respuesta->funciona = true;
        $a = $stmt->get_result();
        if ($a == false){$a = null;}
            
        $respuesta->respuesta = $a;
       
       
        $stmt->close();

        return $respuesta;
    }
}

class respuesta_sql {
    public bool $funciona = false;
    #anda a saber que por que una variable (algo que contiene cosas) no puede contener conjunto vacio (null). I love you php...
    public ?string $problema = null;
    public ?mysqli_result $respuesta = null;
}