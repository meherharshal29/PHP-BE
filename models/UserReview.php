<?php
class UserReview {
    private $conn;
    private $table_name = "user_reviews";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. GET ALL REVIEWS BY ITEM (Polymorphic Filter)
    public function findByItem($referenceId = null, $type = null) {
        $query = "SELECT r.*, u.name as authorName, u.role as authorRole 
                  FROM " . $this->table_name . " r 
                  LEFT JOIN users u ON r.userId = u.id WHERE 1=1 ";
        
        $params = [];
        if ($referenceId !== null) {
            $query .= "AND r.referenceId = :referenceId ";
            $params[':referenceId'] = intval($referenceId);
        }
        if ($type !== null) {
            $query .= "AND r.type = :type ";
            $params[':type'] = $type;
        }
        
        $query .= "ORDER BY r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. GET USER'S OWN REVIEWS (Missing Function 1 - FIXED)
    public function findByUserId($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE userId = :userId ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':userId' => intval($userId)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. CREATE / INSERT NEW REVIEW
    public function create($rating, $comment, $referenceId, $type, $userId) {
        if ($rating < 1 || $rating > 5) {
            throw new Exception("Validation Error: Rating must be an integer between 1 and 5.");
        }

        $query = "INSERT INTO " . $this->table_name . " 
                  (rating, comment, referenceId, type, userId) 
                  VALUES (:rating, :comment, :referenceId, :type, :userId)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':rating' => intval($rating),
            ':comment' => htmlspecialchars(strip_tags($comment)),
            ':referenceId' => intval($referenceId),
            ':type' => $type ? $type : 'general',
            ':userId' => intval($userId)
        ]);

        return $this->conn->lastInsertId();
    }

    // 4. UPDATE REVIEW (Missing Function 2 - FIXED)
    public function updateItem($id, $userId, $rating, $comment) {
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            throw new Exception("Validation Error: Rating must be between 1 and 5.");
        }

        // Pehle check karte hain ki record exist karta hai ya nahi aur usi user ka hai ya nahi
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = :id AND userId = :userId LIMIT 1");
        $stmt->execute([':id' => intval($id), ':userId' => intval($userId)]);
        $current = $stmt->fetch();

        if (!$current) {
            return false;
        }

        $finalRating = ($rating !== null) ? intval($rating) : $current['rating'];
        $finalComment = ($comment !== null) ? htmlspecialchars(strip_tags($comment)) : $current['comment'];

        $query = "UPDATE " . $this->table_name . " SET rating = :rating, comment = :comment WHERE id = :id AND userId = :userId";
        $upStmt = $this->conn->prepare($query);
        return $upStmt->execute([
            ':rating' => $finalRating,
            ':comment' => $finalComment,
            ':id' => intval($id),
            ':userId' => intval($userId)
        ]);
    }

    // 5. DELETE REVIEW (Missing Function 3 - FIXED)
    public function destroy($id, $userId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND userId = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => intval($id), ':userId' => intval($userId)]);
        return $stmt->rowCount() > 0; // Agar record delete hua toh true dega, varna false
    }
}
?>