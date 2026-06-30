<?php
class Camera {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. Fetch all cameras (Using GROUP_CONCAT for universal XAMPP/MariaDB compatibility)
    public function findAll($categoryId = null) {
        $query = "SELECT c.*, 
                  GROUP_CONCAT(
                      IF(ci.id IS NOT NULL, CONCAT(ci.id, '::', ci.url, '::', ci.isPrimary), NULL) 
                      SEPARATOR '||'
                  ) as images_string
                  FROM cameras c
                  LEFT JOIN camera_images ci ON c.id = ci.cameraId ";
        
        if ($categoryId) {
            $query .= "WHERE c.categoryId = :categoryId ";
        }
        $query .= "GROUP BY c.id ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($query);
        if ($categoryId) {
            $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cameras as &$camera) {
            $camera['specifications'] = json_decode($camera['specifications']);
            $camera['images'] = $this->parseImagesString($camera['images_string']);
            unset($camera['images_string']); // Clean temporary string variable
        }
        return $cameras;
    }

    // 2. Fetch single entry specs
    public function findByPk($id) {
        $query = "SELECT c.*, 
                  GROUP_CONCAT(
                      IF(ci.id IS NOT NULL, CONCAT(ci.id, '::', ci.url, '::', ci.isPrimary), NULL) 
                      SEPARATOR '||'
                  ) as images_string
                  FROM cameras c
                  LEFT JOIN camera_images ci ON c.id = ci.cameraId
                  WHERE c.id = :id GROUP BY c.id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $camera = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($camera) {
            $camera['specifications'] = json_decode($camera['specifications']);
            $camera['images'] = $this->parseImagesString($camera['images_string']);
            unset($camera['images_string']);
        }
        return $camera;
    }

    // Helper to format string into clean structured objects matching original array response
    private function parseImagesString($string) {
        if (empty($string)) return [];
        
        $images = [];
        $items = explode('||', $string);
        foreach ($items as $item) {
            $parts = explode('::', $item);
            if (count($parts) === 3) {
                $images[] = [
                    'id' => intval($parts[0]),
                    'url' => $parts[1],
                    'isPrimary' => intval($parts[2]) === 1 ? true : false
                ];
            }
        }
        return $images;
    }
}
?>