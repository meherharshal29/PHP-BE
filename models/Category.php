<?php
class Category {
    private $conn;
    private $table_name = "categories";

    // Schema attributes matching original Sequelize configurations
    public $id;
    public $name;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * 1. CREATE: Add new dynamic category with duplication constraint checks
     */
    public function create($name, $description = null) {
        if (empty(trim($name))) {
            throw new Exception("Validation Error: Category name field cannot be empty.");
        }

        $query = "INSERT INTO " . $this->table_name . " (name, description) VALUES (:name, :description)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->execute([
            ':name' => htmlspecialchars(strip_tags(trim($name))),
            ':description' => $description ? htmlspecialchars(strip_tags($description)) : null
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * 2. FIND ALL: Fetch entire storage listings
     */
    public function findAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 3. FIND BY PK: Fetch single schema mapping parameters
     */
    public function findByPk($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 4. UPDATE: Alter matching elements properties safely
     */
    public function updateItem($id, $name, $description = null) {
        if (empty(trim($name))) {
            throw new Exception("Validation Error: Category name cannot be empty during updates.");
        }

        $query = "UPDATE " . $this->table_name . " SET name = :name, description = :description WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        return $stmt->execute([
            ':name' => htmlspecialchars(strip_tags(trim($name))),
            ':description' => $description ? htmlspecialchars(strip_tags($description)) : null,
            ':id' => intval($id)
        ]);
    }

    /**
     * 5. DESTROY: Remove selected entity mapping out of the engine
     */
    public function destroy($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => intval($id)]);
        return $stmt->rowCount() > 0;
    }
}
?>