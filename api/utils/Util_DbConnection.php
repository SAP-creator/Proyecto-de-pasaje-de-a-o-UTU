<?php

class Util_DbConnection {

    private mysqli $connection;

    public function __construct() {
        $this->connection = new mysqli("localhost", "root", "", "DB");

        if ($this->connection->connect_error) {
            throw new Exception("Connection error: " . $this->connection->connect_error);
        }

        // Necesario para guardar/leer correctamente tildes (ej: "Plástico") en los JSON
        $this->connection->set_charset("utf8mb4");
    }

    public function executeQuery(string $query, string $types = "", mixed ...$params): QueryResult {

        $result = new QueryResult();

        if (strlen($types) != count($params)) {
            $result->error = "Error los tipos de datos no son coincidentes";
            return $result;
        }
        
        $stmt = $this->connection->prepare($query);
        
        if (!$stmt) {
            $result->error = "Error preparando query: " . $this->connection->error;
            return $result;
        }
       
        // Solo vincular parámetros si realmente se pasaron
        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
         
        if (!$stmt->execute()) {
            $result->error = "Error en ejecucion: " . $stmt->error;
            $stmt->close();
            return $result;
        }

        $result->success = true;
        $result->insert_id = (int) $stmt->insert_id;
        $result->affected_rows = (int) $stmt->affected_rows;
        
        // get_result() devuelve false para INSERT, UPDATE, DELETE
        $data = $stmt->get_result();
        $result->data = ($data !== false) ? $data : null;

        $stmt->close();

        return $result;
    }

    /** Execute a model query and record its SQL audit entry. */
    public static function executeLogged(
        self $db,
        string $model,
        string $description,
        string $query,
        string $types = "",
        array $params = []
    ): QueryResult {
        $result = $db->executeQuery($query, $types, ...$params);
        Model_Log::add_log_sql($model, $description, $query, $params);
        return $result;
    }

    /**
     * Transacciones: begin/commit/rollback valen para TODAS las queries
     * ejecutadas en esta conexion desde el begin. Cada instancia tiene su
     * propia conexion, asi que no hay estado compartido entre instancias.
     * Model_Log abre otra conexion distinta, asi que la auditoria de un
     * intento fallido no se pierde con el rollback.
     */
    public function begin(): void {
        $this->connection->begin_transaction();
    }

    public function commit(): void {
        $this->connection->commit();
    }

    public function rollback(): void {
        $this->connection->rollback();
    }
}

class QueryResult {
    public bool $success = false;
    public ?string $error = null;
    public ?mysqli_result $data = null;
    public int $insert_id = 0;
    public int $affected_rows = 0;
}
