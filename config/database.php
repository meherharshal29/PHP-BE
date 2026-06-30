<?php
class Database {
    public $conn;
    public function getConnection() {
        $this->conn = null;

        try {
            $env = require __DIR__ . '/env.php';

            $dsn = "mysql:host=" . $env['DB_HOST'] . ";port=" . $env['DB_PORT'] . ";dbname=" . $env['DB_NAME'] . ";charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $env['DB_USER'], $env['DB_PASS']);
                        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed matrix: " . $exception->getMessage()
            ]);
            exit();
        }

        return $this->conn;
    }
}
?>