<?php

class DatabaseConnection {

    private mysqli $connection;

    public function __construct() {
        $this->connection = new mysqli("localhost", "root", "", "DB");

        if ($this->connection->connect_error) {
            throw new Exception("Connection error: " . $this->connection->connect_error);
        }
    }

    public function executeQuery(string $query, string $types = "", mixed ...$params): QueryResult {

        $result = new QueryResult();

        if (strlen($types) != count($params)) {
            $result->error = "Error los tipos de datos no son coicidentes";
            return $result;
        }

        $stmt = $this->connection->prepare($query);

        if (!$stmt) {
            $result->error = "Error preparando query";
            return $result;
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $result->error = "Error en ejecucion: " . $stmt->error;
            $stmt->close();
            return $result;
        }

        $result->success = true;
        $data = $stmt->get_result();
        $result->data = $data ?: null;

        $stmt->close();

        return $result;
    }
}

class QueryResult {
    public bool $success = false;
    public ?string $error = null;
    public ?mysqli_result $data = null;
}