<?php
class Cart {
    private $conn;
    private $table_name = "carts";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Locates a single specific item in a user's active cart track
     */
    public function findOne($userId, $cameraId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE userId = :userId AND cameraId = :cameraId LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':userId'   => intval($userId), 
            ':cameraId' => intval($cameraId)
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Creates a new cart index row tracking units in 12-hour shifts
     */
    public function create($userId, $cameraId, $quantity, $rentalShifts) {
        $query = "INSERT INTO " . $this->table_name . " (userId, cameraId, quantity, rentalShifts) VALUES (:userId, :cameraId, :quantity, :rentalShifts)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':userId'       => intval($userId),
            ':cameraId'     => intval($cameraId),
            ':quantity'     => intval($quantity),
            ':rentalShifts' => intval($rentalShifts)
        ]);
    }

    /**
     * Modifies quantities or shift metrics for an active item
     */
    public function updateItem($id, $userId, $quantity, $rentalShifts) {
        $query = "UPDATE " . $this->table_name . " SET quantity = :quantity, rentalShifts = :rentalShifts WHERE id = :id AND userId = :userId";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':quantity'     => intval($quantity),
            ':rentalShifts' => intval($rentalShifts),
            ':id'           => intval($id),
            ':userId'       => intval($userId)
        ]);
    }

    /**
     * Counts the total number of items present in a user's cart
     */
    public function countUserItems($userId) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE userId = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':userId' => intval($userId)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Returns all cart listings joined with metadata, mapping internal parameters to 'rentalShifts'
     */
    public function findUserCart($userId) {
        $query = "SELECT ct.id, ct.userId, ct.cameraId, ct.quantity, ct.rentalShifts,
                         c.name as cameraName, c.brand, c.pricePerDay,
                         IFNULL(
                             (SELECT ci.url FROM camera_images ci WHERE ci.cameraId = ct.cameraId AND ci.isPrimary = 1 LIMIT 1),
                             (SELECT ci.url FROM camera_images ci WHERE ci.cameraId = ct.cameraId ORDER BY ci.id ASC LIMIT 1)
                         ) as primaryImage
                  FROM " . $this->table_name . " ct
                  INNER JOIN cameras c ON ct.cameraId = c.id
                  WHERE ct.userId = :userId ORDER BY ct.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':userId' => intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Drops a single targeted row entity safely out of the table matrix
     */
    public function deleteItem($id, $userId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND userId = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':id'     => intval($id), 
            ':userId' => intval($userId)
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Flushes out all entries for a specific user upon successful checkouts
     */
    public function clearCart($userId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE userId = :userId";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':userId' => intval($userId)]);
    }
}
?>